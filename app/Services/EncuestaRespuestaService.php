<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\EncuestaRespuesta;
use App\Models\EncuestaSatisfaccion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Respuesta a una encuesta de satisfaccion. La usan EncuestaController (enlace
 * del correo, por token) y la API v1 (/encuestas/{id}/respuestas).
 */
class EncuestaRespuestaService
{
    /** Preguntas de la plantilla de la encuesta, o las de config/encuestas.php. */
    public function preguntas(EncuestaSatisfaccion $encuesta): array
    {
        return $encuesta->plantilla?->preguntas ?? config('encuestas.preguntas');
    }

    /**
     * Reglas por tipo de pregunta. Sin esto se aceptaba texto de tamano
     * ilimitado y un array inesperado tumbaba la peticion con un 500 por
     * "Array to string conversion".
     * Los 6 tipos que renderiza Encuestas/Responder.vue son:
     * escala, nps, binario, texto, opciones y multiple.
     */
    public function reglas(array $preguntas): array
    {
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

        return $reglas;
    }

    /**
     * Valida y guarda las respuestas ($entrada: pregunta.id => valor).
     *
     * $exigirRequeridas: aplica en el servidor la regla que en la web solo
     * aplica Responder.vue (pregunta visible segun su condicion y sin
     * "requerida: false" => obligatoria). La web por token no la pide.
     *
     * @throws ReglaDeNegocioException si ya estaba respondida
     * @throws ValidationException
     */
    public function responder(EncuestaSatisfaccion $encuesta, array $entrada, bool $exigirRequeridas = false): void
    {
        if ($encuesta->completada()) {
            throw new ReglaDeNegocioException('Esta encuesta ya fue respondida.');
        }

        $preguntas = $this->preguntas($encuesta);
        $datos = Validator::make($entrada, $this->reglas($preguntas))->validate();

        if ($exigirRequeridas) {
            $faltan = [];
            foreach ($preguntas as $pregunta) {
                if ($this->esObligatoria($pregunta, $entrada) && $this->vacia($entrada[$pregunta['id']] ?? null)) {
                    $faltan[$pregunta['id']] = 'Este campo es requerido.';
                }
            }
            if ($faltan) {
                throw ValidationException::withMessages($faltan);
            }
        }

        DB::transaction(function () use ($encuesta, $preguntas, $datos) {
            // Dos envios simultaneos: solo el primero guarda.
            $fila = EncuestaSatisfaccion::whereKey($encuesta->id)->lockForUpdate()->first();
            if ($fila->completada_at !== null) {
                throw new ReglaDeNegocioException('Esta encuesta ya fue respondida.');
            }

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
        });
    }

    /** preguntaVisible() + requerida de Encuestas/Responder.vue. */
    public function esObligatoria(array $pregunta, array $entrada): bool
    {
        return ($pregunta['requerida'] ?? true) !== false && $this->esVisible($pregunta, $entrada);
    }

    public function esVisible(array $pregunta, array $entrada): bool
    {
        $condicion = $pregunta['condicion'] ?? null;
        if (empty($condicion['pregunta_id'])) {
            return true;
        }

        $fuente = $entrada[$condicion['pregunta_id']] ?? null;
        if ($this->vacia($fuente)) {
            return false;
        }

        $rf = is_array($fuente) ? implode(',', $fuente) : (string) $fuente;
        $vl = (string) ($condicion['valor'] ?? '');

        return match ($condicion['operador'] ?? null) {
            'igual'     => $rf === $vl,
            'diferente' => $rf !== $vl,
            'mayor'     => (float) $rf > (float) $vl,
            'menor'     => (float) $rf < (float) $vl,
            default     => true,
        };
    }

    private function vacia(mixed $valor): bool
    {
        return $valor === null || $valor === '' || $valor === [];
    }
}
