<?php

namespace App\Mail;

use App\Models\Restaurantero;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProveedorRechazado extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Restaurantero $restaurantero, public string $motivo = '') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Resultado de revisión de tu perfil — Impulsate');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.proveedor-rechazado', with: ['motivo' => $this->motivo]);
    }
}
