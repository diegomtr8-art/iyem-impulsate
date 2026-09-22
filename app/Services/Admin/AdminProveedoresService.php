<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\ProveedorAprobado;
use App\Mail\ProveedorRechazado;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Proveedores desde el panel admin: listado con busqueda, pendientes de
 * aprobacion, ficha con citas y eventos, edicion (incluido el logo),
 * activar/desactivar, categoria, aprobar y rechazar (con correo y aviso).
 * Lo usan Admin\RestauranteroAdminController (web) y la API v1.
 */
class AdminProveedoresService
{
    public function listar(?string $search, int $porPagina = 15): LengthAwarePaginator
    {
        $query = Restaurantero::with('user')
            ->withCount('citas')
            ->orderByDesc('created_at');

        if (filled($search)) {
            $s = $search;
            $query->where(function ($q) use ($s) {
                $q->where('nombre_restaurante', 'like', "%{$s}%")
                  ->orWhere('telefono', 'like', "%{$s}%")
                  ->orWhere('municipio', 'like', "%{$s}%")
                  ->orWhere('rfc', 'like', "%{$s}%")
                  ->orWhere('descripcion', 'like', "%{$s}%")
                  ->orWhereHas('user', fn ($u) => $u
                      ->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%"));
            });
        }

        return $query->paginate($porPagina)->withQueryString();
    }

    /** Pendientes que ya pidieron aprobacion (el contador del menu). */
    public function contarPendientesAprobacion(): int
    {
        return Restaurantero::whereNotNull('solicitado_aprobacion_at')
            ->where('aprobado', false)
            ->where('rechazado', false)
            ->count();
    }

    /** Todos los no aprobados ni rechazados: primero los que pidieron aprobacion. */
    public function pendientes(): Collection
    {
        return Restaurantero::with('user')
            ->where('aprobado', false)
            ->where('rechazado', false)
            ->orderByRaw('solicitado_aprobacion_at IS NULL ASC')
            ->orderBy('created_at')
            ->get();
    }

    /** Ficha: citas paginadas, calendario, eventos en los que participa y contadores. */
    public function detalle(Restaurantero $restaurantero, int $porPagina = 20): array
    {
        $restaurantero->load(['user', 'servicios', 'horarios']);

        $citas = Cita::with([
            'servicio:id,nombre,duracion_minutos,precio',
            'cliente:id,name,email,telefono',
            'evento:id,nombre',
        ])
            ->where('restaurantero_id', $restaurantero->id)
            ->orderByDesc('inicio')
            ->paginate($porPagina);

        return [
            'restaurantero'       => $restaurantero,
            'citas'               => $citas,
            'citasCalendario'     => $this->citasCalendario($restaurantero),
            'eventosParticipados' => $this->eventosParticipados($restaurantero),
            'stats'               => $this->stats($restaurantero),
        ];
    }

    public function eventosParticipados(Restaurantero $restaurantero): Collection
    {
        if (!$restaurantero->user_id) {
            return collect();
        }

        return DB::table('evento_usuario')
            ->join('eventos', 'evento_usuario.evento_id', '=', 'eventos.id')
            ->where('evento_usuario.user_id', $restaurantero->user_id)
            ->where('evento_usuario.tipo', 'proveedor')
            ->select(
                'eventos.id',
                'eventos.nombre',
                'eventos.fecha_hora_inicio',
                'eventos.fecha_hora_fin',
                'eventos.activa',
                'evento_usuario.estado',
            )
            ->orderByDesc('eventos.fecha_hora_inicio')
            ->get();
    }

    public function stats(Restaurantero $restaurantero): array
    {
        return [
            'total'     => Cita::where('restaurantero_id', $restaurantero->id)->count(),
            'aceptadas' => Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'confirmada')->count(),
            'pendientes'=> Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'pendiente')->count(),
        ];
    }

    public function citasCalendario(Restaurantero $restaurantero): Collection
    {
        return Cita::where('restaurantero_id', $restaurantero->id)
            ->with(['servicio', 'cliente'])
            ->get()
            ->map(function ($cita) {
                return [
                    'id'    => $cita->id,
                    'title' => $cita->cliente->name . ' — ' . $cita->servicio->nombre,
                    'start' => $cita->inicio->toIso8601String(),
                    'end'   => $cita->fin->toIso8601String(),
                    'color' => match ($cita->estado) {
                        'confirmada'  => '#22c55e',
                        'cancelada'   => '#ef4444',
                        'completada'  => '#6366f1',
                        default       => '#f59e0b',
                    },
                    'extendedProps' => [
                        'estado'   => $cita->estado,
                        'cliente'  => $cita->cliente->name,
                        'servicio' => $cita->servicio->nombre,
                    ],
                ];
            });
    }

    public function reglasActualizar(Restaurantero $restaurantero): array
    {
        return [
            'nombre_restaurante'      => 'required|string|max:255',
            'telefono'                => 'nullable|string|max:30',
            'direccion'               => 'nullable|string|max:255',
            'municipio'               => 'nullable|string|max:100',
            'descripcion'             => 'nullable|string|max:1000',
            'logo'                    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'rfc'                     => 'nullable|string|max:13',
            'sitio_web'               => 'nullable|url|max:255',
            'categoria'               => 'nullable|string|max:100',
            'acepta_credito'          => 'nullable|boolean',
            'credito_monto_maximo'    => 'nullable|numeric|min:0',
            'credito_tiempo_cantidad' => 'nullable|integer|min:1',
            'credito_tiempo_unidad'   => ['nullable', 'string', Rule::in(['dias', 'semanas', 'meses'])],
            'credito_a_negociar'      => 'nullable|boolean',
            'pago_contraentrega'      => 'nullable|boolean',
            'factura'                 => 'nullable|boolean',
            'regimen_fiscal'          => 'nullable|string|max:100',
            'entrega_domicilio'       => 'nullable|boolean',
            'cobertura_entrega'       => ['nullable', 'string', Rule::in(['local', 'regional', 'nacional'])],
            'forma_entrega'           => ['nullable', 'string', Rule::in(['programada', 'flexible'])],
            'user_name'               => 'sometimes|required|string|max:255',
            'user_email'              => 'sometimes|required|email|max:255|unique:users,email,' . $restaurantero->user_id,
        ];
    }

    public const CAMPOS_EDITABLES = [
        'nombre_restaurante', 'telefono', 'direccion', 'municipio', 'descripcion',
        'rfc', 'sitio_web', 'categoria',
        'acepta_credito', 'credito_monto_maximo', 'credito_tiempo_cantidad', 'credito_tiempo_unidad', 'credito_a_negociar',
        'pago_contraentrega', 'factura', 'regimen_fiscal',
        'entrega_domicilio', 'cobertura_entrega', 'forma_entrega',
    ];

    /** Actualiza el perfil (y nombre/correo del usuario si llegan). Llamar con la peticion ya validada. */
    public function actualizar(Restaurantero $restaurantero, Request $request): Restaurantero
    {
        $data = $request->only(self::CAMPOS_EDITABLES);

        if ($request->has('user_name') || $request->has('user_email')) {
            $restaurantero->user->update([
                'name'  => $request->input('user_name', $restaurantero->user->name),
                'email' => $request->input('user_email', $restaurantero->user->email),
            ]);
        }

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');

            // extension() adivina la extension a partir del CONTENIDO real.
            // getClientOriginalExtension() devuelve lo que mande el cliente:
            // un JPEG renombrado a .php pasaba la validacion de mimes y se
            // guardaba como .php dentro de storage, que el servidor sirve.
            $extension = strtolower($file->extension());

            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                throw new ReglaDeNegocioException('El archivo debe ser una imagen JPG, PNG o WEBP.', 'logo');
            }

            $filename = 'logo_' . $restaurantero->id . '_' . uniqid() . '.' . $extension;
            $dir      = storage_path('app/public/logos');

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if ($restaurantero->logo_path && str_starts_with($restaurantero->logo_path, 'logos/logo_')) {
                @unlink(storage_path('app/public/' . $restaurantero->logo_path));
            }

            $file->move($dir, $filename);
            $data['logo_path'] = 'logos/' . $filename;
        }

        $restaurantero->update($data);

        return $restaurantero;
    }

    public function cambiarCategoria(Restaurantero $restaurantero, ?string $categoria): Restaurantero
    {
        $restaurantero->update(['categoria' => $categoria ?: null]);

        return $restaurantero;
    }

    public function alternarActivo(Restaurantero $restaurantero): Restaurantero
    {
        $restaurantero->update(['activo' => !$restaurantero->activo]);

        return $restaurantero;
    }

    public function aprobar(Restaurantero $restaurantero): Restaurantero
    {
        $restaurantero->update([
            'aprobado'        => true,
            'rechazado'       => false,
            'motivo_rechazo'  => null,
            'activo'          => true,
        ]);

        $restaurantero->load('user');

        try {
            if ($restaurantero->user?->email) {
                Mail::to($restaurantero->user->email)->send(new ProveedorAprobado($restaurantero));
            }
        } catch (\Throwable $e) {
            Log::warning('Error enviando correo de aprobación de proveedor: ' . $e->getMessage());
        }

        if ($restaurantero->user) {
            Notificacion::crear(
                $restaurantero->user->id,
                'info',
                '🎉 ¡Tu perfil fue aprobado!',
                'Tu perfil fue aprobado. Completa la información de tu negocio y podrás registrarte a los eventos disponibles.',
            );
        }

        return $restaurantero;
    }

    public function rechazar(Restaurantero $restaurantero, string $motivo): Restaurantero
    {
        $restaurantero->update([
            'aprobado'       => false,
            'rechazado'      => true,
            'motivo_rechazo' => $motivo,
            'activo'         => false,
        ]);

        $restaurantero->load('user');

        try {
            if ($restaurantero->user?->email) {
                Mail::to($restaurantero->user->email)->send(new ProveedorRechazado($restaurantero, $motivo));
            }
        } catch (\Throwable $e) {
            Log::warning('Error enviando correo de rechazo de proveedor: ' . $e->getMessage());
        }

        if ($restaurantero->user) {
            Notificacion::crear(
                $restaurantero->user->id,
                'info',
                'Resultado de revisión de perfil',
                'Tu perfil fue revisado. Revisa el correo para más detalles.',
            );
        }

        return $restaurantero;
    }
}
