<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class FijarSuperAdmin extends Command
{
    protected $signature = 'usuario:fijar-superadmin {email=diegomtr8@gmail.com}';
    protected $description = 'Deja una cuenta limpiamente como admin + super-admin, quitando roles de cliente/restaurantero si los tiene';

    public function handle()
    {
        $email = $this->argument('email');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("No existe ningún usuario con el correo {$email}");
            return 1;
        }

        $adminRole      = Role::firstOrCreate(['name' => 'admin']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);

        foreach (['cliente', 'restaurantero'] as $rolIndebido) {
            if ($user->hasRole($rolIndebido)) {
                $user->removeRole($rolIndebido);
                $this->info("Rol '{$rolIndebido}' removido.");
            }
        }

        if (!$user->hasRole('admin'))       { $user->assignRole($adminRole); }
        if (!$user->hasRole('super-admin')) { $user->assignRole($superAdminRole); }

        $user->forceFill([
            'active_role'      => null,
            'rol_seleccionado' => true,
            'perfil_completo'  => true,
            'acepta_aviso_at'  => $user->acepta_aviso_at ?? now(),
        ])->save();

        $this->info("Listo. {$user->name} ({$user->email}) ahora tiene roles: " . implode(', ', $user->getRoleNames()->toArray()));
        return 0;
    }
}
