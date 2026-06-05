<?php

namespace App\Http\Controllers;

use App\Models\Centro;
use App\Models\Docencia;
use App\Models\Docente;
use App\Models\Ciclo;
use App\Models\Modulo;
use App\Models\DocenteCicloModulo;
use App\Services\MoodleApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class EstablecerDocenciaController extends Controller
{
    protected $model = DocenteCicloModulo::class;

    public function index(Request $request)
    {
        $user = Auth::user();
        $centro = $user->centro;

        $docencias = DocenteCicloModulo::with(['docente', 'ciclo', 'modulo'])
            ->where('id_centro', $centro->id_centro)
            ->get();

        $sortField = $request->input('sort', 'nombre');
        $docencias = $this->sortDocencias($docencias, $sortField);

        $ciclos = $centro->ciclos;

        $modulos = Modulo::whereHas('ciclos', function ($query) use ($ciclos) {
            $query->whereIn('ciclo_modulo.id_ciclo', $ciclos->pluck('id_ciclo'));
        })
        ->orderBy('nombre')
        ->get();

        $docentes = Docente::whereIn('dni', function ($query) use ($centro) {
            $query->select('dni')
                ->from('centro_docente')
                ->where('id_centro', $centro->id_centro);
        })
            ->where('de_baja', false)
            ->get(['dni', 'nombre', 'apellido']);

        return view('establecer_docencia', compact('ciclos', 'modulos', 'docentes', 'docencias', 'sortField'));
    }

    private function sortDocencias($docencias, $sortField)
    {
        return match ($sortField) {
            'ciclo'    => $docencias->sortBy(fn($d) => strtolower($d->ciclo->nombre)),
            'modulo'   => $docencias->sortBy(fn($d) => strtolower($d->modulo->nombre)),
            'nombre'   => $docencias->sortBy(fn($d) => [strtolower($d->docente->nombre), strtolower($d->docente->apellido)]),
            'apellido' => $docencias->sortBy(fn($d) => [strtolower($d->docente->apellido), strtolower($d->docente->nombre)]),
            default    => $docencias->sortBy(fn($d) => strtolower($d->docente->$sortField)),
        };
    }

    public function store(Request $request, MoodleApiService $moodle)
    {
        $request->validate([
            'id_ciclo' => 'required|exists:ciclos,id_ciclo',
            'id_modulo' => [
                'required',
                'exists:modulos,id_modulo',
                Rule::exists('ciclo_modulo')->where(function ($query) use ($request) {
                    $query->where('id_ciclo', $request->id_ciclo)
                          ->where('id_modulo', $request->id_modulo);
                }),
            ],
            'dni' => 'required|string|exists:docentes,dni',
        ]);

        $idCentro = Auth::user()->id_centro;

        try {
            DB::transaction(function () use ($request, $idCentro, $moodle) {
                $this->model::create([
                    'id_centro' => $idCentro,
                    'id_ciclo'  => $request->id_ciclo,
                    'id_modulo' => $request->id_modulo,
                    'dni'       => $request->dni,
                ]);

                $docente = Docente::where('dni', $request->dni)->first();
                if ($docente?->is_procesado) {
                    $shortname = $this->courseShortname($idCentro, $request->id_ciclo, $request->id_modulo);
                    if ($shortname !== null) {
                        $moodle->enrolInCourse($moodle->usernameFor($docente), $shortname);
                    }
                }
            });
        } catch (Throwable $e) {
            return redirect()->route('establecer_docencia.index')->withErrors(['error' => 'No se pudo asignar la docencia: ' . $e->getMessage()]);
        }

        $existe = Docencia::where('id_centro', $idCentro)
            ->where('id_ciclo', $request->id_ciclo)
            ->where('id_modulo', $request->id_modulo)
            ->count() > 1;

        if ($existe) {
            return redirect()->route('establecer_docencia.index')->with('success', 'Docencia asignada correctamente. ¡¡¡ATENCIÓN!!! Este módulo ya tenía un docente asignado por lo que ahora este módulo tiene DOS O MÁS docentes asignados.');
        }

        return redirect()->route('establecer_docencia.index')->with('success', 'Docencia asignada correctamente.');
    }

    public function destroy($id, MoodleApiService $moodle)
    {
        $docencia = Docencia::findOrFail($id);

        try {
            DB::transaction(function () use ($docencia, $moodle) {
                $docencia->delete();

                $docente = Docente::where('dni', $docencia->dni)->first();
                if ($docente?->is_procesado) {
                    $shortname = $this->courseShortname($docencia->id_centro, $docencia->id_ciclo, $docencia->id_modulo);
                    if ($shortname !== null) {
                        $moodle->unenrolFromCourse($moodle->usernameFor($docente), $shortname);
                    }
                }
            });
        } catch (Throwable $e) {
            return redirect()->back()->withErrors(['error' => 'No se pudo eliminar la docencia: ' . $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Docencia eliminada correctamente.');
    }

    private function courseShortname(string $idCentro, string $idCiclo, string $idModulo): ?string
    {
        $moodle_codigo = Centro::find($idCentro)?->moodle_codigo;
        if (empty($moodle_codigo)) {
            return null;
        }

        return "{$moodle_codigo}-{$idCiclo}-{$idModulo}";
    }

    public function getModulosPorCiclo($id)
    {
        $modulos = Modulo::whereHas('ciclos', function ($query) use ($id) {
                $query->where('ciclo_modulo.id_ciclo', $id);
            })
            ->select('id_modulo', 'nombre')
            ->get();

        return response()->json($modulos);
    }
}
