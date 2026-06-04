<?php

namespace App\Http\Controllers;

use App\Models\Restaurantero;
use Inertia\Inertia;

class LandingController extends Controller
{
    public function index()
    {
        $restauranteros = Restaurantero::where('activo', true)
            ->withCount('citas')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        return Inertia::render('Welcome', [
            'restauranteros' => $restauranteros,
            'canLogin'       => \Illuminate\Support\Facades\Route::has('login'),
            'canRegister'    => \Illuminate\Support\Facades\Route::has('register'),
        ]);
    }
}
