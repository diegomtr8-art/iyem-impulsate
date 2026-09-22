<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Registrarse con un correo ya existente iniciaba sesion como esa cuenta sin
 * contrasena. Debe fallar la validacion y no tocar la cuenta existente.
 */
class RegistroCorreoExistenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrarse_con_el_correo_de_un_admin_no_da_acceso_a_su_cuenta(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);

        $admin = User::factory()->create(['email' => 'admin@ejemplo.com']);
        $admin->assignRole('admin');

        $this->post('/register', [
            'name'                  => 'Atacante',
            'email'                 => 'admin@ejemplo.com',
            'password'              => 'Cualquiera123!',
            'password_confirmation' => 'Cualquiera123!',
            'acepta_aviso'          => '1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertFalse($admin->fresh()->hasRole('cliente'));
        $this->assertSame(1, User::where('email', 'admin@ejemplo.com')->count());
    }
}
