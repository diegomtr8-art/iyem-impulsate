<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categorias';
    protected $fillable = ['nombre', 'orden', 'activo'];
    protected function casts(): array { return ['activo' => 'boolean']; }

    public function scopeActivas($query) { return $query->where('activo', true)->orderBy('orden'); }

    public function restauranteros()
    {
        return $this->hasMany(Restaurantero::class, 'categoria', 'nombre');
    }
}
