<?php

namespace App\Http\Controllers;

use App\Mail\ProveedorAprobado;
use App\Mail\ProveedorRechazado;
use App\Models\Horario;
use App\Models\Notificacion;
use App\Models\Servicio;
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
            'productos'          => ['nullable', 'array', 'max:5'],
            'productos.*.nombre' => ['nullable', 'string', 'max:200'],
            'productos.*.descripcion' => ['nullable', 'string', 'max:500'],
            'categorias_json'    => ['nullable', 'array'],
        ]);

        $data = $request->only([
            'nombre_restaurante', 'descripcion', 'telefono',
            'direccion', 'municipio', 'rfc', 'sitio_web',
        ]);

        if ($request->hasFile('foto')) {
            if ($restaurantero->logo_path) {
                Storage::disk('public')->delete($restaurantero->logo_path);
            }
            $data['logo_path'] = $request->file('foto')->store('proveedores/fotos', 'public');
        }

        // Procesar productos: nombre, descripción y foto opcional
        $productosExistentes = $restaurantero->productos_top ?? [];
        $productosNuevos = [];
        foreach ($request->productos ?? [] as $i => $prod) {
            if (empty($prod['nombre'])) continue;
            $item = [
                'nombre'      => $prod['nombre'],
                'descripcion' => $prod['descripcion'] ?? '',
                'foto_path'   => $productosExistentes[$i]['foto_path'] ?? null,
            ];
            // Subir foto si viene
            $fotoKey = "producto_foto_{$i}";
            if ($request->hasFile($fotoKey)) {
                $old = $productosExistentes[$i]['foto_path'] ?? null;
                if ($old) Storage::disk('public')->delete($old);
                $item['foto_path'] = $request->file($fotoKey)->store('proveedores/productos', 'public');
            }
            $productosNuevos[] = $item;
        }
        $data['productos_top']   = $productosNuevos;
        $data['categorias_json'] = $request->categorias_json ?? [];
        $data['solicitado_aprobacion_at'] = now();
        $data['rechazado']       = false;
        $data['motivo_rechazo']  = null;
        // Si estaba aprobado y edita, necesita re-aprobación
        if ($restaurantero->aprobado) {
            $data['aprobado'] = false;
            $data['activo']   = false;
        }

        $restaurantero->update($data);

        // Notificar a admins
        \App\Models\User::role('admin')->each(fn($a) =>
            Notificacion::crear($a->id, 'info', 'Proveedor requiere aprobación',
                "{$restaurantero->nombre_restaurante} completó su perfil y espera aprobación.")
        );

        // Crear servicio y horarios si no existen
        if (!$restaurantero->servicios()->exists()) {
            Servicio::create([
                'restaurantero_id' => $restaurantero->id,
                'nombre'           => 'Mesa de Networking',
                'duracion_minutos' => 30,
                'precio'           => 0,
                'activo'           => true,
            ]);
        }
        if (!$restaurantero->horarios()->exists()) {
            for ($dia = 1; $dia <= 5; $dia++) {
                Horario::create([
                    'restaurantero_id' => $restaurantero->id,
                    'dia_semana'       => $dia,
                    'hora_inicio'      => '09:00:00',
                    'hora_fin'         => '16:00:00',
                    'activo'           => true,
                ]);
            }
        }

        return back()->with('success', 'Perfil enviado para revisión. El administrador lo revisará en breve.');
    }
}
