<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\ResumenBajasDocentes;
use Carbon\Carbon;

class EnviarResumenBajas extends Command
{
    /**
     * Nombre y firma del comando Artisan.
     *
     * @var string
     */
    protected $signature = 'docentes:enviar-resumen-bajas
                            {--dry-run : Muestra el resumen por consola sin enviar email ni rotar el log}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Envía un informe semanal con las bajas de docentes registradas en docentes_baja.log';

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $logPath = storage_path('logs/docentes_baja.log');

        // ── 1. Verificar que el fichero de log existe ────────────────────────
        if (! file_exists($logPath)) {
            $this->warn('El fichero docentes_baja.log no existe. No hay bajas que reportar.');
            Log::channel('bajas_docentes')->info('Resumen semanal: no se encontró el fichero de log.');
            return self::SUCCESS;
        }

        // ── 2. Leer líneas de la última semana ───────────────────────────────
        $desde     = Carbon::now()->subWeek();
        $lineas    = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $registros = $this->filtrarUltimaSemana($lineas, $desde);

        if (empty($registros)) {
            $this->info('No se encontraron bajas en los últimos 7 días. No se enviará email.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Se encontraron %d registros de la última semana.', count($registros)));

        // ── 3. Modo dry-run: solo mostrar en consola ─────────────────────────
        if ($this->option('dry-run')) {
            $this->line('--- MODO DRY-RUN: resumen de bajas ---');
            foreach ($registros as $linea) {
                $this->line($linea);
            }
            $this->info('Dry-run completado. No se envió email ni se rotó el log.');
            return self::SUCCESS;
        }

        // ── 4. Enviar email con el resumen ───────────────────────────────────
        $destinatario = config('mail.from.address');
        $alertTo      = env('LOG_ALERT_TO', $destinatario);

        try {
            Mail::to($alertTo)->send(new ResumenBajasDocentes($registros, $desde));
            $this->info("Informe enviado correctamente a: {$alertTo}");
        } catch (\Exception $e) {
            $this->error('Error al enviar el email: ' . $e->getMessage());
            Log::channel('bajas_docentes')->error('Error al enviar resumen semanal de bajas', [
                'error' => $e->getMessage(),
            ]);
            return self::FAILURE;
        }

        // ── 5. Rotar el log (truncar) tras el envío ──────────────────────────
        $this->rotarLog($logPath, $registros);
        $this->info('Log rotado correctamente.');

        Log::channel('bajas_docentes')->info('Resumen semanal enviado y log rotado', [
            'registros_enviados' => count($registros),
            'destinatario'       => $alertTo,
        ]);

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filtra las líneas del log que pertenecen a la última semana.
     * El formato de timestamp de Monolog es: [YYYY-MM-DD HH:MM:SS]
     *
     * @param  array<string>  $lineas
     * @param  Carbon         $desde
     * @return array<string>
     */
    private function filtrarUltimaSemana(array $lineas, Carbon $desde): array
    {
        return array_filter($lineas, function (string $linea) use ($desde): bool {
            // Extraer timestamp del formato Monolog: [2025-01-15 08:00:00]
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $linea, $matches)) {
                try {
                    $fecha = Carbon::createFromFormat('Y-m-d H:i:s', $matches[1]);
                    return $fecha->greaterThanOrEqualTo($desde);
                } catch (\Exception) {
                    return false;
                }
            }
            return false;
        });
    }

    /**
     * Rota el log: las líneas antiguas (enviadas) se eliminan y se conserva
     * el resto del fichero (líneas más nuevas que podrían haber llegado
     * mientras se ejecutaba el comando).
     *
     * @param  string         $logPath
     * @param  array<string>  $registrosEnviados
     */
    private function rotarLog(string $logPath, array $registrosEnviados): void
    {
        try {
            // Releer el fichero por si se añadieron líneas durante la ejecución
            $todasLasLineas = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            // Conservar solo las líneas NO enviadas (más nuevas que la ventana)
            $lineasEnviadas = array_values($registrosEnviados);
            $resto          = array_diff($todasLasLineas, $lineasEnviadas);

            file_put_contents($logPath, implode(PHP_EOL, $resto) . (count($resto) ? PHP_EOL : ''));
        } catch (\Exception $e) {
            $this->warn('No se pudo rotar el log: ' . $e->getMessage());
            Log::channel('bajas_docentes')->warning('No se pudo rotar el log tras el envío del resumen', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
