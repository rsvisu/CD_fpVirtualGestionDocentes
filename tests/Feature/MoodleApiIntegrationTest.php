<?php

/**
 * Suite de tests — Integración con la API de Moodle desde /admin/alta-plataforma.
 *
 * Cubre el camino feliz, idempotencia, errores, lotes mixtos, configuración y
 * las nuevas funcionalidades: auth manual, matrícula en cohortes/cursos,
 * suspensión y reactivación.
 *
 * Ejecución:
 *   ./vendor/bin/pest tests/Feature/MoodleApiIntegrationTest.php --no-coverage
 */

use App\Models\Centro;
use App\Models\Ciclo;
use App\Models\CicloModulo;
use App\Models\Docencia;
use App\Models\Docente;
use App\Models\Modulo;
use App\Models\Tutor;
use App\Models\Usuario;
use App\Services\MoodleApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function moodleAdmin(): Usuario
{
    $admin = Usuario::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

function configureMoodle(): void
{
    config([
        'services.moodle.url'              => 'https://moodle.test',
        'services.moodle.token'            => 'fake-token',
        'services.moodle.auth'             => 'manual',
        'services.moodle.default_password' => 'changeme',
        'services.moodle.lang'             => 'es',
        'services.moodle.timeout'          => 5,
        'services.moodle.teacher_role_id'  => 3,
    ]);
}

function mDocente(array $extra = []): Docente
{
    static $seq = 0;
    $seq++;

    return Docente::forceCreate(array_merge([
        'nombre'        => 'Nombre'.$seq,
        'apellido'      => 'Apellido'.$seq,
        'dni'           => str_pad((string) $seq, 8, '0', STR_PAD_LEFT).'Z',
        'email_virtual' => "moodle_test_{$seq}@fpvirtualaragon.es",
        'de_baja'       => false,
        'is_procesado'  => false,
    ], $extra));
}

/**
 * Fake que responde segun el wsfunction detectado en el body del POST.
 * Incluye respuestas vacias por defecto para los endpoints de matricula.
 */
function fakeByWsFunction(array $byFunction): void
{
    $defaults = [
        'core_cohort_add_cohort_members'    => fn () => Http::response(null, 200),
        'core_cohort_search_cohorts'        => fn () => Http::response(['cohorts' => []], 200),
        'core_cohort_delete_cohort_members' => fn () => Http::response(null, 200),
        'core_course_get_courses_by_field'  => fn () => Http::response(['courses' => []], 200),
        'enrol_manual_enrol_users'          => fn () => Http::response(null, 200),
        'enrol_manual_unenrol_users'        => fn () => Http::response(null, 200),
        'core_user_update_users'            => fn () => Http::response(null, 200),
    ];

    $merged = array_merge($defaults, $byFunction);

    Http::fake(function ($request) use ($merged) {
        $body = (string) $request->body();
        foreach ($merged as $wsfunction => $responder) {
            if (str_contains($body, "wsfunction={$wsfunction}")) {
                return $responder($request);
            }
        }

        return Http::response(['exception' => 'unexpected', 'message' => 'wsfunction inesperada'], 200);
    });
}

// M1 -- Camino feliz

test('M1 - crea un docente nuevo en Moodle y lo marca como procesado', function () {
    configureMoodle();
    fakeByWsFunction([
        'core_user_get_users' => fn () => Http::response(['users' => [], 'warnings' => []], 200),
        'core_user_create_users' => fn () => Http::response([['id' => 101, 'username' => 'prof']], 200),
    ]);

    $d = mDocente(['dni' => '11111111A']);

    $this->actingAs(moodleAdmin())
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]])
        ->assertStatus(200)
        ->assertJson([
            'ok'      => true,
            'created' => ['11111111A'],
            'skipped' => [],
            'failed'  => [],
        ]);

    expect($d->fresh()->is_procesado)->toBeTrue();
    expect($d->fresh()->fecha_procesado)->not->toBeNull();
});

// M2 -- Payload: auth=manual, contrasena changeme y forcepasswordchange

test('M2 - el payload enviado a create_users usa auth=manual, contrasena changeme y fuerza cambio', function () {
    configureMoodle();
    fakeByWsFunction([
        'core_user_get_users'    => fn () => Http::response(['users' => [], 'warnings' => []], 200),
        'core_user_create_users' => fn () => Http::response([['id' => 1]], 200),
    ]);

    $d = mDocente(['dni' => '22222222B', 'nombre' => 'Ada', 'apellido' => 'Lovelace']);

    $this->actingAs(moodleAdmin())
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]])
        ->assertStatus(200);

    Http::assertSent(function ($request) {
        $body = (string) $request->body();

        return str_contains($body, 'wsfunction=core_user_create_users')
            && str_contains($body, 'username%5D=prof22222222b')
            && str_contains($body, 'firstname%5D=Ada')
            && str_contains($body, 'lastname%5D=Lovelace')
            && str_contains($body, 'auth%5D=manual')
            && str_contains($body, 'password%5D=changeme')
            && str_contains($body, 'auth_forcepasswordchange');
    });
});

