<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurantero extends Model
{
    use HasFactory;

    public static array $categorias = [
        'Alimentos y Bebidas',
        'Tecnología',
        'Salud y Bienestar',
        'Servicios Profesionales',
        'Manufactura',
        'Comercio',
        'Construcción',
        'Educación',
        'Turismo',
        'Logística',
        'Finanzas',
        'Textiles',
        'Artesanías',
        'Muebles y decoración',
        'Servicios impresos',
        'Vinos y licores',
        'Entretenimiento',
        'Otro',
    ];

    public static function categoriasActivas(): array
    {
        return \App\Models\Categoria::activas()->pluck('nombre')->toArray();
    }

    protected $fillable = [
        'edicion_id',
        'user_id',
        'nombre_restaurante',
        'razon_social',
        'nombre_representante',
        'curp_representante',
        'fecha_inicio_operaciones',
        'num_empleados',
        'domicilio_en_yucatan',
        'descripcion',
        'mercado_meta',
        'tipo_oferta',
        'tiene_certificaciones_producto',
        'certificaciones_cumple',
        'certificaciones_cumple_otros',
        'apoyo_iyem',
        'apoyo_iyem_otros',
        'tiene_certificaciones_servicio',
        'certificaciones_servicio_detalle',
        'tiempo_vida_anaquel',
        'requisitos_alimentos',
        'apoyo_requisitos',
        'requiere_refrigeracion',
        'requiere_congelacion',
        'telefono',
        'direccion',
        'municipio',
        'categoria',
        'logo_path',
        'foto_path',
        'productos_top',
        'categorias_json',
        'rfc',
        'sitio_web',
        'redes_sociales',
        'activo',
        'aprobado',
        'rechazado',
        'motivo_rechazo',
        'solicitado_aprobacion_at',
        'acepta_credito',
        'credito_monto_maximo',
        'credito_tiempo_cantidad',
        'credito_tiempo_unidad',
        'credito_a_negociar',
        'pago_contraentrega',
        'factura',
        'regimen_fiscal',
        'entrega_domicilio',
        'cobertura_entrega',
        'forma_entrega',
    ];

    protected function casts(): array
    {
        return [
            'activo'                   => 'boolean',
            'aprobado'                 => 'boolean',
            'rechazado'                => 'boolean',
            'domicilio_en_yucatan'     => 'boolean',
            'requiere_refrigeracion'   => 'boolean',
            'requiere_congelacion'     => 'boolean',
            'acepta_credito'           => 'boolean',
            'credito_a_negociar'       => 'boolean',
            'pago_contraentrega'       => 'boolean',
            'factura'                  => 'boolean',
            'entrega_domicilio'        => 'boolean',
            'perfil_completo'          => 'boolean',
            'credito_monto_maximo'     => 'decimal:2',
            'productos_top'            => 'array',
            'categorias_json'          => 'array',
            'redes_sociales'           => 'array',
            'requisitos_alimentos'     => 'array',
            'apoyo_requisitos'         => 'array',
            'tiene_certificaciones_producto' => 'boolean',
            'certificaciones_cumple'         => 'array',
            'apoyo_iyem'                     => 'array',
            'tiene_certificaciones_servicio' => 'boolean',
            'solicitado_aprobacion_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function ($r) {
            $r->perfil_completo = $r->calcularPerfilCompleto();
        });

        static::saved(function ($r) {
            // El guard !$r->rechazado evita que un rechazo manual del admin (que en la
            // misma actualización pone aprobado=false) sea revertido instantáneamente
            // por este mismo hook cuando el perfil ya estaba completo.
            if ($r->perfil_completo && !$r->aprobado && !$r->rechazado) {
                $r->autoAprobar();
            }
        });
    }

    /**
     * Perfil completo = PERFIL aprobado automáticamente (sin revisión manual del
     * admin) para que el proveedor aparezca en el directorio público.
     *
     * Su inscripción al ENCUENTRO se crea en estado='pendiente': quién entra al
     * evento lo decide el admin en /admin/eventos/{evento}/solicitudes.
     */
    protected function autoAprobar(): void
    {
        $evento = Evento::activo();

        $this->forceFill([
            'aprobado'       => true,
            'rechazado'      => false,
            'motivo_rechazo' => null,
            'activo'         => true,
            'edicion_id'     => $evento?->id ?? $this->edicion_id,
        ])->saveQuietly();

        if ($this->user && !$this->user->perfil_completo) {
            $this->user->update(['perfil_completo' => true]);
        }

        // El PERFIL se aprueba solo (arriba: aprobado=true, activo=true) para que
        // el proveedor aparezca en el directorio público. Pero su inscripción al
        // ENCUENTRO queda PENDIENTE: la decide el admin en /admin/eventos/{id}/solicitudes.
        if ($evento && $evento->tipo_evento === 'encuentro_negocios') {
            $registro = \Illuminate\Support\Facades\DB::table('evento_usuario')
                ->where('evento_id', $evento->id)
                ->where('user_id', $this->user_id)
                ->where('tipo', 'proveedor')
                ->first();

            if (!$registro) {
                \Illuminate\Support\Facades\DB::table('evento_usuario')->insert([
                    'evento_id'     => $evento->id,
                    'user_id'       => $this->user_id,
                    'tipo'          => 'proveedor',
                    'estado'        => 'pendiente',
                    'respondido_at' => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                foreach (\App\Models\User::role('admin')->get() as $admin) {
                    Notificacion::crear(
                        $admin->id,
                        'solicitud_registro',
                        'Nueva solicitud de proveedor',
                        ($this->user?->name ?? 'Un proveedor') . ' completó su perfil y solicita unirse al evento "' . $evento->nombre . '".'
                    );
                }
            }
            // Si ya existe un registro NO se toca: no se puede re-aprobar por
            // encima de una decisión del admin.
        }

        if ($this->user?->email) {
            try {
                \Illuminate\Support\Facades\Mail::to($this->user->email)->send(new \App\Mail\ProveedorAprobado($this));
            } catch (\Throwable $e) {
                \Log::warning('Error enviando correo de auto-aprobación de proveedor: ' . $e->getMessage());
            }
        }

        if ($this->user) {
            $esEncuentro = $evento && $evento->tipo_evento === 'encuentro_negocios';

            Notificacion::crear(
                $this->user->id,
                'solicitud_aprobada',
                '🎉 ¡Tu perfil está completo!',
                $esEncuentro
                    ? "Tu perfil quedó completo y ya apareces en el directorio de proveedores. Tu solicitud para el evento \"{$evento->nombre}\" está en revisión: te avisaremos cuando el equipo la apruebe."
                    : 'Tu perfil está completo y ya apareces en el directorio de proveedores.'
            );
        }
    }

    public function calcularPerfilCompleto(): bool
    {
        $infoBasica = filled($this->nombre_restaurante)
            && filled($this->descripcion)
            && filled($this->telefono)
            && filled($this->municipio);

        $tieneLogo = filled($this->logo_path);

        $tieneProducto = !empty($this->productos_top)
            && collect($this->productos_top)->contains(
                fn($p) => filled($p['nombre'] ?? ($p ?? null))
            );

        $facturaOk = !$this->factura
            || (filled($this->rfc) && filled($this->regimen_fiscal));

        $creditoOk = !$this->acepta_credito
            || $this->credito_a_negociar
            || (
                filled($this->credito_monto_maximo)
                && filled($this->credito_tiempo_cantidad)
                && filled($this->credito_tiempo_unidad)
            );

        $logisticaOk = !is_null($this->entrega_domicilio)
            && (
                !$this->entrega_domicilio
                || (filled($this->cobertura_entrega) && filled($this->forma_entrega))
            );

        return $infoBasica && $tieneLogo && $tieneProducto
            && $facturaOk && $creditoOk && $logisticaOk;
    }

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'edicion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function servicios()
    {
        return $this->hasMany(Servicio::class);
    }

    public function horarios()
    {
        return $this->hasMany(Horario::class);
    }

    public function citas()
    {
        return $this->hasMany(Cita::class);
    }
}
