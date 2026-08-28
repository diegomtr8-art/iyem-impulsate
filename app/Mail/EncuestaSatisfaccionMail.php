<?php

namespace App\Mail;

use App\Models\EncuestaSatisfaccion;
use App\Models\PlantillaCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EncuestaSatisfaccionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public EncuestaSatisfaccion $encuesta,
        public bool $esPrueba = false,
    ) {}

    public function envelope(): Envelope
    {
        $plantilla = PlantillaCorreo::paraClave('encuesta_satisfaccion');
        $asunto    = $plantilla->asunto ?? 'Tu opinión importa — Encuesta de satisfacción Impúlsate';

        return new Envelope(
            subject: ($this->esPrueba ? '[PRUEBA] ' : '') . $asunto
        );
    }

    public function content(): Content
    {
        $plantilla = PlantillaCorreo::paraClave('encuesta_satisfaccion');

        // Editable desde Admin > Plantillas Correo (clave "encuesta_satisfaccion").
        // Si no existe/está desactivada, cae al Blade estático como respaldo.
        if ($plantilla) {
            $vars = [
                'nombre_usuario'  => $this->encuesta->user?->name ?? 'Participante',
                'nombre_evento'   => $this->encuesta->evento?->nombre ?? 'el evento',
                'enlace_encuesta' => route('encuestas.responder', $this->encuesta->token),
            ];

            return new Content(
                view: 'mail.plantilla-generica',
                with: ['htmlContenido' => $plantilla->reemplazar($vars)],
            );
        }

        return new Content(
            view: 'mail.encuesta-satisfaccion',
            with: [
                'evento'         => $this->encuesta->evento,
                'user'           => $this->encuesta->user,
                'tipo'           => $this->encuesta->tipo,
                'esPrueba'       => $this->esPrueba,
                'enlaceEncuesta' => route('encuestas.responder', $this->encuesta->token),
            ],
        );
    }
}
