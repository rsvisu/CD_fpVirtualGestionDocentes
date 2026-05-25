<?php

/**
 * Suite de tests — Issue #7: Auditoría y Notificaciones de Bajas de Docentes
 *
 * Cubre:
 *   A) Escritura de logs en el canal bajas_docentes (integración real con fichero físico)
 *   B) Comando Artisan docentes:enviar-resumen-bajas (Mail::fake + rotación de log)
 *   C) Casos límite y protecciones del comando
 *
 * Estrategia para logs:
 *   - El canal 'bajas_docentes' escribe en storage/logs/docentes_baja.log (driver single).
 *   - En los tests de BLOQUE A verificamos el fichero físico directamente con file_get_contents().
 *     Esto no requiere ninguna dependencia extra (como timacdonald/log-fake).
 *   - En los tests de BLOQUE B usamos Mail::fake() para interceptar envíos de correo.
 *
 * NOTA: Si en el futuro se instala `timacdonald/log-fake` (composer require --dev timacdonald/log-fake),
 * los tests del Bloque A pueden migrarse a usar LogFake::bind() y Log::channel('bajas_docentes')->assertLogged().
 *
 * Ejecución:
 *   ./vendor/bin/pest tests/Feature/BajasDocentesLogTest.php --no-coverage
 */

use App\Mail\ResumenBajasDocentes;
use App\Models\Centro;
use App\Models\Docente;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

// ─── Ruta física del log de bajas ────────────────────────────────────────────
it('escribe en el log', function () {
    $path = storage_path('logs/docentes_baja.log');

// ─── Helper: línea de log con timestamp RECIENTE (formato Monolog) ───────────
function logLineReciente(string $nivel = 'INFO', string $mensaje = 'Baja de docente procesada'): string
{
    $ts = Carbon::now()->format('Y-m-d H:i:s');
    return "[{$ts}] bajas_docentes.{$nivel}: {$mensaje} {\"dni_docente\":\"12345678A\",\"usuario_id\":1}";
}

// ─── Helper: línea de log con timestamp ANTIGUO (>7 días) ────────────────────
function logLineAntigua(string $nivel = 'INFO', string $mensaje = 'Baja antigua'): string
{
    $ts = Carbon::now()->subDays(10)->format('Y-m-d H:i:s');
    return "[{$ts}] bajas_docentes.{$nivel}: {$mensaje} {\"dni_docente\":\"99999999Z\",\"usuario_id\":2}";
}

// ─── Setup / Teardown global: limpiar el fichero de log antes y después ──────
beforeEach(function () {
    $logPath = storage_path('logs/docentes_baja.log');
    if (! is_dir(dirname($logPath))) {
        mkdir(dirname($logPath), 0755, true);
    }
    // Fichero limpio para cada test (evita interferencias entre tests)
    file_put_contents($logPath, '');
});

afterEach(function () {
    $logPath = storage_path('logs/docentes_baja.log');
    if (file_exists($logPath)) {
        unlink($logPath);
    }
});
});


// ════════════════════════════════════════════════════════════════════════════
// BLOQUE A — Escritura de logs en BajaDocenteController
// Verificación mediante el fichero físico storage/logs/docentes_baja.log
// ════════════════════════════════════════════════════════════════════════════

