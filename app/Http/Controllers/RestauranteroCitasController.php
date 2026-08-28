<?php

namespace App\Http\Controllers;

use App\Mail\CitaAceptada;
use App\Mail\CitaCancelada;
use App\Mail\CitaRechazada;
use App\Mail\CitaReagendada;
use App\Models\Cita;
use App\Models\Notificacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RestauranteroCitasController extends Controller
{
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
        } catch (\Exception $e) {}

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
        } catch (\Exception $e) {}

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

        $propuestaInicio = Carbon::parse($request->propuesta_fecha . ' ' . $request->propuesta_hora);
        $duracion        = $cita->servicio->duracion_minutos ?? 30;
        $propuestaFin    = $propuestaInicio->copy()->addMinutes($duracion);
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
        } catch (\Exception $e) {}

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

        $cita->update([
            'estado' => 'confirmada',
            'inicio' => $cita->propuesta_inicio,
            'fin'    => $cita->propuesta_fin,
            'token_confirmacion' => null,
        ]);

        $cita->load(['restaurantero', 'cliente']);

        try {
            Mail::to($cita->restaurantero->user->email)->send(new CitaReagendada($cita, 'proveedor'));
        } catch (\Exception $e) {}

        return view('mail.confirmacion-exitosa', ['mensaje' => '¡Cita confirmada con la nueva fecha! Nos vemos pronto.']);
    }

    /** Rechazo del comprador al reagendamiento (ruta pública con token) */
    public function rechazarToken(Cita $cita, string $token)
    {
        if (!$cita->token_confirmacion || !hash_equals($cita->token_confirmacion, $token) || $cita->estado !== 'pendiente_reconfirmacion') {
            abort(404, 'El enlace no es válido o ya fue utilizado.');
        }

        $cita->update([
            'estado' => 'cancelada',
            'token_confirmacion' => null,
        ]);

        $cita->load(['restaurantero', 'cliente']);

        try {
            // El comprador rechazo: la cita quedo CANCELADA. Antes se mandaba
            // el mismo correo que en la aceptacion y el proveedor no podia
            // distinguir un caso del otro.
            Mail::to($cita->restaurantero->user->email)->send(new CitaCancelada($cita));
        } catch (\Exception $e) {
            Log::warning('Error notificando cancelacion al proveedor: ' . $e->getMessage());
        }

        Notificacion::crear(
            $cita->restaurantero->user_id,
            'cita_cancelada',
            'Propuesta de reagenda rechazada',
            'El comprador rechazo la nueva fecha propuesta. La cita del ' .
            $cita->inicio->format('d/m/Y H:i') . ' quedo cancelada.'
        );

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
