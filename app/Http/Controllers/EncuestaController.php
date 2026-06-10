<?php

namespace App\Http\Controllers;

use App\Models\EncuestaSatisfaccion;
use App\Models\EncuestaRespuesta;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EncuestaController extends Controller
{
    public function show(string $token)
    {
        $encuesta = EncuestaSatisfaccion::with(['evento', 'user'])
            ->where('token', $token)
            ->firstOrFail();

        if ($encuesta->completada()) {
            return Inertia::render('Encuestas/Gracias', [
                'evento' => $encuesta->evento?->only(['nombre']),
            ]);
        }

        return Inertia::render('Encuestas/Responder', [
            'encuesta'  => $encuesta->only(['id', 'tipo', 'token']),
            'evento'    => $encuesta->evento?->only(['nombre', 'sector_economico']),
            'usuario'   => $encuesta->user?->only(['name']),
            'preguntas' => config('encuestas.preguntas'),
        ]);
    }

    public function store(Request $request, string $token)
    {
        $encuesta = EncuestaSatisfaccion::where('token', $token)->firstOrFail();

        if ($encuesta->completada()) {
            return back()->withErrors(['error' => 'Esta encuesta ya fue respondida.']);
        }

        $preguntas = config('encuestas.preguntas');

        foreach ($preguntas as $pregunta) {
            $respuesta = $request->input($pregunta['id']);
            if ($respuesta !== null && $respuesta !== '') {
                EncuestaRespuesta::create([
                    'encuesta_satisfaccion_id' => $encuesta->id,
                    'pregunta'                 => $pregunta['texto'],
                    'respuesta'                => (string) $respuesta,
                ]);
            }
        }

        $encuesta->update(['completada_at' => now()]);

        return Inertia::render('Encuestas/Gracias', [
            'evento' => $encuesta->evento?->only(['nombre']),
        ]);
    }
}
