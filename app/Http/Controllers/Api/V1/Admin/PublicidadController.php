<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminPublicidadResource;
use App\Models\Publicidad;
use App\Services\Admin\PublicidadAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Publicidad (popup de la portada) SOLO super-admin (web: /admin/publicidad).
 * Mismas reglas (jpg/jpeg/png/webp, max 4 MB, fecha_fin > fecha_inicio).
 * Para subir la imagen usar POST (multipart).
 */
class PublicidadController extends Controller
{
    public function __construct(private PublicidadAdminService $publicidad) {}

    public function index()
    {
        Gate::authorize('super-admin');
        $lista = $this->publicidad->listar();

        return AdminPublicidadResource::collection($lista)->additional(['meta' => [
            'total'   => $lista->count(),
            // La que hoy ve la portada (la vigente mas reciente), si hay.
            'vigente_id' => Publicidad::vigente()->value('id'),
        ]]);
    }

    public function show(Publicidad $publicidad)
    {
        Gate::authorize('super-admin');

        return new AdminPublicidadResource($publicidad);
    }

    public function store(Request $request)
    {
        Gate::authorize('super-admin');
        $p = $this->publicidad->crear($request, $request->validate($this->publicidad->reglas()));

        return (new AdminPublicidadResource($p->fresh()))
            ->additional(['message' => 'Anuncio creado correctamente.'])
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, Publicidad $publicidad)
    {
        Gate::authorize('super-admin');
        $this->publicidad->actualizar($publicidad, $request, $request->validate($this->publicidad->reglas()));

        return (new AdminPublicidadResource($publicidad->fresh()))->additional(['message' => 'Anuncio actualizado correctamente.']);
    }

    /** Activa / desactiva (web: toggle). */
    public function activa(Publicidad $publicidad)
    {
        Gate::authorize('super-admin');
        $this->publicidad->alternar($publicidad);

        return (new AdminPublicidadResource($publicidad))
            ->additional(['message' => 'Anuncio ' . ($publicidad->activa ? 'activado' : 'desactivado') . '.']);
    }

    public function destroy(Publicidad $publicidad)
    {
        Gate::authorize('super-admin');
        $this->publicidad->eliminar($publicidad);

        return response()->json(['data' => null, 'message' => 'Anuncio eliminado.']);
    }
}
