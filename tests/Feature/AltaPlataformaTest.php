<?php

/**
 * Suite de tests — Issue #60/#61/#62: Alta en Plataforma (Google Workspace / Moodle)
 *
 * Ejecución:
 *   ./vendor/bin/pest tests/Feature/AltaPlataformaTest.php --no-coverage
 */

use App\Models\Admin;
use App\Models\Centro;
use App\Models\CentroDocente;
use App\Models\Docente;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

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
    $dni = str_pad($seq, 8, '0', STR_PAD_LEFT) . 'T';

    return Docente::forceCreate(array_merge([
        'dni'           => $dni,
        'nombre'        => 'Docente' . $seq,
        'apellido'      => 'Apellido' . $seq,
        'email_virtual' => 'docente' . $seq . '@fpvirtualaragon.es',
        'de_baja'       => false,
        'is_procesado'  => false,
        'fecha_procesado' => null,
    ], $extra));
}

// ── BLOQUE A: Acceso y autenticación ──────────────────────────────────────────

test('A1 · usuario no autenticado es redirigido al login de admin', function () {
    $this->get('/admin/alta-plataforma')
         ->assertRedirect('/admin/login');
});

test('A2 · usuario normal (guard web) no puede acceder a la vista de admin', function () {
    $centro  = Centro::forceCreate(['id_centro' => 'AP01', 'nombre' => 'Centro AP Test']);
    $usuario = \App\Models\Usuario::factory()->create(['id_centro' => 'AP01']);

    $this->actingAs($usuario, 'web')
         ->get('/admin/alta-plataforma')
         ->assertRedirect('/admin/login');
});

test('A3 · admin autenticado puede acceder a la vista de alta plataforma', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'admin')
         ->get('/admin/alta-plataforma')
         ->assertStatus(200);
});

test('A4 · la vista muestra docentes con email_virtual asignado', function () {
    $admin   = adminUser();
    $docente = docenteConEmail(['nombre' => 'Alicia', 'apellido' => 'Constante Lanuza']);

    $this->actingAs($admin, 'admin')
         ->get('/admin/alta-plataforma')
         ->assertStatus(200)
         ->assertSee('Alicia')
         ->assertSee('Constante Lanuza');
});

test('A5 · la vista NO muestra docentes dados de baja', function () {
    $admin        = adminUser();
    $bajado       = docenteConEmail(['nombre' => 'BajadoTest', 'de_baja' => true]);
    $activo       = docenteConEmail(['nombre' => 'ActivoTest',  'de_baja' => false]);

    $response = $this->actingAs($admin, 'admin')
                     ->get('/admin/alta-plataforma');

    $response->assertStatus(200)
             ->assertSee('ActivoTest')
             ->assertDontSee('BajadoTest');
});

test('A6 · filtro estado=pendiente muestra solo docentes no procesados', function () {
    $admin     = adminUser();
    $pendiente = docenteConEmail(['nombre' => 'SoloPendienteXYZ', 'is_procesado' => false]);
    $procesado = docenteConEmail(['nombre' => 'SoloProcesadoXYZ', 'is_procesado' => true, 'fecha_procesado' => now()]);

    $response = $this->actingAs($admin, 'admin')
                     ->get('/admin/alta-plataforma?estado=pendiente');

    $response->assertStatus(200)
             ->assertSee('SoloPendienteXYZ')
             ->assertDontSee('SoloProcesadoXYZ');
});

test('A7 · filtro estado=procesado muestra solo docentes ya procesados', function () {
    $admin     = adminUser();
    $pendiente = docenteConEmail(['nombre' => 'Pendiente2', 'is_procesado' => false]);
    $procesado = docenteConEmail(['nombre' => 'Procesado2', 'is_procesado' => true, 'fecha_procesado' => now()]);

    $response = $this->actingAs($admin, 'admin')
                     ->get('/admin/alta-plataforma?estado=procesado');

    $response->assertStatus(200)
             ->assertSee('Procesado2')
             ->assertDontSee('Pendiente2');
});

// ── BLOQUE B: procesarAltas ────────────────────────────────────────────────────