// M3 -- Idempotencia

test('M3 - si el usuario ya existe en Moodle, no se llama a create_users y queda procesado', function () {
    configureMoodle();
    fakeByWsFunction([
        'core_user_get_users' => fn () => Http::response(['users' => [['id' => 7, 'username' => 'prof33333333c']], 'warnings' => []], 200),
    ]);

    $d = mDocente(['dni' => '33333333C']);

    $this->actingAs(moodleAdmin())
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]])
        ->assertStatus(200)
        ->assertJson([
            'ok'      => true,
            'created' => [],
            'skipped' => ['33333333C'],
            'failed'  => [],
        ]);

    expect($d->fresh()->is_procesado)->toBeTrue();

    Http::assertNotSent(function ($request) {
        return str_contains((string) $request->body(), 'wsfunction=core_user_create_users');
    });
});

// M4 -- Error de Moodle

test('M4 - si Moodle devuelve excepcion, el docente queda en failed y no se procesa', function () {
    configureMoodle();
    fakeByWsFunction([
        'core_user_get_users'    => fn () => Http::response(['users' => [], 'warnings' => []], 200),
        'core_user_create_users' => fn () => Http::response([
            'exception' => 'moodle_exception',
            'errorcode' => 'invalidemail',
            'message'   => 'Email no valido',
        ], 200),
    ]);

    $d = mDocente(['dni' => '44444444D']);

    $response = $this->actingAs(moodleAdmin())
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]])
        ->assertStatus(200)
        ->assertJson(['ok' => false, 'created' => [], 'skipped' => []]);

    expect($response->json('failed'))->toHaveKey('44444444D');
    expect($response->json('failed.44444444D'))->toContain('Email no valido');
    expect($d->fresh()->is_procesado)->toBeFalse();
});

// M5 -- Error HTTP 500

test('M5 - si Moodle devuelve HTTP 500, el docente queda en failed', function () {
    configureMoodle();
    fakeByWsFunction([
        'core_user_get_users' => fn () => Http::response('Internal error', 500),
    ]);

    $d = mDocente(['dni' => '55555555E']);

    $response = $this->actingAs(moodleAdmin())
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]])
        ->assertStatus(200)
        ->assertJson(['ok' => false]);

    expect($response->json('failed'))->toHaveKey('55555555E');
    expect($d->fresh()->is_procesado)->toBeFalse();
});

// M6 -- Lote mixto

test('M6 - lote mixto: created + skipped + failed coexisten correctamente', function () {
    configureMoodle();

    Http::fake(function ($request) {
        $body = (string) $request->body();

        if (str_contains($body, 'wsfunction=core_user_get_users')) {
            if (str_contains($body, 'profbbbbbbbb2')) {
                return Http::response(['users' => [['id' => 9, 'username' => 'profbbbbbbbb2']], 'warnings' => []], 200);
            }

            return Http::response(['users' => [], 'warnings' => []], 200);
        }

        if (str_contains($body, 'wsfunction=core_user_create_users')) {
            if (str_contains($body, 'profaaaaaaaa1')) {
                return Http::response([['id' => 201]], 200);
            }
            if (str_contains($body, 'profcccccccc3')) {
                return Http::response([
                    'exception' => 'moodle_exception',
                    'message'   => 'duplicate username',
                ], 200);
            }
        }

        return Http::response(null, 200);
    });

    $dA = mDocente(['dni' => 'AAAAAAAA1', 'nombre' => 'Ana']);
    $dB = mDocente(['dni' => 'BBBBBBBB2', 'nombre' => 'Beto']);
    $dC = mDocente(['dni' => 'CCCCCCCC3', 'nombre' => 'Carla']);

    $response = $this->actingAs(moodleAdmin())
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$dA->id, $dB->id, $dC->id]])
        ->assertStatus(200)
        ->assertJson(['ok' => false]);

    expect($response->json('created'))->toBe(['AAAAAAAA1']);
    expect($response->json('skipped'))->toBe(['BBBBBBBB2']);
    expect($response->json('failed'))->toHaveKey('CCCCCCCC3');

    expect($dA->fresh()->is_procesado)->toBeTrue();
    expect($dB->fresh()->is_procesado)->toBeTrue();
    expect($dC->fresh()->is_procesado)->toBeFalse();
});

// M7 -- Sin configuracion

test('M7 - sin MOODLE_URL/TOKEN configurado, todos los docentes acaban en failed', function () {
    config([
        'services.moodle.url'   => null,
        'services.moodle.token' => null,
    ]);
    Http::fake();

    $d = mDocente(['dni' => '66666666F']);

    $response = $this->actingAs(moodleAdmin())
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]])
        ->assertStatus(200)
        ->assertJson(['ok' => false]);

    expect($response->json('failed'))->toHaveKey('66666666F');
    expect($response->json('failed.66666666F'))->toContain('Moodle no');
    expect($d->fresh()->is_procesado)->toBeFalse();

    Http::assertNothingSent();
});

