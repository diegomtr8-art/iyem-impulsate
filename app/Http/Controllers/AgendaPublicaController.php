<?php

namespace App\Http\Controllers;

use App\Models\AgendaPropuesta;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AgendaPublicaController extends Controller
{
    public function ver(string $token)
    {
        $propuesta = AgendaPropuesta::where('token', $token)
            ->with([
                'comprador:id,name,nombre_empresa',
                'citas.proveedor:id,nombre_restaurante,logo_path,descripcion',
                'evento:id,nombre,fecha_hora_inicio',
            ])
            ->firstOrFail();

        return Inertia::render('Agenda/Responder', [
            'propuesta' => $propuesta,
            'token'     => $token,
        ]);
    }

    public function aceptar(string $token)
    {
        $propuesta = AgendaPropuesta::where('token', $token)
            ->with(['citas.proveedor', 'comprador', 'evento'])
            ->firstOrFail();

        if (!$propuesta->esPendiente()) {
            return Inertia::render('Agenda/Gracias', [
                'tipo'         => $propuesta->estado,
                'propuesta'    => $propuesta,
                'yaRespondida' => true,
            ]);
        }

        $serviciosPorProveedor = Servicio::whereIn('restaurantero_id', $propuesta->citas->pluck('restaurantero_id'))
            ->where('activo', true)
            ->get()
            ->keyBy('restaurantero_id');

        $sinServicio = $propuesta->citas->filter(fn ($c) => !$serviciosPorProveedor->has($c->restaurantero_id));
        if ($sinServicio->isNotEmpty()) {
            abort(422, 'Uno de los proveedores de esta agenda no tiene un servicio activo para agendar citas.');
        }

        DB::transaction(function () use ($propuesta, $serviciosPorProveedor) {
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
        });

        return Inertia::render('Agenda/Gracias', [
            'tipo'         => 'aceptada',
            'propuesta'    => $propuesta->load('citas.proveedor', 'evento'),
            'yaRespondida' => false,
        ]);
    }

    public function rechazar(string $token)
    {
        $propuesta = AgendaPropuesta::where('token', $token)
            ->with(['comprador', 'evento'])
            ->firstOrFail();

        if (!$propuesta->esPendiente()) {
            return Inertia::render('Agenda/Gracias', [
                'tipo'         => $propuesta->estado,
                'propuesta'    => $propuesta,
                'yaRespondida' => true,
            ]);
        }

        $propuesta->update([
            'estado'        => 'rechazada',
            'respondida_at' => now(),
        ]);

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

        return Inertia::render('Agenda/Gracias', [
            'tipo'         => 'rechazada',
            'propuesta'    => $propuesta,
            'yaRespondida' => false,
        ]);
    }
}
