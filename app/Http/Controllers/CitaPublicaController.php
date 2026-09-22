<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Evento;
use App\Services\AgendaCitaService;
use App\Services\DirectorioProveedoresService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CitaPublicaController extends Controller
{
    public function store(Request $request, AgendaCitaService $agenda)
    {
        // El administrador no agenda citas para sí mismo
        $agenda->asegurarNoEsAdmin($request->user());

        $datos = $request->validate(AgendaCitaService::REGLAS_ALTA_COMPRADOR);

        // Las reglas (evento aprobado, ventanas, cupo, solapes, duracion) viven en
        // AgendaCitaService; una ReglaDeNegocioException vuelve como withErrors.
        $agenda->agendarComoComprador($request->user(), $datos);

        return redirect()->route('user.dashboard')
            ->with('success', 'Cita solicitada correctamente.');
    }

    public function destroy(Cita $cita, AgendaCitaService $agenda)
    {
        if ($cita->cliente_id !== auth()->id()) {
            abort(403);
        }

        $agenda->cancelarComoComprador($cita);

        return back()->with('success', 'Cita cancelada correctamente.');
    }

    public function dashboard(Request $request, DirectorioProveedoresService $directorio)
    {
        $evento = Evento::activo();

        $citas = collect();
        $citasCount = 0;

        if ($evento) {
            $citas = $request->user()
                ->citasComoCliente()
                ->with(['restaurantero', 'servicio'])
                ->where('edicion_id', $evento->id)
                ->orderByDesc('inicio')
                ->get();

            $citasCount = $citas->whereNotIn('estado', ['cancelada'])->count();
        }

        // Eventos pasados que tienen citas del usuario
        $edicionesHistorial = Evento::where('activa', false)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($ev) use ($request) {
                $ev->mis_citas = $request->user()
                    ->citasComoCliente()
                    ->with(['restaurantero', 'servicio'])
                    ->where('edicion_id', $ev->id)
                    ->orderByDesc('inicio')
                    ->get();
                return $ev;
            })
            ->filter(fn($ev) => $ev->mis_citas->isNotEmpty())
            ->values();

        $keywords = $directorio->palabrasClave($request->user()->necesidades);

        // Solo mostrar proveedores si el comprador está aprobado en el evento activo
        $compradorAprobado = $evento && DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $request->user()->id)
            ->where('tipo', 'comprador')
            ->where('estado', 'aprobado')
            ->exists();

        $proveedores = collect();

        if ($compradorAprobado) {
            $proveedores = $directorio->delEvento($evento)
                ->withCount(['citas as citas_evento_count' => fn($q) =>
                    $q->where('edicion_id', $evento->id)->whereNotIn('estado', ['cancelada', 'rechazada'])
                ])
                ->select(['id', 'nombre_restaurante', 'categoria', 'descripcion', 'logo_path', 'municipio', 'productos_top'])
                ->get();
            $proveedores = $directorio->ordenarPorCoincidencia($proveedores, $keywords)
                ->take(20)
                ->values();
        }

        $notificaciones = \App\Models\Notificacion::where('user_id', $request->user()->id)
            ->where('leida', false)
            ->orderByDesc('created_at')
            ->take(3)
            ->get(['id', 'tipo', 'titulo', 'mensaje', 'cita_id', 'created_at']);

        $todosLosEventos = Evento::orderByDesc('fecha_hora_inicio')->get()
            ->map(function ($ev) use ($request) {
                $registro = DB::table('evento_usuario')
                    ->where('evento_id', $ev->id)
                    ->where('user_id', $request->user()->id)
                    ->get();
                return [
                    'id'                            => $ev->id,
                    'nombre'                        => $ev->nombre,
                    'sector_economico'              => $ev->sector_economico,
                    'descripcion'                   => $ev->descripcion,
                    'activa'                        => $ev->activa,
                    'tipo_evento'                   => $ev->tipo_evento ?? 'encuentro_negocios',
                    'convocatoria_url'              => $ev->convocatoria_url,
                    'fecha_hora_inicio'             => $ev->fecha_hora_inicio?->toISOString(),
                    'fecha_hora_fin'                => $ev->fecha_hora_fin?->toISOString(),
                    'fecha_hora_inicio_proveedores' => $ev->fecha_hora_inicio_proveedores?->toISOString(),
                    'fecha_hora_fin_proveedores'    => $ev->fecha_hora_fin_proveedores?->toISOString(),
                    'fecha_hora_inicio_compradores' => $ev->fecha_hora_inicio_compradores?->toISOString(),
                    'fecha_hora_fin_compradores'    => $ev->fecha_hora_fin_compradores?->toISOString(),
                    'fecha_aceptacion_solicitudes'  => $ev->fecha_aceptacion_solicitudes?->toISOString(),
                    'max_citas_por_comprador'       => $ev->max_citas_por_comprador,
                    'tiempo_entre_citas_minutos'    => $ev->tiempo_entre_citas_minutos,
                    'registro_proveedor_abierto'    => $ev->registroProveedoresAbierto(),
                    'registro_comprador_abierto'    => $ev->registroCompradoresAbierto(),
                    'segundos_hasta_proveedores'    => $ev->segundosHastaProveedores(),
                    'segundos_hasta_compradores'    => $ev->segundosHastaCompradores(),
                    'segundos_hasta_solicitudes'    => $ev->segundosHastaSolicitudes(),
                    'registro_solicitudes_abierto'  => $ev->registroSolicitudesAbierto(),
                    'mi_registro' => [
                        'comprador' => $registro->firstWhere('tipo', 'comprador'),
                        'proveedor' => $registro->firstWhere('tipo', 'proveedor'),
                        'expositor' => $registro->firstWhere('tipo', 'expositor'),
                    ],
                ];
            });

        return Inertia::render('User/Dashboard', [
            'citas'              => $citas,
            'citasCount'         => $citasCount,
            'evento'             => $evento,
            'edicionesHistorial' => $edicionesHistorial,
            'proveedores'        => $proveedores,
            'compradorAprobado'  => $compradorAprobado,
            'tieneKeywords'      => $keywords->isNotEmpty(),
            'notificaciones'     => $notificaciones,
            'eventos'            => $todosLosEventos,
        ]);
    }

    public function actualizarNotas(Request $request, Cita $cita, AgendaCitaService $agenda)
    {
        if ($cita->cliente_id !== auth()->id()) {
            abort(403);
        }

        $request->validate(['notas' => 'nullable|string|max:1000']);
        $agenda->actualizarNotas($cita, $request->notas);

        return back()->with('success', 'Notas actualizadas.');
    }
}
