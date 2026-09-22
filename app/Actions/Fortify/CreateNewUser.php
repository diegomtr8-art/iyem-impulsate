<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        // Antes, un correo ya registrado iniciaba sesion como esa cuenta SIN
        // pedir contrasena (cualquiera podia entrar como admin conociendo su
        // correo). Ahora falla la validacion 'unique'; quien ya tiene cuenta
        // inicia sesion y suma el rol desde perfil.agregar-rol.
        Validator::make($input, [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'telefono'     => ['nullable', 'string', 'max:20'],
            'curp'         => ['nullable', 'string', 'size:18'],
            'password'     => $this->passwordRules(),
            'terms'        => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
            'acepta_aviso' => ['required', 'accepted'],
        ], [
            'email.unique'          => 'Ya existe una cuenta con este correo. Inicia sesión; si quieres sumar el rol de comprador, hazlo desde tu perfil.',
            'acepta_aviso.required' => 'Debes aceptar el Aviso de Privacidad para registrarte.',
            'acepta_aviso.accepted' => 'Debes aceptar el Aviso de Privacidad para registrarte.',
        ])->validate();

        $user = User::create([
            'name'             => $input['name'],
            'email'            => $input['email'],
            'password'         => Hash::make($input['password']),
            'telefono'         => $input['telefono'] ?? null,
            'curp'             => $input['curp'] ?? null,
            'active_role'      => 'comprador',
            'acepta_aviso_at'  => now(),
        ]);

        // email_verified_at no está en $fillable (por diseño, para que no sea
        // asignable desde formularios) — forceFill es necesario para setearlo aquí.
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole('cliente');

        return $user;
    }
}
