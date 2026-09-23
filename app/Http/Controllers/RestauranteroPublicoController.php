<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Restaurantero;
use App\Services\DirectorioProveedoresService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RestauranteroPublicoController extends Controller
{
    public function __construct(private DirectorioProveedoresService $directorio) {}

    public function index(Request $request)
    {
        $filtros = $request->only(['search', 'categoria']);

        // Todos los encuentros de negocios activos, el destacado primero.
        // Antes se usaba Evento::activo() (uno solo), por eso solo se veia un
        // evento y la pagina salia vacia si nadie estaba aprobado en el.
        $eventos = $this->directorio->encuentrosActivos();

        $proveedoresPlataforma = $this->directorio->dePlataforma()
            ->withCount('citas')
            ->orderByDesc('created_at')
            ->paginate(8, ['*'], 'plataforma_page')
            ->withQueryString();

        // Sin encuentros activos (o solo bazares): mostrar el directorio completo
        if ($eventos->isEmpty()) {
            $queryTodos = $this->directorio->dePlataforma()
                ->withCount('citas')
                ->orderByDesc('created_at');

            $this->aplicarFiltros($queryTodos, $request);

            $categoriasTodos = $this->directorio->categorias($this->directorio->dePlataforma());

            return Inertia::render('Restauranteros/Index', [
                'restauranteros'        => $queryTodos->paginate(12)->withQueryString(),
                'sin_evento'            => true,
                'eventos'               => [],
                'filters'               => $filtros,
                'categorias'            => $categoriasTodos,
                'proveedoresPlataforma' => null,
            ]);
        }

        // Una seccion por evento, en el orden de queryActivos (destacado primero)
        $secciones = $eventos->map(function ($evento) use ($request) {
            $query = $this->directorio->delEvento($evento)
                ->withCount('citas')
                ->orderByDesc('created_at');

            $this->aplicarFiltros($query, $request);

            return [
                'id'          => $evento->id,
                'nombre'      => $evento->nombre,
                'descripcion' => $evento->descripcion,
                'destacado'   => (bool) $evento->destacado,
                'imagen_url'  => $evento->imagen_url,
                'proveedores' => $query->get(),
            ];
        })->values();

        // Categorias de todos los eventos activos juntos, para el filtro
        $categorias = $this->directorio->categorias($this->directorio->delEvento($eventos->pluck('id')));

        return Inertia::render('Restauranteros/Index', [
            'restauranteros'        => null,
            'sin_evento'            => false,
            'eventos'               => $secciones,
            'filters'               => $filtros,
            'categorias'            => $categorias,
            'proveedoresPlataforma' => $proveedoresPlataforma,
        ]);
    }

    /** Filtros de busqueda y categoria, compartidos por todas las consultas (y por la API). */
    private function aplicarFiltros($query, Request $request): void
    {
        $this->directorio->aplicarFiltros($query, $request->input('search'), $request->input('categoria'));
    }

    public function show(Restaurantero $restaurantero, Request $request)
    {
        abort_unless($this->directorio->puedeVer(auth()->user(), $restaurantero), 404);

        // Track page visit
        $this->directorio->registrarVisita($restaurantero, $request);

        $restaurantero->load(['horarios', 'servicios' => function ($q) {
            $q->where('activo', true);
        }]);

        $citasCount = 0;
        $slotsBloquedosCliente = collect();

        if ($request->user()) {
            $citasCount = $request->user()->citasComoCliente()
                ->whereNotIn('estado', ['cancelada'])
                ->count();

            $slotsBloquedosCliente = $request->user()
                ->citasComoCliente()
                ->where('inicio', '>=', now())
                ->where('inicio', '<=', now()->addDays(60))
                ->whereNotIn('estado', ['cancelada'])
                ->get(['inicio', 'fin'])
                ->map(fn($c) => [
                    'inicio' => $c->inicio->copy()->subMinutes(10)->format('Y-m-d H:i'),
                    'fin'    => $c->fin->copy()->addMinutes(10)->format('Y-m-d H:i'),
                ]);
        }

        $citasOcupadas = $restaurantero->citas()
            ->where('inicio', '>=', now())
            ->where('inicio', '<=', now()->addDays(60))
            ->whereNotIn('estado', ['cancelada'])
            ->get(['inicio', 'fin'])
            ->map(fn($c) => [
                'inicio' => $c->inicio->format('Y-m-d H:i'),
                'fin'    => $c->fin->format('Y-m-d H:i'),
            ])
            ->toBase()->merge($slotsBloquedosCliente)
            ->values();

        $evento = Evento::activo();

        return Inertia::render('Restauranteros/Show', [
            'restaurantero' => $restaurantero->makeHidden(['direccion']),
            'citasCount'    => $citasCount,
            'citasOcupadas' => $citasOcupadas,
            'evento'        => $evento ? [
                'nombre'                        => $evento->nombre,
                'fecha_hora_inicio_compradores' => $evento->fecha_hora_inicio_compradores?->format('Y-m-d'),
                'fecha_hora_fin'                => $evento->fecha_hora_fin?->format('Y-m-d H:i'),
                'fecha_inicio_agenda'           => $evento->fecha_hora_inicio_compradores?->format('Y-m-d'),
                'fecha_fin_agenda'              => $evento->fecha_hora_fin?->format('Y-m-d'),
                'fecha_hora_inicio'             => $evento->fecha_hora_inicio?->format('Y-m-d H:i'),
                'tiempo_entre_citas_minutos'    => $evento->tiempo_entre_citas_minutos ?? 30,
                'max_citas_por_comprador'       => $evento->max_citas_por_comprador ?? 3,
            ] : null,
        ]);
    }
}
