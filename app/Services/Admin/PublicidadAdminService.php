<?php

namespace App\Services\Admin;

use App\Models\Publicidad;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Publicidad (popup de la portada, solo super-admin): alta con imagen,
 * edicion, activar/desactivar y baja. La vigencia la dan fecha_inicio y
 * fecha_fin (Publicidad::scopeVigente). Lo usan SuperAdmin\PublicidadController
 * (web) y la API v1.
 */
class PublicidadAdminService
{
    public function listar(): Collection
    {
        return Publicidad::orderByDesc('created_at')->get();
    }

    public function reglas(): array
    {
        // 'image' a secas acepta SVG, y un SVG con <script> es XSS almacenado
        // servido desde nuestro propio origen. La publicidad se pinta en la portada.
        return [
            'titulo'                => ['required', 'string', 'max:255'],
            'imagen'                => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'enlace'                => ['nullable', 'url', 'max:500'],
            'abre_en_nueva_pestana' => ['boolean'],
            'fecha_inicio'          => ['required', 'date'],
            'fecha_fin'             => ['required', 'date', 'after:fecha_inicio'],
            'activa'                => ['boolean'],
        ];
    }

    /** $data = lo validado con reglas(); la imagen se toma del request. */
    public function crear(Request $request, array $data): Publicidad
    {
        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('publicidad', 'public');
        }

        return Publicidad::create($data);
    }

    /** Si llega imagen nueva borra la anterior del disco. */
    public function actualizar(Publicidad $publicidad, Request $request, array $data): Publicidad
    {
        if ($request->hasFile('imagen')) {
            if ($publicidad->imagen) Storage::disk('public')->delete($publicidad->imagen);
            $data['imagen'] = $request->file('imagen')->store('publicidad', 'public');
        }

        $publicidad->update($data);

        return $publicidad;
    }

    public function alternar(Publicidad $publicidad): Publicidad
    {
        $publicidad->update(['activa' => !$publicidad->activa]);

        return $publicidad;
    }

    public function eliminar(Publicidad $publicidad): void
    {
        if ($publicidad->imagen) Storage::disk('public')->delete($publicidad->imagen);
        $publicidad->delete();
    }
}
