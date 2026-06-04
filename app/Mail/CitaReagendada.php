<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitaReagendada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cita   $cita,
        public string $destinatario = 'cliente' // 'cliente' | 'proveedor'
    ) {}

    public function envelope(): Envelope
    {
        $asunto = $this->destinatario === 'proveedor'
            ? 'Respuesta al reagendamiento de cita — Impulsate'
            : '📅 El proveedor propone una nueva fecha — Impulsate';

        return new Envelope(subject: $asunto);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.cita-reagendada', with: ['destinatario' => $this->destinatario]);
    }
}
