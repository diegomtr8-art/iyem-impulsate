<?php

namespace App\Http\Controllers\Admin\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\PlantillaCorreoMail;
use App\Models\Evento;
use App\Models\PlantillaCorreo;
use App\Models\Restaurantero;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class CorreoMasivoController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/SuperAdmin/CorreoMasivo/Index', [
            'plantillas' => PlantillaCorreo::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'asunto', 'contenido', 'tipo_destinatario']),
            'eventos' => Evento::orderByDesc('fecha_hora_inicio')
                ->get(['id', 'nombre']),
            'categorias' => Restaurantero::categoriasActivas(),
        ]);
    }

    public function preview(Request $request)
    {
        $users = $this->filtrarDestinatarios($request);
        return response()->json([
            'total'         => $users->count(),
            'destinatarios' => $users->take(20)->map(fn ($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
                'rol'   => $u->roles->pluck('name')->join(', '),
            ])->values(),
        ]);
    }

    public function prueba(Request $request)
    {
        $request->validate([
            'email_prueba' => ['required', 'email'],
            'asunto'       => ['required', 'string', 'max:255'],
            'contenido'    => ['required', 'string'],
        ]);

        // La plantilla debe persistirse: PlantillaCorreoMail implementa ShouldQueue,
        // y el job serializa el modelo por ID para reconstruirlo al ejecutarse —
        // un modelo sin guardar provoca ModelNotFoundException y el correo nunca sale.
        $plantillaTemporal = PlantillaCorreo::updateOrCreate(
            ['clave' => 'prueba_correo_masivo'],
            [
                'nombre'     => 'Prueba Correo Masivo',
                'asunto'     => '[PRUEBA] ' . $request->asunto,
                'contenido'  => $request->contenido,
                'activo'     => false,
                'es_sistema' => false,
            ]
        );

        try {
            Mail::to($request->email_prueba)->send(
                new PlantillaCorreoMail($plantillaTemporal, [
                    'nombre_usuario' => 'Usuario de Prueba',
                ])
            );
            return back()->with('success', "Correo de prueba enviado a {$request->email_prueba}.");
        } catch (\Exception $e) {
            \Log::error('Error correo prueba masivo: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Error al enviar: ' . $e->getMessage()]);
        }
    }

    public function enviar(Request $request)
    {
        $request->validate([
            'asunto'    => ['required', 'string', 'max:255'],
            'contenido' => ['required', 'string'],
        ]);

        $users = $this->filtrarDestinatarios($request);

        if ($users->isEmpty()) {
            return back()->withErrors(['error' => 'No hay destinatarios con los filtros seleccionados.']);
        }

        // Igual que en prueba(): debe persistirse para que el job en cola pueda
        // restaurar el modelo (ver comentario arriba).
        $plantillaTemporal = PlantillaCorreo::create([
            'clave'      => 'correo_masivo_' . now()->timestamp,
            'nombre'     => 'Correo Masivo',
            'asunto'     => $request->asunto,
            'contenido'  => $request->contenido,
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
                \Log::warning("Correo masivo fallido para {$user->email}: " . $e->getMessage());
            }
        }

        return back()->with('success', "Correos en cola para {$enviados} destinatarios.");
    }

    private function filtrarDestinatarios(Request $request)
    {
        $tipo            = $request->input('tipo', 'todos');
        $categoria       = $request->input('categoria');
        $eventoId        = $request->input('evento_id');
        $estadoEvento    = $request->input('estado_evento');
        $estadoProveedor = $request->input('estado_proveedor');

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
            $userIdsEnEvento = $eventoQuery->pluck('user_id');
            $query->whereIn('id', $userIdsEnEvento);
        }

        return $query->get();
    }
}
