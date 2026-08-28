<?php

namespace App\Jobs;

use App\Mail\RecordatorioCita;
use App\Models\Cita;
use App\Models\Notificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class RecordatorioCita24h implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Cita $cita) {}

    public function handle(): void
    {
        $this->cita->load(['cliente', 'restaurantero.user']);
        $fechaFmt = $this->cita->inicio->format('d/m/Y H:i');

        try {
            Mail::to($this->cita->cliente->email)->send(new RecordatorioCita($this->cita, '24h', 'cliente'));
            if ($this->cita->restaurantero->user?->email) {
                Mail::to($this->cita->restaurantero->user->email)->send(new RecordatorioCita($this->cita, '24h', 'proveedor'));
            }
        } catch (\Throwable $e) {
            \Log::warning('Error enviando correo de recordatorio 24h: ' . $e->getMessage());
        }

        Notificacion::crear(
            $this->cita->cliente_id,
            'recordatorio_24h',
            'Cita mañana',
            "Recuerda tu cita con {$this->cita->restaurantero->nombre_restaurante} el {$fechaFmt}.",
            $this->cita->id
        );

        $this->cita->update(['recordatorio_24h_enviado' => true]);

        // Notificar a todos los admins
        $admins = \App\Models\User::role('admin')->get();
        foreach ($admins as $admin) {
            Notificacion::crear(
                $admin->id,
                'recordatorio_24h',
                '[Admin] Cita mañana: ' . ($this->cita->restaurantero->nombre_restaurante ?? '—'),
                ($this->cita->cliente->name ?? '—') . ' → ' . ($this->cita->restaurantero->nombre_restaurante ?? '—')
                . ' · ' . $this->cita->inicio->format('d/m H:i') . 'h',
                $this->cita->id
            );
        }
    }
}
