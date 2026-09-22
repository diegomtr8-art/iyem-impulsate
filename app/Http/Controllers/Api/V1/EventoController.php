<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventoResource;
use App\Models\Evento;
use App\Services\RegistroEventoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EventoController extends Controller
{
    public function __construct(private RegistroEventoService $registro) {}

    /** Eventos activos y aquellos en los que el usuario tiene registro (misma regla que EventoPolicy::view). */
    public function index(Request $request)
    {
        $user = $request->user();
        $this->vista($request);

        $eventos = $this->registro->eventosVisibles($user)->paginate(20);

        $registros = DB::table('evento_usuario')
            ->where('user_id', $user->id)
            ->whereIn('evento_id', $eventos->pluck('id'))
            ->get()
            ->groupBy('evento_id');

        $eventos->each(fn ($e) => $e->setRelation('mis_registros', $registros->get($e->id, collect())));

        return EventoResource::collection($eventos);
    }

    public function show(Request $request, Evento $evento)
    {
        Gate::authorize('view', $evento);
        $this->vista($request);

        return new EventoResource($evento);
    }

    public function registrar(Request $request, Evento $evento)
    {
        Gate::authorize('registrar', $evento);

        $datos = $request->validate([
            'tipo' => ['required', Rule::in(RegistroEventoService::TIPOS)],
        ]);

        $resultado = $this->registro->registrar($request->user(), $evento, $datos['tipo']);

        // Una cuenta con los dos roles que se registra como proveedor ve la respuesta como proveedor.
        if ($datos['tipo'] === 'proveedor') {
            $request->attributes->set(EventoResource::ATRIBUTO_VISTA, 'proveedor');
        }

        return (new EventoResource($evento->fresh()))
            ->additional(['message' => $resultado['mensaje'], 'resultado' => $resultado['estado']])
            ->response()
            ->setStatusCode(in_array($resultado['estado'], ['creado', 'repostulado']) ? 201 : 200);
    }

    /**
     * ?vista=proveedor: mi_registro/tipo_registro/accion calculados como
     * proveedor aunque active_role sea comprador (cuentas con los dos roles).
     */
    private function vista(Request $request): void
    {
        if ($request->query('vista') === 'proveedor') {
            $request->attributes->set(EventoResource::ATRIBUTO_VISTA, 'proveedor');
        }
    }
}
