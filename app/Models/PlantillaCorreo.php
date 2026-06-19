<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PlantillaCorreo extends Model
{
    protected $table = 'plantillas_correo';

    protected $fillable = [
        'clave', 'nombre', 'asunto', 'contenido',
        'tipo_destinatario', 'es_sistema', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_sistema' => 'boolean',
            'activo'     => 'boolean',
        ];
    }

    public function scopeActiva(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public static function paraClave(string $clave): ?self
    {
        try {
            return static::where('clave', $clave)->where('activo', true)->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    // Reemplaza placeholders {{clave}} en el contenido con los valores dados.
    public function reemplazar(array $vars): string
    {
        $contenido = $this->contenido;
        foreach ($vars as $key => $value) {
            $contenido = str_replace('{{' . $key . '}}', (string) ($value ?? ''), $contenido);
        }
        return $contenido;
    }
}
