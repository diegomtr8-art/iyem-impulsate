<?php

namespace App\Console\Commands;

use App\Jobs\RecordatorioCita24h;
use App\Jobs\RecordatorioCita2h;
use App\Models\Cita;
use Illuminate\Console\Command;

class EnviarRecordatorios extends Command
{
    protected $signature   = 'citas:enviar-recordatorios';
    protected $description = 'Envía recordatorios de citas confirmadas próximas (24h y 2h antes)';

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

        $this->info("Recordatorios: {$citas24h->count()} de 24h y {$citas2h->count()} de 2h despachados.");
    }
}
