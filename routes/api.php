<?php

use App\Http\Controllers\NotificacionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Notificaciones
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notificaciones',                      [NotificacionController::class, 'index']);
    Route::patch('/notificaciones/{notificacion}/leer',[NotificacionController::class, 'marcarLeida']);
    Route::post('/notificaciones/leer-todas',          [NotificacionController::class, 'marcarTodasLeidas']);
});
