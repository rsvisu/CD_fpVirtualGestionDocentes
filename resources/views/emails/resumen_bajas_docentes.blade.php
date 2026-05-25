<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe Semanal de Bajas de Docentes</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f6f9;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 700px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #1a3a5c;
            color: #ffffff;
            padding: 24px 32px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }
        .header p {
            margin: 6px 0 0;
            font-size: 13px;
            opacity: 0.8;
        }
        .body {
            padding: 28px 32px;
        }
        .summary-box {
            background: #eef3f9;
            border-left: 4px solid #1a3a5c;
            padding: 14px 18px;
            border-radius: 4px;
            margin-bottom: 24px;
        }
        .summary-box p {
            margin: 0;
            font-size: 14px;
        }
        .summary-box strong {
            color: #1a3a5c;
        }
        h2 {
            font-size: 15px;
            color: #1a3a5c;
            margin: 0 0 12px;
            border-bottom: 1px solid #dde4ed;
            padding-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 24px;
        }
        th {
            background-color: #1a3a5c;
            color: #fff;
            text-align: left;
            padding: 8px 10px;
            font-weight: 600;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e8edf3;
            vertical-align: top;
        }
        tr:nth-child(even) td {
            background-color: #f7f9fc;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-info     { background: #d1ecf1; color: #0c5460; }
        .badge-notice   { background: #d4edda; color: #155724; }
        .badge-error    { background: #f8d7da; color: #721c24; }
        .badge-critical { background: #6c1a1a; color: #fff; }
        .raw-log {
            background: #1e2a38;
            color: #c8d8e8;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            padding: 16px;
            border-radius: 6px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
            margin-bottom: 24px;
        }
        .footer {
            background: #f4f6f9;
            color: #888;
            font-size: 11px;
            padding: 16px 32px;
            text-align: center;
            border-top: 1px solid #dde4ed;
        }
    </style>
</head>
<body>
<div class="container">

    {{-- ── Cabecera ─────────────────────────────────────────────────────── --}}
    <div class="header">
        <h1>📋 Informe Semanal de Bajas de Docentes</h1>
        <p>
            Período: {{ $desde->format('d/m/Y') }} – {{ now()->format('d/m/Y') }}
            &nbsp;|&nbsp;
            Generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }}
        </p>
    </div>

    <div class="body">

        {{-- ── Resumen ejecutivo ───────────────────────────────────────────── --}}
        <div class="summary-box">
            <p>
                Se han registrado <strong>{{ count($registros) }} evento(s)</strong>
                en el sistema de auditoría de bajas durante los últimos 7 días.
            </p>
        </div>

        {{-- ── Tabla de registros parseados ───────────────────────────────── --}}
        @php
            $parsed = collect($registros)->map(function (string $linea): array {
                $timestamp = '';
                $canal     = '';
                $nivel     = 'info';
                $mensaje   = $linea;
                $contexto  = '';

                // Formato Monolog: [2025-01-15 08:00:00] canal.NIVEL: mensaje {"key":"value"}
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(\S+)\.(\w+):\s+(.+?)(\s+\{.*\})?$/', $linea, $m)) {
                    $timestamp = $m[1];
                    $canal     = $m[2];
                    $nivel     = strtolower($m[3]);
                    $mensaje   = trim($m[4]);
                    $contexto  = isset($m[5]) ? trim($m[5]) : '';
                }

                return compact('timestamp', 'canal', 'nivel', 'mensaje', 'contexto', 'linea');
            });
        @endphp

        @if ($parsed->isNotEmpty())
            <h2>Detalle de eventos</h2>
            <table>
                <thead>
                    <tr>
                        <th>Fecha / Hora</th>
                        <th>Nivel</th>
                        <th>Mensaje</th>
                        <th>Contexto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($parsed as $reg)
                        <tr>
                            <td>{{ $reg['timestamp'] ?: '—' }}</td>
                            <td>
                                @php
                                    $badge = match($reg['nivel']) {
                                        'notice'   => 'badge-notice',
                                        'error'    => 'badge-error',
                                        'critical' => 'badge-critical',
                                        default    => 'badge-info',
                                    };
                                @endphp
                                <span class="badge {{ $badge }}">{{ strtoupper($reg['nivel']) }}</span>
                            </td>
                            <td>{{ $reg['mensaje'] ?: $reg['linea'] }}</td>
                            <td>
                                @if ($reg['contexto'])
                                    <small>{{ $reg['contexto'] }}</small>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- ── Log en bruto (fallback legible) ───────────────────────────── --}}
        <h2>Log completo del período</h2>
        <div class="raw-log">{{ implode("\n", $registros) }}</div>

    </div>

    {{-- ── Pie de página ───────────────────────────────────────────────── --}}
    <div class="footer">
        Este mensaje ha sido generado automáticamente por el sistema de gestión de docentes
        ({{ config('app.name') }}) el {{ now()->format('d/m/Y \a \l\a\s H:i') }}.
        No responda a este correo.
    </div>

</div>
</body>
</html>
