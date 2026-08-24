<?php

namespace App\Http\Controllers;

use App\Http\Controllers\CompletarPerfilController;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserPerfilController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('restaurantero');

        return Inertia::render('Perfil/Show', [
            'user'          => $user,
            'restaurantero' => $user->restaurantero,
            'municipios'    => collect(CompletarPerfilController::MUNICIPIOS)->sort()->values(),
            'categorias'    => \App\Models\Restaurantero::categoriasActivas(),
        ]);
    }
}
