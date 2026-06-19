<?php

namespace App\Console\Commands;

use App\Models\Restaurantero;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SincronizarEventoUsuario extends Command
{
    protected $signature = 'evento:sincronizar-proveedores';
    protected $description = 'Crea registros faltantes en evento_usuario para restauranteros con edicion_id asignado';

    public function handle()
    {
        $restauranteros = Restaurantero::whereNotNull('edicion_id')
            ->whereNotNull('user_id')
            ->get();

        $creados = 0;
        $omitidos = 0;

        foreach ($restauranteros as $r) {
            $existe = DB::table('evento_usuario')
                ->where('evento_id', $r->edicion_id)
                ->where('user_id', $r->user_id)
                ->where('tipo', 'proveedor')
                ->exists();

            if ($existe) {
                $omitidos++;
                continue;
            }

            $estado = $r->aprobado ? 'aprobado' : 'pendiente';

            DB::table('evento_usuario')->insert([
                'evento_id'     => $r->edicion_id,
                'user_id'       => $r->user_id,
                'tipo'          => 'proveedor',
                'estado'        => $estado,
                'respondido_at' => $estado === 'aprobado' ? now() : null,
                'created_at'    => $r->created_at ?? now(),
                'updated_at'    => now(),
            ]);

            $creados++;
        }

        $this->info("Listo: {$creados} registros creados en evento_usuario, {$omitidos} ya existían.");
    }
}
