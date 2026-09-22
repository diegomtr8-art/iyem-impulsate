<?php

namespace Tests\Concerns;

use App\Models\Evento;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Escenario minimo de un encuentro de negocios: evento activo manana,
 * proveedor con servicio y compradores que ya pasaron el onboarding.
 */
trait CreaEscenarioEvento
{
    protected Evento $evento;
    protected Restaurantero $restaurantero;
    protected Servicio $servicio;

    protected function crearEscenario(array $evento = []): void
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['cliente', 'restaurantero', 'admin', 'super-admin'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $this->evento = Evento::create(array_merge([
            'nombre'                     => 'Encuentro Test',
            'fecha_inicio'               => now()->addDay()->toDateString(),
            'tipo_evento'                => 'encuentro_negocios',
            'activa'                     => true,
            'fecha_hora_inicio'          => now()->addDay()->setTime(9, 0),
            'fecha_hora_fin'             => now()->addDays(2)->setTime(18, 0),
            'tiempo_entre_citas_minutos' => 30,
            'max_citas_por_comprador'    => 3,
        ], $evento));

        $this->restaurantero = $this->crearProveedor('Negocio Test');
        $this->servicio = $this->restaurantero->servicios()->first();
    }

    protected function crearProveedor(string $nombre, int $duracionServicio = 30): Restaurantero
    {
        $user = User::factory()->create();
        $user->assignRole('restaurantero');

        $restaurantero = Restaurantero::create([
            'edicion_id'         => $this->evento->id,
            'user_id'            => $user->id,
            'nombre_restaurante' => $nombre,
            'activo'             => true,
        ]);

        Servicio::create([
            'restaurantero_id' => $restaurantero->id,
            'nombre'           => 'Servicio',
            'duracion_minutos' => $duracionServicio,
            'activo'           => true,
        ]);

        return $restaurantero;
    }

    protected function crearComprador(?string $estadoRegistro = 'aprobado', array $atributos = []): User
    {
        $user = User::factory()->create(array_merge([
            'acepta_aviso_at'  => now(),
            'rol_seleccionado' => true,
            'active_role'      => 'comprador',
            'perfil_completo'  => true,
        ], $atributos));
        $user->assignRole('cliente');

        if ($estadoRegistro) {
            DB::table('evento_usuario')->insert([
                'evento_id'  => $this->evento->id,
                'user_id'    => $user->id,
                'tipo'       => 'comprador',
                'estado'     => $estadoRegistro,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }

    protected function crearAdmin(): User
    {
        $admin = User::factory()->create(['acepta_aviso_at' => now(), 'rol_seleccionado' => true]);
        $admin->assignRole('admin');

        return $admin;
    }
}
