<?php

namespace App\Http\Controllers\Api\V1\Proveedor;

use App\Http\Controllers\Api\V1\InicioController;
use App\Http\Controllers\Controller;
use App\Http\Resources\CitaProveedorResource;
use App\Http\Resources\CitaResource;
use App\Http\Resources\EventoResource;
use App\Http\Resources\PerfilProveedorResource;
use App\Models\Cita;
use App\Services\DocumentosUsuarioService;
use App\Services\GestionCitasProveedorService;
use App\Services\NotificacionService;
use App\Services\PerfilProveedorService;
use App\Services\RegistroEventoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Panel del proveedor (Restaurantero/Panel.vue) y su calendario. Todo sale de
 * los mismos servicios que usa RestauranteroPanelController.
 */
class PanelController extends Controller
{
    public function __construct(
        private PerfilProveedorService $perfil,
        private GestionCitasProveedorService $gestion,
        private RegistroEventoService $registro,
    ) {}

    public function panel(Request $request, NotificacionService $notificaciones)
    {
        $user = $request->user();

        // Igual que la web: la primera visita al panel crea el negocio vacio.
        $restaurantero = $this->perfil->asegurarNegocio($user);

        // Registro a eventos visto como PROVEEDOR aunque la cuenta tenga los dos roles.
        $request->attributes->set(EventoResource::ATRIBUTO_VISTA, 'proveedor');

        $eventos = $this->registro->eventosVisibles($user)->get();
        $registros = DB::table('evento_usuario')
            ->where('user_id', $user->id)
            ->whereIn('evento_id', $eventos->pluck('id'))
            ->get()
            ->groupBy('evento_id');
        $eventos->each(fn ($e) => $e->setRelation('mis_registros', $registros->get($e->id, collect())));

        $proximas = Cita::where('restaurantero_id', $restaurantero->id)
            ->where('inicio', '>=', now())
            ->whereIn('estado', InicioController::ESTADOS_VIGENTES)
            ->with(['cliente', 'evento'])
            ->orderBy('inicio')
            ->take(5)
            ->get();

        $proxima2h = $this->gestion->citaProxima2h($restaurantero)?->load('evento');
        $faltantes = $this->perfil->faltantes($restaurantero);

        return response()->json(['data' => [
            'negocio'         => PerfilProveedorResource::negocio($restaurantero, $this->perfil),
            'perfil'          => [
                'completo'            => (bool) $restaurantero->perfil_completo,
                'porcentaje'          => $this->perfil->porcentaje($restaurantero),
                'faltantes'           => $faltantes,
                'secciones_faltantes' => collect($faltantes)->pluck('seccion')->unique()->values()->all(),
            ],
            'kpis'            => $this->gestion->kpis($restaurantero),
            'contadores'      => $this->gestion->contadores($restaurantero),
            'cita_proxima_2h' => $proxima2h ? new CitaProveedorResource($proxima2h) : null,
            'proximas_citas'  => CitaProveedorResource::collection($proximas),
            'eventos'         => EventoResource::collection($eventos),
            // Requisitos del bazar (registro como expositor): los mismos que valida RegistroEventoService.
            'requisitos_bazar' => [
                'tiene_ine'   => (bool) $user->ine_path,
                'tiene_csf'   => (bool) $user->csf_path,
                'csf_fecha'   => $user->csf_fecha ? Carbon::parse($user->csf_fecha)->toDateString() : null,
                'csf_vigente' => DocumentosUsuarioService::csfVigente($user),
                'csf_vigencia_meses' => DocumentosUsuarioService::CSF_VIGENCIA_MESES,
            ],
            'notificaciones_sin_leer' => $notificaciones->contarNoLeidas($user),
        ]]);
    }

    /**
     * Citas del calendario del panel (el feed de FullCalendar de la web:
     * todas menos las canceladas), opcionalmente acotadas a un rango.
     */
    public function calendario(Request $request)
    {
        $datos = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $desde = isset($datos['desde']) ? Carbon::parse($datos['desde'])->startOfDay() : null;
        $hasta = isset($datos['hasta']) ? Carbon::parse($datos['hasta'])->addDay()->startOfDay() : null;

        $restaurantero = $request->user()->restaurantero;
        $citas = $restaurantero
            ? $this->gestion->citasCalendario($restaurantero, $desde, $hasta)->load('evento')->sortBy('inicio')->values()
            : collect();

        return response()->json([
            'data' => $citas->map(fn (Cita $c) => [
                'id'           => $c->id,
                'titulo'       => $c->cliente->name ?? 'Cliente',
                'inicio'       => $c->inicio?->toIso8601String(),
                'fin'          => $c->fin?->toIso8601String(),
                'estado'       => $c->estado,
                'estado_texto' => CitaResource::ESTADOS_TEXTO[$c->estado] ?? ucfirst((string) $c->estado),
                'color'        => $this->gestion->colorCalendario($c->estado),
                'evento'       => $c->evento ? ['id' => $c->evento->id, 'nombre' => $c->evento->nombre] : null,
                'comprador'    => $c->cliente ? [
                    'id'             => $c->cliente->id,
                    'nombre'         => $c->cliente->name,
                    'nombre_empresa' => $c->cliente->nombre_empresa,
                ] : null,
            ])->values(),
            'meta' => [
                'desde' => $datos['desde'] ?? null,
                'hasta' => $datos['hasta'] ?? null,
                'total' => $citas->count(),
            ],
        ]);
    }
}
