<?php

namespace App\Mail;

use App\Models\Evento;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventoSolicitudAprobadaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $user,
        public Evento $evento,
        public string $tipo
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Fuiste aprobado al evento ' . $this->evento->nombre . '! — Impulsate'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.evento-solicitud-aprobada',
            with: [
                'nombreUsuario' => $this->user->name,
                'evento'        => $this->evento,
                'tipo'          => $this->tipo,
            ]
        );
    }
}
