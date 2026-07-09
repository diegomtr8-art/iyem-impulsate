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
            'solicitado_aprobacion_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function ($r) {
            $r->perfil_completo = $r->calcularPerfilCompleto();
        });
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
