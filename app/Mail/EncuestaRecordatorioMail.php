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

class EncuestaRecordatorioMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public EncuestaSatisfaccion $encuesta) {}

    public function envelope(): Envelope
    {
        $plantilla = PlantillaCorreo::paraClave('encuesta_recordatorio');

        return new Envelope(
            subject: $plantilla->asunto ?? '⏰ Recordatorio: Tu opinión es importante para nosotros — Impúlsate',
        );
    }

    public function content(): Content
    {
        $plantilla = PlantillaCorreo::paraClave('encuesta_recordatorio');

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
            view: 'mail.encuesta-recordatorio',
            with: [
                'evento'         => $this->encuesta->evento,
                'user'           => $this->encuesta->user,
                'tipo'           => $this->encuesta->tipo,
                'enlaceEncuesta' => route('encuestas.responder', $this->encuesta->token),
            ],
        );
    }
}
