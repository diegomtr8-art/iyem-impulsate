<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AvisoAceptacionController extends Controller
{
    public function __construct(private OnboardingService $onboarding) {}

    public function show()
    {
        if (auth()->user()->acepta_aviso_at) {
            return redirect()->intended(route('dashboard'));
        }

        return Inertia::render('Auth/AceptarAviso');
    }

    public function store(Request $request)
    {
        $request->validate(OnboardingService::reglasAviso(), OnboardingService::mensajesAviso());

        $this->onboarding->aceptarAviso($request->user());

        if (!$request->user()->rol_seleccionado && !$request->user()->isAdmin()) {
            return redirect()->route('rol.seleccionar');
        }

        return redirect()->intended(route('dashboard'));
    }
}
