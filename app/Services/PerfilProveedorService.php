<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Http\Controllers\CompletarPerfilController;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Perfil de NEGOCIO del proveedor (tabla restauranteros). Antes vivia entero
 * en ProveedorPerfilController::store; ahora lo usan ese controlador (el
 * formulario de /user/profile), RestauranteroPanelController y la API v1
 * (/proveedor/perfil, /proveedor/logo, /proveedor/productos) con las MISMAS
 * reglas, limpiezas y avisos.
 *
 * La web guarda SIEMPRE el formulario completo (cada seccion de Perfil/Show.vue
 * envia todos los campos). La API emula eso: parte de valoresFormulario() (lo
 * que la web precarga) y encima pone lo que manda la app.
 */
class PerfilProveedorService
{
    public const MAX_PRODUCTOS = 5;
    public const FOTO_MIMES = ['jpg', 'jpeg', 'png', 'webp'];
    public const FOTO_MAX_KB = 8192;

    public const TIPOS_OFERTA = ['producto', 'servicio', 'ambos'];
    public const CREDITO_UNIDADES = ['dias', 'semanas', 'meses'];
    public const COBERTURAS = ['local', 'regional', 'nacional'];
    public const FORMAS_ENTREGA = ['programada', 'flexible'];
    public const CAPACIDAD_UNIDADES = ['piezas', 'cajas', 'litros', 'kilogramos'];

    /** Opciones de los checkboxes de certificaciones y de apoyo IYEM (OPCIONES_CERTS en Perfil/Show.vue). */
    public const CERTIFICACIONES = [
        'Tabla Nutrimental',
        'Etiquetado de acuerdo a la NOM',
        'Código de barras',
        'Registro de marca',
        'Certificados de calidad/inocuidad',
    ];

    /** Opciones del <select> de la web. El backend acepta cualquier texto de hasta 100 caracteres. */
    public const REGIMENES_FISCALES = ['Persona Física', 'Persona Moral', 'RESICO'];

    /**
     * Secciones del formulario de la web (sidenav de Perfil/Show.vue) y los
     * campos que cada una envia. productos y foto (logo) tienen endpoints propios en la API.
     */
    public const SECCIONES = [
        'negocio' => [
            'etiqueta' => 'Mi negocio',
            'campos'   => ['nombre_restaurante', 'razon_social', 'nombre_representante', 'curp_representante', 'rfc',
                'num_empleados', 'fecha_inicio_operaciones', 'municipio', 'categorias_json', 'descripcion',
                'mercado_meta', 'domicilio_en_yucatan'],
        ],
        'oferta' => [
            'etiqueta' => 'Productos y servicios',
            'campos'   => ['tipo_oferta', 'tiempo_vida_anaquel', 'tiene_certificaciones_producto', 'certificaciones_cumple',
                'certificaciones_cumple_otros', 'apoyo_iyem', 'apoyo_iyem_otros', 'requiere_refrigeracion',
                'requiere_congelacion', 'tiene_certificaciones_servicio', 'certificaciones_servicio_detalle'],
        ],
        'entrega' => [
            'etiqueta' => 'Entrega y pago',
            'campos'   => ['entrega_domicilio', 'cobertura_entrega', 'forma_entrega', 'pago_contraentrega', 'factura',
                'regimen_fiscal', 'acepta_credito', 'credito_monto_maximo', 'credito_tiempo_cantidad',
                'credito_tiempo_unidad', 'credito_a_negociar'],
        ],
        'contacto' => [
            'etiqueta' => 'Contacto y redes',
            'campos'   => ['telefono', 'sitio_web', 'direccion'],
        ],
    ];

