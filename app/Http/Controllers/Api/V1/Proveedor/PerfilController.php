<?php

namespace App\Http\Controllers\Api\V1\Proveedor;

use App\Http\Controllers\Controller;
use App\Http\Resources\PerfilProveedorResource;
use App\Services\PerfilProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Perfil de negocio del proveedor. La web guarda el formulario completo con
 * las reglas de POST /proveedor/completar-perfil; aqui se reutiliza el mismo
 * servicio partiendo de lo que la web precargaria, asi que un guardado parcial
 * (una seccion) tiene exactamente el efecto que tendria en la web pulsar
 * "Guardar cambios" tras editar solo esos campos. No se relaja ninguna regla.
 */
class PerfilController extends Controller
{
    public function __construct(private PerfilProveedorService $perfil) {}

    public function show(Request $request)
    {
        return new PerfilProveedorResource($request->user());
    }

    public function catalogos()
    {
        return response()->json(['data' => $this->perfil->catalogos()]);
    }

    /** PUT /proveedor/perfil: cualquier campo de cualquier seccion (los ausentes no cambian). */
    public function update(Request $request)
    {
        $campos = collect(PerfilProveedorService::SECCIONES)->pluck('campos')->flatten()->all();

        return $this->guardar($request, $request->only($campos));
    }

    /** PUT /proveedor/perfil/{seccion}: solo los campos de esa seccion (el resto se ignora). */
    public function actualizarSeccion(Request $request, string $seccion)
    {
        return $this->guardar($request, $request->only(PerfilProveedorService::SECCIONES[$seccion]['campos']));
    }

    /** POST /proveedor/logo (multipart, campo foto): mismo limite y formatos que la web. */
    public function logo(Request $request)
    {
        $request->validate(['foto' => ['required']], ['foto.required' => 'Selecciona una imagen.']);

        return $this->guardar($request, [], ['foto' => $request->file('foto')]);
    }

    private function guardar(Request $request, array $cambios, array $archivos = [])
    {
        $user  = $request->user();
        $datos = array_merge($this->perfil->valoresFormulario($user), Arr::except($cambios, ['productos', 'foto']));

        $resultado = $this->perfil->guardar(
            $user,
            $datos,
            array_filter($archivos),
            $this->perfil->fotosProductos($user->restaurantero),
        );

        return (new PerfilProveedorResource($user->fresh()))->additional([
            'message' => $resultado['mensaje'],
        ]);
    }
}
