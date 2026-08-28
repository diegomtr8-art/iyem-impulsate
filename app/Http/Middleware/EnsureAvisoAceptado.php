<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAvisoAceptado
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && is_null(Auth::user()->acepta_aviso_at)) {
            if (!Auth::user()->hasRole('admin') && !Auth::user()->hasRole('super-admin')) {
                if (!$request->routeIs(
                    'aviso.aceptar',
                    'aviso.aceptar.post',
                    'aviso.privacidad',
                    'logout',
                    'profile.*',
                    'verification.*',
                    'password.*',
                    // Rutas publicas por token: deben funcionar aunque el usuario
                    // tenga sesion abierta y el onboarding a medias.
                    'agenda.*',
                    'encuestas.responder',
                    'encuestas.responder.store',
                    'citas.confirmar-token*',
                    'citas.rechazar-token*',
                    'tv.index',
                    'tv.publico',
                    'bazar.evaluacion',
                    'documentos.ver',
                    'perfil.comprador.actualizar',
                    'perfil.agregar-rol'
                )) {
                    return redirect()->route('aviso.aceptar');
                }
            }
        }

        return $next($request);
    }
}
