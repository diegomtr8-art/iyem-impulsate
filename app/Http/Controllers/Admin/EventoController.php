<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Restaurantero;
use App\Services\Admin\AdminEventosService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * La logica (validacion, criterios, destacado, activar/archivar/eliminar)
 * vive en AdminEventosService, que tambien usa la API v1 (/admin/eventos).
 */
class EventoController extends Controller
{
    public function __construct(private AdminEventosService $eventos) {}

    public function index()
    {
        return Inertia::render('Admin/Eventos/Index', [
            'eventos'   => $this->eventos->listar(),
            'categorias' => Restaurantero::categoriasActivas(),
        ]);
    }

    public function store(Request $request)
    {
        $this->eventos->validar($request, 'crear');
        $this->eventos->crear($request);

        return back()->with('success', 'Evento creado. Actívalo cuando estés listo.');
    }

    public function update(Request $request, Evento $evento)
    {
        $this->eventos->validar($request, 'editar');
        $this->eventos->actualizar($evento, $request);

        return back()->with('success', 'Evento actualizado correctamente.');
    }

    public function enviarEncuestas(Evento $evento)
    {
        $enviados = $this->eventos->enviarEncuestas($evento);

        return back()->with('success', "Encuestas enviadas a {$enviados} participante(s). Las ya existentes se omitieron.");
    }

    public function archivar(Evento $evento)
    {
        $this->eventos->archivar($evento);

        // Si el admin estaba operando sobre este evento, se limpia el contexto.
        if (session('admin_evento_id') == $evento->id) {
            session()->forget('admin_evento_id');
        }

        return back()->with('success', 'Evento "' . $evento->nombre . '" archivado correctamente.');
    }

    public function activar(Evento $evento)
    {
        $this->eventos->activar($evento);

        return back()->with('success', 'Evento "' . $evento->nombre . '" activado.');
    }

    /**
     * Cambia el evento sobre el que operan las pantallas de gestión (citas,
     * agenda, exportaciones). La elección vive en la sesión del admin.
     */
    public function contexto(Request $request)
    {
        $request->validate([
            'evento_id' => 'nullable|integer',
        ]);

        if (!$request->evento_id) {
            session()->forget('admin_evento_id');
            return back()->with('success', 'Ahora se usa el evento activo más próximo.');
        }

        $evento = Evento::queryActivos()->where('id', $request->evento_id)->first();

        if (!$evento) {
            return back()->withErrors(['evento_id' => 'Ese evento ya no está activo.']);
        }

        session(['admin_evento_id' => $evento->id]);

        return back()->with('success', 'Ahora estás trabajando sobre "' . $evento->nombre . '".');
    }

    public function destroy(Evento $evento)
    {
        $this->eventos->eliminar($evento);

        return back()->with('success', 'Evento eliminado correctamente.');
    }
}
