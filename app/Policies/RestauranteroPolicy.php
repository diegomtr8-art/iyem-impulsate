<?php

namespace App\Policies;

use App\Models\Restaurantero;
use App\Models\User;
use App\Services\DirectorioProveedoresService;
use Illuminate\Auth\Access\Response;

/** Ficha publica del proveedor: misma regla que /proveedores/{id} (404, no 403, si no se puede ver). */
class RestauranteroPolicy
{
    public function view(?User $user, Restaurantero $restaurantero): Response
    {
        return app(DirectorioProveedoresService::class)->puedeVer($user, $restaurantero)
            ? Response::allow()
            : Response::denyAsNotFound('Proveedor no encontrado.');
    }
}
