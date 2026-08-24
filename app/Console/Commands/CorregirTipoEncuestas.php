<?php

namespace App\Console\Commands;

use App\Models\EncuestaSatisfaccion;
use Illuminate\Console\Command;

class CorregirTipoEncuestas extends Command
{
    protected $signature = 'encuestas:corregir-tipo';
    protected $description = 'Corrige el campo tipo de encuestas_satisfaccion (legacy) según el rol Spatie real del usuario';

    public function handle(): int
    {
        $encuestas = EncuestaSatisfaccion::with('user.roles')
            ->whereNotNull('user_id')
            ->get();

        $corregidos = 0;

        foreach ($encuestas as $encuesta) {
            if (!$encuesta->user) {
                continue;
            }

            if ($encuesta->user->hasRole('restaurantero')) {
                $tipoReal = 'proveedor';
            } elseif ($encuesta->user->hasRole('cliente') && !$encuesta->user->hasRole('admin')) {
                $tipoReal = 'comprador';
            } else {
                $this->warn("Usuario {$encuesta->user_id} sin rol cliente/restaurantero, se omite (encuesta {$encuesta->id}).");
                continue;
            }

            if ($encuesta->tipo !== $tipoReal) {
                $this->line("Encuesta {$encuesta->id} (user {$encuesta->user_id}): {$encuesta->tipo} -> {$tipoReal}");
                $encuesta->update(['tipo' => $tipoReal]);
                $corregidos++;
            }
        }

        $this->info("Listo: {$corregidos} registro(s) corregido(s) de " . $encuestas->count() . ' revisados.');

        return 0;
    }
}
