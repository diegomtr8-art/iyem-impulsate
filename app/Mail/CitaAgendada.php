<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitaAgendada extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Cita $cita, public string $destinatario = 'cliente') {}

    public function envelope(): Envelope
    {
        $subject = $this->destinatario === 'proveedor'
            ? 'Tienes una nueva solicitud de cita pendiente de confirmación'
            : 'Solicitud de cita registrada — pendiente de confirmación';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.cita-agendada',
            with: ['destinatario' => $this->destinatario],
        );
    }
}
