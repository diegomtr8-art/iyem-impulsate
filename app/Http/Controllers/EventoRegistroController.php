<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Services\RegistroEventoService;
use Illuminate\Http\Request;

/**
 * Las reglas viven en RegistroEventoService. Una ReglaDeNegocioException se
 * convierte en back()->withErrors(['error' => ...]) en bootstrap/app.php.
 */
class EventoRegistroController extends Controller
{
    public function __construct(private RegistroEventoService $registro) {}

    public function registrarComprador(Request $request, Evento $evento)
    {
        $resultado = $this->registro->registrarComprador($request->user(), $evento);

        return back()->with('success', $resultado['mensaje']);
    }

    public function registrarProveedor(Request $request, Evento $evento)
    {
        $resultado = $this->registro->registrarProveedor($request->user(), $evento);

        return back()->with('success', $resultado['mensaje']);
    }

    public function registrarBazar(Request $request, Evento $evento)
    {
        $resultado = $this->registro->registrarExpositor($request->user(), $evento);

        return back()->with('success', $resultado['mensaje']);
    }
}
