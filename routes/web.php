<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\RestauranteroPublicoController;
use App\Http\Controllers\CitaPublicaController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\RestauranteroAdminController;
use App\Http\Controllers\Admin\CitaAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Admin\MetricasController;
use App\Http\Controllers\Admin\CalendarioController;
use App\Http\Controllers\Admin\EventoController;
use App\Http\Controllers\Admin\EventoSolicitudesController;
use App\Http\Controllers\Admin\TorreControlController;
use App\Http\Controllers\Admin\SuperAdmin\UsuariosGestionController;
use App\Http\Controllers\Admin\SuperAdmin\PlantillasCorreoController;
use App\Http\Controllers\Admin\SuperAdmin\PublicidadController;
use App\Http\Controllers\Admin\SuperAdmin\CorreoMasivoController;
use App\Http\Controllers\Admin\SuperAdmin\CategoriasController;
use App\Http\Controllers\RestauranteroPanelController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\SeleccionarRolController;
use App\Http\Controllers\Auth\SwitchRoleController;
use App\Http\Controllers\CompletarPerfilController;
use App\Http\Controllers\AvisoPrivacidadController;
use App\Http\Controllers\AvisoAceptacionController;
use App\Http\Controllers\EncuestaController;
use App\Http\Controllers\EventoRegistroController;
use App\Http\Controllers\ProveedorPerfilController;
use App\Http\Controllers\RestauranteroCitasController;

// Google OAuth
Route::get('/auth/google',          [SocialAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback')->middleware('throttle:10,1');

// Selector de rol (post-login)
Route::get('/seleccionar-rol',  [SeleccionarRolController::class, 'create'])->name('rol.seleccionar')->middleware('auth:sanctum');
Route::post('/seleccionar-rol', [SeleccionarRolController::class, 'store'])->name('rol.seleccionar.store')->middleware('auth:sanctum');

// Confirmación/rechazo de reagendamiento por token (rutas públicas)
Route::get('/citas/{cita}/confirmar/{token}', [RestauranteroCitasController::class, 'confirmarToken'])->name('citas.confirmar-token');
Route::get('/citas/{cita}/rechazar/{token}',  [RestauranteroCitasController::class, 'rechazarToken'])->name('citas.rechazar-token');

// Pantalla TV pública (acceso por token)
Route::get('/tv/{token}',              [\App\Http\Controllers\Admin\PantallaTvController::class, 'index'])->name('tv.index');
Route::get('/api/tv/{token}/publico',  [\App\Http\Controllers\Admin\PantallaTvController::class, 'datosPublicos'])->name('tv.publico');

// Encuestas de satisfacción (sin autenticación — acceso por token)
Route::get('/encuesta/{token}',  [EncuestaController::class, 'show'])->name('encuestas.responder');
Route::post('/encuesta/{token}', [EncuestaController::class, 'store'])->name('encuestas.responder.store');

// Aceptación del Aviso de Privacidad (solo auth, sin aviso.aceptado para evitar loop)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/aceptar-aviso',  [AvisoAceptacionController::class, 'show'])->name('aviso.aceptar');
    Route::post('/aceptar-aviso', [AvisoAceptacionController::class, 'store'])->name('aviso.aceptar.post');
});

// Demo Zona Comercial Yucatán 200 (sin auth, sin BD)
Route::get('/demo-prueba/layout', fn() => Inertia::render('Demo/Layout'))->name('demo.layout');
Route::get('/demo-prueba/paid',   fn() => Inertia::render('Demo/Paid'))->name('demo.paid');

