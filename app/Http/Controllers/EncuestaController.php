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
        $encuesta = EncuestaSatisfaccion::with(['evento', 'user', 'plantilla'])
            ->where('token', $token)
            ->firstOrFail();

        if ($encuesta->completada()) {
            return Inertia::render('Encuestas/Gracias', [
                'evento' => $encuesta->evento?->only(['nombre']),
            ]);
        }

        $preguntas = $encuesta->plantilla?->preguntas ?? config('encuestas.preguntas');

        return Inertia::render('Encuestas/Responder', [
            'encuesta'  => $encuesta->only(['id', 'tipo', 'token']),
            'evento'    => $encuesta->evento?->only(['nombre', 'sector_economico']),
            'usuario'   => $encuesta->user?->only(['name']),
            'preguntas' => $preguntas,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $encuesta = EncuestaSatisfaccion::with('plantilla')->where('token', $token)->firstOrFail();

        if ($encuesta->completada()) {
            return back()->withErrors(['error' => 'Esta encuesta ya fue respondida.']);
        }

        $preguntas = $encuesta->plantilla?->preguntas ?? config('encuestas.preguntas');

        foreach ($preguntas as $pregunta) {
            $respuesta = $request->input($pregunta['id']);

            if ($pregunta['tipo'] === 'multiple' && is_array($respuesta)) {
                $respuesta = implode(', ', array_filter($respuesta));
            }

            if ($respuesta !== null && $respuesta !== '' && $respuesta !== []) {
                EncuestaRespuesta::create([
                    'encuesta_satisfaccion_id' => $encuesta->id,
                    'pregunta'                 => $pregunta['texto'],
                    'tipo'                     => $pregunta['tipo'],
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
