<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PlantillaCorreoResource;
use App\Models\PlantillaCorreo;
use App\Services\Admin\PlantillasCorreoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Plantillas de correo SOLO super-admin (web: /admin/plantillas-correo,
 * middleware 'super-admin'). PlantillasCorreoService.
 */
class PlantillaCorreoController extends Controller
{
    public function __construct(private PlantillasCorreoService $plantillas) {}

    public function index()
    {
        Gate::authorize('super-admin');
        $lista = $this->plantillas->listar();

        return PlantillaCorreoResource::collection($lista)->additional(['meta' => [
            'total'              => $lista->count(),
            'tipos_destinatario' => PlantillasCorreoService::TIPOS_DESTINATARIO,
        ]]);
    }

    /** HTML base y variables disponibles para una plantilla nueva (web: /crear). */
    public function nueva()
    {
        Gate::authorize('super-admin');

        return response()->json(['data' => [
            'html_base'    => PlantillasCorreoService::HTML_BASE,
            'placeholders' => $this->placeholders(PlantillasCorreoService::placeholdersPlanos()),
        ]]);
    }

    public function show(PlantillaCorreo $plantilla)
    {
        Gate::authorize('super-admin');

        return (new PlantillaCorreoResource($plantilla))->conContenido()->additional(['meta' => [
            'placeholders' => $this->placeholders($this->plantillas->placeholdersParaClave($plantilla->clave)),
        ]]);
    }

    public function store(Request $request)
    {
        Gate::authorize('super-admin');
        $p = $this->plantillas->crear($request->validate(PlantillasCorreoService::reglasCrear()));

        return (new PlantillaCorreoResource($p->fresh()))->conContenido()
            ->additional(['message' => 'Plantilla creada correctamente.'])
            ->response()->setStatusCode(201);
    }

    /** Solo asunto y contenido (igual que la web; nombre, clave y tipo no se editan). */
    public function update(Request $request, PlantillaCorreo $plantilla)
    {
        Gate::authorize('super-admin');
        $this->plantillas->actualizar($plantilla, $request->validate(PlantillasCorreoService::REGLAS_EDITAR));

        return (new PlantillaCorreoResource($plantilla->fresh()))->conContenido()
            ->additional(['message' => 'Plantilla actualizada correctamente.']);
    }

    /** Activa / desactiva (web: toggle). */
    public function activo(PlantillaCorreo $plantilla)
    {
        Gate::authorize('super-admin');
        $this->plantillas->alternar($plantilla);

        return (new PlantillaCorreoResource($plantilla))
            ->additional(['message' => 'Plantilla ' . ($plantilla->activo ? 'activada' : 'desactivada') . '.']);
    }

    public function destroy(PlantillaCorreo $plantilla)
    {
        Gate::authorize('super-admin');
        $this->plantillas->eliminar($plantilla);

        return response()->json(['data' => null, 'message' => 'Plantilla eliminada.']);
    }

    /** Corre PlantillasCorreoSeeder: vuelve a crear las plantillas de sistema que falten. */
    public function restablecer()
    {
        Gate::authorize('super-admin');
        $this->plantillas->restablecer();

        return response()->json([
            'data'    => ['total' => PlantillaCorreo::count()],
            'message' => 'Todas las plantillas restablecidas a sus valores por defecto.',
        ]);
    }

    /** Envia la plantilla (sin variables) a los correos indicados, en cola. */
    public function enviar(Request $request, PlantillaCorreo $plantilla)
    {
        Gate::authorize('super-admin');
        $d = $request->validate(PlantillasCorreoService::REGLAS_ENVIAR);

        $n = $this->plantillas->enviar($plantilla, $d['destinatarios']);

        return response()->json(['data' => ['encolados' => $n], 'message' => "Correo enviado a {$n} destinatario(s). (Procesando en cola)"]);
    }

    /** HTML del correo tal como se enviaria, con variables de ejemplo. */
    public function vistaPrevia(PlantillaCorreo $plantilla)
    {
        Gate::authorize('super-admin');

        return response()->json(['data' => $this->plantillas->vistaPrevia($plantilla->asunto, $plantilla->contenido)]);
    }

    /** Vista previa de un borrador sin guardarlo (formulario de alta/edicion). */
    public function vistaPreviaBorrador(Request $request)
    {
        Gate::authorize('super-admin');
        $d = $request->validate(PlantillasCorreoService::REGLAS_EDITAR);

        return response()->json(['data' => $this->plantillas->vistaPrevia($d['asunto'], $d['contenido'])]);
    }

    private function placeholders(array $mapa): array
    {
        return collect($mapa)->map(fn ($desc, $clave) => ['clave' => $clave, 'descripcion' => $desc])->values()->all();
    }
}
