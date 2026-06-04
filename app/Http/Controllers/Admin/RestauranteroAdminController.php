<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Edicion;
use App\Models\Horario;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class RestauranteroAdminController extends Controller
{
    public function index()
    {
        $restauranteros = Restaurantero::with('user')
            ->withCount('citas')
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('Admin/Restauranteros/Index', [
            'restauranteros' => $restauranteros,
            'categorias'     => Restaurantero::$categorias,
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
            'edicion_id'         => Edicion::activa()?->id,
            'nombre_restaurante' => $request->nombre_restaurante,
            'telefono'           => $request->telefono,
            'direccion'          => $request->direccion,
            'municipio'          => $request->municipio,
            'categoria'          => $request->categoria,
            'descripcion'        => $request->descripcion,
            'activo'             => true,
        ]);

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
        $categorias = Restaurantero::$categorias;

        $citas = Cita::with(['servicio', 'cliente'])
            ->where('restaurantero_id', $restaurantero->id)
            ->orderByDesc('inicio')
            ->paginate(20);

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
            'restaurantero'   => $restaurantero,
            'citas'           => $citas,
            'citasCalendario' => $citasCalendario,
            'categorias'      => $categorias,
        ]);
    }

    public function update(Request $request, Restaurantero $restaurantero)
    {
        $request->validate([
            'nombre_restaurante' => 'required|string|max:255',
            'telefono'           => 'nullable|string|max:30',
            'direccion'          => 'nullable|string|max:255',
            'municipio'          => 'nullable|string|max:100',
            'descripcion'        => 'nullable|string|max:1000',
            'logo'               => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $data = $request->only(['nombre_restaurante', 'telefono', 'direccion', 'municipio', 'descripcion']);

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
}
