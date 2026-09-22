<?php

namespace App\Http\Controllers\Api\V1\Proveedor;

use App\Exceptions\ReglaDeNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PerfilProveedorResource;
use App\Services\PerfilProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Productos destacados del proveedor (restauranteros.productos_top, maximo 5,
 * con foto y capacidad mensual). En la web se editan como filas del formulario
 * de perfil; aqui cada alta/edicion/baja guarda el formulario completo con el
 * mismo servicio y las mismas reglas (productos.*, producto_foto_{i}).
 *
 * Un producto se identifica por su posicion (indice 0-4), como en la web.
 */
class ProductoController extends Controller
{
    private const CAMPOS = ['nombre', 'descripcion', 'capacidad_cantidad', 'capacidad_unidad'];

    public function __construct(private PerfilProveedorService $perfil) {}

    public function index(Request $request)
    {
        return response()->json($this->lista($request));
    }

    public function store(Request $request)
    {
        $this->exigirNombre($request, true);

        $user      = $request->user();
        $productos = $this->perfil->productosFormulario($user->restaurantero);
        $fotos     = $this->perfil->fotosProductos($user->restaurantero);
        $indice    = count($productos);

        $productos[] = $request->only(self::CAMPOS);
        $fotos[]     = null;

        $resultado = $this->guardar($request, $productos, $fotos, $indice);

        return response()->json(array_merge($this->producto($request, $indice), [
            'message' => $resultado['mensaje'],
        ]), 201);
    }

    public function update(Request $request, int $indice)
    {
        $user      = $request->user();
        $productos = $this->perfil->productosFormulario($user->restaurantero);
        abort_unless(isset($productos[$indice]), 404, 'Producto no encontrado.');

        $this->exigirNombre($request, false);

        $productos[$indice] = array_merge($productos[$indice], $request->only(self::CAMPOS));

        $resultado = $this->guardar($request, $productos, $this->perfil->fotosProductos($user->restaurantero), $indice);

        return response()->json(array_merge($this->producto($request, $indice), [
            'message' => $resultado['mensaje'],
        ]));
    }

    public function destroy(Request $request, int $indice)
    {
        $user      = $request->user();
        $productos = $this->perfil->productosFormulario($user->restaurantero);
        abort_unless(isset($productos[$indice]), 404, 'Producto no encontrado.');

        $fotos = $this->perfil->fotosProductos($user->restaurantero);
        $fotoBorrada = $fotos[$indice] ?? null;
        array_splice($productos, $indice, 1);
        array_splice($fotos, $indice, 1);

        // Las fotos se pasan por posicion para que no se corran al quitar una fila.
        $resultado = $this->guardar($request, $productos, $fotos, null);

        if ($fotoBorrada && !in_array($fotoBorrada, $this->perfil->fotosProductos($user->fresh()->restaurantero), true)) {
            Storage::disk('public')->delete($fotoBorrada);
        }

        return response()->json(array_merge($this->lista($request), ['message' => $resultado['mensaje']]));
    }

    /**
     * La web descarta en silencio una fila sin nombre; en la app eso seria un
     * producto que "desaparece", asi que aqui el nombre es obligatorio.
     */
    private function exigirNombre(Request $request, bool $alta): void
    {
        if ($alta || $request->has('nombre')) {
            Validator::make($request->only('nombre'), ['nombre' => ['required']], [
                'nombre.required' => 'El nombre del producto es obligatorio.',
            ])->validate();
        }
    }

    private function guardar(Request $request, array $productos, array $fotos, ?int $indice): array
    {
        $user  = $request->user();
        $datos = array_merge($this->perfil->valoresFormulario($user), ['productos' => $productos]);
        $archivos = $indice !== null && $request->file('foto') ? ["producto_foto_{$indice}" => $request->file('foto')] : [];

        try {
            return $this->perfil->guardar($user, $datos, $archivos, $fotos);
        } catch (ValidationException $e) {
            // productos.{i}.campo -> campo, producto_foto_{i} -> foto, productos -> productos
            $errores = [];
            foreach ($e->errors() as $clave => $mensajes) {
                $clave = preg_replace(['/^productos\.\d+\./', '/^producto_foto_\d+$/'], ['', 'foto'], $clave);
                // "El campo producto foto 2 ..." / "El campo productos.2.nombre ..." -> nombre del campo de la app
                $mensajes = preg_replace(['/producto foto \d+/', '/productos\.\d+\.([a-z_]+)/'], ['foto', '$1'], $mensajes);
                $errores[$clave] = array_merge($errores[$clave] ?? [], $mensajes);
            }
            throw ValidationException::withMessages($errores);
        } catch (ReglaDeNegocioException $e) {
            throw new ReglaDeNegocioException($e->getMessage(), str_starts_with($e->campo, 'producto_foto_') ? 'foto' : $e->campo);
        }
    }

    private function lista(Request $request): array
    {
        $productos = PerfilProveedorResource::productos($request->user()->fresh()->restaurantero);

        return [
            'data' => $productos,
            'meta' => [
                'max_productos' => PerfilProveedorService::MAX_PRODUCTOS,
                'restantes'     => max(0, PerfilProveedorService::MAX_PRODUCTOS - count($productos)),
                'foto_max_kb'   => PerfilProveedorService::FOTO_MAX_KB,
                'foto_formatos' => PerfilProveedorService::FOTO_MIMES,
                'capacidad_unidades' => PerfilProveedorService::CAPACIDAD_UNIDADES,
            ],
        ];
    }

    private function producto(Request $request, int $indice): array
    {
        $r = $request->user()->fresh()->restaurantero;

        return [
            'data'    => PerfilProveedorResource::productos($r)[$indice] ?? null,
            'negocio' => PerfilProveedorResource::negocio($r, $this->perfil),
        ];
    }
}