    /** Reglas EXACTAS de POST /proveedor/completar-perfil. */
    public static function reglas(array $datos): array
    {
        // $request->boolean('entrega_domicilio') de la web
        $entrega = filter_var($datos['entrega_domicilio'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $foto    = ['nullable', 'image', 'mimes:' . implode(',', self::FOTO_MIMES), 'max:' . self::FOTO_MAX_KB];

        $rules = [
            'nombre_restaurante'      => ['required', 'string', 'max:200'],
            'razon_social'            => ['nullable', 'string', 'max:200'],
            'nombre_representante'    => ['nullable', 'string', 'max:200'],
            'curp_representante'      => ['nullable', 'string', 'max:18'],
            'fecha_inicio_operaciones'=> ['nullable', 'date'],
            'num_empleados'           => ['nullable', 'integer', 'min:0'],
            'domicilio_en_yucatan'    => ['nullable', 'boolean'],
            'descripcion'             => ['nullable', 'string', 'max:2000'],
            'mercado_meta'            => ['nullable', 'string', 'max:1000'],
            'tipo_oferta'             => ['nullable', Rule::in(self::TIPOS_OFERTA)],

            // Certificaciones producto
            'tiene_certificaciones_producto'   => ['nullable', 'boolean'],
            'certificaciones_cumple'           => ['nullable', 'array'],
            'certificaciones_cumple.*'         => ['string', 'max:100'],
            'certificaciones_cumple_otros'     => ['nullable', 'string', 'max:1000'],
            'apoyo_iyem'                        => ['nullable', 'array'],
            'apoyo_iyem.*'                      => ['string', 'max:100'],
            'apoyo_iyem_otros'                  => ['nullable', 'string', 'max:1000'],

            // Certificaciones servicio
            'tiene_certificaciones_servicio'   => ['nullable', 'boolean'],
            'certificaciones_servicio_detalle' => ['nullable', 'string', 'max:2000'],

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
            'foto'                    => $foto,
            'productos'               => ['nullable', 'array', 'max:' . self::MAX_PRODUCTOS],
            'productos.*.nombre'      => ['nullable', 'string', 'max:200'],
            'productos.*.descripcion' => ['nullable', 'string', 'max:500'],
            'categorias_json'         => ['nullable', 'array'],
            'acepta_credito'          => ['nullable', 'boolean'],
            'credito_monto_maximo'    => ['nullable', 'numeric', 'min:0'],
            'credito_tiempo_cantidad' => ['nullable', 'integer', 'min:1'],
            'credito_tiempo_unidad'   => ['nullable', 'string', Rule::in(self::CREDITO_UNIDADES)],
            'credito_a_negociar'      => ['nullable', 'boolean'],
            'pago_contraentrega'      => ['nullable', 'boolean'],
            'factura'                 => ['nullable', 'boolean'],
            'regimen_fiscal'          => ['nullable', 'string', 'max:100'],
            'entrega_domicilio'       => ['nullable', 'boolean'],
            'cobertura_entrega'       => [
                'nullable', 'string',
                Rule::in(self::COBERTURAS),
                Rule::requiredIf($entrega),
            ],
            'forma_entrega'           => [
                'nullable', 'string',
                Rule::in(self::FORMAS_ENTREGA),
                Rule::requiredIf($entrega),
            ],
            'productos.*.capacidad_cantidad' => ['nullable', 'numeric', 'min:0'],
            'productos.*.capacidad_unidad'   => ['nullable', 'string', Rule::in(self::CAPACIDAD_UNIDADES)],
        ];

        foreach (range(0, self::MAX_PRODUCTOS - 1) as $i) {
            $rules["producto_foto_{$i}"] = $foto;
        }

        return $rules;
    }

    /**
     * El panel web crea un negocio vacio (no aprobado) la primera vez que el
     * proveedor entra, con su servicio y horarios por defecto.
     */
    public function asegurarNegocio(User $user): Restaurantero
    {
        $restaurantero = $user->restaurantero;

        if (!$restaurantero) {
            $restaurantero = $user->restaurantero()->create([
                'edicion_id'               => Evento::activo()?->id,
                'nombre_restaurante'       => $user->name . ' — Negocio',
                'activo'                   => false,
                'aprobado'                 => false,
                'solicitado_aprobacion_at' => now(),
            ]);
            $user->setRelation('restaurantero', $restaurantero);
            $this->asegurarServicioYHorarios($restaurantero);
        }

        return $restaurantero;
    }

    /**
     * Guarda el formulario COMPLETO del perfil de negocio (lo que hacia
     * ProveedorPerfilController::store).
     *
     * @param  array  $datos     campos del formulario (sin archivos), como $request->except(archivos)
     * @param  array<string, UploadedFile|null>  $archivos  'foto' (logo) y 'producto_foto_{i}'
     * @param  array<int, ?string>|null  $fotosExistentes  foto_path ya guardada de cada producto por
     *         posicion del NUEVO listado. null = la regla de la web: la foto que tenia el producto que
     *         estaba en esa misma posicion (la API la pasa al borrar un producto para no correr las fotos).
     * @return array{restaurantero: Restaurantero, perfil_completo: bool, mensaje: string}
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws ReglaDeNegocioException  foto que no pudo subirse / foto de producto sin nombre
     */
    public function guardar(User $user, array $datos, array $archivos = [], ?array $fotosExistentes = null): array
    {
        $restaurantero = $user->restaurantero;

        if (!$restaurantero) {
            // Crear el restaurantero base si no existe
            $restaurantero = $user->restaurantero()->create([
                'nombre_restaurante' => $datos['nombre_restaurante'] ?? null,
                'activo'             => false,
                'aprobado'           => false,
            ]);
            $user->setRelation('restaurantero', $restaurantero);
        }

        Validator::make(array_merge($datos, $archivos), self::reglas($datos))->validate();

        $data = Arr::only($datos, [
            'nombre_restaurante', 'razon_social', 'nombre_representante',
            'curp_representante', 'fecha_inicio_operaciones', 'num_empleados',
            'mercado_meta', 'tiempo_vida_anaquel',
            'descripcion', 'telefono', 'direccion', 'municipio', 'rfc', 'sitio_web',
            'credito_tiempo_unidad', 'regimen_fiscal',
            'tiene_certificaciones_producto', 'certificaciones_cumple', 'certificaciones_cumple_otros',
            'apoyo_iyem', 'apoyo_iyem_otros',
            'tiene_certificaciones_servicio', 'certificaciones_servicio_detalle',
        ]);
        $valor = fn (string $campo) => $datos[$campo] ?? null;
        $bool  = fn (string $campo) => filter_var($valor($campo), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if (!empty($data['sitio_web']) && !preg_match('/^https?:\/\//', $data['sitio_web'])) {
            $data['sitio_web'] = 'https://' . $data['sitio_web'];
        }

        $data['domicilio_en_yucatan']   = $bool('domicilio_en_yucatan');
        $data['requiere_refrigeracion'] = $bool('requiere_refrigeracion');
        $data['requiere_congelacion']   = $bool('requiere_congelacion');
        $data['requisitos_alimentos']   = $valor('requisitos_alimentos') ?? [];
        $data['apoyo_requisitos']       = $valor('apoyo_requisitos') ?? [];

        $tipoOferta = $valor('tipo_oferta') ?? 'producto';
        $data['tipo_oferta'] = $tipoOferta;

        $data['tiene_certificaciones_producto'] = $bool('tiene_certificaciones_producto');
        $data['certificaciones_cumple']         = $valor('certificaciones_cumple') ?? [];
        $data['apoyo_iyem']                     = $valor('apoyo_iyem') ?? [];
        $data['tiene_certificaciones_servicio'] = $bool('tiene_certificaciones_servicio');

        // Si no incluye producto, limpiar campos de producto
        if (!in_array($tipoOferta, ['producto', 'ambos'])) {
            $data['tiempo_vida_anaquel']            = null;
            $data['requiere_refrigeracion']         = false;
            $data['requiere_congelacion']           = false;
            $data['tiene_certificaciones_producto'] = null;
            $data['certificaciones_cumple']         = null;
            $data['certificaciones_cumple_otros']   = null;
            $data['apoyo_iyem']                     = null;
            $data['apoyo_iyem_otros']                = null;
        }

        // Si no incluye servicio, limpiar campos de servicio
        if (!in_array($tipoOferta, ['servicio', 'ambos'])) {
            $data['tiene_certificaciones_servicio']   = null;
            $data['certificaciones_servicio_detalle'] = null;
        }

        $data['acepta_credito']     = $bool('acepta_credito') ?? false;
        $data['credito_a_negociar'] = $bool('credito_a_negociar') ?? false;
        $data['pago_contraentrega'] = $bool('pago_contraentrega') ?? false;
        $data['factura']            = $bool('factura') ?? false;

        if (!$data['acepta_credito']) {
            $data['credito_monto_maximo']    = null;
            $data['credito_tiempo_cantidad'] = null;
            $data['credito_tiempo_unidad']   = null;
            $data['credito_a_negociar']      = false;
        } else {
            $data['credito_monto_maximo']    = $valor('credito_monto_maximo') ?: null;
            $data['credito_tiempo_cantidad'] = $valor('credito_tiempo_cantidad') ?: null;
            $data['credito_tiempo_unidad']   = $valor('credito_tiempo_unidad') ?: null;
        }
        $data['regimen_fiscal'] = $data['factura'] ? $valor('regimen_fiscal') : null;

        $data['entrega_domicilio'] = $bool('entrega_domicilio');
        if (is_null($data['entrega_domicilio'])) {
            $data['entrega_domicilio'] = false;
        }
        $data['cobertura_entrega'] = $data['entrega_domicilio'] ? $valor('cobertura_entrega') : null;
        $data['forma_entrega']     = $data['entrega_domicilio'] ? $valor('forma_entrega') : null;

        if ($foto = $this->archivo($archivos, 'foto')) {
            if (!$foto->isValid()) {
                throw new ReglaDeNegocioException('El archivo de foto no pudo subirse. Intenta con una imagen más pequeña (máx. 8MB).', 'foto');
            }
            if ($restaurantero->logo_path) {
                Storage::disk('public')->delete($restaurantero->logo_path);
            }
            $data['logo_path'] = $foto->store('proveedores/fotos', 'public');
        }

        // Procesar productos: nombre, descripción y foto opcional
        $productosExistentes = $restaurantero->productos_top ?? [];
        $fotoExistente = fn (int $i) => $fotosExistentes !== null
            ? ($fotosExistentes[$i] ?? null)
            : ($productosExistentes[$i]['foto_path'] ?? null);
        $productosNuevos = [];
        foreach ($valor('productos') ?? [] as $i => $prod) {
            // Una fila sin nombre se descarta, pero si traia foto habria que
            // tirarla en silencio y correr los indices de las demas: el usuario
            // veria su imagen desaparecer sin explicacion. Mejor avisar.
            if (empty($prod['nombre'])) {
                if ($this->archivo($archivos, "producto_foto_{$i}")) {
                    throw new ReglaDeNegocioException('Ponle nombre al producto ' . ($i + 1)
                        . ' antes de subirle una foto.', "producto_foto_{$i}");
                }
                continue;
            }
            $item = [
                'nombre'             => $prod['nombre'],
                'descripcion'        => $prod['descripcion'] ?? '',
                'foto_path'          => $fotoExistente($i),
                'capacidad_cantidad' => isset($prod['capacidad_cantidad']) && $prod['capacidad_cantidad'] !== ''
                                        ? (float) $prod['capacidad_cantidad'] : null,
                'capacidad_unidad'   => $prod['capacidad_unidad'] ?? null,
            ];
            // Subir foto si viene
            $fotoKey = "producto_foto_{$i}";
            if ($fotoProducto = $this->archivo($archivos, $fotoKey)) {
                // hasFile() no comprueba el codigo de error de PHP: una subida
                // truncada llegaba hasta store() y reventaba a mitad del guardado.
                if (!$fotoProducto->isValid()) {
                    throw new ReglaDeNegocioException('La foto del producto ' . ($i + 1)
                        . ' no pudo subirse. Intenta con una imagen mas pequena (max. 8MB).', $fotoKey);
                }
                $old = $fotoExistente($i);
                if ($old) Storage::disk('public')->delete($old);
                $item['foto_path'] = $fotoProducto->store('proveedores/productos', 'public');
            }
            $productosNuevos[] = $item;
        }
        $data['productos_top']   = $productosNuevos;
        $data['categorias_json'] = $valor('categorias_json') ?? [];
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
        $this->asegurarServicioYHorarios($restaurantero);

        if ($restaurantero->perfil_completo) {
            User::role('admin')->each(fn ($a) =>
                Notificacion::crear($a->id, 'info', 'Nuevo proveedor con perfil completo',
                    "{$restaurantero->nombre_restaurante} completó su perfil y fue aprobado automáticamente.")
            );

            return [
                'restaurantero'   => $restaurantero,
                'perfil_completo' => true,
                'mensaje'         => '¡Perfil completo! Fuiste aprobado automáticamente' .
                    (Evento::activo() ? ' y ya apareces en el directorio del evento activo.' : '.'),
            ];
        }

        return [
            'restaurantero'   => $restaurantero,
            'perfil_completo' => false,
            'mensaje'         => 'Progreso guardado. Completa todos los campos requeridos para que tu perfil se apruebe automáticamente.',
        ];
    }

    /**
     * Lo que la web precarga en el formulario (useForm de Perfil/Show.vue), con
     * sus mismos valores por defecto. Es la base sobre la que la API aplica un
     * guardado parcial: la web siempre envia el formulario entero.
     */
    public function valoresFormulario(User $user, ?Restaurantero $r = null): array
    {
        $r ??= $user->restaurantero;
        // Como llega a la web tras TrimStrings + ConvertEmptyStringsToNull.
        $v = function (string $campo, $defecto = null) use ($r) {
            $valor = $r?->{$campo};
            if (is_string($valor)) {
                $valor = trim($valor) === '' ? null : trim($valor);
            }

            return $valor ?? $defecto;
        };

        return [
            'nombre_restaurante'               => $v('nombre_restaurante'),
            'razon_social'                     => $v('razon_social'),
            'nombre_representante'             => $v('nombre_representante'),
            'curp_representante'               => $v('curp_representante'),
            'fecha_inicio_operaciones'         => $v('fecha_inicio_operaciones'),
            'num_empleados'                    => $v('num_empleados'),
            'domicilio_en_yucatan'             => $v('domicilio_en_yucatan', false),
            'descripcion'                      => $v('descripcion'),
            'mercado_meta'                     => $v('mercado_meta'),
            'tipo_oferta'                      => $v('tipo_oferta', 'producto'),
            'tiempo_vida_anaquel'              => $v('tiempo_vida_anaquel'),
            'tiene_certificaciones_producto'   => $v('tiene_certificaciones_producto'),
            'certificaciones_cumple'           => $v('certificaciones_cumple', []),
            'certificaciones_cumple_otros'     => $v('certificaciones_cumple_otros'),
            'apoyo_iyem'                       => $v('apoyo_iyem', []),
            'apoyo_iyem_otros'                 => $v('apoyo_iyem_otros'),
            'requiere_refrigeracion'           => $v('requiere_refrigeracion'),
            'requiere_congelacion'             => $v('requiere_congelacion'),
            'tiene_certificaciones_servicio'   => $v('tiene_certificaciones_servicio'),
            'certificaciones_servicio_detalle' => $v('certificaciones_servicio_detalle'),
            'telefono'                         => $r?->telefono ?? $user->telefono,
            'municipio'                        => $v('municipio'),
            'direccion'                        => $v('direccion'),
            'rfc'                              => $r?->rfc ?? $user->rfc,
            'sitio_web'                        => $v('sitio_web'),
            'acepta_credito'                   => $v('acepta_credito', false),
            'credito_monto_maximo'             => $v('credito_monto_maximo'),
            'credito_tiempo_cantidad'          => $v('credito_tiempo_cantidad'),
            'credito_tiempo_unidad'            => $v('credito_tiempo_unidad', 'dias'),
            'credito_a_negociar'               => $v('credito_a_negociar', false),
            'pago_contraentrega'               => $v('pago_contraentrega', false),
            'factura'                          => $v('factura', false),
            'regimen_fiscal'                   => $v('regimen_fiscal'),
            'entrega_domicilio'                => $v('entrega_domicilio', false),
            'cobertura_entrega'                => $v('cobertura_entrega'),
            'forma_entrega'                    => $v('forma_entrega'),
            'categorias_json'                  => $v('categorias_json', []),
            'productos'                        => $this->productosFormulario($r),
        ];
    }

    /** productos_top como filas del formulario (formato viejo: lista de nombres). */
    public function productosFormulario(?Restaurantero $r): array
    {
        return collect(is_array($r?->productos_top) ? $r->productos_top : [])
            ->map(fn ($p) => is_string($p)
                ? ['nombre' => $p, 'descripcion' => '', 'capacidad_cantidad' => null, 'capacidad_unidad' => null]
                : [
                    'nombre'             => $p['nombre'] ?? '',
                    'descripcion'        => $p['descripcion'] ?? '',
                    'capacidad_cantidad' => $p['capacidad_cantidad'] ?? null,
                    'capacidad_unidad'   => $p['capacidad_unidad'] ?? null,
                ])
            ->values()
            ->all();
    }

    /** foto_path de cada producto guardado, por posicion. */
    public function fotosProductos(?Restaurantero $r): array
    {
        return collect(is_array($r?->productos_top) ? $r->productos_top : [])
            ->map(fn ($p) => is_array($p) ? ($p['foto_path'] ?? null) : null)
            ->values()
            ->all();
    }

    /**
     * Lo que le falta al negocio para que Restaurantero::calcularPerfilCompleto()
     * de true. Mismas condiciones, en el mismo orden, con la seccion donde se
     * captura cada dato. Vacio <=> perfil completo.
     *
     * @return list<array{campo: string, etiqueta: string, seccion: string}>
     */
    public function faltantes(?Restaurantero $r): array
    {
        $f = [];
        $falta = function (string $campo, string $etiqueta, string $seccion) use (&$f) {
            $f[] = ['campo' => $campo, 'etiqueta' => $etiqueta, 'seccion' => $seccion];
        };

        if (!filled($r?->nombre_restaurante)) $falta('nombre_restaurante', 'Nombre del negocio', 'negocio');
        if (!filled($r?->descripcion))        $falta('descripcion', 'Descripción', 'negocio');
        if (!filled($r?->telefono))           $falta('telefono', 'Teléfono', 'contacto');
        if (!filled($r?->municipio))          $falta('municipio', 'Municipio', 'negocio');
        if (!filled($r?->logo_path))          $falta('logo', 'Logo', 'contacto');

        $tieneProducto = $r && !empty($r->productos_top)
            && collect($r->productos_top)->contains(fn ($p) => filled($p['nombre'] ?? ($p ?? null)));
        if (!$tieneProducto) $falta('productos', 'Al menos 1 producto', 'oferta');

        if ($r?->factura) {
            if (!filled($r->rfc))            $falta('rfc', 'RFC (para facturar)', 'negocio');
            if (!filled($r->regimen_fiscal)) $falta('regimen_fiscal', 'Régimen fiscal (para facturar)', 'entrega');
        }

        if ($r?->acepta_credito && !$r->credito_a_negociar) {
            if (!filled($r->credito_monto_maximo))    $falta('credito_monto_maximo', 'Monto máximo de crédito', 'entrega');
            if (!filled($r->credito_tiempo_cantidad)) $falta('credito_tiempo_cantidad', 'Plazo del crédito', 'entrega');
            if (!filled($r->credito_tiempo_unidad))   $falta('credito_tiempo_unidad', 'Unidad del plazo del crédito', 'entrega');
        }

        if (is_null($r?->entrega_domicilio)) {
            $falta('entrega_domicilio', 'Logística (¿entrega a domicilio?)', 'entrega');
        } elseif ($r->entrega_domicilio) {
            if (!filled($r->cobertura_entrega)) $falta('cobertura_entrega', 'Cobertura de entrega', 'entrega');
            if (!filled($r->forma_entrega))     $falta('forma_entrega', 'Forma de entrega', 'entrega');
        }

        return $f;
    }

    /** El porcentaje de la barra del panel web (perfilPorcentaje de Restaurantero/Panel.vue). */
    public function porcentaje(?Restaurantero $r): int
    {
        if (!$r) {
            return 0;
        }

        $pct = 0;
        if ($r->nombre_restaurante)            $pct += 10;
        if ($r->descripcion)                   $pct += 10;
        if ($r->telefono)                      $pct += 8;
        if ($r->municipio)                     $pct += 7;
        if ($r->logo_path)                     $pct += 15;
        if (!empty($r->productos_top))         $pct += 15;
        if ($r->acepta_credito !== null)       $pct += 5;
        if ($r->pago_contraentrega !== null)   $pct += 5;
        if ($r->factura !== null)              $pct += 5;
        if (!$r->factura || ($r->rfc && $r->regimen_fiscal)) $pct += 10;
        if ($r->entrega_domicilio !== null)    $pct += 10;

        return min($pct, 100);
    }

    /** aprobado | rechazado | pendiente | sin_perfil (el panel web decide su banner con los mismos campos). */
    public function estado(?Restaurantero $r): string
    {
        return match (true) {
            !$r           => 'sin_perfil',
            $r->aprobado  => 'aprobado',
            $r->rechazado => 'rechazado',
            default       => 'pendiente',
        };
    }

    /** Catalogos del formulario (los mismos valores que validan reglas()). */
    public function catalogos(): array
    {
        $municipios = CompletarPerfilController::MUNICIPIOS;
        sort($municipios);

        return [
            'municipios'        => array_values($municipios),
            'categorias'        => Restaurantero::categoriasActivas(),
            'tipos_oferta'      => [
                ['valor' => 'producto', 'etiqueta' => 'Productos'],
                ['valor' => 'servicio', 'etiqueta' => 'Servicios'],
                ['valor' => 'ambos',    'etiqueta' => 'Ambos'],
            ],
            'certificaciones'   => self::CERTIFICACIONES,
            'coberturas_entrega'=> [
                ['valor' => 'local',    'etiqueta' => 'Local (Mérida)'],
                ['valor' => 'regional', 'etiqueta' => 'Estatal (Yucatán)'],
                ['valor' => 'nacional', 'etiqueta' => 'Nacional'],
            ],
            'formas_entrega'    => [
                ['valor' => 'programada', 'etiqueta' => 'Programada'],
                ['valor' => 'flexible',   'etiqueta' => 'Flexible'],
            ],
            'regimenes_fiscales'=> self::REGIMENES_FISCALES,
            'credito_unidades'  => [
                ['valor' => 'dias',    'etiqueta' => 'días'],
                ['valor' => 'semanas', 'etiqueta' => 'semanas'],
                ['valor' => 'meses',   'etiqueta' => 'meses'],
            ],
            'capacidad_unidades'=> self::CAPACIDAD_UNIDADES,
            'secciones'         => collect(self::SECCIONES)->map(fn ($s, $clave) => [
                'clave'    => $clave,
                'etiqueta' => $s['etiqueta'],
                'campos'   => $s['campos'],
            ])->values()->all(),
            'limites'           => [
                'max_productos'  => self::MAX_PRODUCTOS,
                'foto_max_kb'    => self::FOTO_MAX_KB,
                'foto_formatos'  => self::FOTO_MIMES,
            ],
        ];
    }

    private function asegurarServicioYHorarios(Restaurantero $restaurantero): void
    {
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
    }

    /** $request->hasFile(): hay archivo con ruta (no comprueba el codigo de error; eso lo hace isValid()). */
    private function archivo(array $archivos, string $clave): ?UploadedFile
    {
        $f = $archivos[$clave] ?? null;

        return $f instanceof UploadedFile && $f->getPath() !== '' ? $f : null;
    }
}
