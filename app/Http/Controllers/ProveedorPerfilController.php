<?php

namespace App\Http\Controllers;

use App\Mail\ProveedorAprobado;
use App\Mail\ProveedorRechazado;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProveedorPerfilController extends Controller
{
    public function create(Request $request)
    {
        $restaurantero = $request->user()->restaurantero;

        return Inertia::render('Restaurantero/CompletarPerfil', [
            'restaurantero' => $restaurantero,
            'categorias'    => \App\Models\Restaurantero::$categorias,
        ]);
    }

    public function store(Request $request)
    {
        $restaurantero = $request->user()->restaurantero;

        if (!$restaurantero) {
            // Crear el restaurantero base si no existe
            $restaurantero = $request->user()->restaurantero()->create([
                'nombre_restaurante' => $request->nombre_restaurante,
                'activo'             => false,
                'aprobado'           => false,
            ]);
        }

        $request->validate([
            'nombre_restaurante' => ['required', 'string', 'max:200'],
            'descripcion'        => ['nullable', 'string', 'max:2000'],
            'telefono'           => ['nullable', 'string', 'max:20'],
            'direccion'          => ['nullable', 'string', 'max:300'],
            'municipio'          => ['nullable', 'string', 'max:100'],
            'rfc'                => ['nullable', 'string', 'max:13'],
            'sitio_web'          => ['nullable', 'url', 'max:200'],
            'foto'               => ['nullable', 'image', 'max:4096'],
            'productos_top'      => ['nullable', 'array', 'max:5'],
            'productos_top.*'    => ['nullable', 'string', 'max:200'],
            'categorias_json'    => ['nullable', 'array'],
        ]);

        $data = $request->only([
            'nombre_restaurante', 'descripcion', 'telefono',
            'direccion', 'municipio', 'rfc', 'sitio_web',
        ]);

        if ($request->hasFile('foto')) {
            if ($restaurantero->foto_path) {
                Storage::disk('public')->delete($restaurantero->foto_path);
            }
            $data['foto_path'] = $request->file('foto')->store('proveedores/fotos', 'public');
        }

        $data['productos_top']   = array_values(array_filter($request->productos_top ?? []));
        $data['categorias_json'] = $request->categorias_json ?? [];
        $data['solicitado_aprobacion_at'] = now();
        $data['rechazado']       = false;
        $data['motivo_rechazo']  = null;

        $restaurantero->update($data);

        // Notificar a admins
        \App\Models\User::role('admin')->each(fn($a) =>
            Notificacion::crear($a->id, 'info', 'Proveedor requiere aprobación',
                "{$restaurantero->nombre_restaurante} completó su perfil y espera aprobación.")
        );

        return back()->with('success', 'Perfil enviado para revisión. El administrador lo revisará en breve.');
    }
}
