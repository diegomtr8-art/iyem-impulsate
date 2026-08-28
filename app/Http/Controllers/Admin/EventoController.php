<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\EventoCriterio;
use App\Models\Restaurantero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EventoController extends Controller
{
    public function index()
    {
        $pendientesPorEvento = DB::table('evento_usuario')
            ->where('estado', 'pendiente')
            ->selectRaw('evento_id, count(*) as total')
            ->groupBy('evento_id')
            ->pluck('total', 'evento_id');

        $eventos = Evento::withCount(['citas', 'restauranteros'])
            ->with('criterios')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($evento) use ($pendientesPorEvento) {
                $evento->usuarios_count = Cita::where('edicion_id', $evento->id)
                    ->distinct('cliente_id')
                    ->count('cliente_id');
                $evento->solicitudes_pendientes = $pendientesPorEvento[$evento->id] ?? 0;
                $evento->imagen_url = $evento->imagen_url;
                return $evento;
            });

        return Inertia::render('Admin/Eventos/Index', [
            'eventos'   => $eventos,
            'categorias' => Restaurantero::categoriasActivas(),
        ]);
    }

    private function validationRules(bool $requireImagen = false): array
    {
        return [
            'nombre'                          => 'required|string|max:200',
            'imagen'                          => ($requireImagen ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png,webp|max:4096',
            'sector_economico'                => 'nullable|string|max:100',
            'descripcion'                     => 'nullable|string|max:1000',
            'fecha_hora_inicio'               => 'nullable|date',
            'fecha_hora_fin'                  => 'nullable|date|after_or_equal:fecha_hora_inicio',
            'fecha_hora_inicio_proveedores'   => 'nullable|date',
            'fecha_hora_fin_proveedores'      => 'nullable|date|after_or_equal:fecha_hora_inicio_proveedores',
            'fecha_hora_inicio_compradores'   => 'nullable|date|after_or_equal:fecha_hora_inicio_proveedores',
            'fecha_hora_fin_compradores'      => 'nullable|date|after_or_equal:fecha_hora_inicio_compradores',
            'convocatoria_url'                => 'required|url|max:500',
            'imagen_carrusel'                 => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'max_citas_por_comprador'         => 'nullable|integer|min:1|max:50',
            'tiempo_entre_citas_minutos'      => [
                'nullable', 'integer', 'min:5', 'max:120',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value % 5 !== 0) {
                        $fail('El tiempo entre citas debe ser múltiplo de 5 (5, 10, 15, 20, 25, 30...).');
                    }
                },
            ],
            'tipo_evento'                     => 'required|in:encuentro_negocios,bazar_exposicion',
            'fecha_aceptacion_solicitudes'    => 'nullable|date',
            'max_espacios'                    => 'nullable|integer|min:1|max:9999',
            'con_criterios_evaluacion'        => 'boolean',
            'criterios'                       => 'nullable|array',
            'criterios.*.id'                  => 'nullable|integer',
            'criterios.*.nombre'              => 'required_with:criterios|string|max:200',
            'criterios.*.porcentaje'          => 'required_with:criterios|numeric|min:1|max:100',
        ];
    }

    private function validationAttributes(): array
    {
        return [
            'nombre'                        => 'nombre del evento',
            'convocatoria_url'              => 'liga de la convocatoria',
            'imagen'                        => 'imagen del evento',
            'imagen_carrusel'               => 'imagen para el carrusel',
            'sector_economico'              => 'sector económico',
            'fecha_hora_inicio'             => 'fecha de inicio del evento',
            'fecha_hora_fin'                => 'fecha de fin del evento',
            'fecha_hora_inicio_proveedores' => 'apertura de registro de proveedores',
            'fecha_hora_fin_proveedores'    => 'cierre de registro de proveedores',
            'fecha_hora_inicio_compradores' => 'apertura de registro de compradores',
            'fecha_hora_fin_compradores'    => 'cierre de registro de compradores',
            'max_citas_por_comprador'       => 'máximo de citas por comprador',
            'tiempo_entre_citas_minutos'    => 'tiempo entre citas',
            'max_espacios'                  => 'número máximo de espacios',
            'fecha_aceptacion_solicitudes'  => 'apertura de solicitudes',
        ];
    }

    private function validationMessages(): array
    {
        return [
            'convocatoria_url.required' => 'La liga de la convocatoria es obligatoria.',
            'convocatoria_url.url'      => 'La liga de la convocatoria debe ser una URL completa, incluyendo https:// (ej. https://ejemplo.com/convocatoria.pdf).',
        ];
    }

    /**
     * Valida la petición dejando rastro en el log cuando falla, para poder
     * diagnosticar en producción qué regla rechazó un evento (los 422 no
     * generan entrada en laravel.log por sí solos).
     */
    private function validarEvento(Request $request, string $accion): void
    {
        try {
            $request->validate($this->validationRules(), $this->validationMessages(), $this->validationAttributes());
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::warning("Evento: validación fallida al {$accion}", [
                'usuario' => optional($request->user())->email,
                'errores' => $e->errors(),
                'datos'   => $request->except(['imagen', 'imagen_carrusel', '_token', '_method']),
            ]);
            throw $e;
        }
    }

    private function validarSumaCriterios(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        if ($request->boolean('con_criterios_evaluacion') && !empty($request->criterios)) {
            $total = collect($request->criterios)->sum('porcentaje');
            if (abs($total - 100) > 0.01) {
                return back()->withErrors(['criterios' => 'Los porcentajes deben sumar exactamente 100%.']);
            }
        }
        return null;
    }

    /**
     * Sincroniza los criterios SIN borrarlos todos.
     * Borrar y recrear destruye evento_evaluaciones por cascadeOnDelete,
     * perdiendo el trabajo del jurado en cualquier edicion del evento.
     */
    private function sincronizarCriterios(Evento $evento, Request $request): void
    {
        // Si el evento no usa rubrica, no se toca nada: desactivar la casilla
        // no debe destruir evaluaciones ya hechas.
        if (!$request->boolean('con_criterios_evaluacion')) {
            return;
        }

        $enviados = $request->criterios ?? [];

        // Sin criterios en el formulario tampoco borramos: seria el mismo
        // accidente por otra via.
        if (empty($enviados)) {
            return;
        }

        $idsConservados = [];

        foreach ($enviados as $index => $criterio) {
            $id = $criterio['id'] ?? null;

            $existente = $id
                ? EventoCriterio::where('evento_id', $evento->id)->find($id)
                : EventoCriterio::where('evento_id', $evento->id)
                    ->where('nombre', $criterio['nombre'])
                    ->first();

            if ($existente) {
                $existente->update([
                    'nombre'     => $criterio['nombre'],
                    'porcentaje' => $criterio['porcentaje'],
                    'orden'      => $index,
                ]);
                $idsConservados[] = $existente->id;
            } else {
                $nuevo = EventoCriterio::create([
                    'evento_id'  => $evento->id,
                    'nombre'     => $criterio['nombre'],
                    'porcentaje' => $criterio['porcentaje'],
                    'orden'      => $index,
                ]);
                $idsConservados[] = $nuevo->id;
            }
        }

        // Solo se borran los que el admin quito explicitamente del formulario.
        $eliminados = EventoCriterio::where('evento_id', $evento->id)
            ->whereNotIn('id', $idsConservados)
            ->get();

        foreach ($eliminados as $criterio) {
            Log::info("[criterios] Eliminando criterio {$criterio->id} ('{$criterio->nombre}') del evento {$evento->id}. Se perderan sus evaluaciones.");
            $criterio->delete();
        }
    }

    private function eventoFields(Request $request): array
    {
        return [
            'nombre'                         => $request->nombre,
            'sector_economico'               => $request->sector_economico,
            'descripcion'                    => $request->descripcion,
            'convocatoria_url'               => $request->convocatoria_url,
            'fecha_hora_inicio'              => $request->fecha_hora_inicio,
            'fecha_hora_fin'                 => $request->fecha_hora_fin,
            'fecha_hora_inicio_proveedores'  => $request->fecha_hora_inicio_proveedores,
            'fecha_hora_fin_proveedores'     => $request->fecha_hora_fin_proveedores,
            'fecha_hora_inicio_compradores'  => $request->fecha_hora_inicio_compradores,
            'fecha_hora_fin_compradores'     => $request->fecha_hora_fin_compradores,
            'max_citas_por_comprador'        => $request->max_citas_por_comprador ?? 3,
            'tiempo_entre_citas_minutos'     => $request->tiempo_entre_citas_minutos ?? 30,
            'tipo_evento'                    => $request->tipo_evento ?? 'encuentro_negocios',
            'fecha_aceptacion_solicitudes'   => $request->fecha_aceptacion_solicitudes ?: null,
            'max_espacios'                   => $request->tipo_evento === 'bazar_exposicion'
                                                    ? $request->max_espacios
                                                    : null,
            'con_criterios_evaluacion'       => $request->boolean('con_criterios_evaluacion'),
        ];
    }

    public function store(Request $request)
    {
        $this->validarEvento($request, 'crear');

        if ($error = $this->validarSumaCriterios($request)) {
            return $error;
        }

        $fields = array_merge($this->eventoFields($request), [
            'fecha_inicio' => $request->fecha_hora_inicio ?? now()->toDateString(),
            'activa'       => false,
        ]);

        if ($request->hasFile('imagen')) {
            $fields['imagen'] = $request->file('imagen')->store('eventos', 'public');
        }

        if ($request->hasFile('imagen_carrusel')) {
            $fields['imagen_carrusel'] = $request->file('imagen_carrusel')->store('eventos', 'public');
        }

        $evento = Evento::create($fields);

        $this->sincronizarCriterios($evento, $request);

        return back()->with('success', 'Evento creado. Actívalo cuando estés listo.');
    }

    public function update(Request $request, Evento $evento)
    {
        $this->validarEvento($request, 'editar');

        if ($error = $this->validarSumaCriterios($request)) {
            return $error;
        }

        $fields = $this->eventoFields($request);

        if ($request->hasFile('imagen')) {
            if ($evento->imagen) {
                Storage::disk('public')->delete($evento->imagen);
            }
            $fields['imagen'] = $request->file('imagen')->store('eventos', 'public');
        }

        if ($request->hasFile('imagen_carrusel')) {
            if ($evento->imagen_carrusel) {
                Storage::disk('public')->delete($evento->imagen_carrusel);
            }
            $fields['imagen_carrusel'] = $request->file('imagen_carrusel')->store('eventos', 'public');
        }

        $evento->update($fields);

        $this->sincronizarCriterios($evento, $request);

        return back()->with('success', 'Evento actualizado correctamente.');
    }

    public function enviarEncuestas(Evento $evento)
    {
        $plantilla = \App\Models\EncuestaPlantilla::where('activa', true)->first();

        if (!$plantilla) {
            return back()->withErrors(['error' => 'No hay plantilla de encuesta activa. Activa una plantilla primero.']);
        }

        $enviados = app(\App\Services\EncuestaEnvioService::class)->enviarParaEvento($evento, $plantilla);

        return back()->with('success', "Encuestas enviadas a {$enviados} participante(s). Las ya existentes se omitieron.");
    }

    public function archivar(Evento $evento)
    {
        if (!$evento->activa) {
            return back()->withErrors(['error' => 'Este evento no está activo.']);
        }

        $evento->update([
            'activa'      => false,
            'fecha_corte' => now()->toDateString(),
        ]);

        // Si el admin estaba operando sobre este evento, se limpia el contexto.
        if (session('admin_evento_id') == $evento->id) {
            session()->forget('admin_evento_id');
        }

        return back()->with('success', 'Evento "' . $evento->nombre . '" archivado correctamente.');
    }

    public function activar(Evento $evento)
    {
        // Se pueden tener varios eventos activos a la vez: activar uno ya no
        // desactiva los demás.
        $evento->update([
            'activa'      => true,
            'fecha_corte' => null,
        ]);

        // Sync service duration for all approved providers in this event
        if ($evento->tiempo_entre_citas_minutos) {
            \App\Models\Servicio::whereIn('restaurantero_id',
                \App\Models\Restaurantero::whereExists(function ($q) use ($evento) {
                    $q->from('evento_usuario')
                        ->whereColumn('evento_usuario.user_id', 'restauranteros.user_id')
                        ->where('evento_usuario.evento_id', $evento->id)
                        ->where('evento_usuario.tipo', 'proveedor')
                        ->where('evento_usuario.estado', 'aprobado');
                })->pluck('id')
            )->update(['duracion_minutos' => $evento->tiempo_entre_citas_minutos]);
        }

        return back()->with('success', 'Evento "' . $evento->nombre . '" activado.');
    }

    /**
     * Cambia el evento sobre el que operan las pantallas de gestión (citas,
     * agenda, exportaciones). La elección vive en la sesión del admin.
     */
    public function contexto(Request $request)
    {
        $request->validate([
            'evento_id' => 'nullable|integer',
        ]);

        if (!$request->evento_id) {
            session()->forget('admin_evento_id');
            return back()->with('success', 'Ahora se usa el evento activo más próximo.');
        }

        $evento = Evento::queryActivos()->where('id', $request->evento_id)->first();

        if (!$evento) {
            return back()->withErrors(['evento_id' => 'Ese evento ya no está activo.']);
        }

        session(['admin_evento_id' => $evento->id]);

        return back()->with('success', 'Ahora estás trabajando sobre "' . $evento->nombre . '".');
    }

    public function destroy(Evento $evento)
    {
        if ($evento->activa) {
            return back()->withErrors(['error' => 'No puedes eliminar el evento activo. Archívalo primero.']);
        }

        if ($evento->citas()->count() > 0) {
            return back()->withErrors(['error' => 'No puedes eliminar un evento que tiene citas registradas.']);
        }

        $evento->delete();

        return back()->with('success', 'Evento eliminado correctamente.');
    }
}
