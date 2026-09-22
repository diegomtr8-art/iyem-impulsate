<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminAgendaResource;
use App\Http\Resources\Admin\AdminCitaResource;
use App\Http\Resources\Concerns\UrlPublica;
use App\Models\AgendaPropuesta;
use App\Services\Admin\AdminEventosService;
use App\Services\Admin\AgendaAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Agenda propuesta (flujo invertido, web: /admin/agenda): el admin elige un
 * comprador, le asigna proveedores en slots y envia la propuesta por correo.
 * La web no permite editar una propuesta: se borra y se crea otra (igual aqui).
 */
class AgendaController extends Controller
{
    use UrlPublica;

    public function __construct(
        private AgendaAdminService $agenda,
        private AdminEventosService $eventos,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('administrar', AgendaPropuesta::class);
        $f = $request->validate([
            'evento_id' => ['nullable', 'integer'],
            'estado'    => ['nullable', Rule::in(['pendiente', 'aceptada', 'rechazada'])],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return AdminAgendaResource::collection($this->agenda->listar((int) ($f['per_page'] ?? 20), $f));
    }

    /** Datos para construir una propuesta: evento, compradores y proveedores aprobados, slots. */
    public function formulario(Request $request)
    {
        Gate::authorize('administrar', AgendaPropuesta::class);
        $f = $request->validate(['evento_id' => ['nullable', 'integer']]);

        $evento = $this->eventos->contexto($f['evento_id'] ?? null);
        if (!$evento) {
            throw new \App\Exceptions\ReglaDeNegocioException('No hay un evento activo para crear agendas.');
        }

        return response()->json(['data' => [
            'evento'      => ['id' => $evento->id, 'nombre' => $evento->nombre],
            'compradores' => $this->agenda->compradores($evento)->map(fn ($u) => [
                'id'                => $u->id,
                'nombre'            => $u->name,
                'nombre_empresa'    => $u->nombre_empresa,
                'municipio'         => $u->municipio,
                'camara_asociacion' => $u->camara_asociacion,
            ])->values(),
            'proveedores' => $this->agenda->proveedores($evento)->map(fn ($r) => [
                'id'             => $r->id,
                'nombre_negocio' => $r->nombre_restaurante,
                'categoria'      => $r->categoria,
                'descripcion'    => $r->descripcion,
                'logo_url'       => self::urlPublica($r->logo_path),
            ])->values(),
            'slots'       => $this->agenda->slots($evento),
            'minutos_slot' => AgendaAdminService::MINUTOS_SLOT,
        ]]);
    }

    public function store(Request $request)
    {
        Gate::authorize('administrar', AgendaPropuesta::class);
        $datos = $request->validate(array_merge(AgendaAdminService::REGLAS_ALTA, [
            'evento_id' => ['nullable', 'integer'],
        ]));
        $datos['enviar'] = $request->boolean('enviar');

        $propuesta = $this->agenda->crear($this->eventos->contexto($datos['evento_id'] ?? null), $datos, $request->user());

        return (new AdminAgendaResource($this->cargar($propuesta)))
            ->additional(['message' => 'Propuesta de agenda guardada' . ($datos['enviar'] ? ' y enviada al comprador.' : '.')])
            ->response()->setStatusCode(201);
    }

    public function show(Request $request, AgendaPropuesta $agenda)
    {
        Gate::authorize('administrar', $agenda);

        $confirmadas = $this->agenda->citasConfirmadas($agenda)->load(['evento', 'cliente']);

        return (new AdminAgendaResource($this->cargar($agenda)))->additional(['meta' => [
            'citas_confirmadas' => AdminCitaResource::collection($confirmadas)->resolve($request),
        ]]);
    }

    /** Envia (o reenvia) el correo de una propuesta pendiente. */
    public function enviar(AgendaPropuesta $agenda)
    {
        Gate::authorize('administrar', $agenda);
        $this->agenda->enviar($agenda);

        return (new AdminAgendaResource($this->cargar($agenda->fresh())))->additional(['message' => 'Correo enviado al comprador.']);
    }

    /** Deshacer: borra la propuesta (si estaba aceptada, cancela las citas que genero). */
    public function destroy(AgendaPropuesta $agenda)
    {
        Gate::authorize('administrar', $agenda);

        return response()->json(['data' => null, 'message' => $this->agenda->eliminar($agenda)]);
    }

    private function cargar(AgendaPropuesta $agenda): AgendaPropuesta
    {
        return $agenda->load([
            'comprador:id,name,nombre_empresa,email',
            'evento:id,nombre',
            'citas.proveedor:id,nombre_restaurante,logo_path',
        ]);
    }
}
