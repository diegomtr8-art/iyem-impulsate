<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\PlantillaCorreoMail;
use App\Models\Evento;
use App\Models\EventoCriterio;
use App\Models\EventoEvaluacion;
use App\Models\Notificacion;
use App\Models\PlantillaCorreo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Bazar/Exposicion: revision inicial de expositores, evaluacion por
 * criterios, seleccion con limite de espacios y correos de resultado.
 * Lo usan Admin\BazarEvaluacionController (web) y la API v1.
 */
class BazarEvaluacionService
{
    public function asegurarBazar(Evento $evento): void
    {
        abort_if(!$evento->esBazar(), 404, 'Este evento no es de tipo Bazar/Exposición.');
    }

    /** Registro de expositor del usuario en el bazar (null si no tiene). */
    public function registro(Evento $evento, int $userId): ?object
    {
        return $this->query($evento, $userId)->first();
    }

    private function query(Evento $evento, int $userId)
    {
        return DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $userId)
            ->where('tipo', 'expositor');
    }

    /** Expositores con evaluaciones y KPIs (forma de la pagina Admin/Eventos/BazarEvaluacion). */
    public function datos(Evento $evento, ?int $soloUserId = null): array
    {
        $criterios = $evento->criterios()->get();

        $solicitudes = DB::table('evento_usuario as eu')
            ->join('users as u', 'u.id', '=', 'eu.user_id')
            ->where('eu.evento_id', $evento->id)
            ->where('eu.tipo', 'expositor')
            ->when($soloUserId, fn ($q) => $q->where('eu.user_id', $soloUserId))
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

                $s->ine_url = $s->ine_path ? route('documentos.ver', ['user' => $s->user_id, 'tipo' => 'ine']) : null;
                $s->csf_url = $s->csf_path ? route('documentos.ver', ['user' => $s->user_id, 'tipo' => 'csf']) : null;
                $s->tiene_ine = (bool) $s->ine_path;
                $s->tiene_csf = (bool) $s->csf_path;

                // Las rutas internas ya no hacen falta: la vista usa las _url y
                // los booleanos. No se envian al navegador.
                unset($s->ine_path, $s->csf_path);

                $s->csf_vigente = false;
                if ($s->csf_fecha) {
                    $fechaCsf = Carbon::parse($s->csf_fecha);
                    $s->csf_vigente = $fechaCsf->diffInMonths(now()) <= 3 && $fechaCsf->lte(now());
                }

                return $s;
            });

        return [
            'evento'      => $evento->only('id', 'nombre', 'max_espacios', 'con_criterios_evaluacion'),
            'criterios'   => $criterios,
            'solicitudes' => $solicitudes,
            'kpis'        => $this->kpis($evento, $solicitudes),
        ];
    }

    private function kpis(Evento $evento, Collection $solicitudes): array
    {
        return [
            'total'             => $solicitudes->count(),
            'pendientes'        => $solicitudes->where('estado', 'pendiente')->count(),
            'en_evaluacion'     => $solicitudes->where('estado', 'aprobado')->count(),
            'seleccionados'     => $solicitudes->where('seleccionado', true)->count(),
            'no_seleccionados'  => $solicitudes->where('estado', 'aprobado')->where('seleccionado', false)->count(),
            'rechazados'        => $solicitudes->where('estado', 'rechazado')->count(),
            'max_espacios'      => $evento->max_espacios,
            'disponibles'       => max(0, ($evento->max_espacios ?? 0) - $solicitudes->where('seleccionado', true)->count()),
        ];
    }

    /** Pre-aprobar una solicitud pendiente (pasa a "en evaluación"). */
    public function aprobar(Evento $evento, int $userId): void
    {
        $this->query($evento, $userId)->update([
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
    }

    public function rechazar(Evento $evento, int $userId, string $motivo): void
    {
        $this->query($evento, $userId)->update([
            'estado'         => 'rechazado',
            'motivo_rechazo' => $motivo,
            'respondido_at'  => now(),
        ]);

        Notificacion::crear(
            $userId,
            'solicitud_rechazada',
            'Solicitud revisada',
            'Tu solicitud para el bazar "' . $evento->nombre . '" fue revisada. Motivo: ' . $motivo
        );
    }

    public function reglasEvaluar(Evento $evento): array
    {
        return [
            'evaluaciones'               => 'required|array',
            // El criterio debe pertenecer A ESTE evento: si no, se podria
            // inyectar el porcentaje de otro evento y falsear el puntaje.
            'evaluaciones.*.criterio_id' => [
                'required',
                Rule::exists('evento_criterios', 'id')->where('evento_id', $evento->id),
            ],
            'evaluaciones.*.puntaje'     => 'required|numeric|min:0|max:100',
        ];
    }

    /** Guarda los puntajes y recalcula el puntaje ponderado. Devuelve el total. */
    public function evaluar(Evento $evento, int $userId, array $evaluaciones): float
    {
        $puntajeTotal = 0;

        foreach ($evaluaciones as $eval) {
            $criterio = EventoCriterio::find($eval['criterio_id']);
            EventoEvaluacion::updateOrCreate(
                ['evento_id' => $evento->id, 'user_id' => $userId, 'criterio_id' => $eval['criterio_id']],
                ['puntaje'   => $eval['puntaje']]
            );
            $puntajeTotal += ($eval['puntaje'] / 100) * $criterio->porcentaje;
        }

        $this->query($evento, $userId)->update(['puntaje_total' => round($puntajeTotal, 2)]);

        return round($puntajeTotal, 2);
    }

    public function cambiarSeleccion(Evento $evento, int $userId, bool $seleccionado): void
    {
        // Solo se puede seleccionar a alguien que paso la revision inicial.
        // Sin esto se podia seleccionar a un 'pendiente' o 'rechazado' y
        // acababa recibiendo el correo de "Fuiste seleccionado".
        if ($seleccionado) {
            $registro = $this->registro($evento, $userId);

            if (!$registro) {
                throw new ReglaDeNegocioException('Ese participante no tiene una solicitud de expositor en este evento.');
            }

            if ($registro->estado !== 'aprobado') {
                throw new ReglaDeNegocioException('Solo puedes seleccionar participantes que ya pasaron la revision inicial.');
            }
        }

        if ($seleccionado && $evento->max_espacios) {
            $actuales = DB::table('evento_usuario')
                ->where('evento_id', $evento->id)
                ->where('tipo', 'expositor')
                ->where('seleccionado', true)
                ->count();

            if ($actuales >= $evento->max_espacios) {
                throw new ReglaDeNegocioException("Ya alcanzaste el límite de {$evento->max_espacios} espacios.");
            }
        }

        $this->query($evento, $userId)->update(['seleccionado' => $seleccionado]);
    }

    public function guardarNotasRechazo(Evento $evento, int $userId, string $notas): void
    {
        $this->query($evento, $userId)->update(['notas_rechazo' => $notas]);
    }

    /** Correo "Fuiste seleccionado" a los seleccionados aun no notificados. Devuelve cuantos. */
    public function enviarAprobacion(Evento $evento): int
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
            throw new ReglaDeNegocioException('No hay participantes seleccionados pendientes de notificar.');
        }

        $plantilla = PlantillaCorreo::paraClave('bazar_seleccionado');
        if (!$plantilla) {
            throw new ReglaDeNegocioException('Plantilla "bazar_seleccionado" no encontrada.');
        }

        $enviados = 0;
        foreach ($seleccionados as $p) {
            try {
                Mail::to($p->email)->queue(new PlantillaCorreoMail($plantilla, [
                    'nombre_usuario' => $p->name,
                    'nombre_evento'  => $evento->nombre,
                ]));
                $this->query($evento, $p->user_id)->update(['correo_aprobacion_enviado' => true]);
                Notificacion::crear($p->user_id, 'solicitud_aprobada', 'Seleccionado en el bazar',
                    'Fuiste seleccionado(a) para participar como expositor en "' . $evento->nombre . '".');
                $enviados++;
            } catch (\Exception $e) {
                Log::warning("Error correo aprobación bazar user {$p->user_id}: " . $e->getMessage());
            }
        }

        return $enviados;
    }

    /** Correo de resultado (con enlace a su evaluacion) a los NO seleccionados. Devuelve cuantos. */
    public function enviarRechazo(Evento $evento): int
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
            throw new ReglaDeNegocioException('No hay participantes no seleccionados pendientes de notificar.');
        }

        $plantilla = PlantillaCorreo::paraClave('bazar_rechazado');
        if (!$plantilla) {
            throw new ReglaDeNegocioException('Plantilla "bazar_rechazado" no encontrada.');
        }

        $enviados = 0;
        foreach ($rechazados as $p) {
            try {
                $token = $p->token_evaluacion ?? Str::random(48);
                if (!$p->token_evaluacion) {
                    $this->query($evento, $p->user_id)->update(['token_evaluacion' => $token]);
                }
                Mail::to($p->email)->queue(new PlantillaCorreoMail($plantilla, [
                    'nombre_usuario' => $p->name,
                    'nombre_evento'  => $evento->nombre,
                    'url_evaluacion' => route('bazar.evaluacion', ['token' => $token]),
                ]));
                $this->query($evento, $p->user_id)->update(['correo_rechazo_enviado' => true]);
                Notificacion::crear($p->user_id, 'solicitud_rechazada', 'Resultado del bazar',
                    'Tu solicitud para "' . $evento->nombre . '" no fue seleccionada. Revisa tu evaluación en el correo enviado.');
                $enviados++;
            } catch (\Exception $e) {
                Log::warning("Error correo rechazo bazar user {$p->user_id}: " . $e->getMessage());
            }
        }

        return $enviados;
    }
}
