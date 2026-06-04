<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CentroDocente;
use App\Models\Docente;
use App\Services\MoodleApiService;
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
     * Cabecera del CSV de Moodle (mismo formato de 29 columnas que Google Workspace).
     */
    private const MOODLE_HEADER = 'First Name [Required],Last Name [Required],Email Address [Required],'
        . 'Password [Required],Password Hash Function [UPLOAD ONLY],Org Unit Path [Required],'
        . 'New Primary Email [UPLOAD ONLY],Recovery Email,Home Secondary Email,Work Secondary Email,'
        . 'Recovery Phone [MUST BE IN THE E.164 FORMAT],Work Phone,Home Phone,Mobile Phone,'
        . 'Work Address,Home Address,Employee ID,Employee Type,Employee Title,Manager Email,'
        . 'Department,Cost Center,Building ID,Floor Name,Floor Section,'
        . 'Change Password at Next Sign-In,New Status [UPLOAD ONLY],New Licenses [UPLOAD ONLY],'
        . 'Advanced Protection Program enrollment';

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

        // 29 columnas Google Workspace (#61)
        $googleCols = [
            $docente->nombre,           //  1 First Name [Required]
            $docente->apellido,         //  2 Last Name [Required]
            $docente->email_virtual,    //  3 Email Address [Required]
            self::PASSWORD_INICIAL,     //  4 Password [Required]
            '',                         //  5 Password Hash Function [UPLOAD ONLY]
            self::ORG_UNIT,             //  6 Org Unit Path [Required]
            '',                         //  7 New Primary Email [UPLOAD ONLY]
            $emailPersonal,             //  8 Recovery Email
            '',                         //  9 Home Secondary Email
            $emailPersonal,             // 10 Work Secondary Email
            '',                         // 11 Recovery Phone
            '',                         // 12 Work Phone
            '',                         // 13 Home Phone
            '',                         // 14 Mobile Phone
            '',                         // 15 Work Address
            '',                         // 16 Home Address
            $docente->dni,              // 17 Employee ID
            '',                         // 18 Employee Type
            '',                         // 19 Employee Title
            '',                         // 20 Manager Email
            '',                         // 21 Department
            '',                         // 22 Cost Center
            '',                         // 23 Building ID
            '',                         // 24 Floor Name
            '',                         // 25 Floor Section
            'TRUE',                     // 26 Change Password at Next Sign-In
            'Active',                   // 27 New Status [UPLOAD ONLY]
            '',                         // 28 New Licenses [UPLOAD ONLY]
            'FALSE',                    // 29 Advanced Protection Program enrollment
        ];

        // 29 columnas Moodle (#62) — sin Work Secondary Email ni New Status
        $moodleCols = [
            $docente->nombre,           //  1 First Name [Required]
            $docente->apellido,         //  2 Last Name [Required]
            $docente->email_virtual,    //  3 Email Address [Required]
            self::PASSWORD_INICIAL,     //  4 Password [Required]
            '',                         //  5 Password Hash Function [UPLOAD ONLY]
            self::ORG_UNIT,             //  6 Org Unit Path [Required]
            '',                         //  7 New Primary Email [UPLOAD ONLY]
            $emailPersonal,             //  8 Recovery Email
            '',                         //  9 Home Secondary Email
            '',                         // 10 Work Secondary Email
            '',                         // 11 Recovery Phone
            '',                         // 12 Work Phone
            '',                         // 13 Home Phone
            '',                         // 14 Mobile Phone
            '',                         // 15 Work Address
            '',                         // 16 Home Address
            $docente->dni,              // 17 Employee ID
            '',                         // 18 Employee Type
            '',                         // 19 Employee Title
            '',                         // 20 Manager Email
            '',                         // 21 Department
            '',                         // 22 Cost Center
            '',                         // 23 Building ID
            '',                         // 24 Floor Name
            '',                         // 25 Floor Section
            'TRUE',                     // 26 Change Password at Next Sign-In
            '',                         // 27 New Status [UPLOAD ONLY]
            '',                         // 28 New Licenses [UPLOAD ONLY]
            'FALSE',                    // 29 Advanced Protection Program enrollment
        ];

        return response()->json([
            'dni' => $docente->dni,
            'nombre' => $docente->nombre,
            'apellido' => $docente->apellido,
            'email_virtual' => $docente->email_virtual,
            'email_personal' => $emailPersonal,
            'google_csv'    => implode(',', $googleCols),
            'moodle_csv'    => implode(',', $moodleCols),
            'google_header' => self::GOOGLE_HEADER,
            'moodle_header' => self::MOODLE_HEADER,
        ]);
    }

    // ── procesarAltas ─────────────────────────────────────────────────────────

    /**
     * Da de alta en Moodle (vía API) los docentes seleccionados y marca como
     * procesados los que se crearon o ya existían. Los que fallan no se marcan.
     *
     * Respuesta:
     *   {
     *     ok:      bool,
     *     created: ["DNI", ...],   // creados ahora en Moodle
     *     skipped: ["DNI", ...],   // ya existían en Moodle (marcados igual)
     *     failed:  { "DNI": "mensaje de error", ... }
     *   }
     */
    public function procesarAltas(Request $request, MoodleApiService $moodle)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:docentes,id',
        ]);

        $docentes = Docente::whereIn('id', $request->ids)
            ->where('de_baja', false)
            ->whereNotNull('email_virtual')
            ->where('email_virtual', '!=', '')
            ->get();

        $resumen = $moodle->createUsers($docentes);

        $dnisProcesados = array_merge($resumen['created'], $resumen['skipped']);
        if ($dnisProcesados !== []) {
            Docente::whereIn('dni', $dnisProcesados)
                ->update([
                    'is_procesado' => true,
                    'fecha_procesado' => now(),
                ]);
        }

        return response()->json([
            'ok' => $resumen['failed'] === [],
            'created' => $resumen['created'],
            'skipped' => $resumen['skipped'],
            'failed' => $resumen['failed'],
        ]);
    }
}
