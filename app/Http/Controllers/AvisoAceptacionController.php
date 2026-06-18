<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class AvisoAceptacionController extends Controller
{
    public function show()
    {
        if (auth()->user()->acepta_aviso_at) {
            return redirect()->intended(route('dashboard'));
        }

        return Inertia::render('Auth/AceptarAviso');
    }

    public function store(Request $request)
    {
        $request->validate([
            'acepta' => ['required', 'accepted'],
        ], [
            'acepta.required' => 'Debes aceptar el Aviso de Privacidad para continuar.',
            'acepta.accepted' => 'Debes aceptar el Aviso de Privacidad para continuar.',
        ]);

        $request->user()->update(['acepta_aviso_at' => now()]);

        if (!$request->user()->rol_seleccionado && !$request->user()->isAdmin()) {
            return redirect()->route('rol.seleccionar');
        }

        return redirect()->intended(route('dashboard'));
    }
}
