<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\CitaAceptada;
use App\Mail\CitaRechazada;
use App\Mail\CitaReagendada;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Services\AgendaCitaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RestauranteroCitasController extends Controller
{
    public function __construct(private AgendaCitaService $agenda) {}

    public function index(Request $request)
    {
        $restaurantero = $request->user()->restaurantero;
        if (!$restaurantero) {
            abort(403);
        }

        $tab = $request->get('tab', 'pendientes');

        $base = Cita::where('restaurantero_id', $restaurantero->id)
            ->with(['cliente', 'servicio'])
            ->orderByDesc('inicio');

        $estados = match($tab) {
            'pendientes'  => ['pendiente'],
            'confirmadas' => ['confirmada', 'pendiente_reconfirmacion', 'reagendada'],
            'rechazadas'  => ['rechazada', 'cancelada'],
            default       => ['pendiente', 'confirmada', 'cancelada', 'completada', 'rechazada', 'reagendada', 'pendiente_reconfirmacion'],
        };

        $citas = $base->whereIn('estado', $estados)->paginate(20)->withQueryString();

        $counts = [
            'pendientes'  => Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'pendiente')->count(),
            'confirmadas' => Cita::where('restaurantero_id', $restaurantero->id)->whereIn('estado', ['confirmada', 'pendiente_reconfirmacion', 'reagendada'])->count(),
            'rechazadas'  => Cita::where('restaurantero_id', $restaurantero->id)->whereIn('estado', ['rechazada', 'cancelada'])->count(),
        ];

        return Inertia::render('Restaurantero/Citas', [
            'citas'         => $citas,
            'tab'           => $tab,
            'counts'        => $counts,
            'restaurantero' => $restaurantero,
        ]);
    }

    public function aceptar(Request $request, Cita $cita)
    {
        $this->autorizarRestaurantero($request, $cita);

        $cita->update(['estado' => 'confirmada']);

        $cita->load(['cliente', 'restaurantero']);

        try {
            Mail::to($cita->cliente->email)->send(new CitaAceptada($cita));
        } catch (\Throwable $e) {
            Log::warning('Error enviando correo de cita aceptada al comprador: ' . $e->getMessage());
        }

        $fechaFmt = $cita->inicio->format('d/m/Y H:i');
        Notificacion::crear(
            $cita->cliente_id,
            'cita_aceptada',
            'Cita confirmada',
            "Tu cita con {$cita->restaurantero->nombre_restaurante} el {$fechaFmt} fue confirmada.",
            $cita->id
        );

        return back()->with('success', 'Cita aceptada. El comprador fue notificado.');
    }

    public function rechazar(Request $request, Cita $cita)
    {
        $this->autorizarRestaurantero($request, $cita);

        $cita->update(['estado' => 'rechazada']);

        $cita->load(['cliente', 'restaurantero']);

        try {
            Mail::to($cita->cliente->email)->send(new CitaRechazada($cita, 'cliente'));
        } catch (\Throwable $e) {
            Log::warning('Error enviando correo de cita rechazada al comprador: ' . $e->getMessage());
        }

        $fechaFmt = $cita->inicio->format('d/m/Y H:i');
        Notificacion::crear(
            $cita->cliente_id,
            'cita_rechazada',
            'Cita rechazada',
            "Tu cita con {$cita->restaurantero->nombre_restaurante} el {$fechaFmt} fue rechazada.",
            $cita->id
        );

        return back()->with('success', 'Cita rechazada. El comprador fue notificado.');
    }

    public function reagendar(Request $request, Cita $cita)
    {
        $this->autorizarRestaurantero($request, $cita);

        $request->validate([
            'propuesta_fecha' => ['required', 'date', 'after_or_equal:today'],
            'propuesta_hora'  => ['required', 'date_format:H:i'],
        ]);

        // Misma regla de duracion que el alta, y el hueco propuesto debe estar
        // libre para ambas partes (si no, ReglaDeNegocioException -> withErrors).
        $propuestaInicio = Carbon::parse($request->propuesta_fecha . ' ' . $request->propuesta_hora);
        $propuestaFin    = $this->agenda->proponerReagenda($cita, $propuestaInicio);
        $token           = Str::random(64);

        $cita->update([
            'estado'             => 'pendiente_reconfirmacion',
            'propuesta_inicio'   => $propuestaInicio,
            'propuesta_fin'      => $propuestaFin,
            'token_confirmacion' => $token,
        ]);

        $cita->load(['cliente', 'restaurantero', 'servicio']);

        try {
            Mail::to($cita->cliente->email)->send(new CitaReagendada($cita, 'cliente'));
        } catch (\Throwable $e) {
            Log::warning('Error enviando correo de propuesta de reagenda al comprador: ' . $e->getMessage());
        }

        $fechaNueva = $propuestaInicio->format('d/m/Y H:i');
        Notificacion::crear(
            $cita->cliente_id,
            'cita_reagendada',
            'Propuesta de cambio de horario',
            "{$cita->restaurantero->nombre_restaurante} propone mover tu cita al {$fechaNueva}. Revisa tu correo para confirmar.",
            $cita->id
        );

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
