<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PlantillaCorreoMail;
use App\Models\Evento;
use App\Models\EventoCriterio;
use App\Models\EventoEvaluacion;
use App\Models\Notificacion;
use App\Models\PlantillaCorreo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BazarEvaluacionController extends Controller
{
    public function index(Evento $evento)
    {
        abort_if(!$evento->esBazar(), 404, 'Este evento no es de tipo Bazar/Exposición.');

        $criterios = $evento->criterios()->get();

        $solicitudes = DB::table('evento_usuario as eu')
            ->join('users as u', 'u.id', '=', 'eu.user_id')
            ->where('eu.evento_id', $evento->id)
            ->where('eu.tipo', 'expositor')
            ->select([
                'eu.user_id', 'u.name', 'u.email', 'u.nombre_empresa',
                'u.telefono', 'u.municipio', 'u.curp', 'u.rfc',
                'u.ine_path', 'u.csf_path', 'u.csf_fecha',
                'eu.estado', 'eu.puntaje_total', 'eu.seleccionado',
                'eu.notas_rechazo', 'eu.motivo_rechazo',
                'eu.correo_aprobacion_enviado', 'eu.correo_rechazo_enviado',
                'eu.created_at',
            ])
            ->orderByRaw("FIELD(eu.estado, 'pendiente', 'aprobado', 'rechazado')")
            ->orderByDesc('eu.puntaje_total')
            ->get()
            ->map(function ($s) use ($criterios, $evento) {
                $evaluaciones = EventoEvaluacion::where('evento_id', $evento->id)
                    ->where('user_id', $s->user_id)
                    ->pluck('puntaje', 'criterio_id');

                $s->evaluaciones = $criterios->map(fn ($c) => [
                    'criterio_id' => $c->id,
                    'nombre'      => $c->nombre,
                    'porcentaje'  => $c->porcentaje,
                    'puntaje'     => $evaluaciones[$c->id] ?? null,
                ]);
                $s->evaluado = $criterios->count() > 0 && $evaluaciones->count() === $criterios->count();

                $s->ine_url = $s->ine_path ? Storage::disk('public')->url($s->ine_path) : null;
                $s->csf_url = $s->csf_path ? Storage::disk('public')->url($s->csf_path) : null;

                $s->csf_vigente = false;
                if ($s->csf_fecha) {
                    $fechaCsf = \Carbon\Carbon::parse($s->csf_fecha);
                    $s->csf_vigente = $fechaCsf->diffInMonths(now()) <= 3 && $fechaCsf->lte(now());
                }

                return $s;
            });

        $kpis = [
            'total'             => $solicitudes->count(),
            'pendientes'        => $solicitudes->where('estado', 'pendiente')->count(),
            'en_evaluacion'     => $solicitudes->where('estado', 'aprobado')->count(),
            'seleccionados'     => $solicitudes->where('seleccionado', true)->count(),
            'no_seleccionados'  => $solicitudes->where('estado', 'aprobado')->where('seleccionado', false)->count(),
            'rechazados'        => $solicitudes->where('estado', 'rechazado')->count(),
            'max_espacios'      => $evento->max_espacios,
            'disponibles'       => max(0, ($evento->max_espacios ?? 0) - $solicitudes->where('seleccionado', true)->count()),
        ];

        return Inertia::render('Admin/Eventos/BazarEvaluacion', [
            'evento'      => $evento->only('id', 'nombre', 'max_espacios', 'con_criterios_evaluacion'),
            'criterios'   => $criterios,
            'solicitudes' => $solicitudes,
            'kpis'        => $kpis,
        ]);
    }

    /** Pre-aprobar una solicitud pendiente (pasa a "en evaluación") */
    public function aprobar(Request $request, Evento $evento, int $userId)
    {
        abort_if(!$evento->esBazar(), 404);

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $userId)
            ->where('tipo', 'expositor')
            ->update([
                'estado'         => 'aprobado',
                'respondido_at'  => now(),
                'motivo_rechazo' => null,
            ]);

        Notificacion::crear(
            $userId,
            'solicitud_aprobada',
            'Solicitud en revisión',
            'Tu solicitud para el bazar "' . $evento->nombre . '" pasó a revisión. Pronto recibirás el resultado final.'
        );

        return back()->with('success', 'Solicitud aprobada para evaluación.');
    }

    /** Rechazar una solicitud pendiente (motivo requerido) */
    public function rechazar(Request $request, Evento $evento, int $userId)
    {
        abort_if(!$evento->esBazar(), 404);

        $request->validate([
            'motivo_rechazo' => 'required|string|max:500',
        ]);

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $userId)
            ->where('tipo', 'expositor')
            ->update([
                'estado'         => 'rechazado',
                'motivo_rechazo' => $request->motivo_rechazo,
                'respondido_at'  => now(),
            ]);

        Notificacion::crear(
            $userId,
            'solicitud_rechazada',
            'Solicitud revisada',
            'Tu solicitud para el bazar "' . $evento->nombre . '" fue revisada. Motivo: ' . $request->motivo_rechazo
        );

        return back()->with('success', 'Solicitud rechazada.');
    }

    /** Evaluar por criterios (solo para estado='aprobado') */
    public function evaluar(Request $request, Evento $evento, int $userId)
    {
        $request->validate([
            'evaluaciones'               => 'required|array',
            'evaluaciones.*.criterio_id' => 'required|exists:evento_criterios,id',
            'evaluaciones.*.puntaje'     => 'required|numeric|min:0|max:100',
        ]);

        $puntajeTotal = 0;

        foreach ($request->evaluaciones as $eval) {
            $criterio = EventoCriterio::find($eval['criterio_id']);
            EventoEvaluacion::updateOrCreate(
                ['evento_id' => $evento->id, 'user_id' => $userId, 'criterio_id' => $eval['criterio_id']],
                ['puntaje'   => $eval['puntaje']]
            );
            $puntajeTotal += ($eval['puntaje'] / 100) * $criterio->porcentaje;
        }

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $userId)
            ->update(['puntaje_total' => round($puntajeTotal, 2)]);

        return back()->with('success', 'Evaluación guardada.');
    }

    /** Seleccionar / deseleccionar un participante aprobado */
    public function toggleSeleccion(Request $request, Evento $evento)
    {
        $request->validate([
            'user_id'      => 'required|integer|exists:users,id',
            'seleccionado' => 'required|boolean',
        ]);

        if ($request->seleccionado && $evento->max_espacios) {
            $actuales = DB::table('evento_usuario')
                ->where('evento_id', $evento->id)
                ->where('seleccionado', true)
                ->count();

            if ($actuales >= $evento->max_espacios) {
                return back()->withErrors(['error' => "Ya alcanzaste el límite de {$evento->max_espacios} espacios."]);
            }
        }

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $request->user_id)
            ->update(['seleccionado' => $request->seleccionado]);

        return back()->with('success', $request->seleccionado ? 'Participante seleccionado.' : 'Participante deseleccionado.');
    }

    /** Guardar notas de rechazo (por participante no seleccionado) */
    public function guardarNotasRechazo(Request $request, Evento $evento, int $userId)
    {
        $request->validate([
            'notas_rechazo' => 'required|string|max:2000',
        ]);

        DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $userId)
            ->update(['notas_rechazo' => $request->notas_rechazo]);

        return back()->with('success', 'Notas guardadas.');
    }

    /** Enviar correos a seleccionados */
    public function enviarAprobacion(Evento $evento)
    {
        $seleccionados = DB::table('evento_usuario as eu')
            ->join('users as u', 'u.id', '=', 'eu.user_id')
            ->where('eu.evento_id', $evento->id)
            ->where('eu.tipo', 'expositor')
            ->where('eu.seleccionado', true)
            ->where('eu.correo_aprobacion_enviado', false)
            ->select('eu.user_id', 'u.name', 'u.email')
            ->get();

        if ($seleccionados->isEmpty()) {
            return back()->withErrors(['error' => 'No hay participantes seleccionados pendientes de notificar.']);
        }

        $plantilla = PlantillaCorreo::paraClave('bazar_seleccionado');
        if (!$plantilla) {
            return back()->withErrors(['error' => 'Plantilla "bazar_seleccionado" no encontrada.']);
        }

        $enviados = 0;
        foreach ($seleccionados as $p) {
            try {
                Mail::to($p->email)->queue(new PlantillaCorreoMail($plantilla, [
                    'nombre_usuario' => $p->name,
                    'nombre_evento'  => $evento->nombre,
                ]));
                DB::table('evento_usuario')
                    ->where('evento_id', $evento->id)
                    ->where('user_id', $p->user_id)
                    ->update(['correo_aprobacion_enviado' => true]);
                Notificacion::crear($p->user_id, 'solicitud_aprobada', 'Seleccionado en el bazar',
                    'Fuiste seleccionado(a) para participar como expositor en "' . $evento->nombre . '".');
                $enviados++;
            } catch (\Exception $e) {
                \Log::warning("Error correo aprobación bazar user {$p->user_id}: " . $e->getMessage());
            }
        }

        return back()->with('success', "Correo de aprobación enviado a {$enviados} participante(s).");
    }

    /** Enviar correos a NO seleccionados (con token de evaluación) */
    public function enviarRechazo(Evento $evento)
    {
        $rechazados = DB::table('evento_usuario as eu')
            ->join('users as u', 'u.id', '=', 'eu.user_id')
            ->where('eu.evento_id', $evento->id)
            ->where('eu.tipo', 'expositor')
            ->where('eu.estado', 'aprobado')
            ->where('eu.seleccionado', false)
            ->where('eu.correo_rechazo_enviado', false)
            ->select('eu.user_id', 'u.name', 'u.email', 'eu.token_evaluacion')
            ->get();

        if ($rechazados->isEmpty()) {
            return back()->withErrors(['error' => 'No hay participantes no seleccionados pendientes de notificar.']);
        }

        $plantilla = PlantillaCorreo::paraClave('bazar_rechazado');
        if (!$plantilla) {
            return back()->withErrors(['error' => 'Plantilla "bazar_rechazado" no encontrada.']);
        }

        $enviados = 0;
        foreach ($rechazados as $p) {
            try {
                $token = $p->token_evaluacion ?? Str::random(48);
                if (!$p->token_evaluacion) {
                    DB::table('evento_usuario')
                        ->where('evento_id', $evento->id)
                        ->where('user_id', $p->user_id)
                        ->update(['token_evaluacion' => $token]);
                }
                $urlEvaluacion = route('bazar.evaluacion', ['token' => $token]);
                Mail::to($p->email)->queue(new PlantillaCorreoMail($plantilla, [
                    'nombre_usuario' => $p->name,
                    'nombre_evento'  => $evento->nombre,
                    'url_evaluacion' => $urlEvaluacion,
                ]));
                DB::table('evento_usuario')
                    ->where('evento_id', $evento->id)
                    ->where('user_id', $p->user_id)
                    ->update(['correo_rechazo_enviado' => true]);
                Notificacion::crear($p->user_id, 'solicitud_rechazada', 'Resultado del bazar',
                    'Tu solicitud para "' . $evento->nombre . '" no fue seleccionada. Revisa tu evaluación en el correo enviado.');
                $enviados++;
            } catch (\Exception $e) {
                \Log::warning("Error correo rechazo bazar user {$p->user_id}: " . $e->getMessage());
            }
        }

        return back()->with('success', "Correo de resultado enviado a {$enviados} participante(s).");
    }
}
