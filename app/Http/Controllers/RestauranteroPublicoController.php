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

        $proveedoresPlataforma = Restaurantero::query()
            ->where('activo', true)
            ->where('aprobado', true)
            ->whereHas('user', fn ($q) => $q->where('perfil_completo', true))
            ->withCount('citas')
            ->orderByDesc('created_at')
            ->paginate(8, ['*'], 'plataforma_page')
            ->withQueryString();

        if (!$evento) {
            return Inertia::render('Restauranteros/Index', [
                'restauranteros'        => [],
                'sin_evento'            => true,
                'filters'               => $request->only(['search', 'categoria']),
                'categorias'            => [],
                'proveedoresPlataforma' => $proveedoresPlataforma,
            ]);
        }

        $query = Restaurantero::query()
            ->where('activo', true)
            ->where('aprobado', true)
            ->whereHas('user', function ($q) use ($evento) {
                $q->whereExists(function ($sub) use ($evento) {
                    $sub->from('evento_usuario')
                        ->whereColumn('evento_usuario.user_id', 'users.id')
                        ->where('evento_usuario.evento_id', $evento->id)
                        ->where('evento_usuario.tipo', 'proveedor')
                        ->where('evento_usuario.estado', 'aprobado');
                });
            })
            ->withCount('citas')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre_restaurante', 'like', "%{$search}%")
                  ->orWhere('direccion', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        $categorias = Restaurantero::where('activo', true)
            ->where('aprobado', true)
            ->whereHas('user', function ($q) use ($evento) {
                $q->whereExists(function ($sub) use ($evento) {
                    $sub->from('evento_usuario')
                        ->whereColumn('evento_usuario.user_id', 'users.id')
                        ->where('evento_usuario.evento_id', $evento->id)
                        ->where('evento_usuario.tipo', 'proveedor')
                        ->where('evento_usuario.estado', 'aprobado');
                });
            })
            ->whereNotNull('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        $restauranteros = $query->paginate(12)->withQueryString();

        return Inertia::render('Restauranteros/Index', [
            'restauranteros'        => $restauranteros,
            'sin_evento'            => false,
            'filters'               => $request->only(['search', 'categoria']),
            'categorias'            => $categorias,
            'proveedoresPlataforma' => $proveedoresPlataforma,
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
            'restaurantero' => $restaurantero->makeHidden(['telefono', 'rfc', 'direccion']),
            'citasCount'    => $citasCount,
            'citasOcupadas' => $citasOcupadas,
            'evento'        => $evento ? [
                'nombre'                        => $evento->nombre,
                'fecha_hora_inicio_compradores' => $evento->fecha_hora_inicio_compradores?->format('Y-m-d'),
                'fecha_hora_fin'                => $evento->fecha_hora_fin?->format('Y-m-d'),
                'fecha_inicio_agenda'           => $evento->fecha_hora_inicio_compradores?->format('Y-m-d'),
                'fecha_fin_agenda'              => $evento->fecha_hora_fin?->format('Y-m-d'),
                'tiempo_entre_citas_minutos'    => $evento->tiempo_entre_citas_minutos ?? 30,
                'max_citas_por_comprador'       => $evento->max_citas_por_comprador ?? 3,
            ] : null,
        ]);
    }
}
