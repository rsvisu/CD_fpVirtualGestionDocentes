<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use Illuminate\Http\Request;

class AltaPlataformaController extends Controller
{
    /**
     * Listado de docentes pendientes/procesados para dar de alta en plataformas.
     * Excluye los que están de baja y los que no tienen email_virtual aún.
     */
    public function index(Request $request)
    {
        $query = Docente::query()
            ->where('de_baja', false)
            ->whereNotNull('email_virtual')
            ->where('email_virtual', '!=', '');

        // Filtro por estado
        if ($request->filled('estado')) {
            if ($request->estado === 'pendiente') {
                $query->where('is_procesado', false);
            } elseif ($request->estado === 'procesado') {
                $query->where('is_procesado', true);
            }
        }

        // Búsqueda por nombre, apellido o DNI
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('dni', 'like', "%{$buscar}%");
            });
        }

        $docentes = $query->clone()->orderBy('apellido')->orderBy('nombre')->paginate(20)->withQueryString();

        // Embed all docente data as JSON for client-side CSV generation
        // Use clone() so paginate()'s LIMIT/OFFSET don't bleed into this query
        $docentesJson = $query->clone()->orderBy('apellido')->orderBy('nombre')->get()->map(function ($d) {
            return [
                'id'            => $d->id,
                'dni'           => $d->dni,
                'nombre'        => $d->nombre,
                'apellido'      => $d->apellido,
                'email_virtual' => $d->email_virtual,
                'is_procesado'  => $d->is_procesado,
                'fecha_procesado' => $d->fecha_procesado?->format('d/m/Y H:i'),
            ];
        });

        return view('admin.alta_plataforma', compact('docentes', 'docentesJson'));
    }

    /**
     * AJAX — Previsualización de la línea CSV para un docente.
     */
    public function preview(int $id)
    {
        $docente = Docente::findOrFail($id);

        $local = explode('@', $docente->email_virtual)[0] ?? $docente->email_virtual;

        return response()->json([
            'dni'           => $docente->dni,
            'nombre'        => $docente->nombre,
            'apellido'      => $docente->apellido,
            'email_virtual' => $docente->email_virtual,
            'google_csv'    => implode(',', [
                $docente->nombre . ' ' . $docente->apellido,
                $docente->email_virtual,
                '',  // Password (vacío — se generará)
                '',  // Org unit
            ]),
            'moodle_csv'    => implode(',', [
                '"prof' . $docente->dni . '"',  // username
                '"' . $docente->nombre . '"',   // firstname (nombre_normalizado)
                '"' . $docente->apellido . '"', // lastname (apellido_normalizado)
                '"' . $docente->email_virtual . '"', // email
            ]),
        ]);
    }

    /**
     * Marcar los docentes seleccionados como procesados.
     */
    public function procesarAltas(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:docentes,id',
        ]);

        Docente::whereIn('id', $request->ids)
            ->where('de_baja', false)
            ->update([
                'is_procesado'   => true,
                'fecha_procesado' => now(),
            ]);

        return response()->json(['ok' => true, 'procesados' => count($request->ids)]);
    }
}
