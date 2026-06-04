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

        // Admins y proveedores activos no requieren este flujo
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Solo si perfil_completo = false
        if ($user->perfil_completo) {
            return $next($request);
        }

        // No redirigir si ya está en la página de completar perfil
        if ($request->routeIs('perfil.completar', 'perfil.completar.store')) {
            return $next($request);
        }

        return redirect()->route('perfil.completar');
    }
}
