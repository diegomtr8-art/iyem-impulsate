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
        $userIds = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('estado', 'aprobado')
            ->pluck('user_id');

        // Fuente de verdad: rol Spatie del usuario, no evento_usuario.tipo
        // (evita descuadres con Correo Masivo, que ya filtra por rol).
        $usuarios = User::whereIn('id', $userIds)->with('roles');

        match ($segmento) {
            'proveedores', 'proveedores_evento' => $usuarios->whereHas('roles', fn ($q) => $q->where('name', 'restaurantero')),
            'compradores', 'compradores_evento' => $usuarios->whereHas('roles', fn ($q) => $q->where('name', 'cliente'))
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin')),
            default => null, // 'todos' — sin filtro adicional
        };

        $enviados = 0;
        foreach ($usuarios->get() as $user) {
            $tipo = $user->hasRole('restaurantero')
                ? 'proveedor'
                : ($user->hasRole('cliente') && !$user->hasRole('admin') ? 'comprador' : null);

            if (!$tipo) {
                continue;
            }

            // Único por evento+usuario+tipo (restricción de BD); evita reenvíos duplicados
            // aunque cambie la plantilla seleccionada.
            $existe = EncuestaSatisfaccion::where('evento_id', $evento->id)
                ->where('user_id', $user->id)
                ->where('tipo', $tipo)
                ->exists();

            if (!$existe) {
                $encuesta = EncuestaSatisfaccion::create([
                    'evento_id'             => $evento->id,
                    'user_id'               => $user->id,
                    'tipo'                  => $tipo,
                    'segmento'              => $segmento,
                    'token'                 => Str::random(40),
                    'encuesta_plantilla_id' => $plantilla->id,
                ]);

                if ($user->email) {
                    Mail::to($user->email)
                        ->send(new EncuestaSatisfaccionMail($encuesta->load(['evento', 'user', 'plantilla'])));
                    $enviados++;
                }
            }
        }

        return $enviados;
    }
}
