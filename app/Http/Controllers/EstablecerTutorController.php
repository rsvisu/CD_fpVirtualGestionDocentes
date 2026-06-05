<?php

namespace App\Http\Controllers;

use App\Models\Coordinador;
use App\Models\Docente;
use App\Models\Tutor;
use App\Models\Ciclo;
use App\Services\MoodleApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EstablecerTutorController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $centro = $user->centro;

        $sortField = $request->input('sort', 'nombre');

        $tutores = Tutor::with('ciclo', 'docente')
            ->where('id_centro', $centro->id_centro)
            ->get();

        $tutores = match ($sortField) {
            'ciclo'    => $tutores->sortBy(fn($t) => strtolower($t->ciclo->nombre)),
            'nombre'   => $tutores->sortBy(fn($t) => [strtolower($t->docente->nombre), strtolower($t->docente->apellido)]),
            'apellido' => $tutores->sortBy(fn($t) => [strtolower($t->docente->apellido), strtolower($t->docente->nombre)]),
            'dni'      => $tutores->sortBy(fn($t) => strtolower($t->docente->dni)),
            default    => $tutores->sortBy(fn($t) => strtolower($t->docente->$sortField)),
        };

        $ciclos = $centro->ciclos;

        $docentes = Docente::whereIn('dni', function ($query) use ($centro) {
            $query->select('dni')
                ->from('centro_docente')
                ->where('id_centro', $centro->id_centro);
        })->get(['dni', 'nombre', 'apellido']);

        return view('establecer_tutor', compact('ciclos', 'tutores', 'docentes', 'sortField'));
    }

    public function store(Request $request, MoodleApiService $moodle)
    {
        $request->validate([
            'id_ciclo' => 'required|exists:ciclos,id_ciclo',
            'dni'      => 'required|string',
        ]);

        $idCentro = Auth::user()->id_centro;

        $yaExisteTutor = Tutor::where('id_centro', $idCentro)
            ->where('id_ciclo', $request->id_ciclo)
            ->exists();

        if ($yaExisteTutor) {
            return redirect()->back()->withErrors(['id_ciclo' => 'Ya existe un tutor asignado a este ciclo.']);
        }

        DB::transaction(function () use ($request, $idCentro) {
            Tutor::create([
                'id_centro' => $idCentro,
                'id_ciclo'  => $request->id_ciclo,
                'dni'       => $request->dni,
            ]);
        });

        $moodleWarning = null;
        $docente = Docente::where('dni', $request->dni)->first();
        if ($docente?->is_procesado) {
            try {
                $moodle->addToCohort($moodle->usernameFor($docente), "tutores_ciclo_{$request->id_ciclo}");
            } catch (Throwable $e) {
                Log::channel('moodle_api')->error('Error añadiendo tutor a cohorte Moodle', [
                    'dni' => $request->dni, 'error' => $e->getMessage(),
                ]);
                $moodleWarning = 'Aviso: no se pudo sincronizar con Moodle (' . $e->getMessage() . '). El tutor se guardó correctamente.';
            }
        }

        if ($moodleWarning) {
            return redirect()->route('establecer_tutor.index')->with('success', 'Tutor añadido correctamente.')->with('warning', $moodleWarning);
        }

        return redirect()->route('establecer_tutor.index')->with('success', 'Tutor añadido correctamente.');
    }

    public function destroy($id, Request $request, MoodleApiService $moodle)
    {
        $tutor = Tutor::findOrFail($id);

        $esCoordinador = Coordinador::where('id_centro', $tutor->id_centro)
                    ->where('id_ciclo', $tutor->id_ciclo)
                    ->where('dni', $tutor->dni)
                    ->exists();

        $docente = Docente::where('dni', $tutor->dni)->first();
        $eliminarCoord = $request->has('eliminar_coordinador') && $esCoordinador;

        DB::transaction(function () use ($tutor, $eliminarCoord) {
            if ($eliminarCoord) {
                Coordinador::where('id_centro', $tutor->id_centro)
                    ->where('id_ciclo', $tutor->id_ciclo)
                    ->where('dni', $tutor->dni)
                    ->delete();
            }
            $tutor->delete();
        });

        $moodleWarning = null;
        if ($docente?->is_procesado) {
            try {
                if ($eliminarCoord) {
                    $moodle->removeFromCohort($moodle->usernameFor($docente), "coordinadores_ciclo_{$tutor->id_ciclo}");
                }
                $moodle->removeFromCohort($moodle->usernameFor($docente), "tutores_ciclo_{$tutor->id_ciclo}");
            } catch (Throwable $e) {
                Log::channel('moodle_api')->error('Error eliminando tutor de cohorte Moodle', [
                    'dni' => $tutor->dni, 'error' => $e->getMessage(),
                ]);
                $moodleWarning = 'Aviso: no se pudo sincronizar con Moodle (' . $e->getMessage() . '). El tutor se eliminó correctamente.';
            }
        }

        $successMsg = 'Tutor eliminado correctamente' . ($eliminarCoord ? ' y también se ha eliminado como coordinador' : '');

        if ($moodleWarning) {
            return redirect()->back()->with('success', $successMsg)->with('warning', $moodleWarning);
        }

        return redirect()->back()->with('success', $successMsg);
    }
}
