<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CentroDocente;
use App\Models\Docente;
use Illuminate\Http\Request;

class AltaPlataformaController extends Controller
{
    /** Contraseña inicial fija para todos los docentes de Google Workspace */
    private const PASSWORD_INICIAL = 'Cambiam3!_';

    /** Unidad organizativa en Google Workspace */
    private const ORG_UNIT = '/Profesorado';

    /**
     * Cabecera oficial del CSV de Google Workspace (29 columnas).
     */
    private const GOOGLE_HEADER = 'First Name [Required],Last Name [Required],Email Address [Required],'
        . 'Password [Required],Password Hash Function [UPLOAD ONLY],Org Unit Path [Required],'
        . 'New Primary Email [UPLOAD ONLY],Recovery Email,Home Secondary Email,Work Secondary Email,'
        . 'Recovery Phone [MUST BE IN THE E.164 FORMAT],Work Phone,Home Phone,Mobile Phone,'
        . 'Work Address,Home Address,Employee ID,Employee Type,Employee Title,Manager Email,'
        . 'Department,Cost Center,Building ID,Floor Name,Floor Section,'
        . 'Change Password at Next Sign-In,New Status [UPLOAD ONLY],New Licenses [UPLOAD ONLY],'
        . 'Advanced Protection Program enrollment';

    /**
     * Cabecera del CSV de Moodle.
     */
    private const MOODLE_HEADER = 'username,firstname,lastname,email';

    // ── index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Docente::query()
            ->where('de_baja', false)
            ->whereNotNull('email_virtual')
            ->where('email_virtual', '!=', '');

        if ($request->filled('estado')) {
            if ($request->estado === 'pendiente') {
                $query->where('is_procesado', false);
            } elseif ($request->estado === 'procesado') {
                $query->where('is_procesado', true);
            }
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('dni', 'like', "%{$buscar}%");
            });
        }

        $docentes = $query->clone()->orderBy('apellido')->orderBy('nombre')->paginate(20)->withQueryString();

        // Cargamos emails personales en bloque (evita N+1)
        $dnis = $query->clone()->pluck('dni');
        $emailsByDni = CentroDocente::whereIn('dni', $dnis)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id_centro')
            ->get()
            ->groupBy('dni')
            ->map(fn($items) => $items->first()->email);

        $docentesJson = $query->clone()->orderBy('apellido')->orderBy('nombre')->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'dni'            => $d->dni,
                'nombre'         => $d->nombre,
                'apellido'       => $d->apellido,
                'email_virtual'  => $d->email_virtual,
                'email_personal' => $emailsByDni[$d->dni] ?? '',
                'is_procesado'   => $d->is_procesado,
                'fecha_procesado' => $d->fecha_procesado?->format('d/m/Y H:i'),
            ]);

        return view('admin.alta_plataforma', compact('docentes', 'docentesJson'));
    }

    // ── preview ───────────────────────────────────────────────────────────────

    public function preview(int $id)
    {
        $docente       = Docente::findOrFail($id);
        $emailPersonal = CentroDocente::where('dni', $docente->dni)
                            ->whereNotNull('email')->where('email', '!=', '')
                            ->value('email') ?? '';

        // 29 columnas Google Workspace
        $googleCols = [
            $docente->nombre,           // 1  First Name
            $docente->apellido,         // 2  Last Name
            $docente->email_virtual,    // 3  Email Address
            self::PASSWORD_INICIAL,     // 4  Password
            '',                         // 5  Password Hash Function
            self::ORG_UNIT,             // 6  Org Unit Path
            '',                         // 7  New Primary Email
            $emailPersonal,             // 8  Recovery Email
            '', '', '', '', '', '', '', '', // 9-16 empty
            $docente->dni,              // 17 Employee ID
            '', '', '', '', '', '', '', '', // 18-25 empty
            'TRUE',                     // 26 Change Password at Next Sign-In
            '', '',                     // 27-28 New Status, New Licenses
            'FALSE',                    // 29 Advanced Protection Program enrollment
        ];

        // 4 columnas Moodle
        $moodleCols = [
            '"prof' . $docente->dni . '"',
            '"' . $docente->nombre . '"',
            '"' . $docente->apellido . '"',
            '"' . $docente->email_virtual . '"',
        ];

        return response()->json([
            'dni'           => $docente->dni,
            'nombre'        => $docente->nombre,
            'apellido'      => $docente->apellido,
            'email_virtual' => $docente->email_virtual,
            'email_personal' => $emailPersonal,
            'google_csv'    => implode(',', $googleCols),
            'moodle_csv'    => implode(',', $moodleCols),
            'google_header' => self::GOOGLE_HEADER,
            'moodle_header' => self::MOODLE_HEADER,
        ]);
    }

    // ── procesarAltas ─────────────────────────────────────────────────────────

    public function procesarAltas(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:docentes,id',
        ]);

        Docente::whereIn('id', $request->ids)
            ->where('de_baja', false)
            ->update([
                'is_procesado'    => true,
                'fecha_procesado' => now(),
            ]);

        return response()->json(['ok' => true, 'procesados' => count($request->ids)]);
    }
}
