<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class LimpiarYResetearSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('⚠  ATENCIÓN: Este seeder borra TODOS los datos del sistema.');
        $this->command->warn('   Asegúrate de haber hecho un respaldo (mysqldump) antes de continuar.');
        $this->command->info('   Iniciando limpieza...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('encuesta_respuestas')->truncate();
        DB::table('encuestas_satisfaccion')->truncate();
        DB::table('notificaciones')->truncate();
        DB::table('citas')->truncate();
        DB::table('evento_usuario')->truncate();
        DB::table('horarios')->truncate();
        DB::table('servicios')->truncate();
        DB::table('restauranteros')->truncate();
        DB::table('eventos')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('users')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $adminRole = Role::findByName('admin');

        $admin = \App\Models\User::create([
            'name'              => 'Administrador Impulsate',
            'email'             => 'impulsate@iyemyucatan.com',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'active_role'       => 'comprador',
        ]);

        $admin->assignRole('admin');

        $this->command->info('✅ Limpieza completa. Admin creado: impulsate@iyemyucatan.com / password');
    }
}
