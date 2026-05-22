<?php

/**
 * Suite de tests — Issue #58: Generación de correo @fpvirtualaragon.es
 *
 * Cubre:
 *   A) Algoritmo del servicio GeneradorEmailVirtualService
 *   B) Integración con alta de docentes (AltaDocenteController@store)
 *   C) Endpoint AJAX previewEmail
 *   D) Colisiones y casos límite
 *
 * Ejecución:
 *   ./vendor/bin/pest tests/Feature/GeneradorEmailVirtualTest.php --no-coverage
 */

use App\Models\Centro;
use App\Models\Docente;
use App\Models\Usuario;
use App\Services\GeneradorEmailVirtualService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helper ───────────────────────────────────────────────────────────────────
function servicio(): GeneradorEmailVirtualService
{
    return new GeneradorEmailVirtualService();
}


// ════════════════════════════════════════════════════════════════════════════
// BLOQUE A — Algoritmo del servicio
// ════════════════════════════════════════════════════════════════════════════

test('A1 · genera el email correcto para el ejemplo de la tabla (Dario Axel Ureña Garcia)', function () {
    $email = servicio()->previsualizarEmail('Dario Axel', 'Ureña Garcia');
    expect($email)->toBe('daurenag@fpvirtualaragon.es');
});

test('A2 · genera el email correcto para el ejemplo de la tabla (María Del Carmen Mónica Royo Lupón)', function () {
    $email = servicio()->previsualizarEmail('María Del Carmen Mónica', 'Royo Lupón');
    expect($email)->toBe('mdcmroyol@fpvirtualaragon.es');
});

test('A3 · docente con un solo nombre y dos apellidos', function () {
    $email = servicio()->previsualizarEmail('Ana', 'García López');
    expect($email)->toBe('agarcial@fpvirtualaragon.es');
});

test('A4 · docente con un solo apellido (sin segundo apellido)', function () {
    $email = servicio()->previsualizarEmail('Juan', 'García');
    expect($email)->toBe('jgarcia@fpvirtualaragon.es');
});

test('A5 · los acentos se eliminan en la parte local del email', function () {
    $email = servicio()->previsualizarEmail('Óscar', 'Pérez Ávila');
    expect($email)->toBe('opereza@fpvirtualaragon.es');
});

test('A6 · la ñ se convierte en n', function () {
    $email = servicio()->previsualizarEmail('Pedro', 'Muñoz Niño');
    expect($email)->toBe('pmunozn@fpvirtualaragon.es');
});

test('A7 · nombres con múltiples palabras generan todas sus iniciales', function () {
    $email = servicio()->previsualizarEmail('José Luis María', 'Sánchez Torres');
    expect($email)->toBe('jlmsanchezt@fpvirtualaragon.es');
});

test('A8 · el email generado usa siempre el dominio fpvirtualaragon.es', function () {
    $email = servicio()->previsualizarEmail('Test', 'Apellido');
    expect($email)->toEndWith('@fpvirtualaragon.es');
});

test('A9 · el email generado está completamente en minúsculas', function () {
    $email = servicio()->previsualizarEmail('JUAN CARLOS', 'FERNÁNDEZ RUIZ');
    expect($email)->toBe(strtolower($email));
});

test('A10 · no contiene caracteres no ASCII en la parte local', function () {
    $email = servicio()->previsualizarEmail('Ángel', 'Martínez Cañón');
    $localPart = explode('@', $email)[0];
    expect($localPart)->toMatch('/^[a-z0-9]+$/');
});


// ════════════════════════════════════════════════════════════════════════════
// BLOQUE B — Integración con alta de docentes
// ════════════════════════════════════════════════════════════════════════════

