<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class RegistroProveedorController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/RegisterProveedor');
    }

    public function store(Request $request)
    {
        // Si el email ya existe: asignar rol proveedor al usuario existente
        $existing = User::where('email', $request->email)->first();
        if ($existing) {
            if (!$existing->hasRole('restaurantero')) {
                $existing->assignRole('restaurantero');
                // No sobrescribimos active_role — el usuario puede decidir desde el switch
            }
            Auth::login($existing);
            return redirect()->route('dashboard')
                ->with('success', 'El rol de Proveedor fue agregado a tu cuenta. Puedes cambiar de modo en el menú.');
        }

        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'telefono'    => $request->telefono,
            'active_role' => 'proveedor',
        ]);

        $user->assignRole('restaurantero');

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
