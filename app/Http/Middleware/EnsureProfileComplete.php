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

        // Los proveedores puros se miden por el perfil del negocio (Restaurantero),
        // no por el flag genérico de users: ese puede quedar en true por datos viejos
        // aunque el negocio (logo, productos, etc.) siga incompleto.
        $esSoloProveedor = $user->hasRole('restaurantero') && !$user->hasRole('cliente');

        $perfilCompleto = $esSoloProveedor
            ? (bool) $user->restaurantero?->perfil_completo
            : (bool) $user->perfil_completo;

        if ($perfilCompleto) {
            return $next($request);
        }

        if ($request->routeIs(
            'perfil.completar',
            'perfil.completar.store',
            'perfil.necesidades',
            'user.perfil',
            'perfil.documentos.subir',
            // Rutas publicas por token (ver EnsureAvisoAceptado)
            'agenda.*',
            'encuestas.responder',
            'encuestas.responder.store',
            'citas.confirmar-token*',
            'citas.rechazar-token*',
            'tv.index',
            'tv.publico',
            'bazar.evaluacion',
            // El formulario de completar perfil vive dentro del panel del proveedor
            // (Restaurantero/Panel.vue), junto con el resto de sus rutas (calendario,
            // citas). Exentar todo el grupo evita un loop de redirección hacia
            // restaurantero.panel mientras el perfil sigue incompleto.
            'restaurantero.*',
            'logout',
            'verification.*',
            'password.*'
        )) {
            return $next($request);
        }

        return redirect()->route($esSoloProveedor ? 'restaurantero.completar-perfil' : 'perfil.completar');
    }
}