// Rutas públicas
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/aviso-de-privacidad', [AvisoPrivacidadController::class, 'index'])->name('aviso.privacidad');
Route::get('/proveedores', [RestauranteroPublicoController::class, 'index'])->name('proveedores.index');
Route::get('/proveedores/{restaurantero}', [RestauranteroPublicoController::class, 'show'])->name('proveedores.show');
// Redirecciones 301 de URLs antiguas
Route::get('/restauranteros', fn() => redirect('/proveedores', 301));
Route::get('/restauranteros/{id}', fn($id) => redirect('/proveedores/' . $id, 301));

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        if ($user->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }
        // Usuarios con active_role definen qué panel ver
        if ($user->active_role === 'proveedor' && $user->hasRole('restaurantero')) {
            return redirect()->route('restaurantero.panel');
        }
        return redirect()->route('user.dashboard');
    })->name('dashboard');

    // Cambio de rol dual
    Route::post('/switch-role', SwitchRoleController::class)->name('switch.role');

    // Completar perfil (Feature 7)
    Route::get('/completar-perfil',  [CompletarPerfilController::class, 'create'])->name('perfil.completar');
    Route::post('/completar-perfil', [CompletarPerfilController::class, 'store'])->name('perfil.completar.store');
    Route::post('/mi-panel/necesidades', [CompletarPerfilController::class, 'necesidades'])->name('perfil.necesidades');
    Route::post('/mi-panel/perfil',      [CompletarPerfilController::class, 'actualizarComprador'])->name('perfil.comprador.actualizar');
    Route::post('/perfil/agregar-rol',   [CompletarPerfilController::class, 'agregarRol'])->name('perfil.agregar-rol');

    // Registro al evento
    Route::post('/eventos/{evento}/registrar-comprador', [EventoRegistroController::class, 'registrarComprador'])->name('evento.registrar-comprador');
    Route::post('/eventos/{evento}/registrar-proveedor', [EventoRegistroController::class, 'registrarProveedor'])->name('evento.registrar-proveedor');

    // Rutas de usuario autenticado
    Route::get('/mi-panel', [CitaPublicaController::class, 'dashboard'])->name('user.dashboard');
    Route::post('/citas', [CitaPublicaController::class, 'store'])->name('citas.store')->middleware('throttle:10,1');
    Route::delete('/citas/{cita}', [CitaPublicaController::class, 'destroy'])->name('citas.destroy');
    Route::patch('/citas/{cita}/notas', [CitaPublicaController::class, 'actualizarNotas'])->name('citas.notas');
    Route::patch('/notificaciones/{notificacion}/leer', function (\App\Models\Notificacion $notificacion) {
        if ($notificacion->user_id !== auth()->id()) abort(403);
        $notificacion->update(['leida' => true]);
        return back();
    })->name('notificaciones.leer');

    // Completar perfil de proveedor
    Route::prefix('proveedor')->name('restaurantero.')->middleware('role:restaurantero')->group(function () {
        Route::get('/completar-perfil',  [ProveedorPerfilController::class, 'create'])->name('completar-perfil');
        Route::post('/completar-perfil', [ProveedorPerfilController::class, 'store'])->name('completar-perfil.store');
    });

    // Panel Proveedor
    Route::prefix('proveedor')->name('restaurantero.')->middleware('role:restaurantero')->group(function () {
        Route::get('/panel', [RestauranteroPanelController::class, 'index'])->name('panel');
        Route::get('/panel/eventos', [RestauranteroPanelController::class, 'eventos'])->name('panel.eventos');

        // Gestión de citas del proveedor
        Route::get('/citas', [RestauranteroCitasController::class, 'index'])->name('citas.index');
        Route::patch('/citas/{cita}/aceptar',   [RestauranteroCitasController::class, 'aceptar'])->name('citas.aceptar');
        Route::patch('/citas/{cita}/rechazar',  [RestauranteroCitasController::class, 'rechazar'])->name('citas.rechazar');
        Route::post('/citas/{cita}/reagendar',  [RestauranteroCitasController::class, 'reagendar'])->name('citas.reagendar');
    });

    // Admin routes
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

        Route::get('/restauranteros', [RestauranteroAdminController::class, 'index'])->name('restauranteros.index');
        Route::post('/restauranteros', [RestauranteroAdminController::class, 'store'])->name('restauranteros.store');
        Route::get('/restauranteros/{restaurantero}', [RestauranteroAdminController::class, 'show'])->name('restauranteros.show');
        Route::post('/restauranteros/{restaurantero}/update', [RestauranteroAdminController::class, 'update'])->name('restauranteros.update');
        Route::patch('/restauranteros/{restaurantero}/toggle', [RestauranteroAdminController::class, 'toggleActivo'])->name('restauranteros.toggle');
        Route::patch('/restauranteros/{restaurantero}/categoria', [RestauranteroAdminController::class, 'updateCategoria'])->name('restauranteros.update-categoria');
        Route::delete('/restauranteros/{restaurantero}', [RestauranteroAdminController::class, 'destroy'])->name('restauranteros.destroy');
        Route::post('/restauranteros/{restaurantero}/aprobar',  [RestauranteroAdminController::class, 'aprobar'])->name('restauranteros.aprobar');
        Route::post('/restauranteros/{restaurantero}/rechazar', [RestauranteroAdminController::class, 'rechazarAprobacion'])->name('restauranteros.rechazar-aprobacion');

        Route::get('/citas', [CitaAdminController::class, 'index'])->name('citas.index');
        Route::post('/citas', [CitaAdminController::class, 'store'])->name('citas.store');
        Route::patch('/citas/{cita}/estado', [CitaAdminController::class, 'updateEstado'])->name('citas.update-estado');

        Route::get('/usuarios', [UserAdminController::class, 'index'])->name('usuarios.index');
        Route::get('/clientes/{user}', [UserAdminController::class, 'show'])->name('clientes.show');
        Route::delete('/usuarios/{user}', [UserAdminController::class, 'destroy'])->name('usuarios.destroy');

        Route::get('/metricas', [MetricasController::class, 'index'])->name('metricas');

        Route::prefix('eventos')->name('eventos.')->group(function () {
            Route::get('/', [EventoController::class, 'index'])->name('index');
            Route::post('/', [EventoController::class, 'store'])->name('store');
            Route::post('/{evento}', [EventoController::class, 'update'])->name('update');
            Route::patch('/{evento}', [EventoController::class, 'update']);
            Route::post('/{evento}/archivar', [EventoController::class, 'archivar'])->name('archivar');
            Route::post('/{evento}/activar', [EventoController::class, 'activar'])->name('activar');
            Route::post('/{evento}/enviar-encuestas', [EventoController::class, 'enviarEncuestas'])->name('enviar-encuestas');
            Route::delete('/{evento}', [EventoController::class, 'destroy'])->name('destroy');

            // Solicitudes de registro al evento
            Route::get('/{evento}/solicitudes', [EventoSolicitudesController::class, 'index'])->name('solicitudes');
            Route::post('/{evento}/solicitudes/{user}/aprobar', [EventoSolicitudesController::class, 'aprobar'])->name('solicitudes.aprobar');
            Route::post('/{evento}/solicitudes/{user}/rechazar', [EventoSolicitudesController::class, 'rechazar'])->name('solicitudes.rechazar');
            Route::post('/{evento}/solicitudes/{user}/revertir', [EventoSolicitudesController::class, 'revertirPendiente'])->name('solicitudes.revertir');
            Route::post('/{evento}/solicitudes/{user}/eliminar', [EventoSolicitudesController::class, 'eliminar'])->name('solicitudes.eliminar');
            Route::post('/{evento}/solicitudes/aprobar-todos', [EventoSolicitudesController::class, 'aprobarTodos'])->name('solicitudes.aprobar-todos');
        });

        Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario');
        Route::get('/calendario/eventos', [CalendarioController::class, 'events'])->name('calendario.events');

        // Pantalla TV pública (redirige al token)
        Route::get('/tv', fn() => redirect()->route('tv.index', [
            'token' => \App\Http\Controllers\Admin\PantallaTvController::getToken()
        ]))->name('tv.index');

        // Torre de Control
        Route::get('/torre', [TorreControlController::class, 'index'])->name('torre.index');
        Route::get('/torre/estado', [TorreControlController::class, 'estado'])->name('torre.estado');
        Route::post('/torre/citas/{cita}/llamar',    [TorreControlController::class, 'llamar'])->name('torre.llamar');
        Route::post('/torre/citas/{cita}/repetir',   [TorreControlController::class, 'repetirAnuncio'])->name('torre.repetir');
        Route::post('/torre/citas/{cita}/iniciar',   [TorreControlController::class, 'iniciar'])->name('torre.iniciar');
        Route::post('/torre/citas/{cita}/finalizar', [TorreControlController::class, 'finalizar'])->name('torre.finalizar');
        Route::post('/torre/citas/{cita}/ausente',   [TorreControlController::class, 'ausente'])->name('torre.ausente');
        Route::post('/torre/citas/{cita}/retrasar',  [TorreControlController::class, 'retrasar'])->name('torre.retrasar');
        Route::post('/torre/citas/{cita}/mesa',      [TorreControlController::class, 'cambiarMesa'])->name('torre.mesa');

        // Encuestas de satisfacción (admin)
        Route::get('/encuestas',          [\App\Http\Controllers\Admin\EncuestaAdminController::class, 'index'])->name('encuestas.index');
        Route::get('/encuestas/exportar', [\App\Http\Controllers\Admin\EncuestaAdminController::class, 'exportar'])->name('encuestas.exportar');
        Route::post('/encuestas/enviar-evento', [\App\Http\Controllers\Admin\EncuestaAdminController::class, 'enviarParaEvento'])->name('encuestas.enviar-evento');
        Route::post('/encuestas/enviar-prueba', [\App\Http\Controllers\Admin\EncuestaAdminController::class, 'enviarPrueba'])->name('encuestas.enviar-prueba');
        Route::get('/encuestas/plantillas',     [\App\Http\Controllers\Admin\EncuestaAdminController::class, 'plantillas'])->name('encuestas.plantillas');
        Route::post('/encuestas/plantillas',    [\App\Http\Controllers\Admin\EncuestaAdminController::class, 'guardarPlantilla'])->name('encuestas.plantillas.guardar');
        Route::post('/encuestas/plantillas/{plantilla}/activar',  [\App\Http\Controllers\Admin\EncuestaAdminController::class, 'activarPlantilla'])->name('encuestas.plantillas.activar');
        Route::delete('/encuestas/plantillas/{plantilla}', [\App\Http\Controllers\Admin\EncuestaAdminController::class, 'eliminarPlantilla'])->name('encuestas.plantillas.eliminar');

        // ── Módulos exclusivos del super-administrador ──────────────────────────
        Route::middleware('super-admin')->group(function () {
            Route::prefix('usuarios-gestion')->name('usuarios-gestion.')->group(function () {
                Route::get('/',                             [UsuariosGestionController::class, 'index'])->name('index');
                Route::get('/{user}/edit',                  [UsuariosGestionController::class, 'edit'])->name('edit');
                Route::put('/{user}',                       [UsuariosGestionController::class, 'update'])->name('update');
                Route::post('/{user}/password',             [UsuariosGestionController::class, 'updatePassword'])->name('password');
                Route::post('/{user}/roles',                [UsuariosGestionController::class, 'updateRoles'])->name('roles');
                Route::delete('/{user}',                    [UsuariosGestionController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('plantillas-correo')->name('plantillas.')->group(function () {
                Route::get('/',                             [PlantillasCorreoController::class, 'index'])->name('index');
                Route::post('/',                            [PlantillasCorreoController::class, 'store'])->name('store');
                Route::get('/crear',                        [PlantillasCorreoController::class, 'create'])->name('create');
                Route::get('/{plantilla}/edit',             [PlantillasCorreoController::class, 'edit'])->name('edit');
                Route::put('/{plantilla}',                  [PlantillasCorreoController::class, 'update'])->name('update');
                Route::delete('/{plantilla}',               [PlantillasCorreoController::class, 'destroy'])->name('destroy');
                Route::post('/{plantilla}/enviar',          [PlantillasCorreoController::class, 'enviar'])->name('enviar');
                Route::patch('/{plantilla}/toggle',         [PlantillasCorreoController::class, 'toggle'])->name('toggle');
                Route::post('/{plantilla}/restablecer',     [PlantillasCorreoController::class, 'restablecer'])->name('restablecer');
            });

            Route::prefix('correo-masivo')->name('correo-masivo.')->group(function () {
                Route::get('/',                             [CorreoMasivoController::class, 'index'])->name('index');
                Route::post('/preview',                     [CorreoMasivoController::class, 'preview'])->name('preview');
                Route::post('/prueba',                      [CorreoMasivoController::class, 'prueba'])->name('prueba');
                Route::post('/enviar',                      [CorreoMasivoController::class, 'enviar'])->name('enviar');
            });

            Route::prefix('categorias')->name('categorias.')->group(function () {
                Route::get('/',               [CategoriasController::class, 'index'])->name('index');
                Route::post('/',              [CategoriasController::class, 'store'])->name('store');
                Route::put('/{categoria}',    [CategoriasController::class, 'update'])->name('update');
                Route::patch('/{categoria}/toggle', [CategoriasController::class, 'toggle'])->name('toggle');
                Route::delete('/{categoria}', [CategoriasController::class, 'destroy'])->name('destroy');
                Route::post('/reordenar',     [CategoriasController::class, 'reordenar'])->name('reordenar');
            });

            Route::prefix('publicidad')->name('publicidad.')->group(function () {
                Route::get('/',                             [PublicidadController::class, 'index'])->name('index');
                Route::get('/crear',                        [PublicidadController::class, 'create'])->name('create');
                Route::post('/',                            [PublicidadController::class, 'store'])->name('store');
                Route::get('/{publicidad}/edit',            [PublicidadController::class, 'edit'])->name('edit');
                Route::post('/{publicidad}',                [PublicidadController::class, 'update'])->name('update');
                Route::patch('/{publicidad}/toggle',        [PublicidadController::class, 'toggleActiva'])->name('toggle');
                Route::delete('/{publicidad}',              [PublicidadController::class, 'destroy'])->name('destroy');
            });
        });

        // Exportaciones Excel
        Route::get('/exportar',              [\App\Http\Controllers\Admin\ExportController::class, 'index'])->name('exportar.index');
        Route::get('/exportar/evento/{evento}', [\App\Http\Controllers\Admin\ExportController::class, 'eventoCompleto'])->name('exportar.evento');
        Route::get('/exportar/evento-completo', [\App\Http\Controllers\Admin\ExportController::class, 'eventoCompletoActivo'])->name('exportar.evento-completo');
        Route::get('/exportar/compradores', [\App\Http\Controllers\Admin\ExportController::class, 'compradores'])->name('exportar.compradores');
        Route::get('/exportar/proveedores', [\App\Http\Controllers\Admin\ExportController::class, 'proveedores'])->name('exportar.proveedores');
        Route::get('/exportar/citas',       [\App\Http\Controllers\Admin\ExportController::class, 'citas'])->name('exportar.citas');
    });
});
