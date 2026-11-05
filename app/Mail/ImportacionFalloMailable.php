<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ImportacionFalloMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $titulo,
        public string $mensaje,
        public array $detalles = [],
        public ?string $errorMensaje = null,
        public ?string $resumenIa = null,
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
            view: 'emails.importacion.fallo',
            with: [
                'titulo' => $this->titulo,
                'mensaje' => $this->mensaje,
                'detalles' => $this->detalles,
                'errorMensaje' => $this->errorMensaje,
                'resumenIa' => $this->resumenIa,
                'traza' => $this->traza,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

