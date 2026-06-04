<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        // Si el email ya existe: asignar rol comprador al usuario existente
        $existing = User::where('email', $input['email'])->first();
        if ($existing) {
            if (!$existing->hasRole('cliente')) {
                $existing->assignRole('cliente');
                // Si el usuario solo tenía restaurantero, ahora tiene ambos — no cambiamos active_role
            }
            // Logueamos al usuario y lo mandamos al dashboard
            Auth::login($existing);
            return $existing;
        }

        Validator::make($input, [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'curp'     => ['nullable', 'string', 'size:18'],
            'password' => $this->passwordRules(),
            'terms'    => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        $user = User::create([
            'name'        => $input['name'],
            'email'       => $input['email'],
            'password'    => Hash::make($input['password']),
            'telefono'    => $input['telefono'] ?? null,
            'curp'        => $input['curp'] ?? null,
            'active_role' => 'comprador',
        ]);

        $user->assignRole('cliente');

        return $user;
    }
}
