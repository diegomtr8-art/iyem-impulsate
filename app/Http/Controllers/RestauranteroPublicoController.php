<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Restaurantero;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RestauranteroPublicoController extends Controller
{
    public function index(Request $request)
    {
        $evento = Evento::activo();

        $query = Restaurantero::where('activo', true)
            ->withCount('citas')
            ->orderByDesc('created_at');

        if ($evento) {
            $query->where('edicion_id', $evento->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre_restaurante', 'like', "%{$search}%")
                  ->orWhere('direccion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        $categorias = Restaurantero::where('activo', true)
            ->whereNotNull('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        $restauranteros = $query->paginate(12)->withQueryString();

        return Inertia::render('Restauranteros/Index', [
            'restauranteros' => $restauranteros,
            'filters'        => $request->only(['search', 'categoria']),
            'categorias'     => $categorias,
        ]);
    }

    public function show(Restaurantero $restaurantero, Request $request)
    {
        $esDueno = auth()->check() && auth()->id() === $restaurantero->user_id;
        $esAdmin = auth()->check() && auth()->user()->hasRole('admin');
        abort_if(!$restaurantero->activo && !$esDueno && !$esAdmin, 404);

        // Track page visit
        $ua = $request->userAgent() ?? '';
        $device = match(true) {
            str_contains(strtolower($ua), 'mobile') || str_contains(strtolower($ua), 'android') => 'mobile',
            str_contains(strtolower($ua), 'tablet') || str_contains(strtolower($ua), 'ipad')   => 'tablet',
            default => 'desktop',
        };
        \DB::table('page_visits')->insert([
            'restaurantero_id' => $restaurantero->id,
            'url'              => '/restauranteros/' . $restaurantero->id,
            'device_type'      => $device,
            'ip'               => $request->ip(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $restaurantero->load(['horarios', 'servicios' => function ($q) {
            $q->where('activo', true);
        }]);

        $citasCount = 0;
        // Inicializar siempre — si el usuario no está autenticado queda vacío
        $slotsBloquedosCliente = collect();

        if ($request->user()) {
            $citasCount = $request->user()->citasComoCliente()
                ->whereNotIn('estado', ['cancelada'])
                ->count();

            // Traer las citas del cliente para bloquear sus slots en este calendario
            $slotsBloquedosCliente = $request->user()
                ->citasComoCliente()
                ->where('inicio', '>=', now())
                ->where('inicio', '<=', now()->addDays(60))
                ->whereNotIn('estado', ['cancelada'])
                ->get(['inicio', 'fin'])
                ->map(fn($c) => [
                    // Con colchón de 10 min
                    'inicio' => $c->inicio->copy()->subMinutes(10)->format('Y-m-d H:i'),
                    'fin'    => $c->fin->copy()->addMinutes(10)->format('Y-m-d H:i'),
                ]);
        }

        // Citas ocupadas del PROVEEDOR (próximos 60 días)
        $citasOcupadas = $restaurantero->citas()
            ->where('inicio', '>=', now())
            ->where('inicio', '<=', now()->addDays(60))
            ->whereNotIn('estado', ['cancelada'])
            ->get(['inicio', 'fin'])
            ->map(fn($c) => [
                'inicio' => $c->inicio->format('Y-m-d H:i'),
                'fin'    => $c->fin->format('Y-m-d H:i'),
            ])
            // Combinar con los slots bloqueados del cliente
            ->toBase()->merge($slotsBloquedosCliente)
            ->values();

        $evento = Evento::activo();

        return Inertia::render('Restauranteros/Show', [
            'restaurantero' => $restaurantero,
            'citasCount'    => $citasCount,
            'citasOcupadas' => $citasOcupadas,
            'evento'        => $evento ? [
                'nombre'                      => $evento->nombre,
                'fecha_hora_inicio_compradores' => $evento->fecha_hora_inicio_compradores?->format('Y-m-d'),
                'fecha_hora_fin'               => $evento->fecha_hora_fin?->format('Y-m-d'),
                // Legacy campos para compatibilidad con la vista
                'fecha_inicio_agenda' => $evento->fecha_hora_inicio_compradores?->format('Y-m-d'),
                'fecha_fin_agenda'    => $evento->fecha_hora_fin?->format('Y-m-d'),
            ] : null,
        ]);
    }
}
