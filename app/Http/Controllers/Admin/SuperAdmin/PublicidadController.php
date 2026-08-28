<?php

namespace App\Http\Controllers\Admin\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Publicidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PublicidadController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/SuperAdmin/Publicidad/Index', [
            'publicidades' => Publicidad::orderByDesc('created_at')->get()->map(fn ($p) => [
                'id'                   => $p->id,
                'titulo'               => $p->titulo,
                'imagen_url'           => $p->imagen_url,
                'enlace'               => $p->enlace,
                'abre_en_nueva_pestana'=> $p->abre_en_nueva_pestana,
                'fecha_inicio'         => $p->fecha_inicio?->toDateTimeString(),
                'fecha_fin'            => $p->fecha_fin?->toDateTimeString(),
                'activa'               => $p->activa,
                'estado_label'         => $p->estado_label,
            ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/SuperAdmin/Publicidad/Form');
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('publicidad', 'public');
        }

        Publicidad::create($data);

        return redirect()->route('admin.publicidad.index')
            ->with('success', 'Anuncio creado correctamente.');
    }

    public function edit(Publicidad $publicidad)
    {
        return Inertia::render('Admin/SuperAdmin/Publicidad/Form', [
            'publicidad' => [
                'id'                   => $publicidad->id,
                'titulo'               => $publicidad->titulo,
                'enlace'               => $publicidad->enlace,
                'abre_en_nueva_pestana'=> $publicidad->abre_en_nueva_pestana,
                'fecha_inicio'         => $publicidad->fecha_inicio?->format('Y-m-d\TH:i'),
                'fecha_fin'            => $publicidad->fecha_fin?->format('Y-m-d\TH:i'),
                'activa'               => $publicidad->activa,
                'imagen_url'           => $publicidad->imagen_url,
            ],
        ]);
    }

    public function update(Request $request, Publicidad $publicidad)
    {
        $data = $this->validar($request, forUpdate: true);

        if ($request->hasFile('imagen')) {
            if ($publicidad->imagen) Storage::disk('public')->delete($publicidad->imagen);
            $data['imagen'] = $request->file('imagen')->store('publicidad', 'public');
        }

        $publicidad->update($data);

        return redirect()->route('admin.publicidad.index')
            ->with('success', 'Anuncio actualizado correctamente.');
    }

    public function toggleActiva(Publicidad $publicidad)
    {
        $publicidad->update(['activa' => !$publicidad->activa]);
        $estado = $publicidad->activa ? 'activado' : 'desactivado';
        return back()->with('success', "Anuncio {$estado}.");
    }

    public function destroy(Publicidad $publicidad)
    {
        if ($publicidad->imagen) Storage::disk('public')->delete($publicidad->imagen);
        $publicidad->delete();

        return redirect()->route('admin.publicidad.index')
            ->with('success', 'Anuncio eliminado.');
    }

    private function validar(Request $request, bool $forUpdate = false): array
    {
        // 'image' a secas acepta SVG, y un SVG con <script> es XSS almacenado
        // servido desde nuestro propio origen. La publicidad se pinta en la portada.
        $imageRule = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

        return $request->validate([
            'titulo'               => ['required', 'string', 'max:255'],
            'imagen'               => $imageRule,
            'enlace'               => ['nullable', 'url', 'max:500'],
            'abre_en_nueva_pestana'=> ['boolean'],
            'fecha_inicio'         => ['required', 'date'],
            'fecha_fin'            => ['required', 'date', 'after:fecha_inicio'],
            'activa'               => ['boolean'],
        ]);
    }
}
