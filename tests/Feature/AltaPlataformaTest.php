<?php

/**
 * Suite de tests — Issue #60: Vista para altas individuales/masivas del profesorado
 *
 * Cubre:
 *   A) Acceso y autenticación
 *   B) procesarAltas (POST /admin/alta-plataforma/procesar)
 *   C) Endpoint preview (GET /admin/alta-plataforma/{id}/preview)
 *   D) Filtros (estado, búsqueda)
 *
 * Ejecución:
 *   ./vendor/bin/pest tests/Feature/AltaPlataformaTest.php --no-coverage
 */

use App\Models\Admin;
use App\Models\Docente;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helper ────────────────────────────────────────────────────────────────────
function adminUser(): Admin
{
    return Admin::forceCreate([
        'user'     => 'admin_test',
        'email'    => 'admin@test.com',
        'password' => bcrypt('secret'),
    ]);
}

function docenteConEmail(array $extra = []): Docente
{
    static $seq = 0;
    $seq++;
    return Docente::forceCreate(array_merge([
        'nombre'        => 'Docente',
        'apellido'      => 'Apellido',
        'dni'           => str_pad($seq, 8, '0', STR_PAD_LEFT) . 'X',
        'email_virtual' => "docente{$seq}@fpvirtualaragon.es",
        'de_baja'       => false,
        'is_procesado'  => false,
    ], $extra));
}


// ════════════════════════════════════════════════════════════════════════════
// BLOQUE A — Acceso y autenticación
// ════════════════════════════════════════════════════════════════════════════

test('A1 · un invitado es redirigido a admin/login al acceder a alta-plataforma', function () {
    $response = $this->get('/admin/alta-plataforma');
    $response->assertRedirect(route('admin.login'));
});

test('A2 · un usuario normal (guard web) es redirigido a admin/login', function () {
    $centro  = \App\Models\Centro::forceCreate(['id_centro' => 'CA01', 'nombre' => 'Centro A']);
    $usuario = \App\Models\Usuario::factory()->create(['id_centro' => 'CA01']);

    $response = $this->actingAs($usuario)->get('/admin/alta-plataforma');
    $response->assertRedirect(route('admin.login'));
});

test('A3 · un admin autenticado puede acceder a la página', function () {
    $response = $this->actingAs(adminUser(), 'admin')->get('/admin/alta-plataforma');
    $response->assertStatus(200);
});

test('A4 · la página contiene el título esperado', function () {
    $response = $this->actingAs(adminUser(), 'admin')->get('/admin/alta-plataforma');
    $response->assertSee('Alta en Plataforma');
});

test('A5 · la página muestra los docentes activos con email_virtual', function () {
    docenteConEmail(['nombre' => 'Guillermo', 'apellido' => 'Herrera']);
    docenteConEmail(['nombre' => 'Valentina', 'apellido' => 'Montes']);

    $response = $this->actingAs(adminUser(), 'admin')->get('/admin/alta-plataforma');
    $response->assertSee('Guillermo');
    $response->assertSee('Valentina');
});

test('A6 · la página NO muestra docentes de baja', function () {
    docenteConEmail(['nombre' => 'BajaDocente', 'apellido' => 'Oculto', 'de_baja' => true]);

    $response = $this->actingAs(adminUser(), 'admin')->get('/admin/alta-plataforma');
    $response->assertDontSee('BajaDocente');
});

test('A7 · la página NO muestra docentes sin email_virtual', function () {
    Docente::forceCreate([
        'nombre'        => 'SinEmail',
        'apellido'      => 'Virtual',
        'dni'           => '99999901S',
        'email_virtual' => '',
        'de_baja'       => false,
    ]);

    $response = $this->actingAs(adminUser(), 'admin')->get('/admin/alta-plataforma');
    $response->assertDontSee('SinEmail');
});


// ════════════════════════════════════════════════════════════════════════════
// BLOQUE B — procesarAltas
// ════════════════════════════════════════════════════════════════════════════

test('B1 · procesar un docente lo marca como is_procesado=true', function () {
    $docente = docenteConEmail();

    $this->actingAs(adminUser(), 'admin')
         ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$docente->id]])
         ->assertStatus(200)
         ->assertJson(['ok' => true]);

    expect($docente->fresh()->is_procesado)->toBeTrue();
});

test('B2 · procesar un docente guarda la fecha_procesado', function () {
    $docente = docenteConEmail();

    $this->actingAs(adminUser(), 'admin')
         ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$docente->id]]);

    expect($docente->fresh()->fecha_procesado)->not->toBeNull();
});

test('B3 · procesar varios docentes a la vez actualiza todos', function () {
    $d1 = docenteConEmail();
    $d2 = docenteConEmail();

    $this->actingAs(adminUser(), 'admin')
         ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d1->id, $d2->id]])
         ->assertJson(['ok' => true, 'procesados' => 2]);

    expect($d1->fresh()->is_procesado)->toBeTrue();
    expect($d2->fresh()->is_procesado)->toBeTrue();
});

test('B4 · procesar sin ids devuelve error de validación', function () {
    $this->actingAs(adminUser(), 'admin')
         ->postJson('/admin/alta-plataforma/procesar', [])
         ->assertStatus(422);
});

