<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Login de la app: 5 intentos por minuto por correo+IP, y 20 por IP
        // para frenar a quien prueba muchos correos desde la misma red.
        RateLimiter::for('api-login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')) . '|' . $request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        // API autenticada: por token/usuario, no por IP (varios telefonos
        // del evento comparten la misma red Wi-Fi).
        RateLimiter::for('api-v1', fn (Request $request) =>
            Limit::perMinute(120)->by('u:' . ($request->user()?->id ?? $request->ip()))
        );
    }
}
