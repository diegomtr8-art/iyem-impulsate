<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /*
     * Usamos rol `super-admin` (Spatie) en lugar de una columna booleana,
     * para mantener consistencia con el sistema de permisos existente
     * y permitir escalabilidad futura (permisos granulares por rol).
     * El super-admin también tiene el rol `admin` para acceder a todo el
     * panel administrativo estándar más los módulos exclusivos.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole     = Role::firstOrCreate(['name' => 'admin']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::updateOrCreate(
            ['email' => 'diegomtr8@gmail.com'],
            [
                'name'              => 'Diego Martínez',
                'password'          => Hash::make('Realmadrid1'),
                'email_verified_at' => now(),
            ]
        );

        // Asignar ambos roles: admin (acceso al panel) + super-admin (módulos exclusivos)
        if (!$user->hasRole('admin')) {
            $user->assignRole($adminRole);
        }
        if (!$user->hasRole('super-admin')) {
            $user->assignRole($superAdminRole);
        }

        $this->command->info('  ✅ Super-admin creado: diegomtr8@gmail.com / Realmadrid1');
    }
}