describe('A) Auditoría en BajaDocenteController', function () {

    // ── A1: Baja exitosa → escribe entrada de nivel INFO ─────────────────────
    test('dar de baja a un docente escribe una entrada INFO en docentes_baja.log', function () {
        $logPath = storage_path('logs/docentes_baja.log');

        // Arrange
        $centro  = Centro::forceCreate(['id_centro' => 'LA01', 'nombre' => 'Centro Log Test']);
        $usuario = Usuario::factory()->create(['id_centro' => 'LA01']);
        $docente = Docente::forceCreate([
            'nombre'        => 'Ana',
            'apellido'      => 'García',
            'dni'           => 'LA000001A',
            'email_virtual' => 'ana@test.com',
            'de_baja'       => false,
        ]);

        // Act
        $this->actingAs($usuario)->post("/docentes/baja/{$docente->dni}");

        // Assert — el fichero debe existir y contener nivel INFO con el DNI correcto
        expect(file_exists($logPath))->toBeTrue();

        $contenido = file_get_contents($logPath);
        expect($contenido)
            ->toContain('Baja de docente procesada')
            ->toContain('INFO')
            ->toContain('LA000001A');
    });

    // ── A2: Baja exitosa → el log incluye el ID del usuario que actúa ────────
    test('el log de baja incluye el ID del usuario autenticado', function () {
        $logPath = storage_path('logs/docentes_baja.log');

        // Arrange
        $centro  = Centro::forceCreate(['id_centro' => 'LA02', 'nombre' => 'Centro Log Test']);
        $usuario = Usuario::factory()->create(['id_centro' => 'LA02']);
        $docente = Docente::forceCreate([
            'nombre'        => 'Carlos',
            'apellido'      => 'López',
            'dni'           => 'LA000002B',
            'email_virtual' => 'carlos@test.com',
            'de_baja'       => false,
        ]);

        // Act
        $this->actingAs($usuario)->post("/docentes/baja/{$docente->dni}");

        // Assert — el contexto JSON del log debe incluir el usuario_id
        $contenido = file_get_contents($logPath);
        expect($contenido)->toContain((string) $usuario->id);
    });

    // ── A3: Reactivación exitosa → escribe entrada de nivel NOTICE ───────────
    test('reactivar un docente escribe una entrada NOTICE en docentes_baja.log', function () {
        $logPath = storage_path('logs/docentes_baja.log');

        // Arrange
        $centro  = Centro::forceCreate(['id_centro' => 'LA03', 'nombre' => 'Centro Log Test']);
        $usuario = Usuario::factory()->create(['id_centro' => 'LA03']);
        $docente = Docente::forceCreate([
            'nombre'        => 'Pedro',
            'apellido'      => 'Martínez',
            'dni'           => 'LA000003C',
            'email_virtual' => 'pedro@test.com',
            'de_baja'       => true,
        ]);

        // Act
        $this->actingAs($usuario)->post("/docentes/reactivar/{$docente->dni}");

        // Assert
        expect(file_exists($logPath))->toBeTrue();

        $contenido = file_get_contents($logPath);
        expect($contenido)
            ->toContain('Docente reactivado')
            ->toContain('NOTICE')
            ->toContain('LA000003C');
    });

    // ── A4: Fallo de BD (DNI inexistente) → escribe nivel CRITICAL ───────────
    test('un fallo al dar de baja escribe una entrada CRITICAL en docentes_baja.log', function () {
        $logPath = storage_path('logs/docentes_baja.log');

        // Arrange
        $centro  = Centro::forceCreate(['id_centro' => 'LA04', 'nombre' => 'Centro Log Test']);
        $usuario = Usuario::factory()->create(['id_centro' => 'LA04']);

        // DNI que no existe → firstOrFail() lanzará ModelNotFoundException
        $dniInexistente = 'XX0099099Z';

        // Act
        $this->actingAs($usuario)->post("/docentes/baja/{$dniInexistente}");

        // Assert — debe haberse escrito CRITICAL
        expect(file_exists($logPath))->toBeTrue();

        $contenido = file_get_contents($logPath);
        expect($contenido)
            ->toContain('CRITICAL')
            ->toContain('Error al dar de baja')
            ->toContain(strtoupper($dniInexistente));
    });

    // ── A5: Fallo al reactivar (DNI inexistente) → escribe nivel ERROR ───────
    test('un fallo al reactivar escribe una entrada ERROR en docentes_baja.log', function () {
        $logPath = storage_path('logs/docentes_baja.log');

        // Arrange
        $centro  = Centro::forceCreate(['id_centro' => 'LA05', 'nombre' => 'Centro Log Test']);
        $usuario = Usuario::factory()->create(['id_centro' => 'LA05']);
        $dniInexistente = 'YY0088088X';

        // Act
        $this->actingAs($usuario)->post("/docentes/reactivar/{$dniInexistente}");

        // Assert
        $contenido = file_get_contents($logPath);
        expect($contenido)
            ->toContain('ERROR')
            ->toContain('Error al reactivar')
            ->toContain(strtoupper($dniInexistente));
    });

    // ── A6: Baja → el log usa el canal bajas_docentes, NO el canal general ───
    test('la baja no escribe nada en el log general laravel.log', function () {
        $logGeneral = storage_path('logs/laravel.log');
        $contenidoAntes = file_exists($logGeneral) ? file_get_contents($logGeneral) : '';

        // Arrange
        $centro  = Centro::forceCreate(['id_centro' => 'LA06', 'nombre' => 'Centro Log Test']);
        $usuario = Usuario::factory()->create(['id_centro' => 'LA06']);
        $docente = Docente::forceCreate([
            'nombre'        => 'Rafa',
            'apellido'      => 'Sanz',
            'dni'           => 'LA000006D',
            'email_virtual' => 'rafa@test.com',
            'de_baja'       => false,
        ]);

        // Act
        $this->actingAs($usuario)->post("/docentes/baja/{$docente->dni}");

        // Assert — el laravel.log NO debe tener nuevas entradas de baja de docente
        $contenidoDespues = file_exists($logGeneral) ? file_get_contents($logGeneral) : '';
        $lineasNuevas     = str_replace($contenidoAntes, '', $contenidoDespues);
        expect($lineasNuevas)->not->toContain('Baja de docente procesada');
    });

    // ── A7: Múltiples bajas → cada una genera su propia entrada en el log ─────
    test('dar de baja a dos docentes genera dos entradas en docentes_baja.log', function () {
        $logPath = storage_path('logs/docentes_baja.log');

        // Arrange
        $centro  = Centro::forceCreate(['id_centro' => 'LA07', 'nombre' => 'Centro Log Test']);
        $usuario = Usuario::factory()->create(['id_centro' => 'LA07']);
        $doc1    = Docente::forceCreate(['nombre' => 'D1', 'apellido' => 'A', 'dni' => 'LA000007E', 'email_virtual' => 'd1@test.com', 'de_baja' => false]);
        $doc2    = Docente::forceCreate(['nombre' => 'D2', 'apellido' => 'B', 'dni' => 'LA000007F', 'email_virtual' => 'd2@test.com', 'de_baja' => false]);

        // Act
        $this->actingAs($usuario)->post("/docentes/baja/{$doc1->dni}");
        $this->actingAs($usuario)->post("/docentes/baja/{$doc2->dni}");

        // Assert — el fichero debe contener ambos DNIs
        $contenido = file_get_contents($logPath);
        expect($contenido)
            ->toContain('LA000007E')
            ->toContain('LA000007F');

        // Y debe haber al menos 2 líneas de log
        $lineas = array_filter(explode(PHP_EOL, trim($contenido)));
        expect(count($lineas))->toBeGreaterThanOrEqual(2);
    });
});


