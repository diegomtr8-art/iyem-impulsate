<?php

namespace App\Mail;

use App\Models\PlantillaCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlantillaCorreoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $htmlContenido;

    public function __construct(
        public PlantillaCorreo $plantilla,
        public array $vars = [],
    ) {
        $this->htmlContenido = $plantilla->reemplazar($vars);
    }

    public function envelope(): Envelope
    {
        $asunto = $this->plantilla->asunto;
        foreach ($this->vars as $key => $value) {
            $asunto = str_replace('{{' . $key . '}}', (string)($value ?? ''), $asunto);
        }
        return new Envelope(subject: $asunto ?: $this->plantilla->asunto);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.plantilla-generica');
    }
}
