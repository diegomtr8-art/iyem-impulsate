<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\CitaCancelada;
use App\Mail\CitaConfirmada;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\Restaurantero;
use App\Models\User;
use App\Services\AgendaCitaService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Citas desde el panel admin: listado con filtros, alta para un comprador
 * (cupo y solapes en AgendaCitaService) y cambio de estado con su correo.
 * Lo usan Admin\CitaAdminController (web) y la API v1.
 */
class AdminCitasService
{
    public const ESTADOS_ADMIN = ['pendiente', 'confirmada', 'cancelada', 'completada'];

    public const REGLAS_ALTA = [
        'cliente_id'       => 'required|exists:users,id',
        'restaurantero_id' => 'required|exists:restauranteros,id',
        'fecha'            => 'required|date',
        'hora'             => 'required|date_format:H:i',
        'duracion'         => 'required|integer|min:5|max:480',
        'notas'            => 'nullable|string|max:1000',
    ];

    public function __construct(private AgendaCitaService $agenda) {}

    /** @param array{estado?: ?string, search?: ?string} $filtros */
    public function listar(array $filtros, int $porPagina = 20, array $extra = []): LengthAwarePaginator
    {
        $query = Cita::with(['restaurantero.user', 'servicio', 'cliente'])
            ->orderByDesc('inicio');

        if (filled($filtros['estado'] ?? null)) {
            $query->where('estado', $filtros['estado']);
        }

        if (filled($filtros['search'] ?? null)) {
            $query->whereHas('cliente', function ($q) use ($filtros) {
                $q->where('name', 'like', '%' . $filtros['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filtros['search'] . '%');
            });
        }

        // Filtros extra solo de la API (la web no los manda).
        if (filled($extra['evento_id'] ?? null)) {
            $query->where('edicion_id', $extra['evento_id']);
        }
        if (filled($extra['restaurantero_id'] ?? null)) {
            $query->where('restaurantero_id', $extra['restaurantero_id']);
        }
        if (filled($extra['fecha'] ?? null)) {
            $query->whereDate('inicio', $extra['fecha']);
        }

        return $query->paginate($porPagina)->withQueryString();
    }

    /** Compradores (rol cliente) para el formulario de alta. */
    public function clientes(): Collection
    {
        return User::role('cliente')->orderBy('name')->get(['id', 'name', 'email']);
    }

    /** Proveedores activos para el formulario de alta. */
    public function proveedores(): Collection
    {
        return Restaurantero::where('activo', true)->orderBy('nombre_restaurante')->get(['id', 'nombre_restaurante']);
    }

    /** Cita nacida CONFIRMADA en el evento de contexto, con la duracion que fija el admin. */
    public function crear(?Evento $evento, array $datos): Cita
    {
        if (!$evento) {
            throw new ReglaDeNegocioException('No hay un evento activo.', 'edicion');
        }

        $cliente = User::findOrFail($datos['cliente_id']);

        // Verificar que el cliente no sea admin
        if ($cliente->hasRole('admin')) {
            throw new ReglaDeNegocioException('No se puede agendar una cita para un administrador.', 'cliente');
        }

        return $this->agenda->agendar(
            $cliente,
            (int) $datos['restaurantero_id'],
            $evento,
            Carbon::parse($datos['fecha'] . ' ' . $datos['hora']),
            (int) $datos['duracion'],
            ['estado' => 'confirmada', 'notas' => $datos['notas'] ?? null, 'origen' => 'admin'],
        );
    }

    /** Cambia el estado; al pasar a confirmada o cancelada avisa al comprador por correo. */
    public function cambiarEstado(Cita $cita, string $estado): Cita
    {
        $estadoAnterior = $cita->estado;
        $cita->update(['estado' => $estado]);

        if (in_array($estado, ['confirmada', 'cancelada']) && $estadoAnterior !== $estado) {
            $cita->load(['restaurantero', 'cliente']);

            try {
                if ($estado === 'confirmada') {
                    Mail::to($cita->cliente->email)->send(new CitaConfirmada($cita));
                } else {
                    Mail::to($cita->cliente->email)->send(new CitaCancelada($cita));
                }
            } catch (\Exception $e) {
                // No bloqueamos la operación si el correo falla
            }
        }

        return $cita;
    }
}
