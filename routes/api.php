<?php

use App\Http\Controllers\NotificacionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Notificaciones
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notificaciones',                        [NotificacionController::class, 'index']);
    Route::patch('/notificaciones/{notificacion}/leer',  [NotificacionController::class, 'marcarLeida']);
    Route::post('/notificaciones/leer-todas',            [NotificacionController::class, 'marcarTodasLeidas']);
    Route::delete('/notificaciones/{notificacion}',      [NotificacionController::class, 'eliminar']);
    Route::delete('/notificaciones',                     [NotificacionController::class, 'eliminarTodas']);
});

// Formulario de contacto público
Route::post('/contacto', [\App\Http\Controllers\ContactoController::class, 'enviar'])->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| API v1 — app movil
|--------------------------------------------------------------------------
| Todo lo nuevo va bajo /api/v1; las rutas de arriba las usa la web y no se
| tocan. Estas rutas NO pasan por EnsureAvisoAceptado / EnsureRolSeleccionado /
| EnsureProfileComplete (solo estan en el grupo web): el estado de onboarding
| viaja como dato en /auth/login y /auth/me.
*/
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login'])
        ->middleware('throttle:api-login');

    // Cuenta sin sesion: envian correo, por eso el limitador propio.
    Route::middleware('throttle:api-cuenta')->group(function () {
        Route::post('/auth/registro',               [\App\Http\Controllers\Api\V1\CuentaController::class, 'registro']);
        Route::post('/auth/reenviar-verificacion',  [\App\Http\Controllers\Api\V1\CuentaController::class, 'reenviarVerificacion']);
        Route::post('/auth/olvide-contrasena',      [\App\Http\Controllers\Api\V1\CuentaController::class, 'olvideContrasena']);
    });

    // INE / CSF por URL firmada temporal (la emite GET /perfil/documentos).
    Route::get('/documentos/{user}/{tipo}', [\App\Http\Controllers\Api\V1\DocumentoController::class, 'ver'])
        ->whereIn('tipo', ['ine', 'csf'])
        ->middleware(['signed', 'throttle:api-archivos'])
        ->name('api.v1.documentos.ver');

    Route::middleware(['auth:sanctum', 'throttle:api-v1'])->group(function () {
        Route::get('/auth/me',            [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);
        Route::post('/auth/logout',       [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
        Route::post('/auth/logout-todos', [\App\Http\Controllers\Api\V1\AuthController::class, 'logoutTodos']);

        // Onboarding
        Route::get('/aviso-privacidad',          [\App\Http\Controllers\Api\V1\OnboardingController::class, 'avisoPrivacidad']);
        Route::post('/onboarding/aceptar-aviso', [\App\Http\Controllers\Api\V1\OnboardingController::class, 'aceptarAviso']);
        Route::post('/onboarding/rol',           [\App\Http\Controllers\Api\V1\OnboardingController::class, 'seleccionarRol']);

        // Perfil del comprador, documentos y roles
        Route::get('/perfil',                [\App\Http\Controllers\Api\V1\PerfilController::class, 'show']);
        Route::put('/perfil',                [\App\Http\Controllers\Api\V1\PerfilController::class, 'update']);
        Route::post('/perfil/completar',     [\App\Http\Controllers\Api\V1\PerfilController::class, 'completar']);
        Route::get('/perfil/documentos',     [\App\Http\Controllers\Api\V1\PerfilController::class, 'documentos']);
        Route::post('/perfil/documentos',    [\App\Http\Controllers\Api\V1\PerfilController::class, 'subirDocumentos'])
            ->middleware('throttle:api-documentos');
        Route::post('/perfil/cambiar-rol',   [\App\Http\Controllers\Api\V1\PerfilController::class, 'cambiarRol']);
        Route::post('/perfil/agregar-rol',   [\App\Http\Controllers\Api\V1\PerfilController::class, 'agregarRol']);

        // Cuenta
        Route::put('/perfil/contrasena', [\App\Http\Controllers\Api\V1\CuentaController::class, 'cambiarContrasena'])
            ->middleware('throttle:api-credenciales');
        Route::delete('/cuenta',         [\App\Http\Controllers\Api\V1\CuentaController::class, 'eliminarCuenta'])
            ->middleware('throttle:api-credenciales');

        // Notificaciones (las de /api/notificaciones sin v1 son de la web)
        Route::get('/notificaciones',                       [\App\Http\Controllers\Api\V1\NotificacionController::class, 'index']);
        Route::get('/notificaciones/contador',              [\App\Http\Controllers\Api\V1\NotificacionController::class, 'contador']);
        Route::post('/notificaciones/leer-todas',           [\App\Http\Controllers\Api\V1\NotificacionController::class, 'marcarTodasLeidas']);
        Route::patch('/notificaciones/{notificacion}/leer', [\App\Http\Controllers\Api\V1\NotificacionController::class, 'marcarLeida'])
            ->whereNumber('notificacion');
        Route::delete('/notificaciones/{notificacion}',     [\App\Http\Controllers\Api\V1\NotificacionController::class, 'eliminar'])
            ->whereNumber('notificacion');
        Route::delete('/notificaciones',                    [\App\Http\Controllers\Api\V1\NotificacionController::class, 'eliminarTodas']);

        // Pantalla de Inicio y listas de formularios
        Route::get('/inicio',    [\App\Http\Controllers\Api\V1\InicioController::class, 'inicio']);
        Route::get('/catalogos', [\App\Http\Controllers\Api\V1\InicioController::class, 'catalogos']);

        Route::get('/eventos',                    [\App\Http\Controllers\Api\V1\EventoController::class, 'index']);
        Route::get('/eventos/{evento}',           [\App\Http\Controllers\Api\V1\EventoController::class, 'show']);
        Route::post('/eventos/{evento}/registro', [\App\Http\Controllers\Api\V1\EventoController::class, 'registrar'])
            ->middleware('throttle:api-registro-evento');

        // Fase 2 — comprador: directorio, disponibilidad y citas
        Route::get('/proveedores',                            [\App\Http\Controllers\Api\V1\ProveedorController::class, 'index']);
        Route::get('/proveedores/categorias',                 [\App\Http\Controllers\Api\V1\ProveedorController::class, 'categorias']);
        Route::get('/proveedores/{proveedor}',                [\App\Http\Controllers\Api\V1\ProveedorController::class, 'show'])
            ->whereNumber('proveedor');
        Route::get('/proveedores/{proveedor}/disponibilidad', [\App\Http\Controllers\Api\V1\ProveedorController::class, 'disponibilidad'])
            ->whereNumber('proveedor');

        Route::get('/citas',                            [\App\Http\Controllers\Api\V1\CitaController::class, 'index']);
        Route::post('/citas',                           [\App\Http\Controllers\Api\V1\CitaController::class, 'store'])
            ->middleware('throttle:api-agendar');
        Route::get('/citas/{cita}',                     [\App\Http\Controllers\Api\V1\CitaController::class, 'show'])->whereNumber('cita');
        Route::post('/citas/{cita}/cancelar',           [\App\Http\Controllers\Api\V1\CitaController::class, 'cancelar'])->whereNumber('cita');
        Route::patch('/citas/{cita}/notas',             [\App\Http\Controllers\Api\V1\CitaController::class, 'notas'])->whereNumber('cita');
        Route::post('/citas/{cita}/reagenda/aceptar',   [\App\Http\Controllers\Api\V1\CitaController::class, 'aceptarReagenda'])
            ->whereNumber('cita')->middleware('throttle:api-respuestas');
        Route::post('/citas/{cita}/reagenda/rechazar',  [\App\Http\Controllers\Api\V1\CitaController::class, 'rechazarReagenda'])
            ->whereNumber('cita')->middleware('throttle:api-respuestas');

        // Agendas propuestas por el admin
        Route::get('/agendas',                   [\App\Http\Controllers\Api\V1\AgendaController::class, 'index']);
        Route::get('/agendas/{agenda}',          [\App\Http\Controllers\Api\V1\AgendaController::class, 'show'])->whereNumber('agenda');
        Route::post('/agendas/{agenda}/aceptar', [\App\Http\Controllers\Api\V1\AgendaController::class, 'aceptar'])
            ->whereNumber('agenda')->middleware('throttle:api-respuestas');
        Route::post('/agendas/{agenda}/rechazar', [\App\Http\Controllers\Api\V1\AgendaController::class, 'rechazar'])
            ->whereNumber('agenda')->middleware('throttle:api-respuestas');

        // Encuestas de satisfaccion
        Route::get('/encuestas/pendientes',             [\App\Http\Controllers\Api\V1\EncuestaController::class, 'pendientes']);
        Route::get('/encuestas/{encuesta}',             [\App\Http\Controllers\Api\V1\EncuestaController::class, 'show'])->whereNumber('encuesta');
        Route::post('/encuestas/{encuesta}/respuestas', [\App\Http\Controllers\Api\V1\EncuestaController::class, 'responder'])
            ->whereNumber('encuesta')->middleware('throttle:api-encuestas');

        // Fase 3 — proveedor: panel, perfil de negocio, productos, citas recibidas y calendario.
        // Solo cuentas con rol de proveedor (web: role:restaurantero), sin importar active_role.
        Route::prefix('proveedor')->middleware('can:proveedor')->group(function () {
            Route::get('/panel',      [\App\Http\Controllers\Api\V1\Proveedor\PanelController::class, 'panel']);
            Route::get('/calendario', [\App\Http\Controllers\Api\V1\Proveedor\PanelController::class, 'calendario']);

            Route::get('/perfil',            [\App\Http\Controllers\Api\V1\Proveedor\PerfilController::class, 'show']);
            Route::get('/perfil/catalogos',  [\App\Http\Controllers\Api\V1\Proveedor\PerfilController::class, 'catalogos']);
            Route::middleware('throttle:api-proveedor-perfil')->group(function () {
                Route::put('/perfil',            [\App\Http\Controllers\Api\V1\Proveedor\PerfilController::class, 'update']);
                Route::put('/perfil/{seccion}',  [\App\Http\Controllers\Api\V1\Proveedor\PerfilController::class, 'actualizarSeccion'])
                    ->whereIn('seccion', array_keys(\App\Services\PerfilProveedorService::SECCIONES));
                Route::post('/logo',             [\App\Http\Controllers\Api\V1\Proveedor\PerfilController::class, 'logo']);

                Route::post('/productos',            [\App\Http\Controllers\Api\V1\Proveedor\ProductoController::class, 'store']);
                // POST ademas de PUT/PATCH: PHP no lee multipart (la foto) en un PUT.
                Route::match(['put', 'patch', 'post'], '/productos/{indice}', [\App\Http\Controllers\Api\V1\Proveedor\ProductoController::class, 'update'])
                    ->whereNumber('indice');
                Route::delete('/productos/{indice}', [\App\Http\Controllers\Api\V1\Proveedor\ProductoController::class, 'destroy'])
                    ->whereNumber('indice');
            });
            Route::get('/productos', [\App\Http\Controllers\Api\V1\Proveedor\ProductoController::class, 'index']);

            Route::get('/citas',                       [\App\Http\Controllers\Api\V1\Proveedor\CitaController::class, 'index']);
            Route::get('/citas/{cita}',                [\App\Http\Controllers\Api\V1\Proveedor\CitaController::class, 'show'])->whereNumber('cita');
            Route::get('/citas/{cita}/disponibilidad', [\App\Http\Controllers\Api\V1\Proveedor\CitaController::class, 'disponibilidad'])->whereNumber('cita');
            Route::middleware('throttle:api-proveedor-citas')->group(function () {
                Route::post('/citas/{cita}/aceptar',   [\App\Http\Controllers\Api\V1\Proveedor\CitaController::class, 'aceptar'])->whereNumber('cita');
                Route::post('/citas/{cita}/rechazar',  [\App\Http\Controllers\Api\V1\Proveedor\CitaController::class, 'rechazar'])->whereNumber('cita');
                Route::post('/citas/{cita}/reagendar', [\App\Http\Controllers\Api\V1\Proveedor\CitaController::class, 'reagendar'])->whereNumber('cita');
            });
        });
    });
});
