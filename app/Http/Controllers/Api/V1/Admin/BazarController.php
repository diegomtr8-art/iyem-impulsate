<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ExpositorBazarResource;
use App\Http\Resources\DocumentosUsuarioResource;
use App\Models\Evento;
use App\Models\User;
use App\Services\Admin\BazarEvaluacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Bazar/Exposicion (web: /admin/eventos/{evento}/bazar): revision inicial,
 * evaluacion por criterios, seleccion con limite de espacios y correos.
 * Solo para eventos tipo bazar (404 en un encuentro, igual que la web).
 *
 * A diferencia de la web, las acciones sobre un participante exigen que tenga
 * solicitud de expositor en el bazar (404): la web actualiza 0 filas en
 * silencio y, al evaluar, llega a guardar puntajes de alguien que no participa.
 */
class BazarController extends Controller
{
    public function __construct(private BazarEvaluacionService $bazar) {}

    private function preparar(Evento $evento, ?User $user = null): void
    {
        Gate::authorize('administrar', $evento);
        $this->bazar->asegurarBazar($evento);

        if ($user) {
            abort_if(!$this->bazar->registro($evento, $user->id), 404, 'Ese participante no tiene una solicitud de expositor en este evento.');
        }
    }

    public function index(Request $request, Evento $evento)
    {
        $this->preparar($evento);

        $f = $request->validate([
            'estado'       => ['nullable', Rule::in(['pendiente', 'aprobado', 'rechazado'])],
            'seleccionado' => ['nullable', 'boolean'],
        ]);

        $datos = $this->bazar->datos($evento);

        $lista = $datos['solicitudes']
            ->when($f['estado'] ?? null, fn ($c, $e) => $c->where('estado', $e))
            ->when($request->has('seleccionado'), fn ($c) => $c->filter(fn ($s) => (bool) $s->seleccionado === $request->boolean('seleccionado')))
            ->values();

        return response()->json([
            'data' => ExpositorBazarResource::collection($lista)->resolve($request),
            'meta' => [
                'evento'    => [
                    'id'                       => $evento->id,
                    'nombre'                   => $evento->nombre,
                    'max_espacios'             => $evento->max_espacios !== null ? (int) $evento->max_espacios : null,
                    'con_criterios_evaluacion' => (bool) $evento->con_criterios_evaluacion,
                ],
                'criterios' => $datos['criterios']->map(fn ($c) => [
                    'id' => $c->id, 'nombre' => $c->nombre, 'porcentaje' => (float) $c->porcentaje, 'orden' => (int) $c->orden,
                ])->values(),
                'kpis'      => $datos['kpis'],
            ],
        ]);
    }

    /** Un expositor con su INE/CSF por URL firmada temporal. */
    public function show(Request $request, Evento $evento, User $user)
    {
        $this->preparar($evento, $user);

        return response()->json(['data' => array_merge(
            $this->expositor($request, $evento, $user),
            ['documentos' => (new DocumentosUsuarioResource($user))->resolve($request)],
        )]);
    }

    public function aprobar(Request $request, Evento $evento, User $user)
    {
        $this->preparar($evento, $user);
        $this->bazar->aprobar($evento, $user->id);

        return $this->respuesta($request, $evento, $user, 'Solicitud aprobada para evaluación.');
    }

    public function rechazar(Request $request, Evento $evento, User $user)
    {
        $this->preparar($evento, $user);
        $d = $request->validate(['motivo_rechazo' => 'required|string|max:500']);

        $this->bazar->rechazar($evento, $user->id, $d['motivo_rechazo']);

        return $this->respuesta($request, $evento, $user, 'Solicitud rechazada.');
    }

    public function evaluar(Request $request, Evento $evento, User $user)
    {
        $this->preparar($evento, $user);
        $d = $request->validate($this->bazar->reglasEvaluar($evento));

        $this->bazar->evaluar($evento, $user->id, $d['evaluaciones']);

        return $this->respuesta($request, $evento, $user, 'Evaluación guardada.');
    }

    public function notasRechazo(Request $request, Evento $evento, User $user)
    {
        $this->preparar($evento, $user);
        $d = $request->validate(['notas_rechazo' => 'required|string|max:2000']);

        $this->bazar->guardarNotasRechazo($evento, $user->id, $d['notas_rechazo']);

        return $this->respuesta($request, $evento, $user, 'Notas guardadas.');
    }

    public function seleccion(Request $request, Evento $evento)
    {
        $this->preparar($evento);
        $d = $request->validate([
            'user_id'      => 'required|integer|exists:users,id',
            'seleccionado' => 'required|boolean',
        ]);
        $seleccionado = $request->boolean('seleccionado');

        $this->bazar->cambiarSeleccion($evento, (int) $d['user_id'], $seleccionado);

        return $this->respuesta($request, $evento, User::findOrFail($d['user_id']),
            $seleccionado ? 'Participante seleccionado.' : 'Participante deseleccionado.');
    }

    public function enviarAprobacion(Evento $evento)
    {
        $this->preparar($evento);
        $n = $this->bazar->enviarAprobacion($evento);

        return response()->json(['data' => ['enviados' => $n], 'message' => "Correo de aprobación enviado a {$n} participante(s)."]);
    }

    public function enviarRechazo(Evento $evento)
    {
        $this->preparar($evento);
        $n = $this->bazar->enviarRechazo($evento);

        return response()->json(['data' => ['enviados' => $n], 'message' => "Correo de resultado enviado a {$n} participante(s)."]);
    }

    private function expositor(Request $request, Evento $evento, User $user): ?array
    {
        $fila = $this->bazar->datos($evento, $user->id)['solicitudes']->first();

        return $fila ? (new ExpositorBazarResource($fila))->resolve($request) : null;
    }

    private function respuesta(Request $request, Evento $evento, User $user, string $mensaje)
    {
        return response()->json([
            'data'    => $this->expositor($request, $evento, $user),
            'message' => $mensaje,
            'meta'    => ['kpis' => $this->bazar->datos($evento)['kpis']],
        ]);
    }
}
