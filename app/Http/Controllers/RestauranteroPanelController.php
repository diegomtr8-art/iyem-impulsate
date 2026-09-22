<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Services\GestionCitasProveedorService;
use App\Services\PerfilProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Panel web del proveedor. El alta del negocio vacio, los KPIs, la lista de
 * citas y el feed del calendario salen de PerfilProveedorService y
 * GestionCitasProveedorService, que tambien usa la API v1 (/api/v1/proveedor/panel
 * y /api/v1/proveedor/calendario).
 */
class RestauranteroPanelController extends Controller
{
    public function __construct(
        private PerfilProveedorService $perfil,
        private GestionCitasProveedorService $gestion,
    ) {}

    public function index(Request $request)
    {
        $restaurantero = $this->perfil->asegurarNegocio($request->user());

        $kpis = $this->gestion->kpis($restaurantero);

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
        $todasLasCitas = $this->gestion->citasDelPanel($restaurantero)->map($mapCita)->values();

        $citaProxima2h = $this->gestion->citaProxima2h($restaurantero);

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
                    'tipo_evento'                   => $ev->tipo_evento ?? 'encuentro_negocios',
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
                        'expositor' => $registro->firstWhere('tipo', 'expositor'),
                    ],
                ];
            });

        return Inertia::render('Restaurantero/Panel', [
            'restaurantero'   => $restaurantero,
            'citasHoy'        => $kpis['citas_hoy'],
            'citasSemana'     => $kpis['citas_semana'],
            'citasPendientes' => $kpis['citas_pendientes'],
            'todasLasCitas'   => $todasLasCitas,
            'categorias'      => \App\Models\Restaurantero::categoriasActivas(),
            'tasaAceptacion'  => $kpis['tasa_aceptacion'],
            'totalEnEvento'   => $kpis['total_en_evento'],
            'citaProxima2h'   => $citaProxima2h ? [
                'id'           => $citaProxima2h->id,
                'inicio'       => $citaProxima2h->inicio->toISOString(),
                'cliente_name' => $citaProxima2h->cliente->name ?? 'Comprador',
            ] : null,
            'notificaciones'  => $notificaciones,
            'eventos'         => $todosLosEventos,
        ]);
    }

    /** Feed de FullCalendar (GET /proveedor/panel/eventos). */
    public function eventos(Request $request)
    {
        $restaurantero = $request->user()->restaurantero;
        if (!$restaurantero) {
            return response()->json([]);
        }

        $eventos = $this->gestion->citasCalendario($restaurantero)
            ->map(fn($cita) => [
                'id'              => $cita->id,
                'title'           => $cita->cliente->name ?? 'Cliente',
                'start'           => $cita->inicio,
                'end'             => $cita->fin,
                'backgroundColor' => $this->gestion->colorCalendario($cita->estado),
                'borderColor'     => $this->gestion->colorCalendario($cita->estado),
                'textColor'       => '#111827',
            ]);

        return response()->json($eventos);
    }
}
