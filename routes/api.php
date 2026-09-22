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

    Route::middleware(['auth:sanctum', 'throttle:api-v1'])->group(function () {
        Route::get('/auth/me',            [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);
        Route::post('/auth/logout',       [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
        Route::post('/auth/logout-todos', [\App\Http\Controllers\Api\V1\AuthController::class, 'logoutTodos']);

        Route::get('/eventos',                    [\App\Http\Controllers\Api\V1\EventoController::class, 'index']);
        Route::get('/eventos/{evento}',           [\App\Http\Controllers\Api\V1\EventoController::class, 'show']);
        Route::post('/eventos/{evento}/registro', [\App\Http\Controllers\Api\V1\EventoController::class, 'registrar'])
            ->middleware('throttle:10,1');
    });
});
