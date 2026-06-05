<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        if (request()->filled('redirect')) {
            $redirectUrl = request()->get('redirect');
            if (str_starts_with($redirectUrl, '/')) {
                session(['url.intended' => url($redirectUrl)]);
            }
        }

        try {
            return Socialite::driver('google')->stateless()->redirect();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['google' => 'No se pudo iniciar sesión con Google. Verifica la configuración.']);
        }
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['google' => 'Error al conectar con Google. Inténtalo de nuevo.']);
        }

        try {
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                $user = new User();
                $user->forceFill([
                    'name'              => $googleUser->getName(),
                    'email'             => $googleUser->getEmail(),
                    'password'          => bcrypt(Str::random(24)),
                    'email_verified_at' => now(),
                    'active_role'       => null,
                    'rol_seleccionado'  => false,
                    'perfil_completo'   => false,
                ]);
                $user->save();
            } else {
                if (!$user->email_verified_at) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
            }

            Auth::login($user, true);

            // Redirigir a selección de rol si no lo ha elegido todavía
            if (!$user->rol_seleccionado && !$user->isAdmin()) {
                return redirect()->route('rol.seleccionar');
            }

            return redirect()->intended(route('dashboard'));
        } catch (\Exception $e) {
            \Log::error('Google OAuth callback error: ' . $e->getMessage());
            return redirect()->route('login')->withErrors(['google' => 'No se pudo completar el inicio de sesión con Google. Intenta con tu correo y contraseña.']);
        }
    }
}
