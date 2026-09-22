<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificacionResource;
use App\Models\Notificacion;
use App\Services\NotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Notificaciones para la app. Las rutas /api/notificaciones (sin v1) son las
 * del polling de la web y no se tocan; ambas usan NotificacionService.
 */
class NotificacionController extends Controller
{
    public function __construct(private NotificacionService $notificaciones) {}

    public function index(Request $request)
    {
        $request->validate([
            'no_leidas' => ['sometimes', 'boolean'],
            'per_page'  => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        $this->notificaciones->generarRecordatorios($user);

        $pagina = Notificacion::where('user_id', $user->id)
            ->when($request->boolean('no_leidas'), fn ($q) => $q->where('leida', false))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 20))
            ->withQueryString();

        return NotificacionResource::collection($pagina)
            ->additional(['meta' => ['no_leidas' => $this->notificaciones->contarNoLeidas($user)]]);
    }

    /** Pensado para polling: tambien genera los recordatorios de ~30 min como la web. */
    public function contador(Request $request)
    {
        $user = $request->user();
        $this->notificaciones->generarRecordatorios($user);

        return response()->json(['data' => ['no_leidas' => $this->notificaciones->contarNoLeidas($user)]]);
    }

    public function marcarLeida(Notificacion $notificacion)
    {
        Gate::authorize('update', $notificacion);

        $this->notificaciones->marcarLeida($notificacion);

        return new NotificacionResource($notificacion->fresh());
    }

    public function marcarTodasLeidas(Request $request)
    {
        $n = $this->notificaciones->marcarTodasLeidas($request->user());

        return response()->json(['data' => ['actualizadas' => $n], 'message' => 'Notificaciones marcadas como leídas.']);
    }

    public function eliminar(Notificacion $notificacion)
    {
        Gate::authorize('delete', $notificacion);

        $notificacion->delete();

        return response()->json(['data' => null, 'message' => 'Notificación eliminada.']);
    }

    public function eliminarTodas(Request $request)
    {
        $n = $this->notificaciones->eliminarTodas($request->user());

        return response()->json(['data' => ['eliminadas' => $n], 'message' => 'Notificaciones eliminadas.']);
    }
}
