<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $datos = $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'required|string|max:100',
        ]);

        $user = User::where('email', $datos['email'])->first();

        if (!$user || !Hash::check($datos['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        // En la web las rutas de la app exigen 'verified'; la API no puede ser
        // una puerta lateral para cuentas sin verificar.
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Verifica tu correo antes de entrar. Revisa tu bandeja de entrada.',
                'errors'  => ['email' => ['Verifica tu correo antes de entrar. Revisa tu bandeja de entrada.']],
            ], 403);
        }

        $token = $user->createToken($datos['device_name'])->plainTextToken;

        return response()->json([
            'data' => [
                'token'      => $token,
                'user'       => new UserResource($user),
                'onboarding' => $user->onboarding(),
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'user'       => new UserResource($user),
                'onboarding' => $user->onboarding(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['data' => null, 'message' => 'Sesión cerrada.']);
    }

    public function logoutTodos(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['data' => null, 'message' => 'Sesión cerrada en todos los dispositivos.']);
    }
}