// ════════════════════════════════════════════════════════════════════════════
// BLOQUE B — Comando Artisan: docentes:enviar-resumen-bajas
// ════════════════════════════════════════════════════════════════════════════

describe('B) Comando docentes:enviar-resumen-bajas', function () {

    // ── B1: Con registros recientes → envía ResumenBajasDocentes ─────────────
    test('el comando envía el mailable ResumenBajasDocentes cuando hay registros recientes', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        file_put_contents($logPath, logLineReciente() . PHP_EOL);

        $this->artisan('docentes:enviar-resumen-bajas')->assertSuccessful();

        Mail::assertSent(ResumenBajasDocentes::class, 1);
    });

    // ── B2: El Mailable recibe exactamente los registros del log ─────────────
    test('el mailable recibe los registros extraídos del log de la última semana', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        $linea   = logLineReciente('INFO', 'Baja de docente procesada');
        file_put_contents($logPath, $linea . PHP_EOL);

        $this->artisan('docentes:enviar-resumen-bajas')->assertSuccessful();

        Mail::assertSent(ResumenBajasDocentes::class, function (ResumenBajasDocentes $mail) use ($linea) {
            return in_array($linea, $mail->registros, strict: true);
        });
    });

    // ── B3: El Mailable recibe una instancia Carbon correcta como $desde ──────
    test('el mailable recibe una instancia Carbon como fecha de inicio del período', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        file_put_contents($logPath, logLineReciente() . PHP_EOL);
        $antes = Carbon::now()->subWeek()->startOfMinute();

        $this->artisan('docentes:enviar-resumen-bajas')->assertSuccessful();

        Mail::assertSent(ResumenBajasDocentes::class, function (ResumenBajasDocentes $mail) use ($antes) {
            return $mail->desde instanceof Carbon
                && $mail->desde->greaterThanOrEqualTo($antes);
        });
    });

    // ── B4: Tras el envío el fichero de log se vacía (rotación) ──────────────
    test('después del envío exitoso docentes_baja.log queda vacío (rotación)', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        file_put_contents($logPath, logLineReciente() . PHP_EOL);

        $this->artisan('docentes:enviar-resumen-bajas')->assertSuccessful();

        expect(file_exists($logPath))->toBeTrue();
        expect(trim(file_get_contents($logPath)))->toContain('Resumen semanal enviado');
    });

    // ── B5: Líneas antiguas (>7 días) → el email NO se envía ─────────────────
    test('el comando no envía email si solo hay registros de hace más de 7 días', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        file_put_contents($logPath, logLineAntigua() . PHP_EOL);

        $this->artisan('docentes:enviar-resumen-bajas')
            ->expectsOutput('No se encontraron bajas en los últimos 7 días. No se enviará email.')
            ->assertSuccessful();

        Mail::assertNothingSent();
    });

    // ── B6: Líneas antiguas se conservan en el log tras la rotación ──────────
    test('las líneas antiguas no se eliminan en la rotación, solo las enviadas', function () {
        Mail::fake();
        $logPath       = storage_path('logs/docentes_baja.log');
        $lineaReciente = logLineReciente('INFO', 'Baja reciente');
        $lineaAntigua  = logLineAntigua('INFO',  'Baja antigua');
        file_put_contents($logPath, $lineaAntigua . PHP_EOL . $lineaReciente . PHP_EOL);

        $this->artisan('docentes:enviar-resumen-bajas')->assertSuccessful();

        $contenido = file_get_contents($logPath);
        expect($contenido)->toContain($lineaAntigua);
        expect($contenido)->not->toContain($lineaReciente);
    });

    // ── B7: --dry-run → no envía email ───────────────────────────────────────
    test('con --dry-run el comando no envía ningún email', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        file_put_contents($logPath, logLineReciente() . PHP_EOL);

        $this->artisan('docentes:enviar-resumen-bajas --dry-run')
            ->expectsOutputToContain('Dry-run completado')
            ->assertSuccessful();

        Mail::assertNothingSent();
    });

    // ── B8: --dry-run → no modifica el fichero de log ────────────────────────
    test('con --dry-run el fichero de log no es modificado', function () {
        Mail::fake();
        $logPath          = storage_path('logs/docentes_baja.log');
        $linea            = logLineReciente();
        file_put_contents($logPath, $linea . PHP_EOL);
        $contenidoOriginal = file_get_contents($logPath);

        $this->artisan('docentes:enviar-resumen-bajas --dry-run')->assertSuccessful();

        expect(file_get_contents($logPath))->toBe($contenidoOriginal);
    });

    // ── B9: --dry-run → imprime las líneas por consola ───────────────────────
    test('con --dry-run el comando imprime las líneas del log en la consola', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        file_put_contents($logPath, logLineReciente('INFO', 'Baja de docente procesada') . PHP_EOL);

        $this->artisan('docentes:enviar-resumen-bajas --dry-run')
            ->expectsOutputToContain('MODO DRY-RUN')
            ->expectsOutputToContain('Baja de docente procesada')
            ->assertSuccessful();
    });

    // ── B10: Solo se manda UN email por ejecución (no duplicados) ─────────────
    test('el comando envía exactamente un email aunque haya muchos registros recientes', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        $lineas  = '';
        for ($i = 0; $i < 15; $i++) {
            $lineas .= logLineReciente('INFO', "Baja número {$i}") . PHP_EOL;
        }
        file_put_contents($logPath, $lineas);

        $this->artisan('docentes:enviar-resumen-bajas')->assertSuccessful();

        Mail::assertSent(ResumenBajasDocentes::class, 1);
    });

    // ── B11: Múltiples registros → el Mailable los incluye todos ─────────────
    test('cuando hay varios registros recientes todos se incluyen en el mailable', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        $lineas  = implode(PHP_EOL, [
            logLineReciente('INFO',     'Baja de docente procesada'),
            logLineReciente('NOTICE',   'Docente reactivado'),
            logLineReciente('CRITICAL', 'Error al dar de baja al docente'),
        ]) . PHP_EOL;
        file_put_contents($logPath, $lineas);

        $this->artisan('docentes:enviar-resumen-bajas')->assertSuccessful();

        Mail::assertSent(ResumenBajasDocentes::class, function (ResumenBajasDocentes $mail) {
            return count($mail->registros) === 3;
        });
    });

    // ── B12: Mezcla recientes + antiguas → Mailable solo lleva los recientes ──
    test('solo se incluyen en el mailable los registros de los últimos 7 días', function () {
        Mail::fake();
        $logPath       = storage_path('logs/docentes_baja.log');
        $lineaReciente = logLineReciente('INFO', 'Baja reciente');
        $lineaAntigua  = logLineAntigua('INFO',  'Baja antigua');
        file_put_contents($logPath, $lineaAntigua . PHP_EOL . $lineaReciente . PHP_EOL);

        $this->artisan('docentes:enviar-resumen-bajas')->assertSuccessful();

        Mail::assertSent(ResumenBajasDocentes::class, function (ResumenBajasDocentes $mail) use ($lineaReciente, $lineaAntigua) {
            return count($mail->registros) === 1
                && in_array($lineaReciente, $mail->registros, strict: true)
                && ! in_array($lineaAntigua, $mail->registros, strict: true);
        });
    });
});


