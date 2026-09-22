<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProveedorResource;
use App\Http\Resources\ProveedorResumenResource;
use App\Models\Evento;
use App\Models\Restaurantero;
use App\Services\AgendaCitaService;
use App\Services\DirectorioProveedoresService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Directorio y ficha de proveedores (lo que la web muestra en /proveedores y
 * /proveedores/{id}) y los horarios disponibles para agendar.
 */
class ProveedorController extends Controller
{
    public function __construct(
        private DirectorioProveedoresService $directorio,
        private AgendaCitaService $agenda,
    ) {}

    public function index(Request $request)
    {
        $datos = $request->validate([
            'evento_id' => ['nullable', 'integer'],
            'search'    => ['nullable', 'string', 'max:100'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'orden'     => ['nullable', Rule::in(['recientes', 'coincidencia'])],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $eventos = $this->directorio->encuentrosActivos();
        [$query, $evento] = $this->alcance($datos, $eventos);

        $this->directorio->aplicarFiltros($query, $datos['search'] ?? null, $datos['categoria'] ?? null);
        $query->withCount('citas')->orderByDesc('created_at')->orderByDesc('id');

        $perPage  = (int) ($datos['per_page'] ?? 20);
        $orden    = $datos['orden'] ?? 'recientes';
        $palabras = $this->directorio->palabrasClave($request->user()->necesidades);

        if ($orden === 'coincidencia') {
            // Mismo criterio que "Proveedores recomendados" de /mi-panel. Se
            // ordena en PHP (como la web), por eso se pagina a mano.
            $todos = $this->directorio->ordenarPorCoincidencia($query->get(), $palabras)->values()
                ->each(fn ($p) => $p->coincidencias = $this->directorio->coincidencias($p, $palabras));
            $pagina = LengthAwarePaginator::resolveCurrentPage();
            $resultado = new LengthAwarePaginator(
                $todos->forPage($pagina, $perPage)->values(),
                $todos->count(),
                $perPage,
                $pagina,
                ['path' => $request->url(), 'query' => $request->query()],
            );
        } else {
            $resultado = $query->paginate($perPage)->withQueryString();
        }

        return ProveedorResumenResource::collection($resultado)->additional(['meta' => [
            'evento'            => $evento ? $this->eventoCorto($evento) : null,
            'eventos'           => $eventos->map(fn ($e) => $this->eventoCorto($e))->values(),
            'orden'             => $orden,
            'tiene_necesidades' => $palabras->isNotEmpty(),
        ]]);
    }

    public function categorias(Request $request)
    {
        $datos = $request->validate(['evento_id' => ['nullable', 'integer']]);

        [$query] = $this->alcance($datos, $this->directorio->encuentrosActivos());

        return response()->json(['data' => $this->directorio->categorias($query)->values()]);
    }

    public function show(Request $request, Restaurantero $proveedor)
    {
        Gate::authorize('view', $proveedor);

        $this->directorio->registrarVisita($proveedor, $request);

        return new ProveedorResource($proveedor);
    }

    public function disponibilidad(Request $request, Restaurantero $proveedor)
    {
        Gate::authorize('view', $proveedor);

        $datos = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'desde' => ['nullable', 'date_format:Y-m-d', 'prohibits:fecha'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde', 'prohibits:fecha'],
        ]);

        $desde = $datos['fecha'] ?? $datos['desde'] ?? null;
        $hasta = $datos['fecha'] ?? $datos['hasta'] ?? null;

        $disponibilidad = $this->agenda->disponibilidad(
            $request->user(),
            $proveedor,
            $desde ? Carbon::parse($desde) : null,
            $hasta ? Carbon::parse($hasta) : null,
        );

        return response()->json(['data' => array_merge([
            'proveedor' => ['id' => $proveedor->id, 'nombre_negocio' => $proveedor->nombre_restaurante],
        ], $disponibilidad)]);
    }

    /**
     * Con evento_id: proveedores aprobados en ese encuentro activo (una seccion
     * de /proveedores). Sin evento_id: directorio de la plataforma (perfil completo).
     *
     * @return array{0: Builder, 1: ?Evento}
     */
    private function alcance(array $datos, $eventos): array
    {
        if (empty($datos['evento_id'])) {
            return [$this->directorio->dePlataforma(), null];
        }

        $evento = $eventos->firstWhere('id', (int) $datos['evento_id']);
        if (!$evento) {
            throw ValidationException::withMessages([
                'evento_id' => 'El evento no es un encuentro de negocios activo.',
            ]);
        }

        return [$this->directorio->delEvento($evento), $evento];
    }

    private function eventoCorto(Evento $e): array
    {
        return [
            'id'         => $e->id,
            'nombre'     => $e->nombre,
            'destacado'  => (bool) $e->destacado,
            'imagen_url' => $e->imagen_url,
        ];
    }
}
