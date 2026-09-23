<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\UrlPublica;
use App\Models\User;
use App\Services\Admin\MetricasAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Metricas (web: /admin/metricas): los mismos datos agregados que las
 * graficas web (MetricasAdminService), agrupados y en snake_case. La lista de
 * "no identificados" va paginada aparte. El Excel sale por
 * POST /admin/exportaciones/metricas.
 */
class MetricasController extends Controller
{
    use UrlPublica;

    public function __construct(private MetricasAdminService $metricas) {}

    public function index()
    {
        Gate::authorize('admin');

        $v = $this->metricas->visitasKpis();
        $t = $this->metricas->topKpis();
        $g = $this->metricas->generoYRfc();

        $filas = fn ($lista, string $clave, ?string $como = null) => collect($lista)->map(fn ($r) => [
            ($como ?? $clave) => ((array) $r)[$clave] ?? null,
            'total'           => (int) ((array) $r)['total'],
        ])->values();

        return response()->json(['data' => [
            'visitas' => [
                'sin_datos'       => $v['sinDatos'],
                'total'           => (int) $v['totalVisitas'],
                'hoy'             => (int) $v['visitasHoy'],
                'semana'          => (int) $v['visitasSemana'],
                'por_dia'         => $filas($v['visitasPorDia'], 'fecha'),
                'por_pagina'      => $filas($v['visitasPorPagina'], 'pagina'),
                'por_dispositivo' => $filas($v['porDispositivo'], 'device_type', 'dispositivo'),
                'proveedores_top' => collect($v['proveedoresTop'])->map(fn ($r) => [
                    'id'       => (int) $r->id,
                    'nombre'   => $r->nombre,
                    'logo_url' => self::urlPublica($r->logo_path),
                    'total'    => (int) $r->total,
                ])->values(),
            ],
            'top' => [
                'municipios_proveedores' => $filas($t['topMunicipiosProveedores']->map->toArray(), 'municipio'),
                'municipios_compradores' => $filas($t['topMunicipiosCompradores']->map(fn ($u) => ['municipio' => $u->municipio, 'total' => $u->total]), 'municipio'),
                'categorias_proveedores' => $filas($t['topCategoriasProveedores']->map->toArray(), 'categoria'),
                'necesidades_compradores' => $filas($t['topNecesidadesCompradores'], 'necesidad'),
            ],
            'genero' => [
                'hombre'          => $g['generoHombre'],
                'mujer'           => $g['generoMujer'],
                'no_identificado' => $g['generoNoIdentif'],
                'total'           => $g['generoTotal'],
            ],
            'rfc' => [
                'proveedores' => ['con_rfc' => $g['proveedorConRFC'], 'sin_rfc' => $g['proveedorSinRFC']],
                'compradores' => ['con_rfc' => $g['compradorConRFC'], 'sin_rfc' => $g['compradorSinRFC']],
                'total'       => $g['rfcTotal'],
                'con_rfc'     => $g['rfcConRFC'],
                'sin_rfc'     => $g['rfcSinRFC'],
            ],
        ]]);
    }

    /** Usuarios (proveedor o comprador) sin genero capturado, para clasificarlos. */
    public function noIdentificados(Request $request)
    {
        Gate::authorize('admin');
        $f = $request->validate([
            'search'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $pag = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['restaurantero', 'cliente']))
            ->whereNull('genero')
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->with('roles')
            ->orderBy('name')
            ->paginate((int) ($f['per_page'] ?? 50))
            ->withQueryString();

        $pag->through(fn ($u) => [
            'id'     => $u->id,
            'nombre' => $u->name,
            'email'  => $u->email,
            'roles'  => $u->roles->pluck('name')->values(),
        ]);

        return response()->json([
            'data'  => $pag->items(),
            'links' => ['first' => $pag->url(1), 'last' => $pag->url($pag->lastPage()), 'prev' => $pag->previousPageUrl(), 'next' => $pag->nextPageUrl()],
            'meta'  => ['current_page' => $pag->currentPage(), 'last_page' => $pag->lastPage(), 'per_page' => $pag->perPage(), 'total' => $pag->total()],
        ]);
    }

    public function genero(Request $request, User $user)
    {
        Gate::authorize('admin');
        $d = $request->validate(['genero' => ['required', Rule::in(MetricasAdminService::GENEROS)]]);

        $this->metricas->actualizarGenero($user, $d['genero']);

        return response()->json(['data' => ['id' => $user->id, 'genero' => $user->genero], 'message' => 'Género actualizado.']);
    }
}
