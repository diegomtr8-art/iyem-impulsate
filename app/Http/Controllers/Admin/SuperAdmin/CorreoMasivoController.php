<?php

namespace App\Http\Controllers\Admin\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\PlantillaCorreo;
use App\Models\Restaurantero;
use App\Services\Admin\CorreoMasivoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** La logica vive en CorreoMasivoService (compartido con la API v1). */
class CorreoMasivoController extends Controller
{
    public function __construct(private CorreoMasivoService $correo) {}

    public function index()
    {
        return Inertia::render('Admin/SuperAdmin/CorreoMasivo/Index', [
            'plantillas' => PlantillaCorreo::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'asunto', 'contenido', 'tipo_destinatario']),
            'eventos' => Evento::orderByDesc('fecha_hora_inicio')
                ->get(['id', 'nombre']),
            'categorias' => Restaurantero::categoriasActivas(),
        ]);
    }

    public function preview(Request $request)
    {
        $users = $this->correo->destinatarios($this->filtros($request));
        return response()->json([
            'total'         => $users->count(),
            'destinatarios' => $users->take(20)->map(fn ($u) => CorreoMasivoService::filaDestinatario($u))->values(),
        ]);
    }

    public function prueba(Request $request)
    {
        $request->validate(array_merge(['email_prueba' => ['required', 'email']], CorreoMasivoService::REGLAS_CORREO));

        $this->correo->prueba($request->email_prueba, $request->asunto, $request->contenido);

        return back()->with('success', "Correo de prueba enviado a {$request->email_prueba}.");
    }

    public function enviar(Request $request)
    {
        $request->validate(CorreoMasivoService::REGLAS_CORREO);

        $enviados = $this->correo->enviar($this->filtros($request), $request->asunto, $request->contenido);

        return back()->with('success', "Correos en cola para {$enviados} destinatarios.");
    }

    private function filtros(Request $request): array
    {
        return $request->only(['tipo', 'categoria', 'evento_id', 'estado_evento', 'estado_proveedor', 'canirac']);
    }
}
