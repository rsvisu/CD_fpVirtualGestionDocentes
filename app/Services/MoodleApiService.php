<?php

namespace App\Services;

use App\Exceptions\MoodleApiException;
use App\Models\Coordinador;
use App\Models\Docencia;
use App\Models\Docente;
use App\Models\Tutor;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MoodleApiService
 *
 * Envoltorio sobre la API REST (Web Services) de Moodle para gestionar el ciclo
 * de vida completo de los docentes: alta, matrícula en cohortes/cursos, bajas y
 * reactivaciones.
 *
 * Funciones de Moodle utilizadas:
 *   - core_user_get_users_by_field      (lookup por username)
 *   - core_user_create_users            (alta del usuario)
 *   - core_user_update_users            (suspender / reactivar)
 *   - core_cohort_add_cohort_members    (matricular en cohorte)
 *   - core_cohort_search_cohorts        (buscar cohorte por idnumber)
 *   - core_cohort_delete_cohort_members (desmatricular de cohorte)
 *   - core_course_get_courses_by_field  (buscar curso por shortname)
 *   - enrol_manual_enrol_users          (matricular en curso)
 *   - enrol_manual_unenrol_users        (desmatricular de curso)
 *
 * Convenio de username: "prof" + DNI en minúsculas.
 * Convenio cohorte tutor:        tutores_ciclo_{id_ciclo}
 * Convenio cohorte coordinador:  coordinadores_ciclo_{id_ciclo}
 * Convenio curso módulo:         modulo_{id_modulo}
 */
class MoodleApiService
{
    // ── Alta de usuarios ──────────────────────────────────────────────────────

    /**
     * Procesa un lote de docentes. Devuelve un desglose por DNI:
     *   [
     *     'created' => ['12345678A', ...],          // creados ahora vía API
     *     'skipped' => ['87654321B', ...],          // ya existían en Moodle
     *     'failed'  => ['99999999C' => 'mensaje'],  // fallo en la API
     *   ]
     *
     * Captura errores por docente; un fallo no aborta el lote.
     *
     * @param  iterable<Docente>  $docentes
     */
    public function createUsers(iterable $docentes): array
    {
        $resumen = ['created' => [], 'skipped' => [], 'failed' => []];

        foreach ($docentes as $docente) {
            $dni = $docente->dni;
            $username = $this->usernameFor($docente);

            try {
                if ($this->findUserByUsername($username) !== null) {
                    $resumen['skipped'][] = $dni;

                    continue;
                }

                $this->createUser($docente);
                $resumen['created'][] = $dni;
            } catch (Throwable $e) {
                Log::channel('moodle_api')->error('Alta fallida', [
                    'dni'      => $dni,
                    'username' => $username,
                    'error'    => $e->getMessage(),
                ]);
                $resumen['failed'][$dni] = $e->getMessage();
            }
        }

        return $resumen;
    }

    public function usernameFor(Docente $docente): string
    {
        return 'prof'.strtolower($docente->dni);
    }

    // ── Matrícula en cohortes ─────────────────────────────────────────────────

    /**
     * Añade un usuario a una cohorte identificada por su idnumber.
     * La cohorte debe existir previamente en Moodle con ese idnumber.
     */
    public function addToCohort(string $username, string $cohortIdnumber): void
    {
        $this->request('core_cohort_add_cohort_members', [
            'members[0][cohorttype][type]'  => 'idnumber',
            'members[0][cohorttype][value]' => $cohortIdnumber,
            'members[0][usertype][type]'    => 'username',
            'members[0][usertype][value]'   => $username,
        ]);

        Log::channel('moodle_api')->info('Añadido a cohorte', [
            'username' => $username,
            'cohort'   => $cohortIdnumber,
        ]);
    }

    /**
     * Elimina un usuario de una cohorte. Busca el ID numérico de la cohorte
     * por idnumber (requiere core_cohort_search_cohorts) y luego elimina.
     */
    public function removeFromCohort(string $username, string $cohortIdnumber): void
    {
        $cohortId = $this->findCohortByIdnumber($cohortIdnumber);
        if ($cohortId === null) {
            Log::channel('moodle_api')->warning('Cohorte no encontrada, se omite desmatrícula', [
                'cohort' => $cohortIdnumber,
            ]);

            return;
        }

        $userId = $this->findMoodleUserId($username);
        if ($userId === null) {
            Log::channel('moodle_api')->warning('Usuario no encontrado en Moodle, se omite desmatrícula de cohorte', [
                'username' => $username,
                'cohort'   => $cohortIdnumber,
            ]);

            return;
        }

        $this->request('core_cohort_delete_cohort_members', [
            'members[0][cohortid]' => $cohortId,
            'members[0][userid]'   => $userId,
        ]);

        Log::channel('moodle_api')->info('Eliminado de cohorte', [
            'username' => $username,
            'cohort'   => $cohortIdnumber,
        ]);
    }

