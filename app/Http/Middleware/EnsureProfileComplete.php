<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        $esSoloProveedor = $user->esSoloProveedor();

        if ($user->perfilCompletoEfectivo()) {
            return $next($request);
        }

        if ($request->routeIs(
            // Rutas publicas: un usuario con sesion no puede tener MENOS acceso
            // que uno anonimo. El perfil publico daba 200 sin sesion y 302 con
            // ella, asi que "Ver perfil" nunca abria.
            'home',
            'proveedores.*',
            'aviso.privacidad',
            'terminos.condiciones',
            // El bazar se rige por sus propios requisitos (INE + CSF vigente, que
            // valida registrarBazar), no por el perfil de negocio.
            'evento.registrar-bazar',
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
            'documentos.ver',
            'perfil.comprador.actualizar',
            'perfil.agregar-rol',
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

        // Redirigir una peticion que no es navegacion la vuelve invisible: Inertia
        // recibe un 302 hacia una pagina valida, lo toma por exito y dispara
        // onSuccess. El usuario veia "solicitud registrada" sin que el controlador
        // se hubiera ejecutado nunca. Un error explicito si se ve.
        if (!$request->isMethod('GET')) {
            throw ValidationException::withMessages([
                'error' => 'Completa tu perfil antes de continuar. Faltan datos obligatorios.',
            ]);
        }

        return redirect()->route($esSoloProveedor ? 'restaurantero.completar-perfil' : 'perfil.completar');
    }
}
