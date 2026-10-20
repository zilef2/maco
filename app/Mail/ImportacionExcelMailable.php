<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo genérico para los resultados de una importación desde Excel.
 */
class ImportacionExcelMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $detalles  Detalle técnico (solo admin).
     * @param  string|null  $traza  Traza del error (solo admin).
     */
    public function __construct(
        public string $titulo,
        public string $mensaje,
        public array $detalles = [],
        public ?string $traza = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->titulo);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.importacion.excel',
            with: [
                'titulo' => $this->titulo,
                'mensaje' => $this->mensaje,
                'detalles' => $this->detalles,
                'traza' => $this->traza,
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