    // ── Matrícula en cursos ───────────────────────────────────────────────────

    /**
     * Matricula un usuario en un curso identificado por su shortname.
     * roleId 3 = editingteacher en Moodle estándar (configurable con MOODLE_TEACHER_ROLE_ID).
     */
    public function enrolInCourse(string $username, string $courseShortname, ?int $roleId = null): void
    {
        $courseId = $this->findCourseByShortname($courseShortname);
        if ($courseId === null) {
            Log::channel('moodle_api')->warning('Curso no encontrado, se omite matrícula', [
                'course' => $courseShortname,
            ]);

            return;
        }

        $userId = $this->findMoodleUserId($username);
        if ($userId === null) {
            Log::channel('moodle_api')->warning('Usuario no encontrado en Moodle, se omite matrícula en curso', [
                'username' => $username,
                'course'   => $courseShortname,
            ]);

            return;
        }

        $roleId ??= (int) config('services.moodle.teacher_role_id', 3);

        $this->request('enrol_manual_enrol_users', [
            'enrolments[0][roleid]'  => $roleId,
            'enrolments[0][userid]'  => $userId,
            'enrolments[0][courseid]' => $courseId,
        ]);

        Log::channel('moodle_api')->info('Matriculado en curso', [
            'username' => $username,
            'course'   => $courseShortname,
            'roleid'   => $roleId,
        ]);
    }

    /**
     * Desmatricula un usuario de un curso identificado por su shortname.
     */
    public function unenrolFromCourse(string $username, string $courseShortname): void
    {
        $courseId = $this->findCourseByShortname($courseShortname);
        if ($courseId === null) {
            Log::channel('moodle_api')->warning('Curso no encontrado, se omite desmatrícula', [
                'course' => $courseShortname,
            ]);

            return;
        }

        $userId = $this->findMoodleUserId($username);
        if ($userId === null) {
            Log::channel('moodle_api')->warning('Usuario no encontrado en Moodle, se omite desmatrícula de curso', [
                'username' => $username,
                'course'   => $courseShortname,
            ]);

            return;
        }

        $this->request('enrol_manual_unenrol_users', [
            'enrolments[0][userid]'   => $userId,
            'enrolments[0][courseid]' => $courseId,
        ]);

        Log::channel('moodle_api')->info('Desmatriculado de curso', [
            'username' => $username,
            'course'   => $courseShortname,
        ]);
    }

    // ── Suspensión / reactivación ─────────────────────────────────────────────

    public function suspendUser(string $username): void
    {
        $userId = $this->findMoodleUserId($username);
        if ($userId === null) {
            Log::channel('moodle_api')->warning('Usuario no encontrado en Moodle, no se puede suspender', [
                'username' => $username,
            ]);

            return;
        }

        $this->request('core_user_update_users', [
            'users[0][id]'        => $userId,
            'users[0][suspended]' => 1,
        ]);

        Log::channel('moodle_api')->info('Usuario suspendido', ['username' => $username]);
    }

    public function unsuspendUser(string $username): void
    {
        $userId = $this->findMoodleUserId($username);
        if ($userId === null) {
            Log::channel('moodle_api')->warning('Usuario no encontrado en Moodle, no se puede reactivar', [
                'username' => $username,
            ]);

            return;
        }

        $this->request('core_user_update_users', [
            'users[0][id]'        => $userId,
            'users[0][suspended]' => 0,
        ]);

        Log::channel('moodle_api')->info('Usuario reactivado', ['username' => $username]);
    }

    // ── Matrícula/desmatrícula completa de un docente ─────────────────────────

