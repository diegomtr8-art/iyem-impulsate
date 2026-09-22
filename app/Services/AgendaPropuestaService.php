<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\AgendaPropuesta;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Respuesta del COMPRADOR a una agenda propuesta por el admin (varias citas
 * con distintos proveedores). La usan AgendaPublicaController (enlace del
 * correo, por token) y la API v1 (/agendas/{id}/aceptar|rechazar).
 */
class AgendaPropuestaService
{
    /**
     * Crea las citas confirmadas de la propuesta y avisa a proveedores y
     * comprador. Devuelve false si la propuesta ya no estaba pendiente (nada
     * cambia). ReglaDeNegocioException si algun proveedor no tiene servicio activo.
     *
     * Nota: igual que siempre, NO comprueba solapes con otras citas (ver reporte).
     */
    public function aceptar(AgendaPropuesta $propuesta): bool
    {
        $propuesta->loadMissing(['citas.proveedor', 'comprador', 'evento']);

        if (!$propuesta->esPendiente()) {
            return false;
        }

        $serviciosPorProveedor = Servicio::whereIn('restaurantero_id', $propuesta->citas->pluck('restaurantero_id'))
            ->where('activo', true)
            ->get()
            ->keyBy('restaurantero_id');

        $sinServicio = $propuesta->citas->filter(fn ($c) => !$serviciosPorProveedor->has($c->restaurantero_id));
        if ($sinServicio->isNotEmpty()) {
            throw new ReglaDeNegocioException('Uno de los proveedores de esta agenda no tiene un servicio activo para agendar citas.');
        }

        return DB::transaction(function () use ($propuesta, $serviciosPorProveedor) {
            // Dos respuestas simultaneas (doble clic, correo + app): solo la
            // primera crea las citas.
            if (AgendaPropuesta::whereKey($propuesta->id)->lockForUpdate()->value('estado') !== 'pendiente') {
                return false;
            }

            $propuesta->update([
                'estado'        => 'aceptada',
                'respondida_at' => now(),
            ]);

            foreach ($propuesta->citas as $citaPropuesta) {
                $yaExiste = Cita::where('restaurantero_id', $citaPropuesta->restaurantero_id)
                    ->where('cliente_id', $propuesta->user_id)
                    ->where('inicio', $citaPropuesta->slot_inicio)
                    ->whereNotIn('estado', ['cancelada', 'rechazada'])
                    ->exists();

                if ($yaExiste) continue;

                $servicio = $serviciosPorProveedor->get($citaPropuesta->restaurantero_id);

                $cita = Cita::create([
                    'edicion_id'       => $propuesta->evento_id,
                    'restaurantero_id' => $citaPropuesta->restaurantero_id,
                    'servicio_id'      => $servicio->id,
                    'cliente_id'       => $propuesta->user_id,
                    'inicio'           => $citaPropuesta->slot_inicio,
                    'fin'              => $citaPropuesta->slot_fin,
                    'estado'           => 'confirmada',
                ]);

                // Guardar el vinculo para poder deshacer SOLO estas citas
                $citaPropuesta->update(['cita_id' => $cita->id]);

                $proveedor = $citaPropuesta->proveedor;
                if ($proveedor) {
                    Notificacion::crear(
                        $proveedor->user_id,
                        'cita_nueva',
                        '¡Nueva cita confirmada!',
                        'El comprador ' . ($propuesta->comprador->nombre_empresa ?? $propuesta->comprador->name) .
                        ' confirmó su cita contigo el ' . $citaPropuesta->slot_inicio->format('d/m/Y H:i') . '.',
                        $cita->id
                    );
                }
            }

            Notificacion::crear(
                $propuesta->user_id,
                'agenda_aceptada',
                '¡Agenda aceptada!',
                'Has aceptado tu propuesta de agenda para el evento "' . $propuesta->evento->nombre .
                '". Tus citas han sido registradas en el sistema.'
            );

            return true;
        });
    }

    /** Marca la propuesta como rechazada y avisa a los admins y al comprador. False si ya estaba respondida. */
    public function rechazar(AgendaPropuesta $propuesta): bool
    {
        $propuesta->loadMissing(['comprador', 'evento']);

        if (!$propuesta->esPendiente()) {
            return false;
        }

        $cambio = DB::transaction(function () use ($propuesta) {
            if (AgendaPropuesta::whereKey($propuesta->id)->lockForUpdate()->value('estado') !== 'pendiente') {
                return false;
            }

            $propuesta->update([
                'estado'        => 'rechazada',
                'respondida_at' => now(),
            ]);

            return true;
        });

        if (!$cambio) {
            return false;
        }

        $nombreComprador = $propuesta->comprador->nombre_empresa ?? $propuesta->comprador->name;

        foreach (User::role(['admin', 'super-admin'])->get() as $admin) {
            Notificacion::crear(
                $admin->id,
                'agenda_rechazada',
                'Propuesta de agenda rechazada',
                'El comprador "' . $nombreComprador . '" rechazó su propuesta de agenda para el evento "' .
                $propuesta->evento->nombre . '". Es necesario hacer una re-propuesta.'
            );
        }

        Notificacion::crear(
            $propuesta->user_id,
            'agenda_rechazada',
            'Propuesta rechazada',
            'Has rechazado la propuesta de agenda. El equipo de IMPULSATE te contactará pronto.'
        );

        return true;
    }
}
