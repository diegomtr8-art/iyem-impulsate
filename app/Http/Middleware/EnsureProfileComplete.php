<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
            return $next($request);
        }

        // Esperar a que el usuario haya pasado por aviso de privacidad y selección de rol
        // antes de exigir el perfil completo, para no generar otro loop de redirecciones.
        if (is_null($user->acepta_aviso_at) || !$user->rol_seleccionado) {
            return $next($request);
        }

        if ($user->perfil_completo) {
            return $next($request);
        }

        if ($request->routeIs(
            'perfil.completar',
            'perfil.completar.store',
            'perfil.necesidades',
            'logout',
            'verification.*',
            'password.*'
        )) {
            return $next($request);
        }

        return redirect()->route('perfil.completar');
    }
}
