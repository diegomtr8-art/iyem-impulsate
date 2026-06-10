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
