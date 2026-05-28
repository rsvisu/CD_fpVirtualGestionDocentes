<?php

namespace App\Services;

use App\Exceptions\MoodleApiException;
use App\Models\Docente;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MoodleApiService
 *
 * Envoltorio sobre la API REST (Web Services) de Moodle para dar de alta
 * docentes desde /admin/alta-plataforma. Sustituye la subida manual del CSV.
 *
 * Funciones de Moodle utilizadas:
 *   - core_user_get_users_by_field  (idempotencia: lookup por username)
 *   - core_user_create_users        (alta del usuario)
 *
 * Convención de username: "prof" + DNI del docente (mismo formato que el CSV).
 * Auth: oauth2 por defecto (los docentes entran con Google a @fpvirtualaragon.es).
 */
class MoodleApiService
{
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
                    'dni' => $dni,
                    'username' => $username,
                    'error' => $e->getMessage(),
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

    /**
     * Lookup en Moodle por username. Devuelve el primer usuario encontrado o null.
     */
    private function findUserByUsername(string $username): ?array
    {
        $response = $this->request('core_user_get_users_by_field', [
            'field' => 'username',
            'values[0]' => $username,
        ]);

        // core_user_get_users_by_field devuelve un array (vacío si no existe).
        if (! is_array($response) || $response === []) {
            return null;
        }

        return $response[0] ?? null;
    }

    /**
     * Crea el usuario en Moodle. Devuelve el id asignado por Moodle.
     */
    private function createUser(Docente $docente): int
    {
        $payload = [
            'users[0][username]' => $this->usernameFor($docente),
            'users[0][firstname]' => $docente->nombre,
            'users[0][lastname]' => $docente->apellido,
            'users[0][email]' => $docente->email_virtual,
            'users[0][auth]' => (string) config('services.moodle.auth', 'oauth2'),
            'users[0][lang]' => (string) config('services.moodle.lang', 'es'),
            'users[0][createpassword]' => 0,
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

        $logContext = ['wsfunction' => $wsfunction];

        Log::channel('moodle_api')->info('Moodle API request', $logContext);

        try {
            $response = $this->http()->asForm()->post(
                $this->endpoint(),
                array_merge($params, [
                    'wstoken' => (string) config('services.moodle.token'),
                    'wsfunction' => $wsfunction,
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
                errorCode: $body['errorcode'] ?? null,
                debugInfo: $body['debuginfo'] ?? null,
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
