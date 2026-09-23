<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminEventoResource;
use App\Models\Evento;
use App\Services\Admin\AdminEventosService;
use App\Services\Admin\PantallaTvService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Eventos desde el panel admin: mismas reglas, imagenes, criterios y
 * "un solo destacado" que la web (AdminEventosService). Para subir imagenes
 * usar POST (multipart); PUT/PATCH solo sirve sin archivos.
 */
class EventoController extends Controller
{
    public function __construct(private AdminEventosService $eventos) {}

    public function index(Request $request)
    {
        Gate::authorize('administrar', Evento::class);

        $datos = $request->validate([
            'estado'      => ['nullable', Rule::in(['activo', 'inactivo', 'finalizado'])],
            'tipo_evento' => ['nullable', Rule::in(AdminEventosService::TIPOS)],
        ]);

        $lista = $this->eventos->listar()
            ->when($datos['tipo_evento'] ?? null, fn ($c, $t) => $c->filter(fn ($e) => ($e->tipo_evento ?? 'encuentro_negocios') === $t))
            ->values();

        $recursos = AdminEventoResource::collection($lista)->resolve($request);

        if ($estado = $datos['estado'] ?? null) {
            $recursos = array_values(array_filter($recursos, fn ($e) => $e['estado'] === $estado));
        }

        return response()->json(['data' => $recursos, 'meta' => ['total' => count($recursos)]]);
    }

    public function show(Evento $evento)
    {
        Gate::authorize('administrar', $evento);

        return new AdminEventoResource($this->eventos->detalle($evento));
    }

    public function store(Request $request)
    {
        Gate::authorize('administrar', Evento::class);

        $this->eventos->validar($request, 'crear');
        $evento = $this->eventos->crear($request);

        return (new AdminEventoResource($this->eventos->detalle($evento->fresh())))
            ->additional(['message' => 'Evento creado. Actívalo cuando estés listo.'])
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, Evento $evento)
    {
        Gate::authorize('administrar', $evento);

        $this->eventos->validar($request, 'editar');
        $this->eventos->actualizar($evento, $request);

        return $this->respuesta($evento, 'Evento actualizado correctamente.');
    }

    public function activar(Evento $evento)
    {
        Gate::authorize('administrar', $evento);
        $this->eventos->activar($evento);

        return $this->respuesta($evento, 'Evento "' . $evento->nombre . '" activado.');
    }

    public function archivar(Evento $evento)
    {
        Gate::authorize('administrar', $evento);
        $this->eventos->archivar($evento);

        return $this->respuesta($evento, 'Evento "' . $evento->nombre . '" archivado correctamente.');
    }

    public function destroy(Evento $evento)
    {
        Gate::authorize('administrar', $evento);
        $this->eventos->eliminar($evento);

        return response()->json(['data' => null, 'message' => 'Evento eliminado correctamente.']);
    }

    public function enviarEncuestas(Evento $evento)
    {
        Gate::authorize('administrar', $evento);
        $enviados = $this->eventos->enviarEncuestas($evento);

        return response()->json([
            'data'    => ['enviadas' => $enviados],
            'message' => "Encuestas enviadas a {$enviados} participante(s). Las ya existentes se omitieron.",
        ]);
    }

    /**
     * Enlace de la Pantalla TV publica del evento (la web lo abre desde
     * /admin/tv con el evento de contexto). Genera el token si no existe.
     */
    public function tv(Evento $evento)
    {
        Gate::authorize('administrar', $evento);

        return response()->json(['data' => $this->datosTv($evento, $evento->tokenTv())]);
    }

    /** Rota el token: las pantallas abiertas con el enlace anterior dejan de funcionar. */
    public function rotarTv(Evento $evento)
    {
        Gate::authorize('administrar', $evento);

        return response()->json([
            'data'    => $this->datosTv($evento, $evento->rotarTokenTv()),
            'message' => 'Token de la pantalla TV rotado. Las pantallas abiertas dejaran de funcionar.',
        ]);
    }

    /**
     * Lo mismo que la Pantalla TV publica consulta cada 3 s
     * (/api/tv/{token}/publico), para previsualizarla desde la app sin el token.
     */
    public function tvDatos(Evento $evento, PantallaTvService $tv)
    {
        Gate::authorize('administrar', $evento);

        return response()->json(['data' => $tv->datos($evento)]);
    }

    private function datosTv(Evento $evento, string $token): array
    {
        return [
            'evento_id' => $evento->id,
            'token'     => $token,
            'url'       => route('tv.index', ['token' => $token]),
        ];
    }

    private function respuesta(Evento $evento, string $mensaje)
    {
        return (new AdminEventoResource($this->eventos->detalle($evento->fresh())))->additional(['message' => $mensaje]);
    }
}
