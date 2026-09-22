<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Services\Admin\TorreControlService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** La logica vive en TorreControlService (compartido con la API v1). */
class TorreControlController extends Controller
{
    public function __construct(private TorreControlService $torre) {}

    public function index()
    {
        return Inertia::render('Admin/TorreControl', [
            'numMesas' => $this->torre->numMesas(),
        ]);
    }

    public function actualizarMesas(Request $request)
    {
        $request->validate(['num_mesas' => 'required|integer|min:1|max:500']);

        $this->torre->actualizarMesas((int) $request->num_mesas);

        return back()->with('success', 'Número de mesas actualizado a ' . $request->num_mesas . '.');
    }

    /** API: estado completo para polling (3s) */
    public function estado(Request $request)
    {
        return response()->json($this->torre->estado());
    }

    public function llamar(Request $request, Cita $cita)
    {
        $request->validate(['mesa' => 'required|string|max:20']);

        return $this->ok($this->torre->llamar($cita, $request->mesa));
    }

    public function repetirAnuncio(Cita $cita)
    {
        return $this->ok($this->torre->repetirAnuncio($cita));
    }

    public function iniciar(Cita $cita)
    {
        return $this->ok($this->torre->iniciar($cita));
    }

    public function finalizar(Cita $cita)
    {
        return $this->ok($this->torre->finalizar($cita));
    }

    public function ausente(Cita $cita)
    {
        return $this->ok($this->torre->ausente($cita));
    }

    public function retrasar(Request $request, Cita $cita)
    {
        $request->validate(['minutos' => 'required|integer|in:5,10,15,20']);

        return $this->ok($this->torre->retrasar($cita, (int) $request->minutos));
    }

    public function cambiarMesa(Request $request, Cita $cita)
    {
        $request->validate(['mesa' => 'required|string|max:20']);

        return $this->ok($this->torre->cambiarMesa($cita, $request->mesa));
    }

    private function ok(Cita $cita)
    {
        return response()->json(['ok' => true, 'cita' => $this->torre->mapCita($cita)]);
    }
}
