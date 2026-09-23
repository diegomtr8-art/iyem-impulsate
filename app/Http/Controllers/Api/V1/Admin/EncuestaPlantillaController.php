<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\EncuestaPlantillaResource;
use App\Models\EncuestaPlantilla;
use App\Models\EncuestaSatisfaccion;
use App\Services\Admin\EncuestasAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Plantillas de preguntas de encuesta (web: /admin/encuestas/plantillas).
 * Mismas reglas que la web (EncuestasAdminService::REGLAS_PLANTILLA): tipos
 * escala|texto|binario|opciones|multiple|nps, condicion por pregunta_id.
 */
class EncuestaPlantillaController extends Controller
{
    public function __construct(private EncuestasAdminService $encuestas) {}

    public function index()
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $lista = $this->encuestas->plantillas();

        return EncuestaPlantillaResource::collection($lista)->additional(['meta' => [
            'total'           => $lista->count(),
            'segmentos'       => EncuestasAdminService::SEGMENTOS_ENVIO,
            'tipos_pregunta'  => EncuestasAdminService::TIPOS_PREGUNTA,
        ]]);
    }

    public function show(EncuestaPlantilla $plantilla)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);

        return (new EncuestaPlantillaResource($plantilla))->conPreguntas();
    }

    public function store(Request $request)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $data = $request->validate(Arr::except(EncuestasAdminService::REGLAS_PLANTILLA, ['id']));

        $p = $this->encuestas->guardarPlantilla($data);

        return (new EncuestaPlantillaResource($p->fresh()))->conPreguntas()
            ->additional(['message' => 'Plantilla guardada correctamente.'])
            ->response()->setStatusCode(201);
    }

    /** Reemplaza nombre, descripcion, segmento y preguntas (la web guarda todo el formulario). */
    public function update(Request $request, EncuestaPlantilla $plantilla)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $data = $request->validate(Arr::except(EncuestasAdminService::REGLAS_PLANTILLA, ['id']));

        $p = $this->encuestas->guardarPlantilla(array_merge($data, ['id' => $plantilla->id]));

        return (new EncuestaPlantillaResource($p->fresh()))->conPreguntas()
            ->additional(['message' => 'Plantilla guardada correctamente.']);
    }

    public function activar(EncuestaPlantilla $plantilla)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $this->encuestas->activarPlantilla($plantilla);

        return (new EncuestaPlantillaResource($plantilla->fresh()))
            ->additional(['message' => "Plantilla \"{$plantilla->nombre}\" activada. Se usará en los próximos envíos."]);
    }

    public function destroy(EncuestaPlantilla $plantilla)
    {
        Gate::authorize('administrar', EncuestaSatisfaccion::class);
        $this->encuestas->eliminarPlantilla($plantilla);

        return response()->json(['data' => null, 'message' => 'Plantilla eliminada.']);
    }
}
