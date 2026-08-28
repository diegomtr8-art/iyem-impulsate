<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TorreControlController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/TorreControl', [
            'numMesas' => $this->numMesas(),
        ]);
    }

    private function numMesas(): int
    {
        return (int) (DB::table('config_evento')->where('clave', 'num_mesas')->value('valor') ?? 50);
    }

    public function actualizarMesas(Request $request)
    {
        $request->validate(['num_mesas' => 'required|integer|min:1|max:500']);

        DB::table('config_evento')->updateOrInsert(
            ['clave' => 'num_mesas'],
            ['valor' => $request->num_mesas, 'updated_at' => now()]
        );

        return back()->with('success', 'Número de mesas actualizado a ' . $request->num_mesas . '.');
    }

    /** API: estado completo para polling (3s) */
    public function estado(Request $request)
    {
        $hoy = now()->toDateString();

        $citas = Cita::whereBetween('inicio', [
                \Carbon\Carbon::parse($hoy)->startOfDay(),
                \Carbon\Carbon::parse($hoy)->endOfDay(),
            ])
            ->whereNotIn('estado', ['cancelada', 'rechazada'])
            ->with(['cliente', 'restaurantero'])
            ->orderBy('inicio')
            ->get()
            ->map(fn($c) => $this->mapCita($c));

        $mesasOcupadas = $citas->whereIn('estado_tv', ['llamando','en_curso'])->pluck('mesa')->filter()->unique()->values();

        return response()->json([
            'citas'          => $citas,
            'mesas_ocupadas' => $mesasOcupadas,
            'timestamp'      => now()->format('H:i:s'),
        ]);
    }

    public function llamar(Request $request, Cita $cita)
    {
        $request->validate(['mesa' => 'required|string|max:20']);

        $cita->update([
            'mesa'       => $request->mesa,
            'estado_tv'  => 'llamando',
            'llamada_at' => now(),
        ]);

        return response()->json(['ok' => true, 'cita' => $this->mapCita($cita->fresh(['cliente','restaurantero']))]);
    }

    public function repetirAnuncio(Cita $cita)
    {
        $cita->update(['llamada_at' => now(), 'estado_tv' => 'llamando']);
        return response()->json(['ok' => true, 'cita' => $this->mapCita($cita->fresh(['cliente','restaurantero']))]);
    }

    public function iniciar(Cita $cita)
    {
        $cita->update([
            'estado_tv'        => 'en_curso',
            'hora_real_inicio' => now(),
        ]);
        return response()->json(['ok' => true, 'cita' => $this->mapCita($cita->fresh(['cliente','restaurantero']))]);
    }

    public function finalizar(Cita $cita)
    {
        $cita->update([
            'estado_tv'     => 'finalizada',
            'hora_real_fin' => now(),
            'estado'        => 'completada',
        ]);
        return response()->json(['ok' => true, 'cita' => $this->mapCita($cita->fresh(['cliente','restaurantero']))]);
    }

    public function ausente(Cita $cita)
    {
        $cita->update(['estado_tv' => 'ausente']);
        return response()->json(['ok' => true, 'cita' => $this->mapCita($cita->fresh(['cliente','restaurantero']))]);
    }

    public function retrasar(Request $request, Cita $cita)
    {
        $request->validate(['minutos' => 'required|integer|in:5,10,15,20']);
        $min = $request->minutos;
        $cita->update([
            'inicio' => $cita->inicio->addMinutes($min),
            'fin'    => $cita->fin->addMinutes($min),
        ]);
        return response()->json(['ok' => true, 'cita' => $this->mapCita($cita->fresh(['cliente','restaurantero']))]);
    }

    public function cambiarMesa(Request $request, Cita $cita)
    {
        $request->validate(['mesa' => 'required|string|max:20']);
        $cita->update(['mesa' => $request->mesa]);
        return response()->json(['ok' => true, 'cita' => $this->mapCita($cita->fresh(['cliente','restaurantero']))]);
    }

    private function mapCita(Cita $c): array
    {
        $duracionReal = null;
        if ($c->hora_real_inicio && $c->hora_real_fin) {
            $duracionReal = $c->hora_real_inicio->diffInMinutes($c->hora_real_fin);
        }

        return [
            'id'               => $c->id,
            'hora_prog'        => $c->inicio->format('H:i'),
            'hora_fin_prog'    => $c->fin->format('H:i'),
            'hora_real_inicio' => $c->hora_real_inicio?->format('H:i'),
            'hora_real_fin'    => $c->hora_real_fin?->format('H:i'),
            'llamada_at'       => $c->llamada_at?->format('H:i:s'),
            'llamada_ts'       => $c->llamada_at?->timestamp,
            'mesa'             => $c->mesa,
            'estado_tv'        => $c->estado_tv ?? 'pendiente',
            'estado'           => $c->estado,
            'duracion_real'    => $duracionReal,
            'comprador'        => $c->cliente?->name ?? '—',
            'comprador_tel'    => $c->cliente?->telefono ?? '',
            'proveedor'        => $c->restaurantero?->nombre_restaurante ?? '—',
            'categoria'        => $c->restaurantero?->categoria ?? '',
        ];
    }
}
