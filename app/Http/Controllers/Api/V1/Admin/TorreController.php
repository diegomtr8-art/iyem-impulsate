<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Services\Admin\TorreControlService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Torre de control del dia (web: /admin/torre, polling cada 3 s).
 *
 * "Tiempo real" por polling: GET /admin/torre devuelve `actualizado_at`
 * (ultima modificacion de una cita de hoy) y `servidor_at` (hora del
 * servidor al responder). La app manda en la siguiente consulta
 * `?desde=<servidor_at anterior>`: si ninguna cita de hoy cambio desde
 * entonces, la respuesta trae `sin_cambios: true` y `citas: null` (una
 * sola consulta de existencia, sin cargar el tablero).
 */
class TorreController extends Controller
{
    public function __construct(private TorreControlService $torre) {}

    public function index(Request $request)
    {
        Gate::authorize('administrar', Cita::class);
        $f = $request->validate([
            'desde'     => ['nullable', 'date'],
            'evento_id' => ['nullable', 'integer'],
        ]);

        $eventoId = isset($f['evento_id']) ? (int) $f['evento_id'] : null;
        $ahora    = now();
        $meta     = fn (bool $sinCambios) => [
            'sin_cambios'    => $sinCambios,
            'actualizado_at' => $this->torre->actualizadoAt($eventoId)?->toIso8601String(),
            'servidor_at'    => $ahora->toIso8601String(),
            'evento_id'      => $eventoId,
        ];

        if (isset($f['desde']) && !$this->torre->huboCambiosDesde(Carbon::parse($f['desde']), $eventoId)) {
            return response()->json(['data' => ['citas' => null, 'mesas_ocupadas' => null, 'num_mesas' => $this->torre->numMesas()], 'meta' => $meta(true)]);
        }

        $citas = $this->torre->citasDelDia($eventoId);

        return response()->json([
            'data' => [
                'citas'          => $citas->values(),
                'mesas_ocupadas' => $this->torre->mesasOcupadas($citas),
                'num_mesas'      => $this->torre->numMesas(),
            ],
            'meta' => $meta(false),
        ]);
    }

    public function mesas(Request $request)
    {
        Gate::authorize('administrar', Cita::class);
        $d = $request->validate(['num_mesas' => 'required|integer|min:1|max:500']);

        $this->torre->actualizarMesas((int) $d['num_mesas']);

        return response()->json([
            'data'    => ['num_mesas' => $this->torre->numMesas()],
            'message' => 'Número de mesas actualizado a ' . $d['num_mesas'] . '.',
        ]);
    }

    public function llamar(Request $request, Cita $cita)
    {
        Gate::authorize('administrar', $cita);
        $d = $request->validate(['mesa' => 'required|string|max:20']);

        return $this->ok($this->torre->llamar($cita, $d['mesa']), 'Llamando a la mesa ' . $d['mesa'] . '.');
    }

    public function repetir(Cita $cita)
    {
        Gate::authorize('administrar', $cita);

        return $this->ok($this->torre->repetirAnuncio($cita), 'Anuncio repetido.');
    }

    public function iniciar(Cita $cita)
    {
        Gate::authorize('administrar', $cita);

        return $this->ok($this->torre->iniciar($cita), 'Cita en curso.');
    }

    public function finalizar(Cita $cita)
    {
        Gate::authorize('administrar', $cita);

        return $this->ok($this->torre->finalizar($cita), 'Cita finalizada.');
    }

    public function ausente(Cita $cita)
    {
        Gate::authorize('administrar', $cita);

        return $this->ok($this->torre->ausente($cita), 'Marcada como ausente.');
    }

    public function retrasar(Request $request, Cita $cita)
    {
        Gate::authorize('administrar', $cita);
        $d = $request->validate(['minutos' => ['required', 'integer', Rule::in(TorreControlService::MINUTOS_RETRASO)]]);

        return $this->ok($this->torre->retrasar($cita, (int) $d['minutos']), 'Cita retrasada ' . $d['minutos'] . ' minutos.');
    }

    public function mesa(Request $request, Cita $cita)
    {
        Gate::authorize('administrar', $cita);
        $d = $request->validate(['mesa' => 'required|string|max:20']);

        return $this->ok($this->torre->cambiarMesa($cita, $d['mesa']), 'Mesa actualizada.');
    }

    private function ok(Cita $cita, string $mensaje)
    {
        return response()->json(['data' => $this->torre->mapCita($cita), 'message' => $mensaje]);
    }
}