test('B5 · procesar con ids vacíos devuelve error de validación', function () {
    $this->actingAs(adminUser(), 'admin')
         ->postJson('/admin/alta-plataforma/procesar', ['ids' => []])
         ->assertStatus(422);
});

test('B6 · un docente de baja no se marca como procesado aunque esté en los ids', function () {
    $docente = docenteConEmail(['de_baja' => true, 'is_procesado' => false]);

    $this->actingAs(adminUser(), 'admin')
         ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$docente->id]]);

    expect($docente->fresh()->is_procesado)->toBeFalse();
});

test('B7 · un invitado no puede llamar a procesarAltas', function () {
    $docente = docenteConEmail();

    $this->postJson('/admin/alta-plataforma/procesar', ['ids' => [$docente->id]])
         ->assertStatus(302); // redirige al login
});


// ════════════════════════════════════════════════════════════════════════════
// BLOQUE C — Endpoint preview
// ════════════════════════════════════════════════════════════════════════════

test('C1 · el endpoint preview devuelve el dni del docente', function () {
    $docente = docenteConEmail(['dni' => '12345678C']);

    $this->actingAs(adminUser(), 'admin')
         ->getJson("/admin/alta-plataforma/{$docente->id}/preview")
         ->assertStatus(200)
         ->assertJsonPath('dni', '12345678C');
});

test('C2 · el endpoint preview devuelve el email_virtual', function () {
    $docente = docenteConEmail(['email_virtual' => 'preview@fpvirtualaragon.es']);

    $this->actingAs(adminUser(), 'admin')
         ->getJson("/admin/alta-plataforma/{$docente->id}/preview")
         ->assertStatus(200)
         ->assertJsonPath('email_virtual', 'preview@fpvirtualaragon.es');
});

test('C3 · el endpoint preview incluye la línea google_csv', function () {
    $docente = docenteConEmail();

    $response = $this->actingAs(adminUser(), 'admin')
         ->getJson("/admin/alta-plataforma/{$docente->id}/preview");

    $response->assertStatus(200);
    expect($response->json('google_csv'))->toBeString()->not->toBeEmpty();
});

test('C4 · el endpoint preview incluye la línea moodle_csv', function () {
    $docente = docenteConEmail();

    $response = $this->actingAs(adminUser(), 'admin')
         ->getJson("/admin/alta-plataforma/{$docente->id}/preview");

    $response->assertStatus(200);
    expect($response->json('moodle_csv'))->toBeString()->not->toBeEmpty();
});

test('C5 · la línea moodle_csv empieza con "prof" seguido del DNI', function () {
    $docente = docenteConEmail(['dni' => '87654321M']);

    $response = $this->actingAs(adminUser(), 'admin')
         ->getJson("/admin/alta-plataforma/{$docente->id}/preview");

    expect($response->json('moodle_csv'))->toStartWith('"prof87654321M"');
});

test('C6 · el endpoint preview devuelve 404 para un id inexistente', function () {
    $this->actingAs(adminUser(), 'admin')
         ->getJson('/admin/alta-plataforma/9999/preview')
         ->assertStatus(404);
});


// ════════════════════════════════════════════════════════════════════════════
// BLOQUE D — Filtros
// ════════════════════════════════════════════════════════════════════════════

test('D1 · el filtro estado=pendiente solo muestra docentes no procesados', function () {
    docenteConEmail(['nombre' => 'Guillermo', 'apellido' => 'Sin Procesar', 'is_procesado' => false]);
    docenteConEmail(['nombre' => 'Valentina', 'apellido' => 'Ya Procesada', 'is_procesado' => true]);

    $response = $this->actingAs(adminUser(), 'admin')
         ->get('/admin/alta-plataforma?estado=pendiente');

    $response->assertSee('Guillermo');
    $response->assertDontSee('Valentina');
});

test('D2 · el filtro estado=procesado solo muestra docentes procesados', function () {
    docenteConEmail(['nombre' => 'Guillermo', 'apellido' => 'Sin Procesar', 'is_procesado' => false]);
    docenteConEmail(['nombre' => 'Valentina', 'apellido' => 'Ya Procesada', 'is_procesado' => true]);

    $response = $this->actingAs(adminUser(), 'admin')
         ->get('/admin/alta-plataforma?estado=procesado');

    $response->assertSee('Valentina');
    $response->assertDontSee('Guillermo');
});

test('D3 · la búsqueda por nombre filtra correctamente', function () {
    docenteConEmail(['nombre' => 'Guillermo', 'apellido' => 'Herrera']);
    docenteConEmail(['nombre' => 'Valentina', 'apellido' => 'Montes']);

    $response = $this->actingAs(adminUser(), 'admin')
         ->get('/admin/alta-plataforma?buscar=Guillermo');

    $response->assertSee('Guillermo');
    $response->assertDontSee('Valentina');
});

test('D4 · la búsqueda por DNI filtra correctamente', function () {
    docenteConEmail(['dni' => 'A1111111Z', 'nombre' => 'Guillermo', 'apellido' => 'Herrera']);
    docenteConEmail(['dni' => 'B2222222Z', 'nombre' => 'Valentina', 'apellido' => 'Montes']);

    $response = $this->actingAs(adminUser(), 'admin')
         ->get('/admin/alta-plataforma?buscar=A1111111Z');

    $response->assertSee('Guillermo');
    $response->assertDontSee('Valentina');
});
