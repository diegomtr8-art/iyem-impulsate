<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecordatorioCita extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cita   $cita,
        public string $tipo         = '24h',  // '24h' | '2h'
        public string $destinatario = 'cliente'
    ) {}

    public function envelope(): Envelope
    {
        $emoji = $this->tipo === '2h' ? '🔔' : '⏰';
        $tiempo = $this->tipo === '2h' ? 'en 2 horas' : 'mañana';
        return new Envelope(subject: "{$emoji} Recordatorio: tienes una cita {$tiempo} — Impulsate");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.recordatorio-cita',
            with: [
                'tipo'         => $this->tipo,
                'destinatario' => $this->destinatario,
            ]
        );
    }
}
