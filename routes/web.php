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
use App\Http\Controllers\Admin\TorreControlController;
use App\Http\Controllers\RestauranteroPanelController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\SeleccionarRolController;
use App\Http\Controllers\Auth\SwitchRoleController;
use App\Http\Controllers\CompletarPerfilController;
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

// Rutas públicas
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/restauranteros', [RestauranteroPublicoController::class, 'index'])->name('restauranteros.index');
Route::get('/restauranteros/{restaurantero}', [RestauranteroPublicoController::class, 'show'])->name('restauranteros.show');

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
            Route::post('/{evento}/archivar', [EventoController::class, 'archivar'])->name('archivar');
            Route::post('/{evento}/activar', [EventoController::class, 'activar'])->name('activar');
            Route::delete('/{evento}', [EventoController::class, 'destroy'])->name('destroy');
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

        // Exportaciones Excel
        Route::get('/exportar',              [\App\Http\Controllers\Admin\ExportController::class, 'index'])->name('exportar.index');
        Route::get('/exportar/evento-completo', [\App\Http\Controllers\Admin\ExportController::class, 'eventoCompleto'])->name('exportar.evento-completo');
        Route::get('/exportar/compradores', [\App\Http\Controllers\Admin\ExportController::class, 'compradores'])->name('exportar.compradores');
        Route::get('/exportar/proveedores', [\App\Http\Controllers\Admin\ExportController::class, 'proveedores'])->name('exportar.proveedores');
        Route::get('/exportar/citas',       [\App\Http\Controllers\Admin\ExportController::class, 'citas'])->name('exportar.citas');
    });
});
