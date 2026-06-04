<?php

namespace App\Mail;

use App\Models\Restaurantero;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProveedorAprobado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Restaurantero $restaurantero) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '🎉 ¡Tu perfil de proveedor fue aprobado! — Impulsate');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.proveedor-aprobado');
    }
}
