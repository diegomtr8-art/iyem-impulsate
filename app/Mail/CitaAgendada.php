<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitaAgendada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Cita $cita, public string $destinatario = 'cliente') {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva cita agendada — Encuentro de Negocios Impulsate',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.cita-agendada',
            with: ['destinatario' => $this->destinatario],
        );
    }
}