test('B1 · POST sin autenticación devuelve redirección', function () {
    $docente = docenteConEmail();

    $this->post('/admin/alta-plataforma/procesar', ['ids' => [$docente->id]])
         ->assertRedirect();
});

test('B2 · procesarAltas marca is_procesado=true y guarda fecha_procesado', function () {
    $admin   = adminUser();
    $docente = docenteConEmail(['is_procesado' => false]);

    $this->actingAs($admin, 'admin')
         ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$docente->id]])
         ->assertStatus(200)
         ->assertJson(['ok' => true]);

    $this->assertDatabaseHas('docentes', [
        'id'           => $docente->id,
        'is_procesado' => true,
    ]);

    $docente->refresh();
    expect($docente->fecha_procesado)->not->toBeNull();
});

test('B3 · procesarAltas no modifica docentes de baja', function () {
    $admin  = adminUser();
    $bajado = docenteConEmail(['de_baja' => true, 'is_procesado' => false]);

    $this->actingAs($admin, 'admin')
         ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$bajado->id]])
         ->assertStatus(200);

    $this->assertDatabaseHas('docentes', [
        'id'           => $bajado->id,
        'is_procesado' => false,
    ]);
});

test('B4 · procesarAltas valida que ids sea requerido', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'admin')
         ->postJson('/admin/alta-plataforma/procesar', [])
         ->assertStatus(422)
         ->assertJsonValidationErrors(['ids']);
});

test('B5 · procesarAltas valida que ids sea un array', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'admin')
         ->postJson('/admin/alta-plataforma/procesar', ['ids' => 'no-es-array'])
         ->assertStatus(422)
         ->assertJsonValidationErrors(['ids']);
});

test('B6 · procesarAltas valida que los ids existen en la tabla docentes', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'admin')
         ->postJson('/admin/alta-plataforma/procesar', ['ids' => [99999]])
         ->assertStatus(422)
         ->assertJsonValidationErrors(['ids.0']);
});

test('B7 · procesarAltas devuelve JSON con ok=true y el número de procesados', function () {
    $admin = adminUser();
    $d1    = docenteConEmail();
    $d2    = docenteConEmail();

    $this->actingAs($admin, 'admin')
         ->postJson('/admin/alta-plataforma/procesar', ['ids' => [$d1->id, $d2->id]])
         ->assertStatus(200)
         ->assertJson(['ok' => true, 'procesados' => 2]);
});

// ── BLOQUE C: preview ─────────────────────────────────────────────────────────

test('C1 · preview devuelve JSON con todos los campos esperados', function () {
    $admin   = adminUser();
    $docente = docenteConEmail();

    $this->actingAs($admin, 'admin')
         ->getJson("/admin/alta-plataforma/{$docente->id}/preview")
         ->assertStatus(200)
         ->assertJsonStructure([
             'dni', 'nombre', 'apellido', 'email_virtual', 'email_personal',
             'google_csv', 'moodle_csv', 'google_header', 'moodle_header',
         ]);
});

test('C2 · google_csv tiene exactamente 29 columnas', function () {
    $admin   = adminUser();
    $docente = docenteConEmail();

    $response = $this->actingAs($admin, 'admin')
                     ->getJson("/admin/alta-plataforma/{$docente->id}/preview");

    $googleCsv = $response->json('google_csv');
    $cols      = str_getcsv($googleCsv);
    expect(count($cols))->toBe(29);
});

test('C3 · google_csv contiene la contraseña y la unidad organizativa correctas', function () {
    $admin   = adminUser();
    $docente = docenteConEmail();

    $response = $this->actingAs($admin, 'admin')
                     ->getJson("/admin/alta-plataforma/{$docente->id}/preview");

    $cols = str_getcsv($response->json('google_csv'));

    expect($cols[3])->toBe('Cambiam3!_')   // col 4: Password
         ->and($cols[5])->toBe('/Profesorado'); // col 6: Org Unit Path
});

