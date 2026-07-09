<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class RestauranteroPanelController extends Controller
{
    public function index(Request $request)
    {
        $restaurantero = $request->user()->restaurantero;

        if (!$restaurantero) {
            $restaurantero = $request->user()->restaurantero()->create([
                'edicion_id'               => Evento::activo()?->id,
                'nombre_restaurante'       => $request->user()->name . ' — Negocio',
                'activo'                   => false,
                'aprobado'                 => false,
                'solicitado_aprobacion_at' => now(),
            ]);
            if (!$restaurantero->servicios()->exists()) {
                Servicio::create([
                    'restaurantero_id' => $restaurantero->id,
                    'nombre'           => 'Mesa de Networking',
                    'duracion_minutos' => 30,
                    'precio'           => 0,
                    'activo'           => true,
                ]);
            }
            if (!$restaurantero->horarios()->exists()) {
                for ($dia = 1; $dia <= 5; $dia++) {
                    Horario::create([
                        'restaurantero_id' => $restaurantero->id,
                        'dia_semana'       => $dia,
                        'hora_inicio'      => '09:00:00',
                        'hora_fin'         => '16:00:00',
                        'activo'           => true,
                    ]);
                }
            }
        }

        $hoy       = Carbon::today();
        $finSemana = Carbon::today()->endOfWeek();

        $citasHoy        = Cita::where('restaurantero_id', $restaurantero->id)->whereDate('inicio', $hoy)->count();
        $citasSemana     = Cita::where('restaurantero_id', $restaurantero->id)->whereBetween('inicio', [$hoy, $finSemana])->count();
        $citasPendientes = Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'pendiente')->count();

        $mapCita = fn($cita) => [
            'id'               => $cita->id,
            'inicio'           => $cita->inicio->toISOString(),
            'fin'              => $cita->fin->toISOString(),
            'estado'           => $cita->estado,
            'notas'            => $cita->notas,
            'propuesta_inicio' => $cita->propuesta_inicio?->toISOString(),
            'cliente' => [
                'name'        => $cita->cliente->name ?? 'N/A',
                'email'       => $cita->cliente->email ?? 'N/A',
                'telefono'    => $cita->cliente->telefono ?? 'N/A',
                'necesidades' => $cita->cliente->necesidades ?? null,
            ],
        ];

        // Todas las citas (ordenadas: pendientes primero, luego por fecha)
        $todasLasCitas = Cita::where('restaurantero_id', $restaurantero->id)
            ->with('cliente')
            ->orderByRaw("FIELD(estado, 'pendiente', 'confirmada', 'reagendada', 'pendiente_reconfirmacion', 'completada', 'cancelada', 'rechazada')")
            ->orderBy('inicio', 'desc')
            ->take(50)
            ->get()
            ->map($mapCita)
            ->values();

        $totalConfirmadas = Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'confirmada')->count();
        $totalRechazadas  = Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'rechazada')->count();
        $totalEnEvento    = Cita::where('restaurantero_id', $restaurantero->id)->whereNotIn('estado', ['cancelada', 'rechazada'])->count();
        $tasaAceptacion   = ($totalConfirmadas + $totalRechazadas) > 0
            ? round(($totalConfirmadas / ($totalConfirmadas + $totalRechazadas)) * 100)
            : null;

        $citaProxima2h = Cita::where('restaurantero_id', $restaurantero->id)
            ->whereIn('estado', ['confirmada', 'pendiente'])
            ->whereBetween('inicio', [Carbon::now(), Carbon::now()->addHours(2)])
            ->with('cliente')
            ->orderBy('inicio')
            ->first();

        $notificaciones = \App\Models\Notificacion::where('user_id', $request->user()->id)
            ->where('leida', false)
            ->orderByDesc('created_at')
            ->take(3)
            ->get(['id', 'tipo', 'titulo', 'mensaje', 'cita_id', 'created_at']);

        $todosLosEventos = Evento::orderByDesc('fecha_hora_inicio')->get()
            ->map(function ($ev) use ($request) {
                $registro = DB::table('evento_usuario')
                    ->where('evento_id', $ev->id)
                    ->where('user_id', $request->user()->id)
                    ->get();
                return [
                    'id'                            => $ev->id,
                    'nombre'                        => $ev->nombre,
                    'sector_economico'              => $ev->sector_economico,
                    'activa'                        => $ev->activa,
                    'convocatoria_url'              => $ev->convocatoria_url,
                    'fecha_hora_inicio'             => $ev->fecha_hora_inicio?->toISOString(),
                    'fecha_hora_fin'                => $ev->fecha_hora_fin?->toISOString(),
                    'fecha_hora_inicio_proveedores' => $ev->fecha_hora_inicio_proveedores?->toISOString(),
                    'fecha_hora_fin_proveedores'    => $ev->fecha_hora_fin_proveedores?->toISOString(),
                    'fecha_hora_inicio_compradores' => $ev->fecha_hora_inicio_compradores?->toISOString(),
                    'fecha_hora_fin_compradores'    => $ev->fecha_hora_fin_compradores?->toISOString(),
                    'max_citas_por_comprador'       => $ev->max_citas_por_comprador,
                    'tiempo_entre_citas_minutos'    => $ev->tiempo_entre_citas_minutos,
                    'registro_proveedor_abierto'    => $ev->registroProveedoresAbierto(),
                    'registro_comprador_abierto'    => $ev->registroCompradoresAbierto(),
                    'segundos_hasta_proveedores'    => $ev->segundosHastaProveedores(),
                    'segundos_hasta_compradores'    => $ev->segundosHastaCompradores(),
                    'mi_registro' => [
                        'comprador' => $registro->firstWhere('tipo', 'comprador'),
                        'proveedor' => $registro->firstWhere('tipo', 'proveedor'),
                    ],
                ];
            });

        return Inertia::render('Restaurantero/Panel', [
            'restaurantero'   => $restaurantero,
            'citasHoy'        => $citasHoy,
            'citasSemana'     => $citasSemana,
            'citasPendientes' => $citasPendientes,
            'todasLasCitas'   => $todasLasCitas,
            'categorias'      => \App\Models\Restaurantero::categoriasActivas(),
            'tasaAceptacion'  => $tasaAceptacion,
            'totalEnEvento'   => $totalEnEvento,
            'citaProxima2h'   => $citaProxima2h ? [
                'id'           => $citaProxima2h->id,
                'inicio'       => $citaProxima2h->inicio->toISOString(),
                'cliente_name' => $citaProxima2h->cliente->name ?? 'Comprador',
            ] : null,
            'notificaciones'  => $notificaciones,
            'eventos'         => $todosLosEventos,
        ]);
    }

    public function eventos(Request $request)
    {
        $restaurantero = $request->user()->restaurantero;
        if (!$restaurantero) {
            return response()->json([]);
        }

        $colores = [
            'pendiente'  => '#f59e0b',
            'confirmada' => '#10b981',
            'cancelada'  => '#6b7280',
            'completada' => '#3b82f6',
        ];

        $eventos = Cita::where('restaurantero_id', $restaurantero->id)
            ->with('cliente')
            ->whereNotIn('estado', ['cancelada'])
            ->get()
            ->map(fn($cita) => [
                'id'              => $cita->id,
                'title'           => $cita->cliente->name ?? 'Cliente',
                'start'           => $cita->inicio,
                'end'             => $cita->fin,
                'backgroundColor' => $colores[$cita->estado] ?? '#6b7280',
                'borderColor'     => $colores[$cita->estado] ?? '#6b7280',
                'textColor'       => '#111827',
            ]);

        return response()->json($eventos);
    }
}
