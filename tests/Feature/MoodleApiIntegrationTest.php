<?php

/**
 * Suite de tests — Integración con la API de Moodle desde /admin/alta-plataforma.
 *
 * Cubre el camino feliz, idempotencia (usuario ya existe), errores de Moodle
 * (exception en respuesta), errores HTTP y lotes mixtos.
 *
 * Ejecución:
 *   ./vendor/bin/pest tests/Feature/MoodleApiIntegrationTest.php --no-coverage
 */

use App\Models\Admin;
use App\Models\Docente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────
function moodleAdmin(): Admin
{
    return Admin::forceCreate([
        'user' => 'admin_moodle_test',
        'email' => 'admin_moodle@test.com',
        'password' => bcrypt('secret'),
    ]);
}

function configureMoodle(): void
{
    config([
        'services.moodle.url' => 'https://moodle.test',
        'services.moodle.token' => 'fake-token',
        'services.moodle.auth' => 'oauth2',
        'services.moodle.lang' => 'es',
        'services.moodle.timeout' => 5,
    ]);
}

function docente(array $extra = []): Docente
{
    static $seq = 0;
    $seq++;

    return Docente::forceCreate(array_merge([
        'nombre' => 'Nombre'.$seq,
        'apellido' => 'Apellido'.$seq,
        'dni' => str_pad((string) $seq, 8, '0', STR_PAD_LEFT).'Z',
        'email_virtual' => "moodle_test_{$seq}@fpvirtualaragon.es",
        'de_baja' => false,
        'is_procesado' => false,
    ], $extra));
}

/**
 * Crea un fake que responde según el wsfunction detectado en el body del POST.
 * $byFunction es un mapa wsfunction => closure(request) que devuelve Http::response().
 */
function fakeByWsFunction(array $byFunction): void
{
    Http::fake(function ($request) use ($byFunction) {
        $body = (string) $request->body();
        foreach ($byFunction as $wsfunction => $responder) {
            if (str_contains($body, "wsfunction={$wsfunction}")) {
                return $responder($request);
            }
        }

        return Http::response(['exception' => 'unexpected', 'message' => 'wsfunction inesperada'], 200);
    });
}

// ════════════════════════════════════════════════════════════════════════════
// M1 — Camino feliz: docente nuevo se crea en Moodle y queda procesado
// ════════════════════════════════════════════════════════════════════════════

test('M1 · crea un docente nuevo en Moodle y lo marca como procesado', function () {
    configureMoodle();
    fakeByWsFunction([
        'core_user_get_users_by_field' => fn () => Http::response([], 200),
        'core_user_create_users' => fn () => Http::response([['id' => 101, 'username' => 'prof']], 200),
    ]);

    $d = docente(['dni' => '11111111A']);

    $response = $this->actingAs(moodleAdmin(), 'admin')
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]]);

    $response->assertStatus(200)
        ->assertJson([
            'ok' => true,
            'created' => ['11111111A'],
            'skipped' => [],
            'failed' => [],
        ]);

    expect($d->fresh()->is_procesado)->toBeTrue();
    expect($d->fresh()->fecha_procesado)->not->toBeNull();
});

test('M1b · el payload enviado a create_users incluye username prof+dni y auth oauth2', function () {
    configureMoodle();
    fakeByWsFunction([
        'core_user_get_users_by_field' => fn () => Http::response([], 200),
        'core_user_create_users' => fn () => Http::response([['id' => 1]], 200),
    ]);

    $d = docente(['dni' => '22222222B', 'nombre' => 'Ada', 'apellido' => 'Lovelace']);

    $this->actingAs(moodleAdmin(), 'admin')
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]])
        ->assertStatus(200);

    Http::assertSent(function ($request) {
        $body = (string) $request->body();

        return str_contains($body, 'wsfunction=core_user_create_users')
            && str_contains($body, 'username%5D=prof22222222b')
            && str_contains($body, 'firstname%5D=Ada')
            && str_contains($body, 'lastname%5D=Lovelace')
            && str_contains($body, 'auth%5D=oauth2');
    });
});

// ════════════════════════════════════════════════════════════════════════════
// M2 — Idempotencia: el usuario ya existe en Moodle → skipped + marcado procesado
// ════════════════════════════════════════════════════════════════════════════

test('M2 · si el usuario ya existe en Moodle, no se llama a create_users y queda procesado', function () {
    configureMoodle();
    fakeByWsFunction([
        'core_user_get_users_by_field' => fn () => Http::response([['id' => 7, 'username' => 'prof33333333c']], 200),
        'core_user_create_users' => fn () => Http::response(['exception' => 'should_not_be_called'], 200),
    ]);

    $d = docente(['dni' => '33333333C']);

    $response = $this->actingAs(moodleAdmin(), 'admin')
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]]);

    $response->assertStatus(200)
        ->assertJson([
            'ok' => true,
            'created' => [],
            'skipped' => ['33333333C'],
            'failed' => [],
        ]);

    expect($d->fresh()->is_procesado)->toBeTrue();

    Http::assertNotSent(function ($request) {
        return str_contains((string) $request->body(), 'wsfunction=core_user_create_users');
    });
});

// ════════════════════════════════════════════════════════════════════════════
// M3 — Error de Moodle: respuesta con "exception" → failed, NO se marca procesado
// ════════════════════════════════════════════════════════════════════════════

