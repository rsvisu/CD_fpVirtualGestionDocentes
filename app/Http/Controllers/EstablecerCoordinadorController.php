<?php

namespace App\Http\Controllers;

use App\Models\Coordinador;
use App\Models\Ciclo;
use App\Models\Docente;
use App\Models\Tutor;
use App\Services\MoodleApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class EstablecerCoordinadorController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $centro = $user->centro;

        $sortField = $request->input('sort', 'nombre');

        $coordinadores = Coordinador::with('ciclo', 'centro', 'docente')
            ->where('id_centro', $centro->id_centro)
            ->get();

        $coordinadores = match ($sortField) {
            'ciclo'    => $coordinadores->sortBy(fn($c) => strtolower($c->ciclo->nombre)),
            'nombre'   => $coordinadores->sortBy(fn($c) => [strtolower($c->docente->nombre), strtolower($c->docente->apellido)]),
            'apellido' => $coordinadores->sortBy(fn($c) => [strtolower($c->docente->apellido), strtolower($c->docente->nombre)]),
            default    => $coordinadores->sortBy(fn($c) => strtolower($c->docente->$sortField)),
        };

        $ciclos = $centro->ciclos;

        $docentes = Docente::whereIn('dni', function ($query) use ($centro) {
            $query->select('dni')
                  ->from('centro_docente')
                  ->where('id_centro', $centro->id_centro);
        })->get(['dni', 'nombre', 'apellido']);

        return view('establecer_coordinador', compact('ciclos', 'coordinadores', 'docentes', 'sortField'));
    }

    public function store(Request $request, MoodleApiService $moodle)
    {
        $request->validate([
            'id_ciclo' => 'required|exists:ciclos,id_ciclo',
            'dni'      => 'required|string',
            'es_tutor' => 'nullable|boolean',
        ]);

        $idCentro = Auth::user()->id_centro;

        $yaExisteCoordinador = Coordinador::where('id_centro', $idCentro)
            ->where('id_ciclo', $request->id_ciclo)
            ->exists();

        if ($yaExisteCoordinador) {
            return redirect()->back()->withErrors(['id_ciclo' => 'Ya existe un coordinador asignado a este ciclo.']);
        }

        if ($request->es_tutor == 1) {
            $yaExisteTutor = Tutor::where('id_centro', $idCentro)
                ->where('id_ciclo', $request->id_ciclo)
                ->exists();

            if ($yaExisteTutor) {
                return redirect()->back()->withErrors(['id_ciclo' => 'Ya existe un tutor asignado a este ciclo.']);
            }

            Tutor::create([
                'id_centro' => $idCentro,
                'id_ciclo'  => $request->id_ciclo,
                'dni'       => $request->dni,
            ]);

            $this->syncAddToCohort($moodle, $request->dni, "tutores_ciclo_{$request->id_ciclo}");
        }

        Coordinador::create([
            'id_centro' => $idCentro,
            'id_ciclo'  => $request->id_ciclo,
            'dni'       => $request->dni,
        ]);

        $this->syncAddToCohort($moodle, $request->dni, "coordinadores_ciclo_{$request->id_ciclo}");

        return redirect()->route('establecer_coordinador.index')->with('success', 'Coordinador añadido correctamente.');
    }

    public function destroy($id, Request $request, MoodleApiService $moodle)
    {
        $coordinador = Coordinador::findOrFail($id);

        $esTutor = Tutor::where('id_centro', $coordinador->id_centro)
                    ->where('id_ciclo', $coordinador->id_ciclo)
                    ->where('dni', $coordinador->dni)
                    ->exists();

        if ($request->has('eliminar_tutor') && $esTutor) {
            Tutor::where('id_centro', $coordinador->id_centro)
                ->where('id_ciclo', $coordinador->id_ciclo)
                ->where('dni', $coordinador->dni)
                ->delete();

            $this->syncRemoveFromCohort($moodle, $coordinador->dni, "tutores_ciclo_{$coordinador->id_ciclo}");
        }

        $coordinador->delete();

        $this->syncRemoveFromCohort($moodle, $coordinador->dni, "coordinadores_ciclo_{$coordinador->id_ciclo}");

        return redirect()->back()->with('success', 'Coordinador eliminado correctamente' .
            ($request->has('eliminar_tutor') && $esTutor ? ' y también se ha eliminado como tutor' : ''));
    }

    // ── Helpers de sync Moodle (fire-and-forget, sin afectar la respuesta) ──

    private function syncAddToCohort(MoodleApiService $moodle, string $dni, string $cohort): void
    {
        $docente = Docente::where('dni', $dni)->first();
        if (! $docente?->is_procesado) {
            return;
        }
        try {
            $moodle->addToCohort($moodle->usernameFor($docente), $cohort);
        } catch (Throwable $e) {
            Log::channel('moodle_api')->error('Error sync add cohorte coordinador', [
                'dni' => $dni, 'cohort' => $cohort, 'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncRemoveFromCohort(MoodleApiService $moodle, string $dni, string $cohort): void
    {
        $docente = Docente::where('dni', $dni)->first();
        if (! $docente?->is_procesado) {
            return;
        }
        try {
            $moodle->removeFromCohort($moodle->usernameFor($docente), $cohort);
        } catch (Throwable $e) {
            Log::channel('moodle_api')->error('Error sync remove cohorte coordinador', [
                'dni' => $dni, 'cohort' => $cohort, 'error' => $e->getMessage(),
            ]);
        }
    }
}
