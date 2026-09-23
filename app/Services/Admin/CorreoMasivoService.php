<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\PlantillaCorreoMail;
use App\Models\PlantillaCorreo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Correo masivo (solo super-admin): segmentar destinatarios, vista previa de
 * la lista, correo de prueba y envio real EN COLA (un PlantillaCorreoMail por
 * persona). Lo usan SuperAdmin\CorreoMasivoController (web) y la API v1.
 */
class CorreoMasivoService
{
    public const TIPOS = ['todos', 'compradores', 'proveedores'];
    public const ESTADOS_PROVEEDOR = ['aprobado', 'pendiente', 'rechazado'];
    public const ESTADOS_EVENTO = ['pendiente', 'aprobado', 'rechazado'];

    public const REGLAS_CORREO = [
        'asunto'    => ['required', 'string', 'max:255'],
        'contenido' => ['required', 'string'],
    ];

    /**
     * Usuarios con correo verificado que cumplen los filtros: tipo
     * (todos|compradores|proveedores), categoria, evento_id + estado_evento,
     * estado_proveedor (solo con tipo=proveedores) y canirac (si|no).
     * "todos" excluye a admin y super-admin.
     */
    public function destinatarios(array $f): Collection
    {
        $tipo            = $f['tipo'] ?? 'todos';
        $categoria       = $f['categoria'] ?? null;
        $eventoId        = $f['evento_id'] ?? null;
        $estadoEvento    = $f['estado_evento'] ?? null;
        $estadoProveedor = $f['estado_proveedor'] ?? null;
        $canirac         = $f['canirac'] ?? null; // 'si' | 'no' | null

        $query = User::with(['roles', 'restaurantero'])
            ->where('email_verified_at', '!=', null);

        if ($tipo === 'proveedores') {
            $query->whereHas('roles', fn ($q) => $q->where('name', 'restaurantero'));
        } elseif ($tipo === 'compradores') {
            $query->whereHas('roles', fn ($q) => $q->where('name', 'cliente'));
            $query->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'));
        } else {
            $query->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'super-admin']));
        }

        if ($categoria) {
            $query->whereHas('restaurantero', fn ($q) => $q->where('categoria', $categoria));
        }

        if ($estadoProveedor && $tipo === 'proveedores') {
            if ($estadoProveedor === 'aprobado') {
                $query->whereHas('restaurantero', fn ($q) => $q->where('aprobado', true));
            } elseif ($estadoProveedor === 'pendiente') {
                $query->whereHas('restaurantero', fn ($q) => $q->where('aprobado', false)->where('rechazado', false));
            } elseif ($estadoProveedor === 'rechazado') {
                $query->whereHas('restaurantero', fn ($q) => $q->where('rechazado', true));
            }
        }

        if ($eventoId) {
            $eventoQuery = DB::table('evento_usuario')->where('evento_id', $eventoId);
            if ($estadoEvento) {
                $eventoQuery->where('estado', $estadoEvento);
            }

            // Filtrar también por el tipo de participación en el evento, solo
            // cuando hay un segmento específico seleccionado: evita que un
            // cliente que participó como proveedor en ESE evento (edge case)
            // pase el filtro de compradores, y viceversa.
            if ($tipo === 'compradores') {
                $eventoQuery->where('tipo', 'comprador');
            } elseif ($tipo === 'proveedores') {
                $eventoQuery->where('tipo', 'proveedor');
            }

            $query->whereIn('id', $eventoQuery->pluck('user_id'));
        }

        if ($canirac === 'si') {
            $query->where('camara_asociacion', 'CANIRAC');
        } elseif ($canirac === 'no') {
            $query->where(fn ($q) =>
                $q->whereNull('camara_asociacion')
                  ->orWhere('camara_asociacion', '!=', 'CANIRAC')
            );
        }

        return $query->get();
    }

    /** Fila de la vista previa de destinatarios (la misma que pinta la web). */
    public static function filaDestinatario(User $u): array
    {
        return [
            'id'      => $u->id,
            'name'    => $u->name,
            'email'   => $u->email,
            'rol'     => $u->roles->pluck('name')->join(', '),
            'canirac' => $u->camara_asociacion === 'CANIRAC',
        ];
    }

    /**
     * Correo de prueba a UN correo con "[PRUEBA]" en el asunto. La plantilla se
     * persiste (clave prueba_correo_masivo): PlantillaCorreoMail implementa
     * ShouldQueue y el job reconstruye el modelo por ID al ejecutarse.
     */
    public function prueba(string $email, string $asunto, string $contenido): void
    {
        $plantillaTemporal = PlantillaCorreo::updateOrCreate(
            ['clave' => 'prueba_correo_masivo'],
            [
                'nombre'     => 'Prueba Correo Masivo',
                'asunto'     => '[PRUEBA] ' . $asunto,
                'contenido'  => $contenido,
                'activo'     => false,
                'es_sistema' => false,
            ]
        );

        try {
            Mail::to($email)->send(
                new PlantillaCorreoMail($plantillaTemporal, [
                    'nombre_usuario' => 'Usuario de Prueba',
                ])
            );
        } catch (\Exception $e) {
            Log::error('Error correo prueba masivo: ' . $e->getMessage());
            throw new ReglaDeNegocioException('Error al enviar: ' . $e->getMessage());
        }
    }

    /**
     * Encola un correo por destinatario (nombre_usuario personalizado).
     * Devuelve cuantos quedaron en cola.
     */
    public function enviar(array $filtros, string $asunto, string $contenido): int
    {
        $users = $this->destinatarios($filtros);

        if ($users->isEmpty()) {
            throw new ReglaDeNegocioException('No hay destinatarios con los filtros seleccionados.');
        }

        // Igual que en prueba(): debe persistirse para que el job en cola pueda
        // restaurar el modelo.
        $plantillaTemporal = PlantillaCorreo::create([
            'clave'      => 'correo_masivo_' . now()->timestamp,
            'nombre'     => 'Correo Masivo',
            'asunto'     => $asunto,
            'contenido'  => $contenido,
            'activo'     => false,
            'es_sistema' => false,
        ]);

        $enviados = 0;
        foreach ($users as $user) {
            try {
                Mail::to($user->email)->queue(
                    new PlantillaCorreoMail($plantillaTemporal, [
                        'nombre_usuario' => $user->name,
                    ])
                );
                $enviados++;
            } catch (\Exception $e) {
                Log::warning("Correo masivo fallido para {$user->email}: " . $e->getMessage());
            }
        }

        return $enviados;
    }
}