test('C4 · el email_personal aparece en la columna Recovery Email (posición 8)', function () {
    $admin   = adminUser();
    $docente = docenteConEmail();
    $centro  = Centro::forceCreate(['id_centro' => 'CP01', 'nombre' => 'Centro Preview']);
    CentroDocente::forceCreate([
        'dni'       => $docente->dni,
        'id_centro' => 'CP01',
        'email'     => 'personal@example.com',
    ]);

    $response = $this->actingAs($admin, 'admin')
                     ->getJson("/admin/alta-plataforma/{$docente->id}/preview");

    $cols = str_getcsv($response->json('google_csv'));

    expect($cols[7])->toBe('personal@example.com'); // col 8 (index 7)
});

test('C5 · moodle_csv tiene exactamente 29 columnas (mismo formato que Google Workspace)', function () {
    $admin   = adminUser();
    $docente = docenteConEmail();

    $response = $this->actingAs($admin, 'admin')
                     ->getJson("/admin/alta-plataforma/{$docente->id}/preview");

    $moodleCsv = $response->json('moodle_csv');
    $cols      = str_getcsv($moodleCsv);

    expect(count($cols))->toBe(29);
});

test('C7 · moodle_header tiene exactamente 29 nombres de columna', function () {
    $admin   = adminUser();
    $docente = docenteConEmail();

    $response = $this->actingAs($admin, 'admin')
                     ->getJson("/admin/alta-plataforma/{$docente->id}/preview");

    $header = $response->json('moodle_header');
    $cols   = str_getcsv($header);

    expect(count($cols))->toBe(29)
         ->and($cols[0])->toBe('First Name [Required]')
         ->and($cols[28])->toBe('Advanced Protection Program enrollment');
});

test('C6 · google_header tiene exactamente 29 nombres de columna', function () {
    $admin   = adminUser();
    $docente = docenteConEmail();

    $response = $this->actingAs($admin, 'admin')
                     ->getJson("/admin/alta-plataforma/{$docente->id}/preview");

    $header = $response->json('google_header');
    $cols   = str_getcsv($header);

    expect(count($cols))->toBe(29)
         ->and($cols[0])->toBe('First Name [Required]')
         ->and($cols[2])->toBe('Email Address [Required]')
         ->and($cols[28])->toBe('Advanced Protection Program enrollment');
});

// ── BLOQUE D: Filtros ─────────────────────────────────────────────────────────

test('D1 · búsqueda por nombre muestra solo los docentes que coinciden', function () {
    $admin     = adminUser();
    $encontrar = docenteConEmail(['nombre' => 'UnicoNombre']);
    $otro      = docenteConEmail(['nombre' => 'OtroNombre']);

    $this->actingAs($admin, 'admin')
         ->get('/admin/alta-plataforma?buscar=UnicoNombre')
         ->assertStatus(200)
         ->assertSee('UnicoNombre')
         ->assertDontSee('OtroNombre');
});

test('D2 · búsqueda por apellido muestra solo los docentes que coinciden', function () {
    $admin     = adminUser();
    $encontrar = docenteConEmail(['apellido' => 'UnicoApellido']);
    $otro      = docenteConEmail(['apellido' => 'OtroApellido']);

    $this->actingAs($admin, 'admin')
         ->get('/admin/alta-plataforma?buscar=UnicoApellido')
         ->assertStatus(200)
         ->assertSee('UnicoApellido')
         ->assertDontSee('OtroApellido');
});

test('D3 · búsqueda por DNI muestra solo el docente que coincide', function () {
    $admin     = adminUser();
    $encontrar = docenteConEmail(['dni' => '99887766X']);
    $otro      = docenteConEmail(['dni' => '11223344Y']);

    $this->actingAs($admin, 'admin')
         ->get('/admin/alta-plataforma?buscar=99887766X')
         ->assertStatus(200)
         ->assertSee('99887766X')
         ->assertDontSee('11223344Y');
});

test('D4 · sin filtros se muestran todos los docentes activos con email_virtual', function () {
    $admin = adminUser();
    $d1    = docenteConEmail(['nombre' => 'Primero']);
    $d2    = docenteConEmail(['nombre' => 'Segundo']);
    $d3    = docenteConEmail(['nombre' => 'Tercero']);

    $this->actingAs($admin, 'admin')
         ->get('/admin/alta-plataforma')
         ->assertStatus(200)
         ->assertSee('Primero')
         ->assertSee('Segundo')
         ->assertSee('Tercero');
});
