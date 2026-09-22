<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Support\Facades\Storage;

/** URL absoluta de un archivo del disco publico (logos, fotos de producto). */
trait UrlPublica
{
    protected static function urlPublica(?string $ruta): ?string
    {
        return filled($ruta) ? Storage::disk('public')->url($ruta) : null;
    }
}
