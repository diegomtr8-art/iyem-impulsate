<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Jetstream\Contracts\DeletesUsers;

/**
 * Alta, verificacion, contrasena y baja de la cuenta. Reutiliza las mismas
 * acciones de Fortify/Jetstream que usa la web (CreateNewUser,
 * UpdateUserPassword, DeleteUser); aqui solo se adapta la entrada/salida.
 */
class CuentaController extends Controller
{
    public const MENSAJE_GENERICO_VERIFICACION = 'Si el correo está registrado y aún no está verificado, te enviamos un nuevo enlace de verificación.';
    public const MENSAJE_GENERICO_RESET = 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa tu bandeja de entrada.';

    public function registro(Request $request, CreatesNewUsers $creador)
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        // CreateNewUser, ante un correo ya registrado, le agrega el rol comprador
        // a esa cuenta y la devuelve (la web inicia sesion con ella). La API no
        // puede hacer eso: seria tomar la cuenta ajena sin contrasena.
        if (User::where('email', $request->input('email'))->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta con este correo. Inicia sesión o recupera tu contraseña.',
            ]);
        }

        $user = $creador->create($request->only([
            'name', 'email', 'telefono', 'curp', 'password', 'password_confirmation', 'acepta_aviso', 'terms',
        ]));

        // Igual que Fortify\RegisteredUserController: el listener manda el
        // correo de verificacion solo si la cuenta quedo sin verificar.
        event(new Registered($user));

        $pendiente = !$user->hasVerifiedEmail();

        return response()->json([
            'data' => [
                'email'                  => $user->email,
                'verificacion_pendiente' => $pendiente,
            ],
            'message' => $pendiente
                ? 'Cuenta creada. Revisa tu correo para verificarla antes de iniciar sesión.'
                : 'Cuenta creada. Ya puedes iniciar sesión con tu correo y contraseña.',
        ], 201);
    }

    public function reenviarVerificacion(Request $request)
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $user = User::where('email', $request->input('email'))->first();

        if ($user && !$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['data' => null, 'message' => self::MENSAJE_GENERICO_VERIFICACION]);
    }

    public function olvideContrasena(Request $request)
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        // Mismo broker que Fortify (POST /forgot-password). El enlace del correo
        // abre /reset-password en la web. El estado no se revela.
        Password::broker(config('fortify.passwords'))->sendResetLink($request->only('email'));

        return response()->json(['data' => null, 'message' => self::MENSAJE_GENERICO_RESET]);
    }

    public function cambiarContrasena(Request $request, UpdatesUserPasswords $actualizador)
    {
        $user = $request->user();

        // UpdateUserPassword valida 'current_password:web'. En la API el usuario
        // llega por token (guard sanctum); se le presenta al guard web solo para
        // esa regla, sin tocar sesion.
        Auth::guard('web')->setUser($user);

        $actualizador->update($user, $request->only(['current_password', 'password', 'password_confirmation']));

        return response()->json(['data' => null, 'message' => 'Contraseña actualizada.']);
    }

    public function eliminarCuenta(Request $request, DeletesUsers $eliminador)
    {
        $request->validate(['password' => ['required', 'string']]);

        // Misma comprobacion que Jetstream\CurrentUserController::destroy.
        if (!Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => [__('This password does not match our records.')],
            ]);
        }

        $eliminador->delete($request->user()->fresh());

        return response()->json(['data' => null, 'message' => 'Tu cuenta fue eliminada.']);
    }
}
