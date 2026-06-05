<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CentroDocente;
use App\Models\Docente;
use App\Services\MoodleApiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Tutor;
use App\Models\Coordinador;
use App\Models\Docencia;
use Throwable;

class BajaDocenteController extends Controller
{
    public function index()
    {
        $centro = Auth::user()->centro;
        $idCentro = $centro->id_centro;

        $docentes = Docente::whereIn('dni', function ($query) use ($idCentro) {
            $query->select('dni')
                ->from('centro_docente')
                ->where('id_centro', $idCentro);
        })->get();

        foreach ($docentes as $docente) {
            $dni = $docente->dni;
            $docente->es_tutor = Tutor::where('dni', $dni)->where('id_centro', $idCentro)->exists();
            $docente->es_coordinador = Coordinador::where('dni', $dni)->where('id_centro', $idCentro)->exists();
            $docente->tiene_docencia = Docencia::where('dni', $dni)->where('id_centro', $idCentro)->exists();
        }

        return view('baja_docente', compact('docentes'));
    }

    public function destroy($dni, MoodleApiService $moodle)
    {
        $centro = Auth::user()->centro;
        $idCentro = $centro->id_centro;

        DB::beginTransaction();

        try {
            $dniUpper = strtoupper($dni);
            $docente = Docente::where('dni', $dniUpper)->firstOrFail();

            // ── Sincronización con Moodle ────────────────────────────────────
            if ($docente->is_procesado) {
                $username = $moodle->usernameFor($docente);

                try {
                    // Desmatricular de todos los roles de ESTE centro
                    $moodle->unenrolDocente($docente, $idCentro);

                    // Suspender solo si no tiene otros centros activos
                    $tieneOtrosCentros = CentroDocente::where('dni', $dniUpper)
                        ->where('id_centro', '!=', $idCentro)
                        ->whereHas('docente', fn ($q) => $q->where('de_baja', false))
                        ->exists();

                    if (! $tieneOtrosCentros) {
                        $moodle->suspendUser($username);
                    }
                } catch (Throwable $e) {
                    // Error en Moodle no aborta la baja en BD
                    Log::channel('moodle_api')->error('Error al dar de baja en Moodle', [
                        'dni'   => $dniUpper,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $docente->de_baja = true;
            $docente->save();

            DB::commit();

            Log::channel('bajas_docentes')->info('Baja de docente procesada', [
                'dni_docente'  => $dniUpper,
                'nombre'       => $docente->nombre . ' ' . $docente->apellido,
                'usuario_id'   => Auth::id(),
                'usuario_name' => Auth::user()->name ?? Auth::user()->email ?? 'desconocido',
                'id_centro'    => $idCentro,
                'ip'           => request()->ip(),
            ]);

            return redirect()->route('docentes.index')->with('success', 'Docente dado de baja correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('bajas_docentes')->critical('Error al dar de baja al docente', [
                'dni_docente' => strtoupper($dni),
                'usuario_id'  => Auth::id(),
                'id_centro'   => $idCentro ?? null,
                'error'       => $e->getMessage(),
            ]);

            return redirect()->route('docentes.index')
                ->withErrors(['error' => 'Error al dar de baja al docente: ' . $e->getMessage()]);
        }
    }

    public function reactivar($dni, MoodleApiService $moodle)
    {
        DB::beginTransaction();

        try {
            $dniUpper = strtoupper($dni);
            $docente = Docente::where('dni', $dniUpper)->firstOrFail();

            $docente->update(['de_baja' => false]);

            DB::commit();

            // ── Sincronización con Moodle ────────────────────────────────────
            if ($docente->is_procesado) {
                try {
                    $moodle->unsuspendUser($moodle->usernameFor($docente));
                    $moodle->enrollDocente($docente);
                } catch (Throwable $e) {
                    Log::channel('moodle_api')->error('Error al reactivar en Moodle', [
                        'dni'   => $dniUpper,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::channel('bajas_docentes')->notice('Docente reactivado', [
                'dni_docente'  => $dniUpper,
                'nombre'       => $docente->nombre . ' ' . $docente->apellido,
                'usuario_id'   => Auth::id(),
                'usuario_name' => Auth::user()->name ?? Auth::user()->email ?? 'desconocido',
                'ip'           => request()->ip(),
            ]);

            return redirect()->route('docentes.index')
                ->with('success', "El docente {$docente->nombre} ha sido reactivado y ya puede acceder al sistema.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('bajas_docentes')->error('Error al reactivar docente', [
                'dni_docente' => strtoupper($dni),
                'usuario_id'  => Auth::id(),
                'error'       => $e->getMessage(),
            ]);

            return redirect()->route('docentes.index')
                ->withErrors(['error' => 'Error al reactivar al docente: ' . $e->getMessage()]);
        }
    }
}
