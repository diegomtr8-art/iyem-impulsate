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

class EventoSolicitudRechazadaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $user,
        public Evento $evento,
        public string $tipo,
        public string $motivo
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resultado de tu solicitud — ' . $this->evento->nombre . ' · Impulsate'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.evento-solicitud-rechazada',
            with: [
                'nombreUsuario' => $this->user->name,
                'evento'        => $this->evento,
                'tipo'          => $this->tipo,
                'motivo'        => $this->motivo,
            ]
        );
    }
}
