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
    public function enviarParaEvento(
        Evento $evento,
        EncuestaPlantilla $plantilla,
        string $segmento = 'todos'
    ): int {
        $query = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('estado', 'aprobado');

        match ($segmento) {
            'proveedores', 'proveedores_evento' => $query->where('tipo', 'proveedor'),
            'compradores', 'compradores_evento' => $query->where('tipo', 'comprador'),
            default => null, // 'todos' — sin filtro adicional
        };

        $participantes = $query->get();

        $enviados = 0;
        foreach ($participantes as $p) {
            // Único por evento+usuario+tipo (restricción de BD); evita reenvíos duplicados
            // aunque cambie la plantilla seleccionada.
            $existe = EncuestaSatisfaccion::where('evento_id', $evento->id)
                ->where('user_id', $p->user_id)
                ->where('tipo', $p->tipo)
                ->exists();

            if (!$existe) {
                $encuesta = EncuestaSatisfaccion::create([
                    'evento_id'             => $evento->id,
                    'user_id'               => $p->user_id,
                    'tipo'                  => $p->tipo,
                    'segmento'              => $segmento,
                    'token'                 => Str::random(40),
                    'encuesta_plantilla_id' => $plantilla->id,
                ]);

                $user = User::find($p->user_id);
                if ($user?->email) {
                    Mail::to($user->email)
                        ->send(new EncuestaSatisfaccionMail($encuesta->load(['evento', 'user', 'plantilla'])));
                    $enviados++;
                }
            }
        }

        return $enviados;
    }
}