// M8 -- Docente de baja ignorado

test('M8 - un docente de baja se ignora antes de llamar a Moodle', function () {
    configureMoodle();
    Http::fake();

    $d = mDocente(['dni' => '77777777G', 'de_baja' => true]);

    $this->actingAs(moodleAdmin())
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]])
        ->assertStatus(200)
        ->assertJson(['ok' => true, 'created' => [], 'skipped' => [], 'failed' => []]);

    expect($d->fresh()->is_procesado)->toBeFalse();
    Http::assertNothingSent();
});

// M9 -- Matricula en cohortes y cursos tras crear

test('M9 - tras crear en Moodle se llama a addToCohort y enrolInCourse segun roles del docente', function () {
    configureMoodle();

    $centro  = Centro::forceCreate(['id_centro' => 'MTC01', 'nombre' => 'Centro Moodle Test']);
    $ciclo   = Ciclo::forceCreate(['id_ciclo' => 'IFC201', 'nombre' => 'Ciclo Test']);
    $modulo  = Modulo::forceCreate(['id_modulo' => 'MOD01', 'nombre' => 'Modulo Test']);
    $docente = mDocente(['dni' => '88888888H']);

    Tutor::forceCreate(['id_centro' => 'MTC01', 'id_ciclo' => 'IFC201', 'dni' => '88888888H']);
    Docencia::forceCreate(['id_centro' => 'MTC01', 'id_ciclo' => 'IFC201', 'id_modulo' => 'MOD01', 'dni' => '88888888H']);

    $cohortCalls     = [];
    $courseCalls     = [];
    $lookupCallCount = 0;

    Http::fake(function ($request) use (&$cohortCalls, &$courseCalls, &$lookupCallCount) {
        $body = (string) $request->body();

        if (str_contains($body, 'wsfunction=core_user_get_users')) {
            $lookupCallCount++;
            // 1ª llamada: idempotency check en createUsers → usuario no existe → se crea
            // Llamadas posteriores: findMoodleUserId en enrolInCourse → usuario ya existe
            if ($lookupCallCount === 1) {
                return Http::response(['users' => [], 'warnings' => []], 200);
            }

            return Http::response(['users' => [['id' => 42, 'username' => 'prof88888888h']], 'warnings' => []], 200);
        }
        if (str_contains($body, 'wsfunction=core_user_create_users')) {
            return Http::response([['id' => 42, 'username' => 'prof88888888h']], 200);
        }
        if (str_contains($body, 'wsfunction=core_cohort_add_cohort_members')) {
            $cohortCalls[] = $body;

            return Http::response(null, 200);
        }
        if (str_contains($body, 'wsfunction=core_course_get_courses_by_field')) {
            $courseCalls[] = $body;

            return Http::response(['courses' => [['id' => 99]]], 200);
        }
        if (str_contains($body, 'wsfunction=enrol_manual_enrol_users')) {
            $courseCalls[] = $body;

            return Http::response(null, 200);
        }

        return Http::response(null, 200);
    });

    $this->actingAs(moodleAdmin())
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$docente->id]])
        ->assertStatus(200)
        ->assertJson(['ok' => true]);

    $allCohortBodies = implode(' ', $cohortCalls);
    expect($allCohortBodies)->toContain('tutores_ciclo_IFC201');

    // shortname: {id_centro}-{id_ciclo}-{id_modulo} = MTC01-IFC201-MOD01
    $allCourseBodies = implode(' ', $courseCalls);
    expect($allCourseBodies)->toContain('MTC01-IFC201-MOD01');
});

// M10 -- suspendUser y unsuspendUser

test('M10 - suspendUser llama a core_user_update_users con suspended=1', function () {
    configureMoodle();

    Http::fake(function ($request) {
        $body = (string) $request->body();
        if (str_contains($body, 'wsfunction=core_user_get_users')) {
            return Http::response(['users' => [['id' => 55, 'username' => 'proftest']], 'warnings' => []], 200);
        }

        return Http::response(null, 200);
    });

    app(MoodleApiService::class)->suspendUser('proftest');

    Http::assertSent(function ($request) {
        $body = (string) $request->body();

        return str_contains($body, 'wsfunction=core_user_update_users')
            && str_contains($body, 'suspended%5D=1');
    });
});

test('M10b - unsuspendUser llama a core_user_update_users con suspended=0', function () {
    configureMoodle();

    Http::fake(function ($request) {
        $body = (string) $request->body();
        if (str_contains($body, 'wsfunction=core_user_get_users')) {
            return Http::response(['users' => [['id' => 55, 'username' => 'proftest']], 'warnings' => []], 200);
        }

        return Http::response(null, 200);
    });

    app(MoodleApiService::class)->unsuspendUser('proftest');

    Http::assertSent(function ($request) {
        $body = (string) $request->body();

        return str_contains($body, 'wsfunction=core_user_update_users')
            && str_contains($body, 'suspended%5D=0');
    });
});
