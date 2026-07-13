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
use Illuminate\Validation\Rule;

class ProveedorPerfilController extends Controller
{
    public function create()
    {
        // El formulario de completar perfil vive dentro del panel del proveedor
        // (Restaurantero/Panel.vue), no en una página independiente.
        return redirect()->route('restaurantero.panel');
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
            'nombre_restaurante'      => ['required', 'string', 'max:200'],
            'razon_social'            => ['nullable', 'string', 'max:200'],
            'nombre_representante'    => ['nullable', 'string', 'max:200'],
            'curp_representante'      => ['nullable', 'string', 'max:18'],
            'fecha_inicio_operaciones'=> ['nullable', 'date'],
            'num_empleados'           => ['nullable', 'integer', 'min:0'],
            'domicilio_en_yucatan'    => ['nullable', 'boolean'],
            'descripcion'             => ['nullable', 'string', 'max:2000'],
            'mercado_meta'            => ['nullable', 'string', 'max:1000'],
            'tiempo_vida_anaquel'     => ['nullable', 'string', 'max:500'],
            'requisitos_alimentos'    => ['nullable', 'array'],
            'apoyo_requisitos'        => ['nullable', 'array'],
            'requiere_refrigeracion'  => ['nullable', 'boolean'],
            'requiere_congelacion'    => ['nullable', 'boolean'],
            'telefono'                => ['nullable', 'string', 'max:20'],
            'direccion'               => ['nullable', 'string', 'max:300'],
            'municipio'               => ['nullable', 'string', 'max:100'],
            'rfc'                     => ['nullable', 'string', 'max:13'],
            'sitio_web'               => ['nullable', 'string', 'max:200'],
            'foto'                    => ['nullable', 'image', 'max:4096'],
            'productos'               => ['nullable', 'array', 'max:5'],
            'productos.*.nombre'      => ['nullable', 'string', 'max:200'],
            'productos.*.descripcion' => ['nullable', 'string', 'max:500'],
            'categorias_json'         => ['nullable', 'array'],
            'acepta_credito'          => ['nullable', 'boolean'],
            'credito_monto_maximo'    => ['nullable', 'numeric', 'min:0'],
            'credito_tiempo_cantidad' => ['nullable', 'integer', 'min:1'],
            'credito_tiempo_unidad'   => ['nullable', 'string', Rule::in(['dias', 'semanas', 'meses'])],
            'credito_a_negociar'      => ['nullable', 'boolean'],
            'pago_contraentrega'      => ['nullable', 'boolean'],
            'factura'                 => ['nullable', 'boolean'],
            'regimen_fiscal'          => ['nullable', 'string', 'max:100'],
            'entrega_domicilio'       => ['nullable', 'boolean'],
            'cobertura_entrega'       => [
                'nullable', 'string',
                Rule::in(['local', 'regional', 'nacional']),
                Rule::requiredIf((bool) $request->boolean('entrega_domicilio')),
            ],
            'forma_entrega'           => [
                'nullable', 'string',
                Rule::in(['programada', 'flexible']),
                Rule::requiredIf((bool) $request->boolean('entrega_domicilio')),
            ],
            'productos.*.capacidad_cantidad' => ['nullable', 'numeric', 'min:0'],
            'productos.*.capacidad_unidad'   => ['nullable', 'string', Rule::in(['piezas', 'cajas', 'litros', 'kilogramos'])],
        ]);

        $data = $request->only([
            'nombre_restaurante', 'razon_social', 'nombre_representante',
            'curp_representante', 'fecha_inicio_operaciones', 'num_empleados',
            'mercado_meta', 'tiempo_vida_anaquel',
            'descripcion', 'telefono', 'direccion', 'municipio', 'rfc', 'sitio_web',
            'credito_tiempo_unidad', 'regimen_fiscal',
        ]);

        if (!empty($data['sitio_web']) && !preg_match('/^https?:\/\//', $data['sitio_web'])) {
            $data['sitio_web'] = 'https://' . $data['sitio_web'];
        }

        $data['domicilio_en_yucatan']   = filter_var($request->domicilio_en_yucatan, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $data['requiere_refrigeracion'] = filter_var($request->requiere_refrigeracion, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $data['requiere_congelacion']   = filter_var($request->requiere_congelacion, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $data['requisitos_alimentos']   = $request->requisitos_alimentos ?? [];
        $data['apoyo_requisitos']       = $request->apoyo_requisitos ?? [];

        $data['acepta_credito']     = filter_var($request->acepta_credito, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        $data['credito_a_negociar'] = filter_var($request->credito_a_negociar, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        $data['pago_contraentrega'] = filter_var($request->pago_contraentrega, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        $data['factura']            = filter_var($request->factura, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        if (!$data['acepta_credito']) {
            $data['credito_monto_maximo']    = null;
            $data['credito_tiempo_cantidad'] = null;
            $data['credito_tiempo_unidad']   = null;
            $data['credito_a_negociar']      = false;
        } else {
            $data['credito_monto_maximo']    = $request->credito_monto_maximo ?: null;
            $data['credito_tiempo_cantidad'] = $request->credito_tiempo_cantidad ?: null;
            $data['credito_tiempo_unidad']   = $request->credito_tiempo_unidad ?: null;
        }
        $data['regimen_fiscal']          = $data['factura'] ? $request->regimen_fiscal : null;

        $data['entrega_domicilio'] = filter_var(
            $request->entrega_domicilio,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
        if (is_null($data['entrega_domicilio'])) {
            $data['entrega_domicilio'] = false;
        }
        $data['cobertura_entrega'] = $data['entrega_domicilio']
            ? $request->cobertura_entrega
            : null;
        $data['forma_entrega'] = $data['entrega_domicilio']
            ? $request->forma_entrega
            : null;

        if ($request->hasFile('foto')) {
            if (!$request->file('foto')->isValid()) {
                return back()->withErrors(['foto' => 'El archivo de foto no pudo subirse. Intenta con una imagen más pequeña (máx. 4MB).']);
            }
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
                'nombre'             => $prod['nombre'],
                'descripcion'        => $prod['descripcion'] ?? '',
                'foto_path'          => $productosExistentes[$i]['foto_path'] ?? null,
                'capacidad_cantidad' => isset($prod['capacidad_cantidad']) && $prod['capacidad_cantidad'] !== ''
                                        ? (float) $prod['capacidad_cantidad'] : null,
                'capacidad_unidad'   => $prod['capacidad_unidad'] ?? null,
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

        if ($restaurantero->perfil_completo) {
            \App\Models\User::role('admin')->each(fn($a) =>
                Notificacion::crear($a->id, 'info', 'Nuevo proveedor con perfil completo',
                    "{$restaurantero->nombre_restaurante} completó su perfil y fue aprobado automáticamente.")
            );

            return back()->with('success', '¡Perfil completo! Fuiste aprobado automáticamente' .
                (\App\Models\Evento::activo() ? ' y ya apareces en el directorio del evento activo.' : '.'));
        }

        return back()->with('success', 'Progreso guardado. Completa todos los campos requeridos para que tu perfil se apruebe automáticamente.');
    }
}
