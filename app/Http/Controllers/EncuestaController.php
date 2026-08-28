<?php

namespace App\Http\Controllers;

use App\Models\EncuestaSatisfaccion;
use App\Models\EncuestaRespuesta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        // Reglas por tipo de pregunta. Sin esto se aceptaba texto de tamano
        // ilimitado y un array inesperado tumbaba la peticion con un 500 por
        // "Array to string conversion".
        // Los 6 tipos que renderiza Encuestas/Responder.vue son:
        // escala, nps, binario, texto, opciones y multiple.
        $reglas = [];
        foreach ($preguntas as $pregunta) {
            $id       = $pregunta['id'];
            $opciones = $pregunta['opciones'] ?? null;

            switch ($pregunta['tipo']) {
                case 'escala':
                    // El rango es por pregunta, no fijo 1-5.
                    $reglas[$id] = [
                        'nullable', 'integer',
                        'min:' . ($pregunta['escala_min'] ?? 1),
                        'max:' . ($pregunta['escala_max'] ?? 5),
                    ];
                    break;
                case 'nps':
                    $reglas[$id] = ['nullable', 'integer', 'min:0', 'max:10'];
                    break;
                case 'binario':
                    $reglas[$id] = ['nullable', Rule::in(['Si', 'Sí', 'No'])];
                    break;
                case 'opciones':
                    $reglas[$id] = $opciones
                        ? ['nullable', Rule::in($opciones)]
                        : ['nullable', 'string', 'max:255'];
                    break;
                case 'multiple':
                    $reglas[$id]        = ['nullable', 'array', 'max:20'];
                    $reglas[$id . '.*'] = $opciones
                        ? [Rule::in($opciones)]
                        : ['string', 'max:255'];
                    break;
                default:
                    $reglas[$id] = ['nullable', 'string', 'max:2000'];
            }
        }

        $datos = $request->validate($reglas);

        foreach ($preguntas as $pregunta) {
            $respuesta = $datos[$pregunta['id']] ?? null;

            if (is_array($respuesta)) {
                $respuesta = implode(', ', array_filter($respuesta));
            }

            if ($respuesta === null || $respuesta === '' || $respuesta === []) {
                continue;
            }

            EncuestaRespuesta::create([
                'encuesta_satisfaccion_id' => $encuesta->id,
                'pregunta'                 => $pregunta['texto'],
                'tipo'                     => $pregunta['tipo'],
                'respuesta'                => (string) $respuesta,
            ]);
        }

        $encuesta->update(['completada_at' => now()]);

        return Inertia::render('Encuestas/Gracias', [
            'evento' => $encuesta->evento?->only(['nombre']),
        ]);
    }
}
