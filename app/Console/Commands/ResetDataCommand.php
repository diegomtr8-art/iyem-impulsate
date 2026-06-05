<?php

namespace App\Console\Commands;

use App\Models\Cita;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\Servicio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDataCommand extends Command
{
    protected $signature = 'app:reset-data';
    protected $description = 'Limpia todos los datos del sistema excepto el admin. SOLO usar en local.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando NO puede ejecutarse en producción.');
            return 1;
        }

        if (!$this->confirm('¿Estás seguro de que quieres BORRAR todos los datos (excepto admin)? Esta acción es irreversible.')) {
            $this->info('Operación cancelada.');
            return 0;
        }

        $this->info('Iniciando limpieza de datos...');

        $conteos = [
            'citas'          => Cita::count(),
            'horarios'       => Horario::count(),
            'servicios'      => Servicio::count(),
            'restauranteros' => Restaurantero::count(),
            'notificaciones' => Notificacion::count(),
            'page_visits'    => DB::table('page_visits')->count(),
            'evento_usuario' => DB::table('evento_usuario')->count(),
            'eventos'        => Evento::count(),
            'usuarios'       => DB::table('users')->where('email', '!=', 'Admin@citas.com')->count(),
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Cita::truncate();
        $this->line("  ✓ Citas: {$conteos['citas']} registros eliminados");

        Horario::truncate();
        $this->line("  ✓ Horarios: {$conteos['horarios']} registros eliminados");

        Servicio::truncate();
        $this->line("  ✓ Servicios: {$conteos['servicios']} registros eliminados");

        Restaurantero::truncate();
        $this->line("  ✓ Restauranteros: {$conteos['restauranteros']} registros eliminados");

        Notificacion::truncate();
        $this->line("  ✓ Notificaciones: {$conteos['notificaciones']} registros eliminados");

        DB::table('page_visits')->truncate();
        $this->line("  ✓ Page Visits: {$conteos['page_visits']} registros eliminados");

        DB::table('evento_usuario')->truncate();
        $this->line("  ✓ Registros evento_usuario: {$conteos['evento_usuario']} eliminados");

        Evento::truncate();
        $this->line("  ✓ Eventos: {$conteos['eventos']} registros eliminados");

        $adminId = DB::table('users')->where('email', 'Admin@citas.com')->value('id');
        DB::table('users')->where('email', '!=', 'Admin@citas.com')->delete();
        $this->line("  ✓ Usuarios: {$conteos['usuarios']} registros eliminados (admin conservado)");

        if ($adminId) {
            DB::table('model_has_roles')
                ->where('model_type', \App\Models\User::class)
                ->where('model_id', '!=', $adminId)
                ->delete();
            DB::table('model_has_permissions')
                ->where('model_type', \App\Models\User::class)
                ->where('model_id', '!=', $adminId)
                ->delete();
            $this->line('  ✓ Roles/permisos huérfanos eliminados (admin conservado)');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info('✅ Limpieza completada. Admin@citas.com y sus permisos conservados.');

        return 0;
    }
}
