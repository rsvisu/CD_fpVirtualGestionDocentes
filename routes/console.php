<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// ─── Comando de demostración incluido por defecto ────────────────────────────
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Tarea programada: Informe semanal de bajas de docentes ──────────────────
// Se ejecuta todos los lunes a las 08:00 AM.
// Envía por email el resumen de bajas registradas en docentes_baja.log
// y rota el log tras el envío para evitar duplicados.
//
// Para probar manualmente:
//   php artisan docentes:enviar-resumen-bajas
//   php artisan docentes:enviar-resumen-bajas --dry-run
//
// Para ejecutar el scheduler en desarrollo:
//   php artisan schedule:work
//
Schedule::command('docentes:enviar-resumen-bajas')
    ->weeklyOn(1, '08:00')          // 1 = lunes, a las 08:00
    ->timezone('Europe/Madrid')
    ->withoutOverlapping()          // evita ejecuciones solapadas
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::channel('bajas_docentes')
            ->info('Scheduler: tarea semanal completada correctamente.');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::channel('bajas_docentes')
            ->critical('Scheduler: la tarea semanal de bajas FALLÓ.');
    });
