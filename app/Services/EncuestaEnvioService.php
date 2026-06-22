<?php

namespace App\Services;

use App\Mail\EncuestaSatisfaccionMail;
use App\Models\EncuestaPlantilla;
use App\Models\EncuestaSatisfaccion;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EncuestaEnvioService
{
    public function enviarParaEvento(Evento $evento): int
    {
        $plantillaActivaId = EncuestaPlantilla::where('activa', true)->value('id');

        $participantes = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('estado', 'aprobado')
            ->get();

        $enviados = 0;
        foreach ($participantes as $p) {
            $existe = EncuestaSatisfaccion::where('evento_id', $evento->id)
                ->where('user_id', $p->user_id)
                ->where('tipo', $p->tipo)
                ->exists();

            if (!$existe) {
                $encuesta = EncuestaSatisfaccion::create([
                    'evento_id'             => $evento->id,
                    'user_id'               => $p->user_id,
                    'tipo'                  => $p->tipo,
                    'token'                 => Str::random(40),
                    'encuesta_plantilla_id' => $plantillaActivaId,
                ]);

                $user = User::find($p->user_id);
                if ($user) {
                    Mail::to($user->email)
                        ->send(new EncuestaSatisfaccionMail($encuesta->load(['evento', 'user'])));
                    $enviados++;
                }
            }
        }

        return $enviados;
    }
}
