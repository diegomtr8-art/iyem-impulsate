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
            && !$request->routeIs(
                'rol.seleccionar',
                'rol.seleccionar.store',
                'logout',
                'profile.*',
                'verification.*',
                'password.*'
            )
        ) {
            return redirect()->route('rol.seleccionar');
        }

        return $next($request);
    }
}