test('B1 · al dar de alta un docente nuevo se genera automáticamente el email_virtual', function () {
    $centro  = Centro::forceCreate(['id_centro' => 'C001', 'nombre' => 'Centro Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'C001']);

    $this->actingAs($usuario)->post('/alta-docente', [
        'nombre'    => 'Laura',
        'apellido'  => 'Sánchez Pérez',
        'dni'       => '12345678L',
        'email'     => 'laura@micentro.com',
        'id_centro' => 'C001',
    ]);

    $docente = Docente::where('dni', '12345678L')->first();
    expect($docente)->not->toBeNull();
    expect($docente->email_virtual)->toBe('lsanchezp@fpvirtualaragon.es');
});

test('B2 · el email_virtual termina en @fpvirtualaragon.es al dar de alta', function () {
    $centro  = Centro::forceCreate(['id_centro' => 'C002', 'nombre' => 'Centro Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'C002']);

    $this->actingAs($usuario)->post('/alta-docente', [
        'nombre'    => 'Carlos',
        'apellido'  => 'Ruiz',
        'dni'       => '87654321C',
        'email'     => 'carlos@micentro.com',
        'id_centro' => 'C002',
    ]);

    $docente = Docente::where('dni', '87654321C')->first();
    expect($docente->email_virtual)->toEndWith('@fpvirtualaragon.es');
});

test('B3 · el email personal del docente se guarda en centro_docente, no en email_virtual', function () {
    $centro  = Centro::forceCreate(['id_centro' => 'C003', 'nombre' => 'Centro Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'C003']);

    $this->actingAs($usuario)->post('/alta-docente', [
        'nombre'    => 'Elena',
        'apellido'  => 'Gómez Torres',
        'dni'       => '11223344E',
        'email'     => 'elena.personal@centro.com',
        'id_centro' => 'C003',
    ]);

    $docente = Docente::where('dni', '11223344E')->first();
    // email_virtual NO es el email personal
    expect($docente->email_virtual)->not->toBe('elena.personal@centro.com');
    expect($docente->email_virtual)->toEndWith('@fpvirtualaragon.es');

    // El email personal sí está en centro_docente
    $this->assertDatabaseHas('centro_docente', [
        'dni'   => '11223344E',
        'email' => 'elena.personal@centro.com',
    ]);
});

test('B4 · si el docente ya existe en BD se respeta su email_virtual original', function () {
    $centro  = Centro::forceCreate(['id_centro' => 'C004', 'nombre' => 'Centro Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'C004']);

    // Crear docente con email_virtual ya asignado
    Docente::forceCreate([
        'nombre'        => 'Pedro',
        'apellido'      => 'López García',
        'dni'           => '99887766P',
        'email_virtual' => 'plopezg@fpvirtualaragon.es',
        'de_baja'       => false,
    ]);

    // Dar de alta en otro centro
    $centro2 = Centro::forceCreate(['id_centro' => 'C005', 'nombre' => 'Centro 2']);
    $this->actingAs($usuario)->post('/alta-docente', [
        'nombre'    => 'Pedro',
        'apellido'  => 'López García',
        'dni'       => '99887766P',
        'email'     => 'pedro@otro-centro.com',
        'id_centro' => 'C004',
    ]);

    // El email_virtual original NO se modifica
    $docente = Docente::where('dni', '99887766P')->first();
    expect($docente->email_virtual)->toBe('plopezg@fpvirtualaragon.es');
});


// ════════════════════════════════════════════════════════════════════════════
// BLOQUE C — Endpoint AJAX previewEmail
// ════════════════════════════════════════════════════════════════════════════

test('C1 · el endpoint preview-email devuelve el email generado para nombre y apellido', function () {
    $centro  = Centro::forceCreate(['id_centro' => 'C010', 'nombre' => 'Centro Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'C010']);

    $response = $this->actingAs($usuario)
        ->getJson('/alta-docente/preview-email?nombre=Marta&apellido=Torres Ruiz');

    $response->assertStatus(200)
             ->assertJson(['email' => 'mtorresr@fpvirtualaragon.es']);
});

test('C2 · el endpoint preview-email devuelve null si falta el nombre', function () {
    $centro  = Centro::forceCreate(['id_centro' => 'C011', 'nombre' => 'Centro Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'C011']);

    $response = $this->actingAs($usuario)
        ->getJson('/alta-docente/preview-email?apellido=Torres');

    $response->assertStatus(200)
             ->assertJson(['email' => null]);
});

test('C3 · el endpoint preview-email devuelve null si falta el apellido', function () {
    $centro  = Centro::forceCreate(['id_centro' => 'C012', 'nombre' => 'Centro Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'C012']);

    $response = $this->actingAs($usuario)
        ->getJson('/alta-docente/preview-email?nombre=Marta');

    $response->assertStatus(200)
             ->assertJson(['email' => null]);
});


// ════════════════════════════════════════════════════════════════════════════
// BLOQUE D — Colisiones y casos límite
// ════════════════════════════════════════════════════════════════════════════

test('D1 · si el email generado ya existe se añade sufijo numérico', function () {
    // Crear docente que "ocupa" el email base
    Docente::forceCreate([
        'nombre'        => 'Ana',
        'apellido'      => 'García López',
        'dni'           => '11111111A',
        'email_virtual' => 'agarcial@fpvirtualaragon.es',
    ]);

    $email = servicio()->generarOObtenerExistente('Ana', 'García López');
    expect($email)->toBe('agarcial2@fpvirtualaragon.es');
});

test('D2 · si el email con sufijo 2 también existe prueba con 3', function () {
    Docente::forceCreate(['nombre'=>'A','apellido'=>'B','dni'=>'11111111X','email_virtual'=>'ab@fpvirtualaragon.es']);
    Docente::forceCreate(['nombre'=>'A','apellido'=>'B','dni'=>'22222222X','email_virtual'=>'ab2@fpvirtualaragon.es']);

    $email = servicio()->generarOObtenerExistente('A', 'B');
    expect($email)->toBe('ab3@fpvirtualaragon.es');
});

test('D3 · generarOObtenerExistente devuelve el email_virtual existente si el docente ya está en BD', function () {
    Docente::forceCreate([
        'nombre'        => 'Luis',
        'apellido'      => 'Pérez Sanz',
        'dni'           => '55555555L',
        'email_virtual' => 'lperezs@fpvirtualaragon.es',
    ]);

    $email = servicio()->generarOObtenerExistente('Luis', 'Pérez Sanz', '55555555L');
    expect($email)->toBe('lperezs@fpvirtualaragon.es');
});

test('D4 · comprobarDocente devuelve el email_virtual del docente existente', function () {
    $centro  = Centro::forceCreate(['id_centro' => 'C020', 'nombre' => 'Centro Test']);
    $usuario = Usuario::factory()->create(['id_centro' => 'C020']);

    Docente::forceCreate([
        'nombre'        => 'Rosa',
        'apellido'      => 'Fernández',
        'dni'           => '66666666R',
        'email_virtual' => 'rfernandez@fpvirtualaragon.es',
    ]);

    $response = $this->actingAs($usuario)
        ->getJson('/comprobar-docente/66666666R');

    $response->assertStatus(200)
             ->assertJsonPath('email_virtual', 'rfernandez@fpvirtualaragon.es');
});
