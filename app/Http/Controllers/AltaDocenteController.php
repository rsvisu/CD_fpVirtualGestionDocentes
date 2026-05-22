<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use App\Services\GeneradorEmailVirtualService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\CentroDocente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AltaDocenteController extends Controller
{
    public function __construct(private GeneradorEmailVirtualService $emailService) {}

    public function create()
    {
        $centro = Auth::user()->centro;
        return view('alta_docente', compact('centro'));
    }

    /**
     * Normaliza nombres quitando caracteres prohibidos y poniendo mayúsculas.
     */
    private function normalizarNombreYApellido(string $string): string
    {
        $limpio = str_replace(['º', '.'], '', $string);
        return mb_convert_case(mb_strtolower($limpio, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * AJAX — Previsualización del email @fpvirtualaragon.es sin resolver colisiones.
     * GET /alta-docente/preview-email?nombre=X&apellido=Y
     */
    public function previewEmail(Request $request)
    {
        $nombre   = trim($request->input('nombre', ''));
        $apellido = trim($request->input('apellido', ''));

        if (empty($nombre) || empty($apellido)) {
            return response()->json(['email' => null]);
        }

        $nombreNorm   = $this->normalizarNombreYApellido($nombre);
        $apellidoNorm = $this->normalizarNombreYApellido($apellido);

        return response()->json([
            'email' => $this->emailService->previsualizarEmail($nombreNorm, $apellidoNorm),
        ]);
    }

    public function store(Request $request)
    {
        // ── Validación básica ─────────────────────────────────────────────
        $request->validate([
            'dni'       => 'required|string|max:10',
            'email'     => 'required|email',
            'nombre'    => 'required|string|max:255',
            'apellido'  => 'required|string|max:255',
            'id_centro' => 'required',
        ]);

        $dni = strtoupper($request->dni);

        $validator = Validator::make($request->all(), [
            'dni' => [
                'required',
                'string',
                'max:10',
                'regex:/^(\d{8}|[XYZ]\d{7})[A-Z]$/i',
                function ($attribute, $value, $fail) use ($request) {
                    if (CentroDocente::where('dni', strtoupper($value))
                        ->where('id_centro', $request->id_centro)
                        ->exists()) {
                        $fail('Este docente ya está asignado a este centro.');
                    }
                },
            ],
            'email'     => ['required', 'email'],
            'nombre'    => 'required|string|max:255',
            'apellido'  => 'required|string|max:255',
            'id_centro' => 'required|string',
            'formacion' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $docente = Docente::where('dni', $dni)->first();

            // ── Módulos ───────────────────────────────────────────────────
            DB::table('docente_modulo_ciclo')->where('dni', $dni)->delete();
            if ($request->has('modulos')) {
                foreach ($request->modulos as $id_modulo) {
                    DB::table('docente_modulo_ciclo')->insert([
                        'dni'        => strtoupper($dni),
                        'id_modulo'  => $id_modulo,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $nombreNorm   = $this->normalizarNombreYApellido($request->nombre);
            $apellidoNorm = $this->normalizarNombreYApellido($request->apellido);

            if ($docente) {
                // ── Actualizar docente existente ──────────────────────────
                $actualizado = false;

                if ($docente->nombre !== $nombreNorm) {
                    $docente->nombre = $nombreNorm;
                    $actualizado = true;
                }

                if ($docente->apellido !== $apellidoNorm) {
                    $docente->apellido = $apellidoNorm;
                    $actualizado = true;
                }

                // Solo generar email_virtual si el docente aún no lo tiene (#58)
                if (empty($docente->email_virtual)) {
                    $docente->email_virtual = $this->emailService->generarOObtenerExistente(
                        $nombreNorm, $apellidoNorm, $dni
                    );
                    $actualizado = true;
                }

                if ($docente->de_baja) {
                    $docente->de_baja = false;
                    $actualizado = true;
                }

                if ($actualizado) {
                    $docente->save();
                }

            } else {
                // ── Crear nuevo docente ───────────────────────────────────
                // El email @fpvirtualaragon.es se genera automáticamente (#58)
                $emailVirtual = $this->emailService->generarOObtenerExistente(
                    $nombreNorm, $apellidoNorm, $dni
                );

                $docente = Docente::create([
                    'dni'           => $dni,
                    'nombre'        => $nombreNorm,
                    'apellido'      => $apellidoNorm,
                    'formacion'     => $request->boolean('formacion'),
                    'email_virtual' => $emailVirtual,
                ]);
            }

            // ── Asignar al centro ─────────────────────────────────────────
            // El email personal (no @fpvirtualaragon.es) va en la tabla pivote
            CentroDocente::create([
                'dni'       => $dni,
                'id_centro' => $request->id_centro,
                'email'     => $request->email,
            ]);

            DB::commit();

            return redirect()
                ->route('establecer_docencia.index')
                ->with('success', 'Docente asignado correctamente.')
                ->with('docente_dni', $docente->dni);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors(['error' => 'Hubo un error al guardar el docente: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * AJAX — Comprueba si el DNI existe para autocompletar el formulario.
     * Devuelve también el email_virtual si ya está generado (#58).
     */
    public function comprobarDocente($dni)
    {
        $docente  = Docente::where('dni', $dni)->first();
        $idCentro = Auth::user()->centro->id_centro;

        if ($docente) {
            return response()->json([
                'existe'        => true,
                'nombre'        => $docente->nombre,
                'apellido'      => $docente->apellido,
                'email'         => CentroDocente::where('dni', $dni)
                                    ->where('id_centro', $idCentro)
                                    ->value('email'),
                'email_virtual' => $docente->email_virtual,
            ]);
        }

        return response()->json(['existe' => false]);
    }

    protected function ejecutarMoosh(string $command): void
    {
        exec($command, $output, $status);
        if ($status !== 0) {
            \Log::error("Fallo Moosh: " . implode("\n", $output));
            throw new \Exception("Fallo al ejecutar comando Moosh.");
        }
    }
}