test('M3 · si Moodle devuelve excepción, el docente queda en failed y no se procesa', function () {
    configureMoodle();
    fakeByWsFunction([
        'core_user_get_users_by_field' => fn () => Http::response([], 200),
        'core_user_create_users' => fn () => Http::response([
            'exception' => 'moodle_exception',
            'errorcode' => 'invalidemail',
            'message' => 'Email no válido',
        ], 200),
    ]);

    $d = docente(['dni' => '44444444D']);

    $response = $this->actingAs(moodleAdmin(), 'admin')
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]]);

    $response->assertStatus(200)
        ->assertJson([
            'ok' => false,
            'created' => [],
            'skipped' => [],
        ]);

    expect($response->json('failed'))->toHaveKey('44444444D');
    expect($response->json('failed.44444444D'))->toContain('Email no válido');

    expect($d->fresh()->is_procesado)->toBeFalse();
});

// ════════════════════════════════════════════════════════════════════════════
// M4 — Error HTTP (500) → failed, NO se marca procesado
// ════════════════════════════════════════════════════════════════════════════

test('M4 · si Moodle devuelve HTTP 500, el docente queda en failed', function () {
    configureMoodle();
    fakeByWsFunction([
        'core_user_get_users_by_field' => fn () => Http::response('Internal error', 500),
    ]);

    $d = docente(['dni' => '55555555E']);

    $response = $this->actingAs(moodleAdmin(), 'admin')
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]]);

    $response->assertStatus(200)
        ->assertJson(['ok' => false]);

    expect($response->json('failed'))->toHaveKey('55555555E');
    expect($d->fresh()->is_procesado)->toBeFalse();
});

// ════════════════════════════════════════════════════════════════════════════
// M5 — Lote mixto: uno crea, uno ya existe, uno falla
// ════════════════════════════════════════════════════════════════════════════

test('M5 · lote mixto: created + skipped + failed coexisten correctamente', function () {
    configureMoodle();

    // El comportamiento depende del username consultado en core_user_get_users_by_field.
    Http::fake(function ($request) {
        $body = (string) $request->body();

        if (str_contains($body, 'wsfunction=core_user_get_users_by_field')) {
            if (str_contains($body, 'profaaaaaaaa1')) {
                return Http::response([], 200);                                       // A → no existe
            }
            if (str_contains($body, 'profbbbbbbbb2')) {
                return Http::response([['id' => 9, 'username' => 'profbbbbbbbb2']], 200); // B → existe
            }
            if (str_contains($body, 'profcccccccc3')) {
                return Http::response([], 200);                                       // C → no existe (pero falla en create)
            }

            return Http::response([], 200);
        }

        if (str_contains($body, 'wsfunction=core_user_create_users')) {
            if (str_contains($body, 'profaaaaaaaa1')) {
                return Http::response([['id' => 201]], 200);
            }
            if (str_contains($body, 'profcccccccc3')) {
                return Http::response([
                    'exception' => 'moodle_exception',
                    'message' => 'duplicate username',
                ], 200);
            }
        }

        return Http::response(['exception' => 'unexpected'], 200);
    });

    $dA = docente(['dni' => 'AAAAAAAA1', 'nombre' => 'Ana']);
    $dB = docente(['dni' => 'BBBBBBBB2', 'nombre' => 'Beto']);
    $dC = docente(['dni' => 'CCCCCCCC3', 'nombre' => 'Carla']);

    $response = $this->actingAs(moodleAdmin(), 'admin')
        ->postJson('/admin/alta-plataforma/procesar', [
            'ids' => [$dA->id, $dB->id, $dC->id],
        ]);

    $response->assertStatus(200)
        ->assertJson(['ok' => false]);

    expect($response->json('created'))->toBe(['AAAAAAAA1']);
    expect($response->json('skipped'))->toBe(['BBBBBBBB2']);
    expect($response->json('failed'))->toHaveKey('CCCCCCCC3');

    expect($dA->fresh()->is_procesado)->toBeTrue();
    expect($dB->fresh()->is_procesado)->toBeTrue();
    expect($dC->fresh()->is_procesado)->toBeFalse();
});

// ════════════════════════════════════════════════════════════════════════════
// M6 — Configuración Moodle ausente: failed con mensaje claro
// ════════════════════════════════════════════════════════════════════════════

test('M6 · sin MOODLE_URL/TOKEN configurado, todos los docentes acaban en failed', function () {
    config([
        'services.moodle.url' => null,
        'services.moodle.token' => null,
    ]);
    Http::fake(); // No deberían salir peticiones de red

    $d = docente(['dni' => '66666666F']);

    $response = $this->actingAs(moodleAdmin(), 'admin')
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]]);

    $response->assertStatus(200)
        ->assertJson(['ok' => false]);

    expect($response->json('failed'))->toHaveKey('66666666F');
    expect($response->json('failed.66666666F'))->toContain('Moodle no está configurado');
    expect($d->fresh()->is_procesado)->toBeFalse();

    Http::assertNothingSent();
});

// ════════════════════════════════════════════════════════════════════════════
// M7 — Un docente de baja en el lote nunca llama a Moodle
// ════════════════════════════════════════════════════════════════════════════

test('M7 · un docente de baja se ignora antes de llamar a Moodle', function () {
    configureMoodle();
    Http::fake();

    $d = docente(['dni' => '77777777G', 'de_baja' => true]);

    $this->actingAs(moodleAdmin(), 'admin')
        ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d->id]])
        ->assertStatus(200)
        ->assertJson(['ok' => true, 'created' => [], 'skipped' => [], 'failed' => []]);

    expect($d->fresh()->is_procesado)->toBeFalse();
    Http::assertNothingSent();
});
