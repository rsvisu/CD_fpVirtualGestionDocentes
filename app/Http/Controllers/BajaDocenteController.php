<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Docente;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Tutor;
use App\Models\Coordinador;
use App\Models\Docencia;

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

    public function destroy($dni)
    {
        $centro = Auth::user()->centro;
        $idCentro = $centro->id_centro;

        DB::beginTransaction();

        try {
            $dniUpper = strtoupper($dni);
            // Buscamos al docente para poder usar su nombre en el mensaje
            /*$docente = Docente::where('dni', $dniUpper)->firstOrFail();
            $docente->update([
                'de_baja' => true, // Marcamos como inactivo en Docker
            ]);
             */
            // Obtener todas las asignaciones antes de borrar
            /*$tutorias = Tutor::where('dni', $dni)->where('id_centro', $idCentro)->get();
            $coordinaciones = Coordinador::where('dni', $dni)->where('id_centro', $idCentro)->get();
            $docencias = Docencia::where('dni', $dni)->where('id_centro', $idCentro)->get();

            // Comando moosh para desmatricular de cohorts tutores
            foreach ($tutorias as $tutor) {
                $cohortName = "tutores_ciclo_{$tutor->id_ciclo}";
                $command = "moosh cohort-unenrol -u " . escapeshellarg($dni) . " " . escapeshellarg($cohortName);
                $this->ejecutarMoosh($command);
            }

            // Comando moosh para desmatricular de cohorts coordinadores
            foreach ($coordinaciones as $coordinador) {
                $cohortName = "coordinadores_ciclo_{$coordinador->id_ciclo}";
                $command = "moosh cohort-unenrol -u " . escapeshellarg($dni) . " " . escapeshellarg($cohortName);
                $this->ejecutarMoosh($command);
            }

            // Comando moosh para desmatricular de cursos (docencia)
            foreach ($docencias as $docencia) {
                $courseName = "modulo_{$docencia->id_modulo}";
                $command = "moosh course-unenrol -u " . escapeshellarg($dni) . " " . escapeshellarg($courseName);
                $this->ejecutarMoosh($command);
            }*/

            // Verificar si el docente pertenece a más centros
            /*$otrosCentros = DB::table('centro_docente')
                ->where('dni', $dni)
                ->exists();

            // Si no pertenece a ningún otro centro, suspender usuario en Moodle
            if (!$otrosCentros) {
                // Comando moosh para suspender cuenta
                $usuarioMoodle = escapeshellarg($dni);
                $command = "moosh user-suspend " . $usuarioMoodle;
                $this->ejecutarMoosh($command);
            }*/

            $docente = Docente::where('dni', $dniUpper)->firstOrFail();
            $docente->de_baja = true;
            $docente->save();

            DB::commit();

            // --- Auditoría: registrar la baja en el canal dedicado ---
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

            // --- Auditoría: error crítico al dar de baja ---
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
    public function reactivar($dni)
    {
        DB::beginTransaction();

        try {
            $dniUpper = strtoupper($dni);

            // Buscamos al docente que está de baja
            $docente = Docente::where('dni', $dniUpper)->firstOrFail();

            // Cambiamos el estado a ACTIVO
            $docente->update([
                'de_baja' => false,
            ]);

            DB::commit();

            // --- Auditoría: registrar la reactivación en el canal dedicado ---
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

    //Ejecuta & Control de errores para comandos moosh
    protected function ejecutarMoosh($command)
    {
        exec($command, $output, $status);
        if ($status !== 0) {
            Log::error("Fallo Moosh: " . implode("\n", $output));
            throw new \Exception("Fallo al ejecutar comando Moosh.");
        }
    }


}
