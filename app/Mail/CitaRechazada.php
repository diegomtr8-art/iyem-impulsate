<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitaRechazada extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Cita $cita, public string $destinatario = 'cliente') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu cita fue rechazada — Impulsate');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.cita-rechazada', with: ['destinatario' => $this->destinatario]);
    }
}
