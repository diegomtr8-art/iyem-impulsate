<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Cita;
use App\Services\AgendaCitaService;
use App\Services\GestionCitasProveedorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RestauranteroCitasController extends Controller
{
    public function __construct(
        private AgendaCitaService $agenda,
        private GestionCitasProveedorService $gestion,
    ) {}

    public function index(Request $request)
    {
        $restaurantero = $request->user()->restaurantero;
        if (!$restaurantero) {
            abort(403);
        }

        $tab = $request->get('tab', 'pendientes');

        $citas = $this->gestion->citasDePestana($restaurantero, $tab)
            ->with(['cliente', 'servicio'])
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Restaurantero/Citas', [
            'citas'         => $citas,
            'tab'           => $tab,
            'counts'        => $this->gestion->contadores($restaurantero),
            'restaurantero' => $restaurantero,
        ]);
    }

    // Aceptar, rechazar y reagendar (correos + notificaciones) viven en
    // GestionCitasProveedorService, que tambien usa la API v1 (/api/v1/proveedor/citas).

    public function aceptar(Request $request, Cita $cita)
    {
        $this->autorizarRestaurantero($request, $cita);

        $this->gestion->aceptar($cita);

        return back()->with('success', 'Cita aceptada. El comprador fue notificado.');
    }

    public function rechazar(Request $request, Cita $cita)
    {
        $this->autorizarRestaurantero($request, $cita);

        $this->gestion->rechazar($cita);

        return back()->with('success', 'Cita rechazada. El comprador fue notificado.');
    }

    public function reagendar(Request $request, Cita $cita)
    {
        $this->autorizarRestaurantero($request, $cita);

        $request->validate(GestionCitasProveedorService::REGLAS_REAGENDAR);

        // Hueco ocupado -> ReglaDeNegocioException -> withErrors (bootstrap/app.php).
        $this->gestion->proponerReagenda($cita, $request->propuesta_fecha, $request->propuesta_hora);

        return back()->with('success', 'Propuesta de reagendamiento enviada al comprador.');
    }

    /** Respuesta del comprador al reagendamiento (ruta pública con token) */
    /**
     * Pagina intermedia: el GET del correo ya no ejecuta la accion.
     * Muestra la cita y dos botones que hacen POST.
     */
    public function mostrarConfirmacion(Cita $cita, string $token)
    {
        if (!$cita->token_confirmacion
            || !hash_equals($cita->token_confirmacion, $token)
            || $cita->estado !== 'pendiente_reconfirmacion') {
            abort(404, 'El enlace no es valido o ya fue utilizado.');
        }

        $cita->load(['restaurantero', 'cliente']);

        return Inertia::render('Citas/ConfirmarReagenda', [
            'cita'  => [
                'id'               => $cita->id,
                'proveedor'        => $cita->restaurantero->nombre_restaurante ?? '',
                'inicio_original'  => $cita->inicio,
                'propuesta_inicio' => $cita->propuesta_inicio,
                'propuesta_fin'    => $cita->propuesta_fin,
            ],
            'token' => $token,
        ]);
    }

    public function confirmarToken(Cita $cita, string $token)
    {
        if (!$cita->token_confirmacion || !hash_equals($cita->token_confirmacion, $token) || $cita->estado !== 'pendiente_reconfirmacion') {
            abort(404, 'El enlace no es válido o ya fue utilizado.');
        }

        try {
            // Mismo camino que la app (POST /api/v1/citas/{id}/reagenda/aceptar).
            $this->agenda->aceptarReagenda($cita);
        } catch (ReglaDeNegocioException $e) {
            // El hueco se ocupo mientras la propuesta esperaba respuesta: el
            // servicio ya aviso al proveedor y la cita conserva su horario.
            return view('mail.confirmacion-exitosa', [
                'error'   => true,
                'titulo'  => 'Horario ya no disponible',
                'mensaje' => 'El horario propuesto ya no está disponible. Tu cita conserva su horario original; el proveedor te propondrá otro.',
            ]);
        }

        return view('mail.confirmacion-exitosa', ['mensaje' => '¡Cita confirmada con la nueva fecha! Nos vemos pronto.']);
    }

    /** Rechazo del comprador al reagendamiento (ruta pública con token) */
    public function rechazarToken(Cita $cita, string $token)
    {
        if (!$cita->token_confirmacion || !hash_equals($cita->token_confirmacion, $token) || $cita->estado !== 'pendiente_reconfirmacion') {
            abort(404, 'El enlace no es válido o ya fue utilizado.');
        }

        // Mismo camino que la app (POST /api/v1/citas/{id}/reagenda/rechazar).
        $this->agenda->rechazarReagenda($cita);

        return view('mail.confirmacion-exitosa', ['mensaje' => 'Has rechazado la propuesta. La cita quedó cancelada.']);
    }

    private function autorizarRestaurantero(Request $request, Cita $cita): void
    {
        $restaurantero = $request->user()->restaurantero;
        if (!$restaurantero || $cita->restaurantero_id !== $restaurantero->id) {
            abort(403);
        }
    }
}
