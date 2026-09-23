<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaDeNegocioException;
use App\Services\PerfilProveedorService;
use Illuminate\Http\Request;

class ProveedorPerfilController extends Controller
{
    public function create()
    {
        // El formulario de completar perfil vive dentro del panel del proveedor
        // (Restaurantero/Panel.vue), no en una página independiente.
        return redirect()->route('restaurantero.panel');
    }

    /**
     * Reglas, limpiezas, fotos (8 MB, foto de producto sin nombre, subidas
     * truncadas), re-aprobacion y avisos viven en PerfilProveedorService, que
     * tambien usa la API v1 (/api/v1/proveedor/perfil, /logo, /productos).
     */
    public function store(Request $request, PerfilProveedorService $perfil)
    {
        $archivos = ['foto' => $request->file('foto')];
        foreach (range(0, PerfilProveedorService::MAX_PRODUCTOS - 1) as $i) {
            $archivos["producto_foto_{$i}"] = $request->file("producto_foto_{$i}");
        }

        try {
            $resultado = $perfil->guardar(
                $request->user(),
                $request->except(array_keys($archivos)),
                array_filter($archivos),
            );
        } catch (ReglaDeNegocioException $e) {
            // Mismas respuestas que antes: el logo sin withInput(), las fotos de producto con el.
            $respuesta = back()->withErrors([$e->campo => $e->getMessage()]);

            return $e->campo === 'foto' ? $respuesta : $respuesta->withInput();
        }

        return back()->with('success', $resultado['mensaje']);
    }
}