    /**
     * Matricula al docente en todas sus cohortes y cursos según la BD.
     * Errores por matrícula individual se loguean sin abortar el conjunto.
     */
    public function enrollDocente(Docente $docente): void
    {
        $username = $this->usernameFor($docente);

        Tutor::where('dni', $docente->dni)->each(function (Tutor $t) use ($username) {
            try {
                $this->addToCohort($username, "tutores_ciclo_{$t->id_ciclo}");
            } catch (Throwable $e) {
                Log::channel('moodle_api')->error('Error añadiendo a cohorte tutor', [
                    'username' => $username, 'ciclo' => $t->id_ciclo, 'error' => $e->getMessage(),
                ]);
            }
        });

        Coordinador::where('dni', $docente->dni)->each(function (Coordinador $c) use ($username) {
            try {
                $this->addToCohort($username, "coordinadores_ciclo_{$c->id_ciclo}");
            } catch (Throwable $e) {
                Log::channel('moodle_api')->error('Error añadiendo a cohorte coordinador', [
                    'username' => $username, 'ciclo' => $c->id_ciclo, 'error' => $e->getMessage(),
                ]);
            }
        });

        Docencia::where('dni', $docente->dni)->each(function (Docencia $d) use ($username) {
            try {
                $this->enrolInCourse($username, "modulo_{$d->id_modulo}");
            } catch (Throwable $e) {
                Log::channel('moodle_api')->error('Error matriculando en curso', [
                    'username' => $username, 'modulo' => $d->id_modulo, 'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Desmatricula al docente de todas sus cohortes y cursos según la BD.
     * Útil durante la baja: llamar antes de suspender.
     *
     * @param  string|null  $idCentro  Si se pasa, solo desmatricula los roles de ese centro.
     */
    public function unenrolDocente(Docente $docente, ?string $idCentro = null): void
    {
        $username = $this->usernameFor($docente);

        $tutorQuery = Tutor::where('dni', $docente->dni);
        $coordQuery = Coordinador::where('dni', $docente->dni);
        $docenciaQuery = Docencia::where('dni', $docente->dni);

        if ($idCentro !== null) {
            $tutorQuery->where('id_centro', $idCentro);
            $coordQuery->where('id_centro', $idCentro);
            $docenciaQuery->where('id_centro', $idCentro);
        }

        $tutorQuery->each(function (Tutor $t) use ($username) {
            try {
                $this->removeFromCohort($username, "tutores_ciclo_{$t->id_ciclo}");
            } catch (Throwable $e) {
                Log::channel('moodle_api')->error('Error eliminando de cohorte tutor', [
                    'username' => $username, 'ciclo' => $t->id_ciclo, 'error' => $e->getMessage(),
                ]);
            }
        });

        $coordQuery->each(function (Coordinador $c) use ($username) {
            try {
                $this->removeFromCohort($username, "coordinadores_ciclo_{$c->id_ciclo}");
            } catch (Throwable $e) {
                Log::channel('moodle_api')->error('Error eliminando de cohorte coordinador', [
                    'username' => $username, 'ciclo' => $c->id_ciclo, 'error' => $e->getMessage(),
                ]);
            }
        });

        $docenciaQuery->each(function (Docencia $d) use ($username) {
            try {
                $this->unenrolFromCourse($username, "modulo_{$d->id_modulo}");
            } catch (Throwable $e) {
                Log::channel('moodle_api')->error('Error desmatriculando de curso', [
                    'username' => $username, 'modulo' => $d->id_modulo, 'error' => $e->getMessage(),
                ]);
            }
        });
    }

    // ── Métodos privados ──────────────────────────────────────────────────────

    /**
     * Lookup en Moodle por username. Devuelve el primer usuario encontrado o null.
     */
    private function findUserByUsername(string $username): ?array
    {
        $response = $this->request('core_user_get_users_by_field', [
            'field'     => 'username',
            'values[0]' => $username,
        ]);

        if (! is_array($response) || $response === []) {
            return null;
        }

        return $response[0] ?? null;
    }

    /**
     * Devuelve el ID numérico de Moodle para un username dado.
     */
    private function findMoodleUserId(string $username): ?int
    {
        $user = $this->findUserByUsername($username);

        return isset($user['id']) ? (int) $user['id'] : null;
    }

    /**
     * Busca un curso por shortname y devuelve su ID numérico de Moodle.
     */
    private function findCourseByShortname(string $shortname): ?int
    {
        $response = $this->request('core_course_get_courses_by_field', [
            'field' => 'shortname',
            'value' => $shortname,
        ]);

        // Respuesta: { "courses": [...], "warnings": [...] }
        $courses = is_array($response) ? ($response['courses'] ?? $response) : [];

        if (empty($courses[0]['id'])) {
            return null;
        }

        return (int) $courses[0]['id'];
    }

    /**
     * Busca una cohorte por idnumber y devuelve su ID numérico de Moodle.
     * Usa core_cohort_search_cohorts y busca coincidencia exacta por idnumber.
     */
    private function findCohortByIdnumber(string $idnumber): ?int
    {
        $response = $this->request('core_cohort_search_cohorts', [
            'query'          => $idnumber,
            'context[contextid]' => 1,  // contexto del sistema
            'includes'       => 'all',
            'limitfrom'      => 0,
            'limitnum'       => 20,
        ]);

        $cohorts = is_array($response) ? ($response['cohorts'] ?? $response) : [];

        foreach ($cohorts as $cohort) {
            if (($cohort['idnumber'] ?? '') === $idnumber) {
                return (int) $cohort['id'];
            }
        }

        return null;
    }

    /**
     * Crea el usuario en Moodle. Devuelve el id asignado por Moodle.
     */
    private function createUser(Docente $docente): int
    {
        $payload = [
            'users[0][username]'  => $this->usernameFor($docente),
            'users[0][firstname]' => $docente->nombre,
            'users[0][lastname]'  => $docente->apellido,
            'users[0][email]'     => $docente->email_virtual,
            'users[0][auth]'      => (string) config('services.moodle.auth', 'manual'),
            'users[0][password]'  => (string) config('services.moodle.default_password', 'changeme'),
            'users[0][lang]'      => (string) config('services.moodle.lang', 'es'),
            // Forzar cambio de contraseña en el primer inicio de sesión
            'users[0][preferences][0][type]'  => 'auth_forcepasswordchange',
            'users[0][preferences][0][value]' => '1',
        ];

        $response = $this->request('core_user_create_users', $payload);

        if (! is_array($response) || empty($response[0]['id'])) {
            throw new MoodleApiException(
                'Respuesta inesperada de core_user_create_users: '.json_encode($response)
            );
        }

        return (int) $response[0]['id'];
    }

    /**
     * Llama a una función del Web Service de Moodle y devuelve el cuerpo decodificado.
     *
     * Detecta el formato de error de Moodle ({"exception":"...","message":"..."}) y
     * lo convierte en MoodleApiException con el mensaje original.
     */
    private function request(string $wsfunction, array $params): mixed
    {
        $this->assertConfigured();

        Log::channel('moodle_api')->info('Moodle API request', ['wsfunction' => $wsfunction]);

        try {
            $response = $this->http()->asForm()->post(
                $this->endpoint(),
                array_merge($params, [
                    'wstoken'            => (string) config('services.moodle.token'),
                    'wsfunction'         => $wsfunction,
                    'moodlewsrestformat' => 'json',
                ]),
            );
        } catch (Throwable $e) {
            throw new MoodleApiException(
                "Error de red llamando a Moodle ({$wsfunction}): ".$e->getMessage(),
                previous: $e,
            );
        }

        if (! $response->successful()) {
            throw new MoodleApiException(
                "HTTP {$response->status()} llamando a {$wsfunction}"
            );
        }

        $body = $response->json();

        // Moodle devuelve 200 OK incluso en errores de la API; vienen como objeto
        // con claves "exception", "errorcode", "message", "debuginfo".
        if (is_array($body) && array_key_exists('exception', $body)) {
            throw new MoodleApiException(
                $body['message'] ?? 'Error desconocido de Moodle',
                moodleException: $body['exception'] ?? null,
                errorCode:       $body['errorcode'] ?? null,
                debugInfo:       $body['debuginfo'] ?? null,
            );
        }

        return $body;
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) config('services.moodle.timeout', 15))
            ->acceptJson();
    }

    private function endpoint(): string
    {
        $base = rtrim((string) config('services.moodle.url'), '/');

        return $base.'/webservice/rest/server.php';
    }

    private function assertConfigured(): void
    {
        if (empty(config('services.moodle.url')) || empty(config('services.moodle.token'))) {
            throw new MoodleApiException(
                'Moodle no está configurado: define MOODLE_URL y MOODLE_TOKEN en .env'
            );
        }
    }
}
