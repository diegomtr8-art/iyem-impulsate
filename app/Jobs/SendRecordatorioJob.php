<?php

namespace App\Jobs;

use App\Mail\EncuestaRecordatorioMail;
use App\Models\EncuestaSatisfaccion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendRecordatorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public int $encuestaId) {}

    public function handle(): void
    {
        $encuesta = EncuestaSatisfaccion::with(['evento', 'user', 'plantilla'])
            ->find($this->encuestaId);

        if (!$encuesta || $encuesta->completada_at) {
            return;
        }

        $email = $encuesta->user?->email ?? $encuesta->email_prueba;
        if (!$email) {
            return;
        }

        Mail::to($email)->send(new EncuestaRecordatorioMail($encuesta));
    }
}
