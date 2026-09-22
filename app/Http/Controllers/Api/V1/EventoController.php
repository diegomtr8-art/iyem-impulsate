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
        $esAdmin = $user->hasRole('admin') || $user->hasRole('super-admin');

        $eventos = Evento::query()
            ->when(!$esAdmin, fn ($q) => $q->where(fn ($q) => $q
                ->where('activa', true)
                ->orWhereExists(fn ($sub) => $sub->from('evento_usuario')
                    ->whereColumn('evento_usuario.evento_id', 'eventos.id')
                    ->where('evento_usuario.user_id', $user->id))))
            ->orderByDesc('activa')
            ->orderByDesc('destacado')
            ->orderByDesc('fecha_hora_inicio')
            ->orderByDesc('id')
            ->paginate(20);

        $registros = DB::table('evento_usuario')
            ->where('user_id', $user->id)
            ->whereIn('evento_id', $eventos->pluck('id'))
            ->get()
            ->groupBy('evento_id');

        $eventos->each(fn ($e) => $e->setRelation('mis_registros', $registros->get($e->id, collect())));

        return EventoResource::collection($eventos);
    }

    public function show(Evento $evento)
    {
        Gate::authorize('view', $evento);

        return new EventoResource($evento);
    }

    public function registrar(Request $request, Evento $evento)
    {
        Gate::authorize('registrar', $evento);

        $datos = $request->validate([
            'tipo' => ['required', Rule::in(RegistroEventoService::TIPOS)],
        ]);

        $resultado = $this->registro->registrar($request->user(), $evento, $datos['tipo']);

        return (new EventoResource($evento->fresh()))
            ->additional(['message' => $resultado['mensaje'], 'resultado' => $resultado['estado']])
            ->response()
            ->setStatusCode(in_array($resultado['estado'], ['creado', 'repostulado']) ? 201 : 200);
    }
}
