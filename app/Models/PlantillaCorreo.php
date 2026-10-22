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
        return self::sustituir($this->contenido, $vars);
    }

    // Sustituye {{clave}} en un texto y borra los marcadores que nadie
    // rellena: mejor un hueco que "{{nombre_usuario}}" en el correo.
    public static function sustituir(?string $texto, array $vars): string
    {
        $texto = (string) $texto;
        foreach ($vars as $key => $value) {
            $texto = str_replace('{{' . $key . '}}', (string) ($value ?? ''), $texto);
        }
        return preg_replace('/\{\{\s*[a-z0-9_]+\s*\}\}/i', '', $texto);
    }
}
