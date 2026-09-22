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

        // Registro, reenvio de verificacion y "olvide mi contrasena": envian
        // correo, asi que se limitan por correo+IP y por IP.
        RateLimiter::for('api-cuenta', fn (Request $request) => [
            Limit::perMinute(3)->by('c:' . mb_strtolower((string) $request->input('email')) . '|' . $request->ip()),
            Limit::perMinute(10)->by('c:' . $request->ip()),
        ]);

        // Limitadores con NOMBRE (clave propia) para las acciones sensibles.
        // Un 'throttle:6,1' numerico usa como clave solo el id del usuario, asi
        // que TODAS las rutas con throttle numerico (web y API) comparten el
        // mismo contador: subir documentos consumia el cupo de borrar la cuenta.
        RateLimiter::for('api-credenciales', fn (Request $request) =>
            Limit::perMinute(6)->by('cred:' . ($request->user()?->id ?? $request->ip()))
        );
        RateLimiter::for('api-documentos', fn (Request $request) =>
            Limit::perMinute(10)->by('docs:' . ($request->user()?->id ?? $request->ip()))
        );
        RateLimiter::for('api-registro-evento', fn (Request $request) =>
            Limit::perMinute(10)->by('reg:' . ($request->user()?->id ?? $request->ip()))
        );
        RateLimiter::for('api-archivos', fn (Request $request) =>
            Limit::perMinute(60)->by('arch:' . $request->ip())
        );

        // API autenticada: por token/usuario, no por IP (varios telefonos
        // del evento comparten la misma red Wi-Fi).
        RateLimiter::for('api-v1', fn (Request $request) =>
            Limit::perMinute(120)->by('u:' . ($request->user()?->id ?? $request->ip()))
        );
    }
}
