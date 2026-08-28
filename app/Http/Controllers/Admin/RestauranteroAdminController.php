<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ProveedorAprobado;
use App\Mail\ProveedorRechazado;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RestauranteroAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Restaurantero::with('user')
            ->withCount('citas')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nombre_restaurante', 'like', "%{$s}%")
                  ->orWhere('telefono', 'like', "%{$s}%")
                  ->orWhere('municipio', 'like', "%{$s}%")
                  ->orWhere('rfc', 'like', "%{$s}%")
                  ->orWhere('descripcion', 'like', "%{$s}%")
                  ->orWhereHas('user', fn($u) => $u
                      ->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%"));
            });
        }

        $restauranteros = $query->paginate(15)->withQueryString();

        $pendientesAprobacion = Restaurantero::whereNotNull('solicitado_aprobacion_at')
            ->where('aprobado', false)
            ->where('rechazado', false)
            ->count();

        $proveedoresPendientes = Restaurantero::with('user')
            ->where('aprobado', false)
            ->where('rechazado', false)
            ->orderByRaw('solicitado_aprobacion_at IS NULL ASC')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Admin/Restauranteros/Index', [
            'restauranteros'        => $restauranteros,
            'categorias'            => Restaurantero::categoriasActivas(),
            'pendientesAprobacion'  => $pendientesAprobacion,
            'proveedoresPendientes' => $proveedoresPendientes,
            'filters'               => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'               => 'required|string|max:255',
            'email'              => 'required|email|unique:users,email',
            'password'           => 'required|string|min:8',
            'nombre_restaurante' => 'required|string|max:255',
            'telefono'           => 'nullable|string|max:30',
            'direccion'          => 'nullable|string|max:255',
            'municipio'          => 'nullable|string|max:100',
            'categoria'          => 'nullable|string|max:100',
            'descripcion'        => 'nullable|string|max:1000',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->assignRole('restaurantero');

        $restaurantero = Restaurantero::create([
            'user_id'            => $user->id,
            'edicion_id'         => Evento::contextoAdmin()?->id,
            'nombre_restaurante' => $request->nombre_restaurante,
            'telefono'           => $request->telefono,
            'direccion'          => $request->direccion,
            'municipio'          => $request->municipio,
            'categoria'          => $request->categoria,
            'descripcion'        => $request->descripcion,
            'activo'             => true,
        ]);

        Evento::registrarProveedorEnEventoActivo($user->id, true);

        Servicio::create([
            'restaurantero_id' => $restaurantero->id,
            'nombre'           => 'Mesa de Networking',
            'duracion_minutos' => 30,
            'precio'           => 0,
            'activo'           => true,
        ]);

        for ($dia = 1; $dia <= 5; $dia++) {
            Horario::create([
                'restaurantero_id' => $restaurantero->id,
                'dia_semana'       => $dia,
                'hora_inicio'      => '09:00:00',
                'hora_fin'         => '16:00:00',
                'activo'           => true,
            ]);
        }

        return redirect()->route('admin.restauranteros.index')
            ->with('success', 'Proveedor creado correctamente.');
    }

    public function show(Restaurantero $restaurantero)
    {
        $restaurantero->load(['user', 'servicios', 'horarios']);
        $categorias = Restaurantero::categoriasActivas();

        $citas = Cita::with([
            'servicio:id,nombre,duracion_minutos,precio',
            'cliente:id,name,email,telefono',
            'evento:id,nombre',
        ])
            ->where('restaurantero_id', $restaurantero->id)
            ->orderByDesc('inicio')
            ->paginate(20);

        $eventosParticipados = collect();
        if ($restaurantero->user_id) {
            $eventosParticipados = DB::table('evento_usuario')
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

        $totalCitas = Cita::where('restaurantero_id', $restaurantero->id)->count();
        $citasAcept = Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'confirmada')->count();
        $citasPend  = Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'pendiente')->count();

        $citasCalendario = Cita::where('restaurantero_id', $restaurantero->id)
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

        return Inertia::render('Admin/Restauranteros/Show', [
            'restaurantero'       => $restaurantero,
            'citas'               => $citas,
            'citasCalendario'     => $citasCalendario,
            'categorias'          => $categorias,
            'eventosParticipados' => $eventosParticipados,
            'stats'               => [
                'total'     => $totalCitas,
                'aceptadas' => $citasAcept,
                'pendientes'=> $citasPend,
            ],
        ]);
    }

    public function editar(Restaurantero $restaurantero)
    {
        $restaurantero->load('user:id,name,email');

        return Inertia::render('Admin/Restauranteros/Editar', [
            'restaurantero' => $restaurantero,
            'categorias'    => Restaurantero::categoriasActivas(),
        ]);
    }

    public function update(Request $request, Restaurantero $restaurantero)
    {
        $request->validate([
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
        ]);

        $data = $request->only([
            'nombre_restaurante', 'telefono', 'direccion', 'municipio', 'descripcion',
            'rfc', 'sitio_web', 'categoria',
            'acepta_credito', 'credito_monto_maximo', 'credito_tiempo_cantidad', 'credito_tiempo_unidad', 'credito_a_negociar',
            'pago_contraentrega', 'factura', 'regimen_fiscal',
            'entrega_domicilio', 'cobertura_entrega', 'forma_entrega',
        ]);

        if ($request->has('user_name') || $request->has('user_email')) {
            $restaurantero->user->update([
                'name'  => $request->input('user_name', $restaurantero->user->name),
                'email' => $request->input('user_email', $restaurantero->user->email),
            ]);
        }

        if ($request->hasFile('logo')) {
            $file     = $request->file('logo');
            $filename = 'logo_' . $restaurantero->id . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
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

        return back()->with('success', 'Perfil actualizado correctamente.');
    }

    public function updateCategoria(Request $request, Restaurantero $restaurantero)
    {
        $request->validate(['categoria' => 'nullable|string|max:100']);
        $restaurantero->update(['categoria' => $request->categoria ?: null]);

        return back()->with('success', 'Categoría actualizada correctamente.');
    }

    public function toggleActivo(Restaurantero $restaurantero)
    {
        $restaurantero->update(['activo' => !$restaurantero->activo]);

        return back()->with('success', 'Estado actualizado correctamente.');
    }

    public function destroy(Restaurantero $restaurantero)
    {
        $user = $restaurantero->user;
        $restaurantero->delete();
        $user->delete();

        return redirect()->route('admin.restauranteros.index')
            ->with('success', 'Restaurantero eliminado correctamente.');
    }

    public function aprobar(Restaurantero $restaurantero)
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
        } catch (\Exception $e) {}

        if ($restaurantero->user) {
            Notificacion::crear(
                $restaurantero->user->id,
                'info',
                '🎉 ¡Tu perfil fue aprobado!',
                'Tu perfil fue aprobado. Completa la información de tu negocio y podrás registrarte a los eventos disponibles.',
            );
        }

        return back()->with('success', 'Proveedor aprobado y notificado.');
    }

    public function rechazarAprobacion(Request $request, Restaurantero $restaurantero)
    {
        $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $restaurantero->update([
            'aprobado'       => false,
            'rechazado'      => true,
            'motivo_rechazo' => $request->motivo,
            'activo'         => false,
        ]);

        $restaurantero->load('user');

        try {
            if ($restaurantero->user?->email) {
                Mail::to($restaurantero->user->email)->send(new ProveedorRechazado($restaurantero, $request->motivo));
            }
        } catch (\Exception $e) {}

        if ($restaurantero->user) {
            Notificacion::crear(
                $restaurantero->user->id,
                'info',
                'Resultado de revisión de perfil',
                'Tu perfil fue revisado. Revisa el correo para más detalles.',
            );
        }

        return back()->with('success', 'Perfil rechazado y proveedor notificado.');
    }
}
