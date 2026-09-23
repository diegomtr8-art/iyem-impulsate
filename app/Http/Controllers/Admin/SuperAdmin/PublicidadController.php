<?php

namespace App\Http\Controllers\Admin\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Publicidad;
use App\Services\Admin\PublicidadAdminService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** La logica vive en PublicidadAdminService (compartido con la API v1). */
class PublicidadController extends Controller
{
    public function __construct(private PublicidadAdminService $publicidad) {}

    public function index()
    {
        return Inertia::render('Admin/SuperAdmin/Publicidad/Index', [
            'publicidades' => $this->publicidad->listar()->map(fn ($p) => [
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
        $this->publicidad->crear($request, $request->validate($this->publicidad->reglas()));

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
        $this->publicidad->actualizar($publicidad, $request, $request->validate($this->publicidad->reglas()));

        return redirect()->route('admin.publicidad.index')
            ->with('success', 'Anuncio actualizado correctamente.');
    }

    public function toggleActiva(Publicidad $publicidad)
    {
        $this->publicidad->alternar($publicidad);
        $estado = $publicidad->activa ? 'activado' : 'desactivado';
        return back()->with('success', "Anuncio {$estado}.");
    }

    public function destroy(Publicidad $publicidad)
    {
        $this->publicidad->eliminar($publicidad);

        return redirect()->route('admin.publicidad.index')
            ->with('success', 'Anuncio eliminado.');
    }
}
