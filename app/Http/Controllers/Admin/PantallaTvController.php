<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PantallaTvService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Los datos los arma PantallaTvService (compartido con la API v1 del admin). */
class PantallaTvController extends Controller
{
    public function __construct(private PantallaTvService $tv) {}

    /**
     * El token vive en la fila del evento, no en una constante del codigo.
     * Antes era un literal publicado en DEPLOY.md y en el repo.
     */
    public static function getToken(): string
    {
        return \App\Models\Evento::contextoAdmin()?->tokenTv() ?? '';
    }

    public function index(Request $request, string $token)
    {
        $evento = $this->tv->eventoPorToken($token);
        abort_if(!$evento, 404);
        return Inertia::render('Admin/PantallaTv', [
            'tvToken' => $token,
        ]);
    }

    /** Datos públicos para la Pantalla Pública (polling 3s) */
    public function datosPublicos(Request $request, string $token)
    {
        $evento = $this->tv->eventoPorToken($token);
        abort_if(!$evento, 404);

        return response()->json($this->tv->datos($evento));
    }
}
