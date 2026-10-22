<?php

namespace App\Http\Controllers\Admin\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlantillaCorreo;
use App\Services\Admin\PlantillasCorreoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** La logica vive en PlantillasCorreoService (compartido con la API v1). */
class PlantillasCorreoController extends Controller
{
    public function __construct(private PlantillasCorreoService $plantillas) {}

    public function index()
    {
        return Inertia::render('Admin/SuperAdmin/Plantillas/Index', [
            'plantillas' => $this->plantillas->listar()->map(fn ($p) => [
                'id'               => $p->id,
                'clave'            => $p->clave,
                'nombre'           => $p->nombre,
                'asunto'           => $p->asunto,
                'tipo_destinatario'=> $p->tipo_destinatario,
                'es_sistema'       => $p->es_sistema,
                'activo'           => $p->activo,
            ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/SuperAdmin/Plantillas/Create', [
            'htmlBase'     => PlantillasCorreoService::HTML_BASE,
            'grupos'       => PlantillasCorreoService::GRUPOS_PLACEHOLDERS,
        ]);
    }

    public function store(Request $request)
    {
        $this->plantillas->crear($request->validate(PlantillasCorreoService::reglasCrear()));

        return redirect()->route('admin.plantillas.index')->with('success', 'Plantilla creada correctamente.');
    }

    public function edit(PlantillaCorreo $plantilla)
    {
        return Inertia::render('Admin/SuperAdmin/Plantillas/Edit', [
            'plantilla'    => $plantilla,
            'grupos'       => PlantillasCorreoService::GRUPOS_PLACEHOLDERS,
            'placeholders' => $this->plantillas->placeholdersParaClave($plantilla->clave),
        ]);
    }

    public function update(Request $request, PlantillaCorreo $plantilla)
    {
        $this->plantillas->actualizar($plantilla, $request->validate(PlantillasCorreoService::REGLAS_EDITAR));

        return back()->with('success', 'Plantilla actualizada correctamente.');
    }

    public function toggle(PlantillaCorreo $plantilla)
    {
        $this->plantillas->alternar($plantilla);
        $estado = $plantilla->activo ? 'activada' : 'desactivada';
        return back()->with('success', "Plantilla {$estado}.");
    }

    public function restablecer(PlantillaCorreo $plantilla)
    {
        $this->plantillas->restablecer();
        return back()->with('success', 'Todas las plantillas restablecidas a sus valores por defecto.');
    }

    public function enviar(Request $request, PlantillaCorreo $plantilla)
    {
        $data = $request->validate(PlantillasCorreoService::REGLAS_ENVIAR);

        $enviados = $this->plantillas->enviar($plantilla, $data['destinatarios']);

        return back()->with('success', "Correo enviado a {$enviados} destinatario(s). (Procesando en cola)");
    }

    /** Vista previa de un borrador (layout real + variables de ejemplo). No guarda nada. */
    public function vistaPrevia(Request $request)
    {
        $d = $request->validate(PlantillasCorreoService::REGLAS_EDITAR);

        return response()->json($this->plantillas->vistaPrevia($d['asunto'], $d['contenido']));
    }

    /** Envia el borrador a un correo de prueba, sin guardar la plantilla. */
    public function enviarPrueba(Request $request)
    {
        $d = $request->validate(PlantillasCorreoService::REGLAS_PRUEBA);

        $this->plantillas->enviarPrueba($d['asunto'], $d['contenido'], $d['email']);

        return back()->with('success', "Correo de prueba enviado a {$d['email']}.");
    }

    public function destroy(PlantillaCorreo $plantilla)
    {
        $this->plantillas->eliminar($plantilla);
        return redirect()->route('admin.plantillas.index')->with('success', 'Plantilla eliminada.');
    }
}
