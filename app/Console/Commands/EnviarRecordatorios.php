<?php

namespace App\Console\Commands;

use App\Jobs\RecordatorioCita24h;
use App\Jobs\RecordatorioCita2h;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Console\Command;

class EnviarRecordatorios extends Command
{
    protected $signature   = 'citas:enviar-recordatorios';
    protected $description = 'Envía recordatorios de citas confirmadas próximas (24h, 2h y 1h antes)';

    public function handle(): void
    {
        $ahora = now();

        // Recordatorio 24h: citas que empiezan entre 23:45 y 24:15 desde ahora
        $citas24h = Cita::where('estado', 'confirmada')
            ->where('recordatorio_24h_enviado', false)
            ->whereBetween('inicio', [
                $ahora->copy()->addHours(23)->addMinutes(45),
                $ahora->copy()->addHours(24)->addMinutes(15),
            ])
            ->get();

        foreach ($citas24h as $cita) {
            RecordatorioCita24h::dispatch($cita);
        }

        // Recordatorio 2h: citas que empiezan entre 1:45 y 2:15 desde ahora
        $citas2h = Cita::where('estado', 'confirmada')
            ->where('recordatorio_2h_enviado', false)
            ->whereBetween('inicio', [
                $ahora->copy()->addHours(1)->addMinutes(45),
                $ahora->copy()->addHours(2)->addMinutes(15),
            ])
            ->get();

        foreach ($citas2h as $cita) {
            RecordatorioCita2h::dispatch($cita);
        }

        // Recordatorio 1h: citas que empiezan entre 45 y 75 minutos desde ahora
        $citas1h = Cita::where('estado', 'confirmada')
            ->where('recordatorio_1h_enviado', false)
            ->whereBetween('inicio', [
                $ahora->copy()->addMinutes(45),
                $ahora->copy()->addMinutes(75),
            ])
            ->with(['cliente', 'restaurantero.user'])
            ->get();

        foreach ($citas1h as $cita) {
            // Notificación in-app al comprador
            Notificacion::crear(
                $cita->cliente_id,
                'recordatorio_1h',
                '¡Tu cita empieza en 1 hora!',
                'Tienes una cita con ' . ($cita->restaurantero->nombre_restaurante ?? 'el proveedor')
                . ' a las ' . $cita->inicio->format('H:i') . 'h. ¡Prepárate!',
                $cita->id
            );

            // Notificación in-app al proveedor
            if ($cita->restaurantero?->user_id) {
                Notificacion::crear(
                    $cita->restaurantero->user_id,
                    'recordatorio_1h',
                    '¡Cita en 1 hora!',
                    'Tienes una cita con ' . ($cita->cliente->name ?? 'un comprador')
                    . ' a las ' . $cita->inicio->format('H:i') . 'h.',
                    $cita->id
                );
            }

            // Notificar a todos los admins
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                Notificacion::crear(
                    $admin->id,
                    'recordatorio_1h',
                    '[Admin] Cita en 1h: ' . ($cita->restaurantero->nombre_restaurante ?? '—'),
                    ($cita->cliente->name ?? '—') . ' → ' . ($cita->restaurantero->nombre_restaurante ?? '—')
                    . ' · ' . $cita->inicio->format('d/m H:i') . 'h',
                    $cita->id
                );
            }

            $cita->update(['recordatorio_1h_enviado' => true]);
        }

        $this->info("Recordatorios: {$citas24h->count()} de 24h, {$citas2h->count()} de 2h y {$citas1h->count()} de 1h procesados.");
    }
}
