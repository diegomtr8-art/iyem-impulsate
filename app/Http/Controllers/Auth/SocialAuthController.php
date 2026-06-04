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
            // Solo permitir URLs relativas para evitar open redirect
            if (str_starts_with($redirectUrl, '/')) {
                session(['url.intended' => url($redirectUrl)]);
            }
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['google' => 'No se pudo autenticar con Google.']);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if (!$user) {
            $user = User::create([
                'name'              => $googleUser->getName(),
                'email'             => $googleUser->getEmail(),
                'password'          => bcrypt(Str::random(24)),
                'email_verified_at' => now(),
            ]);
            $user->assignRole('cliente');
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard'));
    }
}
