<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRolSeleccionado
{
    public function handle(Request $request, Closure $next)
    {
        if (
            auth()->check()
            && !auth()->user()->isAdmin()
            && !auth()->user()->rol_seleccionado
            && !is_null(auth()->user()->acepta_aviso_at)
            && !$request->routeIs(
                'rol.seleccionar',
                'rol.seleccionar.store',
                'logout',
                'profile.*',
                'verification.*',
                'password.*',
                // Rutas publicas por token (ver EnsureAvisoAceptado)
                'agenda.*',
                'encuestas.responder',
                'encuestas.responder.store',
                'citas.confirmar-token*',
                'citas.rechazar-token*',
                'tv.index',
                'tv.publico',
                'bazar.evaluacion'
            )
        ) {
            return redirect()->route('rol.seleccionar');
        }

        return $next($request);
    }
}
