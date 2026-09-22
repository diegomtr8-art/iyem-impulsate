<?php

namespace App\Http\Controllers;

use App\Models\EncuestaSatisfaccion;
use App\Services\EncuestaRespuestaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EncuestaController extends Controller
{
    public function show(string $token, EncuestaRespuestaService $respuestas)
    {
        $encuesta = EncuestaSatisfaccion::with(['evento', 'user', 'plantilla'])
            ->where('token', $token)
            ->firstOrFail();

        if ($encuesta->completada()) {
            return Inertia::render('Encuestas/Gracias', [
                'evento' => $encuesta->evento?->only(['nombre']),
            ]);
        }

        $preguntas = $respuestas->preguntas($encuesta);

        return Inertia::render('Encuestas/Responder', [
            'encuesta'  => $encuesta->only(['id', 'tipo', 'token']),
            'evento'    => $encuesta->evento?->only(['nombre', 'sector_economico']),
            'usuario'   => $encuesta->user?->only(['name']),
            'preguntas' => $preguntas,
        ]);
    }

    public function store(Request $request, string $token, EncuestaRespuestaService $respuestas)
    {
        $encuesta = EncuestaSatisfaccion::with('plantilla')->where('token', $token)->firstOrFail();

        // Mismo camino que la app (POST /api/v1/encuestas/{id}/respuestas). Si
        // ya estaba respondida, ReglaDeNegocioException -> withErrors('error').
        $respuestas->responder($encuesta, $request->all());

        return Inertia::render('Encuestas/Gracias', [
            'evento' => $encuesta->evento?->only(['nombre']),
        ]);
    }
}
