<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResumenBajasDocentes extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string>  $registros  Líneas del log de la última semana
     * @param  Carbon         $desde      Fecha de inicio del período
     */
    public function __construct(
        public readonly array  $registros,
        public readonly Carbon $desde,
    ) {}

    /**
     * Configura el sobre del email (asunto, remitente, etc.).
     */
    public function envelope(): Envelope
    {
        $hasta = Carbon::now()->format('d/m/Y');
        $desdeFormato = $this->desde->format('d/m/Y');

        return new Envelope(
            subject: "[Gestión Docentes] Informe semanal de bajas ({$desdeFormato} – {$hasta})",
        );
    }

    /**
     * Configura el contenido del email (vista Blade).
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.resumen_bajas_docentes',
        );
    }

    /**
     * Archivos adjuntos (ninguno en este caso).
     */
    public function attachments(): array
    {
        return [];
    }
}
