<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Cita;
use App\Models\EncuestaPlantilla;
use App\Models\Evento;
use App\Models\EventoCriterio;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Services\EncuestaEnvioService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Alta, edicion, activacion, archivo y baja de eventos desde el panel admin.
 * Lo usan Admin\EventoController (web) y la API v1 (/admin/eventos).
 *
 * Reglas movidas tal cual del controlador web: validacion con log de los 422,
 * porcentajes de criterios que suman 100, criterios sincronizados sin borrar
 * evaluaciones, un solo evento destacado y la duracion de los servicios
 * sincronizada al activar.
 */
class AdminEventosService
{
    public const TIPOS = ['encuentro_negocios', 'bazar_exposicion'];

    /** Lista del admin con contadores (misma forma que la pagina Admin/Eventos/Index). */
    public function listar(): Collection
    {
        $pendientesPorEvento = DB::table('evento_usuario')
            ->where('estado', 'pendiente')
            ->selectRaw('evento_id, count(*) as total')
            ->groupBy('evento_id')
            ->pluck('total', 'evento_id');

        return Evento::withCount(['citas', 'restauranteros'])
            ->with('criterios')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Evento $evento) => $this->conContadores($evento, $pendientesPorEvento[$evento->id] ?? 0));
    }

    /** Un evento con los mismos contadores que la lista. */
    public function detalle(Evento $evento): Evento
    {
        $evento->loadCount(['citas', 'restauranteros'])->load('criterios');

        $pendientes = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('estado', 'pendiente')
            ->count();

        return $this->conContadores($evento, $pendientes);
    }

    private function conContadores(Evento $evento, int $pendientes): Evento
    {
        $evento->usuarios_count = Cita::where('edicion_id', $evento->id)
            ->distinct('cliente_id')
            ->count('cliente_id');
        $evento->solicitudes_pendientes = $pendientes;
        $evento->imagen_url = $evento->imagen_url;

        return $evento;
    }

    public function reglas(bool $requireImagen = false): array
    {
        return [
            'nombre'                          => 'required|string|max:200',
            'imagen'                          => ($requireImagen ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png,webp|max:10240',
            'sector_economico'                => 'nullable|string|max:100',
            'descripcion'                     => 'nullable|string|max:1000',
            'fecha_hora_inicio'               => 'nullable|date',
            'fecha_hora_fin'                  => 'nullable|date|after_or_equal:fecha_hora_inicio',
            'fecha_hora_inicio_proveedores'   => 'nullable|date',
            'fecha_hora_fin_proveedores'      => 'nullable|date|after_or_equal:fecha_hora_inicio_proveedores',
            'fecha_hora_inicio_compradores'   => 'nullable|date|after_or_equal:fecha_hora_inicio_proveedores',
            'fecha_hora_fin_compradores'      => 'nullable|date|after_or_equal:fecha_hora_inicio_compradores',
            'convocatoria_url'                => 'required|url|max:500',
            'imagen_carrusel'                 => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
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
            'destacado'                       => 'boolean',
            'fecha_aceptacion_solicitudes'    => 'nullable|date',
            'max_espacios'                    => 'nullable|integer|min:1|max:9999',
            'con_criterios_evaluacion'        => 'boolean',
            'criterios'                       => 'nullable|array',
            'criterios.*.id'                  => 'nullable|integer',
            'criterios.*.nombre'              => 'required_with:criterios|string|max:200',
            'criterios.*.porcentaje'          => 'required_with:criterios|numeric|min:1|max:100',
        ];
    }

    public function atributos(): array
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

    public function mensajes(): array
    {
        return [
            'convocatoria_url.required' => 'La liga de la convocatoria es obligatoria.',
            'convocatoria_url.url'      => 'La liga de la convocatoria debe ser una URL completa, incluyendo https:// (ej. https://ejemplo.com/convocatoria.pdf).',
        ];
    }

    /**
     * Valida la petición dejando rastro en el log cuando falla, para poder
     * diagnosticar en producción qué regla rechazó un evento (los 422 no
     * generan entrada en laravel.log por sí solos). Tambien exige que los
     * porcentajes de los criterios sumen 100 (422 en 'criterios').
     */
    public function validar(Request $request, string $accion): void
    {
        try {
            $request->validate($this->reglas(), $this->mensajes(), $this->atributos());
        } catch (ValidationException $e) {
            Log::warning("Evento: validación fallida al {$accion}", [
                'usuario' => optional($request->user())->email,
                'errores' => $e->errors(),
                'datos'   => $request->except(['imagen', 'imagen_carrusel', '_token', '_method']),
            ]);
            throw $e;
        }

        if ($request->boolean('con_criterios_evaluacion') && !empty($request->criterios)) {
            $total = collect($request->criterios)->sum('porcentaje');
            if (abs($total - 100) > 0.01) {
                throw new ReglaDeNegocioException('Los porcentajes deben sumar exactamente 100%.', 'criterios');
            }
        }
    }

    /** Crea el evento INACTIVO. Llamar despues de validar(). */
    public function crear(Request $request): Evento
    {
        $fields = array_merge($this->campos($request), [
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

        $this->unSoloDestacado($evento, $request);
        $this->sincronizarCriterios($evento, $request);

        return $evento;
    }

    /** Actualiza el evento (reemplaza imagenes si llegan nuevas). Llamar despues de validar(). */
    public function actualizar(Evento $evento, Request $request): Evento
    {
        $fields = $this->campos($request);

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

        $this->unSoloDestacado($evento, $request);
        $this->sincronizarCriterios($evento, $request);

        return $evento;
    }

    /**
     * Solo puede haber un evento destacado: al marcar uno, se desmarcan
     * los demas. Sin esto el orden entre dos destacados seria arbitrario.
     */
    private function unSoloDestacado(Evento $evento, Request $request): void
    {
        if ($request->boolean('destacado')) {
            Evento::where('id', '!=', $evento->id)
                ->where('destacado', true)
                ->update(['destacado' => false]);
        }
    }

    private function campos(Request $request): array
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
            'destacado'                      => $request->boolean('destacado'),
        ];
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

    /** Envia la encuesta de satisfaccion (plantilla activa) a los participantes. Devuelve cuantas se enviaron. */
    public function enviarEncuestas(Evento $evento): int
    {
        $plantilla = EncuestaPlantilla::where('activa', true)->first();

        if (!$plantilla) {
            throw new ReglaDeNegocioException('No hay plantilla de encuesta activa. Activa una plantilla primero.');
        }

        return app(EncuestaEnvioService::class)->enviarParaEvento($evento, $plantilla);
    }

    public function archivar(Evento $evento): Evento
    {
        if (!$evento->activa) {
            throw new ReglaDeNegocioException('Este evento no está activo.');
        }

        $evento->update([
            'activa'      => false,
            'fecha_corte' => now()->toDateString(),
        ]);

        return $evento;
    }

    /**
     * Se pueden tener varios eventos activos a la vez: activar uno ya no
     * desactiva los demás. Sincroniza la duracion del servicio de los
     * proveedores aprobados con el tiempo entre citas del evento.
     */
    public function activar(Evento $evento): Evento
    {
        $evento->update([
            'activa'      => true,
            'fecha_corte' => null,
        ]);

        if ($evento->tiempo_entre_citas_minutos) {
            Servicio::whereIn('restaurantero_id',
                Restaurantero::whereExists(function ($q) use ($evento) {
                    $q->from('evento_usuario')
                        ->whereColumn('evento_usuario.user_id', 'restauranteros.user_id')
                        ->where('evento_usuario.evento_id', $evento->id)
                        ->where('evento_usuario.tipo', 'proveedor')
                        ->where('evento_usuario.estado', 'aprobado');
                })->pluck('id')
            )->update(['duracion_minutos' => $evento->tiempo_entre_citas_minutos]);
        }

        return $evento;
    }

    public function eliminar(Evento $evento): void
    {
        if ($evento->activa) {
            throw new ReglaDeNegocioException('No puedes eliminar el evento activo. Archívalo primero.');
        }

        if ($evento->citas()->count() > 0) {
            throw new ReglaDeNegocioException('No puedes eliminar un evento que tiene citas registradas.');
        }

        $evento->delete();
    }

    /**
     * Evento de CONTEXTO para la API. La web lo guarda en la sesion
     * (Evento::contextoAdmin); la app lo manda explicito como evento_id.
     * Sin evento_id: el evento activo principal (Evento::activo()), igual que
     * la web cuando no hay eleccion. Con evento_id de un evento no activo: 422.
     */
    public function contexto(?int $eventoId): ?Evento
    {
        if (!$eventoId) {
            return Evento::activo();
        }

        $evento = Evento::queryActivos()->where('id', $eventoId)->first();

        if (!$evento) {
            throw new ReglaDeNegocioException('Ese evento ya no está activo.', 'evento_id');
        }

        return $evento;
    }
}
