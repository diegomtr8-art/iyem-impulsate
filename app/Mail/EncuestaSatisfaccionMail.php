<?php

namespace App\Mail;

use App\Models\EncuestaSatisfaccion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EncuestaSatisfaccionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EncuestaSatisfaccion $encuesta,
        public bool $esPrueba = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->esPrueba ? '[PRUEBA] ' : '') . 'Tu opinión importa — Encuesta de satisfacción Impúlsate'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.encuesta-satisfaccion',
            with: [
                'evento'       => $this->encuesta->evento,
                'user'         => $this->encuesta->user,
                'tipo'         => $this->encuesta->tipo,
                'enlaceEncuesta' => route('encuestas.responder', $this->encuesta->token),
            ],
        );
    }
}
