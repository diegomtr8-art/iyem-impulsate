<?php

use App\Http\Controllers\Api\V1\Admin as AdminApi;
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

        // Fase 4A — panel de ADMINISTRACION (web: prefijo /admin, middleware 'admin').
        // Solo cuentas con rol admin (403 JSON); usuarios-gestion ademas super-admin.
        Route::prefix('admin')->middleware('can:admin')->group(function () {
            $correo = 'throttle:api-admin-correos';

            Route::get('/dashboard', [AdminApi\DashboardController::class, 'dashboard']);
            Route::get('/contexto',  [AdminApi\DashboardController::class, 'contexto']);

            // Eventos
            Route::get('/eventos',             [AdminApi\EventoController::class, 'index']);
            Route::post('/eventos',            [AdminApi\EventoController::class, 'store']);
            Route::get('/eventos/{evento}',    [AdminApi\EventoController::class, 'show'])->whereNumber('evento');
            // POST ademas de PUT/PATCH: PHP no lee multipart (imagenes) en un PUT.
            Route::match(['put', 'patch', 'post'], '/eventos/{evento}', [AdminApi\EventoController::class, 'update'])->whereNumber('evento');
            Route::delete('/eventos/{evento}', [AdminApi\EventoController::class, 'destroy'])->whereNumber('evento');
            Route::post('/eventos/{evento}/activar',  [AdminApi\EventoController::class, 'activar'])->whereNumber('evento');
            Route::post('/eventos/{evento}/archivar', [AdminApi\EventoController::class, 'archivar'])->whereNumber('evento');
            Route::post('/eventos/{evento}/enviar-encuestas', [AdminApi\EventoController::class, 'enviarEncuestas'])->whereNumber('evento')->middleware($correo);
            Route::get('/eventos/{evento}/tv',        [AdminApi\EventoController::class, 'tv'])->whereNumber('evento');
            Route::post('/eventos/{evento}/tv/rotar', [AdminApi\EventoController::class, 'rotarTv'])->whereNumber('evento');

            // Solicitudes de registro al evento
            Route::prefix('eventos/{evento}/solicitudes')->whereNumber('evento')->group(function () use ($correo) {
                Route::get('/',            [AdminApi\SolicitudController::class, 'index']);
                Route::get('/candidatos',  [AdminApi\SolicitudController::class, 'candidatos']);
                Route::get('/{user}',      [AdminApi\SolicitudController::class, 'show'])->whereNumber('user');
                Route::middleware($correo)->group(function () {
                    Route::post('/aprobar-todos',     [AdminApi\SolicitudController::class, 'aprobarTodos']);
                    Route::post('/agregar-proveedor', [AdminApi\SolicitudController::class, 'agregarProveedor']);
                    Route::post('/agregar-comprador', [AdminApi\SolicitudController::class, 'agregarComprador']);
                    Route::post('/{user}/aprobar',    [AdminApi\SolicitudController::class, 'aprobar'])->whereNumber('user');
                    Route::post('/{user}/rechazar',   [AdminApi\SolicitudController::class, 'rechazar'])->whereNumber('user');
                    Route::post('/{user}/eliminar',   [AdminApi\SolicitudController::class, 'eliminar'])->whereNumber('user');
                });
                Route::post('/{user}/revertir',   [AdminApi\SolicitudController::class, 'revertir'])->whereNumber('user');
            });

            // Bazar / exposicion
            Route::prefix('eventos/{evento}/bazar')->whereNumber('evento')->group(function () use ($correo) {
                Route::get('/',                      [AdminApi\BazarController::class, 'index']);
                Route::post('/seleccion',            [AdminApi\BazarController::class, 'seleccion']);
                Route::post('/enviar-aprobacion',    [AdminApi\BazarController::class, 'enviarAprobacion'])->middleware($correo);
                Route::post('/enviar-rechazo',       [AdminApi\BazarController::class, 'enviarRechazo'])->middleware($correo);
                Route::get('/{user}',                [AdminApi\BazarController::class, 'show'])->whereNumber('user');
                Route::post('/{user}/aprobar',       [AdminApi\BazarController::class, 'aprobar'])->whereNumber('user');
                Route::post('/{user}/rechazar',      [AdminApi\BazarController::class, 'rechazar'])->whereNumber('user');
                Route::post('/{user}/evaluar',       [AdminApi\BazarController::class, 'evaluar'])->whereNumber('user');
                Route::post('/{user}/notas-rechazo', [AdminApi\BazarController::class, 'notasRechazo'])->whereNumber('user');
            });

            // Proveedores
            Route::get('/proveedores',            [AdminApi\ProveedorController::class, 'index']);
            Route::get('/proveedores/pendientes', [AdminApi\ProveedorController::class, 'pendientes']);
            Route::get('/proveedores/{restaurantero}',       [AdminApi\ProveedorController::class, 'show'])->whereNumber('restaurantero');
            Route::get('/proveedores/{restaurantero}/citas', [AdminApi\ProveedorController::class, 'citas'])->whereNumber('restaurantero');
            Route::match(['put', 'patch', 'post'], '/proveedores/{restaurantero}', [AdminApi\ProveedorController::class, 'update'])->whereNumber('restaurantero');
            Route::patch('/proveedores/{restaurantero}/categoria', [AdminApi\ProveedorController::class, 'categoria'])->whereNumber('restaurantero');
            Route::post('/proveedores/{restaurantero}/activo',     [AdminApi\ProveedorController::class, 'activo'])->whereNumber('restaurantero');
            Route::post('/proveedores/{restaurantero}/aprobar',    [AdminApi\ProveedorController::class, 'aprobar'])->whereNumber('restaurantero')->middleware($correo);
            Route::post('/proveedores/{restaurantero}/rechazar',   [AdminApi\ProveedorController::class, 'rechazar'])->whereNumber('restaurantero')->middleware($correo);

            // Usuarios / compradores
            Route::get('/usuarios',                   [AdminApi\UsuarioController::class, 'index']);
            Route::get('/usuarios/{user}',            [AdminApi\UsuarioController::class, 'show'])->whereNumber('user');
            Route::put('/usuarios/{user}',            [AdminApi\UsuarioController::class, 'update'])->whereNumber('user');
            Route::delete('/usuarios/{user}',         [AdminApi\UsuarioController::class, 'destroy'])->whereNumber('user');
            Route::get('/usuarios/{user}/documentos', [AdminApi\UsuarioController::class, 'documentos'])->whereNumber('user');

            // Gestion de cuentas: solo super-admin
            Route::prefix('usuarios-gestion')->middleware('can:super-admin')->group(function () {
                Route::get('/',                 [AdminApi\UsuarioGestionController::class, 'index']);
                Route::get('/roles',            [AdminApi\UsuarioGestionController::class, 'roles']);
                Route::get('/{user}',           [AdminApi\UsuarioGestionController::class, 'show'])->whereNumber('user');
                Route::put('/{user}',           [AdminApi\UsuarioGestionController::class, 'update'])->whereNumber('user');
                Route::post('/{user}/password', [AdminApi\UsuarioGestionController::class, 'password'])->whereNumber('user')->middleware('throttle:api-credenciales');
                Route::post('/{user}/roles',    [AdminApi\UsuarioGestionController::class, 'actualizarRoles'])->whereNumber('user');
                Route::delete('/{user}',        [AdminApi\UsuarioGestionController::class, 'destroy'])->whereNumber('user');
            });

            // Citas
            Route::get('/citas',                 [AdminApi\CitaController::class, 'index']);
            Route::get('/citas/formulario',      [AdminApi\CitaController::class, 'formulario']);
            Route::post('/citas',                [AdminApi\CitaController::class, 'store'])->middleware($correo);
            Route::get('/citas/{cita}',          [AdminApi\CitaController::class, 'show'])->whereNumber('cita');
            Route::patch('/citas/{cita}/estado', [AdminApi\CitaController::class, 'estado'])->whereNumber('cita')->middleware($correo);

            // Agenda propuesta (flujo invertido)
            Route::get('/agenda',                  [AdminApi\AgendaController::class, 'index']);
            Route::get('/agenda/formulario',       [AdminApi\AgendaController::class, 'formulario']);
            Route::post('/agenda',                 [AdminApi\AgendaController::class, 'store'])->middleware($correo);
            Route::get('/agenda/{agenda}',         [AdminApi\AgendaController::class, 'show'])->whereNumber('agenda');
            Route::post('/agenda/{agenda}/enviar', [AdminApi\AgendaController::class, 'enviar'])->whereNumber('agenda')->middleware($correo);
            Route::delete('/agenda/{agenda}',      [AdminApi\AgendaController::class, 'destroy'])->whereNumber('agenda');

            // Torre de control
            Route::get('/torre',         [AdminApi\TorreController::class, 'index']);
            Route::patch('/torre/mesas', [AdminApi\TorreController::class, 'mesas']);
            Route::prefix('torre/citas/{cita}')->whereNumber('cita')->group(function () {
                Route::post('/llamar',    [AdminApi\TorreController::class, 'llamar']);
                Route::post('/repetir',   [AdminApi\TorreController::class, 'repetir']);
                Route::post('/iniciar',   [AdminApi\TorreController::class, 'iniciar']);
                Route::post('/finalizar', [AdminApi\TorreController::class, 'finalizar']);
                Route::post('/ausente',   [AdminApi\TorreController::class, 'ausente']);
                Route::post('/retrasar',  [AdminApi\TorreController::class, 'retrasar']);
                Route::post('/mesa',      [AdminApi\TorreController::class, 'mesa']);
            });

            // Calendario
            Route::get('/calendario',             [AdminApi\CalendarioController::class, 'index']);
            Route::get('/calendario/proveedores', [AdminApi\CalendarioController::class, 'proveedores']);
        });
    });
});
