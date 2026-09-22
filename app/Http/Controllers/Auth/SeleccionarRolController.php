<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SeleccionarRolController extends Controller
{
    public function __construct(private OnboardingService $onboarding) {}

    public function create()
    {
        return Inertia::render('Auth/SeleccionarRol');
    }

    public function store(Request $request)
    {
        $request->validate([
            'rol' => 'required|in:' . implode(',', OnboardingService::ROLES_SELECCIONABLES),
        ]);

        $this->onboarding->seleccionarRol($request->user(), $request->rol);

        return redirect()->route('dashboard')->with('success', '¡Bienvenido a Impulsate!');
    }
}
