<?php

namespace App\Http\Controllers\Admin;

use App\Exports\MetricasExport;
use App\Http\Controllers\Controller;
use App\Models\Restaurantero;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class MetricasController extends Controller
{
    public function index()
    {
        $data = array_merge($this->visitasKpis(), $this->topKpis(), $this->generoYRfc());

        return Inertia::render('Admin/Metricas', $data);
    }

    public function actualizarGenero(Request $request, User $user)
    {
        $request->validate([
            'genero' => 'required|in:hombre,mujer',
        ]);

        $user->update(['genero' => $request->genero]);

        return back()->with('success', 'Género actualizado.');
    }

    public function exportar()
    {
        $data = array_merge($this->visitasKpis(), $this->topKpis(), $this->generoYRfc());

        return Excel::download(new MetricasExport($data), 'metricas_impulsate_' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * KPIs de visitas a perfiles de proveedores (tabla page_visits).
     */
    private function visitasKpis(): array
    {
        if (!Schema::hasTable('page_visits')) {
            return [
                'sinDatos'         => true,
                'totalVisitas'     => 0,
                'visitasHoy'       => 0,
                'visitasSemana'    => 0,
                'visitasPorDia'    => [],
                'visitasPorPagina' => [],
                'porDispositivo'   => [],
                'proveedoresTop'   => [],
            ];
        }

        $totalVisitas  = DB::table('page_visits')->count();
        $visitasHoy    = DB::table('page_visits')->whereDate('created_at', today())->count();
        $visitasSemana = DB::table('page_visits')->where('created_at', '>=', now()->startOfWeek())->count();

        $visitasPorDia = DB::table('page_visits')
            ->selectRaw('DATE(created_at) as fecha, count(*) as total')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        // Acotado a 90 dias: sin filtro esta agrupacion crece para siempre
        // aunque tenga indice, y page_visits es la tabla que mas crece.
        $visitasPorPagina = DB::table('page_visits')
            ->selectRaw('url as pagina, count(*) as total')
            ->where('created_at', '>=', now()->subDays(90)->startOfDay())
            ->groupBy('url')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $porDispositivo = DB::table('page_visits')
            ->selectRaw('device_type, count(*) as total')
            ->groupBy('device_type')
            ->orderByDesc('total')
            ->get();

        $proveedoresTop = DB::table('page_visits')
            ->join('restauranteros', 'page_visits.restaurantero_id', '=', 'restauranteros.id')
            ->selectRaw('restauranteros.id, restauranteros.nombre_restaurante as nombre, restauranteros.logo_path, count(*) as total')
            ->whereNotNull('page_visits.restaurantero_id')
            ->groupBy('restauranteros.id', 'restauranteros.nombre_restaurante', 'restauranteros.logo_path')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'sinDatos'         => false,
            'totalVisitas'     => $totalVisitas,
            'visitasHoy'       => $visitasHoy,
            'visitasSemana'    => $visitasSemana,
            'visitasPorDia'    => $visitasPorDia,
            'visitasPorPagina' => $visitasPorPagina,
            'porDispositivo'   => $porDispositivo,
            'proveedoresTop'   => $proveedoresTop,
        ];
    }

    /**
     * KPIs de distribución geográfica y categorías (independientes de page_visits).
     */
    private function topKpis(): array
    {
        $topMunicipiosProveedores = Restaurantero::query()
            ->select('municipio', DB::raw('COUNT(*) as total'))
            ->whereNotNull('municipio')
            ->where('municipio', '!=', '')
            ->groupBy('municipio')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $topMunicipiosCompradores = User::role('cliente')
            ->select('municipio', DB::raw('COUNT(*) as total'))
            ->whereNotNull('municipio')
            ->where('municipio', '!=', '')
            ->groupBy('municipio')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $topCategoriasProveedores = Restaurantero::query()
            ->select('categoria', DB::raw('COUNT(*) as total'))
            ->whereNotNull('categoria')
            ->where('categoria', '!=', '')
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // 'necesidades' es texto libre (hasta 2000 caracteres), no una categoría fija,
        // así que se cuentan palabras clave en lugar de agrupar por texto exacto
        // (mismo criterio de palabras >=4 caracteres que usa CompletarPerfilController::necesidades()).
        $stopwords = [
            'para', 'como', 'necesito', 'busco', 'productos', 'producto',
            'proveedor', 'proveedores', 'servicio', 'servicios', 'nuestro',
            'nuestra', 'este', 'esta', 'estos', 'estas', 'tener', 'sobre',
            'desde', 'hasta', 'entre', 'porque', 'tambien', 'también',
            'pero', 'muy', 'mas', 'más', 'que',
        ];

        $topNecesidadesCompradores = User::role('cliente')
            ->whereNotNull('necesidades')
            ->where('necesidades', '!=', '')
            ->pluck('necesidades')
            ->flatMap(fn ($texto) => preg_split('/\s+/', mb_strtolower(trim($texto))))
            ->map(fn ($palabra) => trim($palabra, ".,;:()¡!¿?\"'"))
            ->filter(fn ($palabra) => mb_strlen($palabra) >= 4 && !in_array($palabra, $stopwords, true))
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->map(fn ($total, $necesidad) => ['necesidad' => $necesidad, 'total' => $total])
            ->values();

        return [
            'topMunicipiosProveedores'  => $topMunicipiosProveedores,
            'topMunicipiosCompradores'  => $topMunicipiosCompradores,
            'topCategoriasProveedores'  => $topCategoriasProveedores,
            'topNecesidadesCompradores' => $topNecesidadesCompradores,
        ];
    }

    /**
     * Distribución de género (deduplicado, un usuario dual-rol cuenta como
     * una sola persona) y formalización vía RFC de proveedores/compradores.
     */
    private function generoYRfc(): array
    {
        // ── GÉNERO (usuarios únicos con cualquier rol activo) ─────────────
        $usuariosConRol = User::whereHas('roles', fn ($q) =>
            $q->whereIn('name', ['restaurantero', 'cliente'])
        )->get(['id', 'name', 'genero']);

        $generoHombre    = $usuariosConRol->where('genero', 'hombre')->count();
        $generoMujer     = $usuariosConRol->where('genero', 'mujer')->count();
        $generoNoIdentif = $usuariosConRol->whereNull('genero')->count();
        $generoTotal     = $usuariosConRol->count();

        // ── RFC (formalización) ────────────────────────────────────────────
        $proveedorTotal  = Restaurantero::count();
        $proveedorConRFC = Restaurantero::whereNotNull('rfc')
            ->where('rfc', '!=', '')->where('rfc', '!=', '—')->count();
        $proveedorSinRFC = $proveedorTotal - $proveedorConRFC;

        $compradorTotal  = User::role('cliente')->count();
        $compradorConRFC = User::role('cliente')
            ->whereNotNull('rfc')
            ->where('rfc', '!=', '')->where('rfc', '!=', '—')->count();
        $compradorSinRFC = $compradorTotal - $compradorConRFC;

        $rfcTotal  = $proveedorTotal + $compradorTotal;
        $rfcConRFC = $proveedorConRFC + $compradorConRFC;
        $rfcSinRFC = $rfcTotal - $rfcConRFC;

        // ── LISTA NO IDENTIFICADOS (para clasificar manualmente) ──────────
        $noIdentificados = User::whereHas('roles', fn ($q) =>
                $q->whereIn('name', ['restaurantero', 'cliente'])
            )
            ->whereNull('genero')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'roles' => $u->roles->pluck('name'),
            ]);

        return [
            'generoHombre'    => $generoHombre,
            'generoMujer'     => $generoMujer,
            'generoNoIdentif' => $generoNoIdentif,
            'generoTotal'     => $generoTotal,
            'proveedorConRFC' => $proveedorConRFC,
            'proveedorSinRFC' => $proveedorSinRFC,
            'compradorConRFC' => $compradorConRFC,
            'compradorSinRFC' => $compradorSinRFC,
            'rfcTotal'        => $rfcTotal,
            'rfcConRFC'       => $rfcConRFC,
            'rfcSinRFC'       => $rfcSinRFC,
            'noIdentificados' => $noIdentificados,
        ];
    }
}