// ════════════════════════════════════════════════════════════════════════════
// BLOQUE C — Casos límite y protecciones del comando
// ════════════════════════════════════════════════════════════════════════════

describe('C) Casos límite del comando', function () {

    // ── C1: Fichero de log inexistente → éxito sin email ─────────────────────
    test('el comando finaliza con éxito y no envía email si el fichero de log no existe', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        if (file_exists($logPath)) {
            unlink($logPath);
        }

        $this->artisan('docentes:enviar-resumen-bajas')
            ->expectsOutputToContain('no existe')
            ->assertSuccessful();

        Mail::assertNothingSent();
    });

    // ── C2: Fichero vacío → no se envía email ────────────────────────────────
    test('el comando no envía email si el fichero de log está vacío', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        file_put_contents($logPath, '');

        $this->artisan('docentes:enviar-resumen-bajas')
            ->expectsOutput('No se encontraron bajas en los últimos 7 días. No se enviará email.')
            ->assertSuccessful();

        Mail::assertNothingSent();
    });

    // ── C3: El comando devuelve código de salida 0 en el escenario normal ─────
    test('el comando retorna exit code 0 cuando completa el envío con éxito', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        file_put_contents($logPath, logLineReciente() . PHP_EOL);

        $this->artisan('docentes:enviar-resumen-bajas')->assertSuccessful();
    });

    // ── C4: El comando muestra cuántos registros se enviaron ──────────────────
    test('el comando imprime en consola el número de registros encontrados', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        file_put_contents($logPath, logLineReciente() . PHP_EOL . logLineReciente() . PHP_EOL);

        $this->artisan('docentes:enviar-resumen-bajas')
            ->expectsOutputToContain('registros')
            ->assertSuccessful();
    });

    // ── C5: El fichero de log se recrea en el siguiente ciclo ─────────────────
    test('el fichero de log puede volver a recibir entradas después de la rotación', function () {
        Mail::fake();
        $logPath = storage_path('logs/docentes_baja.log');
        file_put_contents($logPath, logLineReciente() . PHP_EOL);

        // Primera ejecución → rota el log (queda vacío)
        $this->artisan('docentes:enviar-resumen-bajas')->assertSuccessful();
        expect(trim(file_get_contents($logPath)))->toContain('Resumen semanal enviado');

        // Simula una nueva baja tras la rotación
        $centro  = Centro::forceCreate(['id_centro' => 'LC05', 'nombre' => 'Centro Rotación']);
        $usuario = Usuario::factory()->create(['id_centro' => 'LC05']);
        $docente = Docente::forceCreate([
            'nombre'        => 'Nueva',
            'apellido'      => 'Baja',
            'dni'           => 'LC000005G',
            'email_virtual' => 'nueva@test.com',
            'de_baja'       => false,
        ]);
        $this->actingAs($usuario)->post("/docentes/baja/{$docente->dni}");

        // El fichero debe haber sido recreado con la nueva entrada
        $contenido = file_get_contents($logPath);
        expect($contenido)->toContain('LC000005G');
    });
});
