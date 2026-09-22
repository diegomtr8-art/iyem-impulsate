<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Perfil del comprador: formulario de /completar-perfil (store) y edicion en
 * /user/profile (actualizarComprador). Lo usan CompletarPerfilController y la
 * API v1 con las mismas reglas.
 */
class PerfilCompradorService
{
    public const CAMARAS = ['CANIRAC', 'Ninguna'];
    public const GENEROS = ['hombre', 'mujer'];

    /** Reglas de POST /completar-perfil. */
    public static function reglasCompletar(?string $camara): array
    {
        return [
            'telefono'              => ['required', 'string', 'max:20'],
            'curp'                  => ['nullable', 'string', 'max:18'],
            'rfc'                   => ['nullable', 'string', 'max:13'],
            'municipio'             => ['required', 'string', 'max:100'],
            'nombre_empresa'        => ['required', 'string', 'max:200'],
            'camara_asociacion'     => ['required', 'string', 'in:CANIRAC,Ninguna'],
            'nombre_establecimiento'=> [
                'nullable', 'string', 'max:255',
                Rule::requiredIf($camara === 'CANIRAC'),
            ],
            'necesidades'           => ['required', 'string', 'max:2000'],
            'es_restaurantero'      => ['boolean'],
        ];
    }

    /** Reglas de POST /mi-panel/perfil. */
    public static function reglasActualizar(?string $camara): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'telefono'              => ['nullable', 'string', 'max:20'],
            'curp'                  => ['nullable', 'string', 'max:18'],
            'rfc'                   => ['nullable', 'string', 'max:13'],
            'municipio'             => ['nullable', 'string', 'max:100'],
            'genero'                => ['nullable', 'in:hombre,mujer'],
            'nombre_empresa'        => ['required', 'string', 'max:200'],
            'sitio_web'             => ['nullable', 'url', 'max:200'],
            'necesidades'           => ['nullable', 'string', 'max:2000'],
            'camara_asociacion'     => ['required', 'string', 'in:CANIRAC,Ninguna'],
            'nombre_establecimiento'=> [
                'nullable', 'string', 'max:255',
                Rule::requiredIf($camara === 'CANIRAC'),
            ],
            'es_restaurantero'      => ['boolean'],
        ];
    }

    /**
     * $datos son los campos del formulario ya validados; es_restaurantero debe
     * venir ya convertido a bool (en la web: $request->boolean()).
     */
    public function completar(User $user, array $datos): void
    {
        $esRestaurantero = (bool) ($datos['es_restaurantero'] ?? false);

        $user->update([
            'telefono'               => $datos['telefono'] ?? null,
            'curp'                   => !empty($datos['curp']) ? strtoupper($datos['curp']) : null,
            'rfc'                    => !empty($datos['rfc']) ? strtoupper($datos['rfc']) : null,
            'municipio'              => $datos['municipio'] ?? null,
            'nombre_empresa'         => $datos['nombre_empresa'] ?? null,
            'perfil_completo'        => true,
            'camara_asociacion'      => $datos['camara_asociacion'] ?? null,
            'nombre_establecimiento' => ($datos['camara_asociacion'] ?? null) === 'CANIRAC'
                                        ? ($datos['nombre_establecimiento'] ?? null)
                                        : null,
            'necesidades'            => $datos['necesidades'] ?? null,
            'es_restaurantero'       => $esRestaurantero,
        ]);

        // Auto-registro al encuentro de negocios activo si es restaurantero.
        // IMPORTANTE: queda en 'pendiente'. La aprobación la decide el admin
        // desde /admin/eventos/{evento}/solicitudes.
        if ($esRestaurantero) {
            $this->registrarPendienteEnEncuentro(
                $user,
                'El equipo la revisará y te avisaremos por correo cuando sea aprobada.'
            );
        }
    }

    public function actualizar(User $user, array $datos): void
    {
        $esRestaurantero = (bool) ($datos['es_restaurantero'] ?? false);
        $seAcabaDeVolverRestaurantero = $esRestaurantero && !$user->es_restaurantero;

        $user->update([
            'name'                   => $datos['name'],
            'telefono'               => $datos['telefono'] ?? null,
            'curp'                   => !empty($datos['curp']) ? strtoupper($datos['curp']) : null,
            'rfc'                    => !empty($datos['rfc']) ? strtoupper($datos['rfc']) : null,
            'municipio'              => $datos['municipio'] ?? null,
            'genero'                 => !empty($datos['genero']) ? $datos['genero'] : null,
            'nombre_empresa'         => $datos['nombre_empresa'] ?? null,
            'sitio_web'              => $datos['sitio_web'] ?? null,
            'necesidades'            => $datos['necesidades'] ?? null,
            'camara_asociacion'      => $datos['camara_asociacion'] ?? null,
            'nombre_establecimiento' => ($datos['camara_asociacion'] ?? null) === 'CANIRAC'
                                        ? ($datos['nombre_establecimiento'] ?? null)
                                        : null,
            'es_restaurantero'       => $esRestaurantero,
        ]);

        // Si acaba de marcar "¿Eres restaurantero?", registrarlo al encuentro
        // activo como PENDIENTE. La aprobación la decide el admin.
        if ($seAcabaDeVolverRestaurantero) {
            $this->registrarPendienteEnEncuentro(
                $user,
                'Te avisaremos por correo cuando sea aprobada.'
            );
        }
    }

    /**
     * Solo encuentro de negocios: si el evento activo más próximo es un bazar,
     * no se inscribe aquí (el bazar tiene su propio flujo). Si ya existía un
     * registro (pendiente, aprobado o rechazado) NO se toca: el estado lo
     * gobierna el admin o el flujo de re-postulación.
     */
    private function registrarPendienteEnEncuentro(User $user, string $colaMensaje): void
    {
        $evento = Evento::activo();

        if (!$evento || $evento->tipo_evento !== 'encuentro_negocios') {
            return;
        }

        $registro = DB::table('evento_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $user->id)
            ->where('tipo', 'comprador')
            ->first();

        if ($registro) {
            return;
        }

        DB::table('evento_usuario')->insert([
            'evento_id'     => $evento->id,
            'user_id'       => $user->id,
            'tipo'          => 'comprador',
            'estado'        => 'pendiente',
            'respondido_at' => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        Notificacion::crear(
            $user->id,
            'solicitud_registro',
            'Tu solicitud está en revisión',
            'Registramos tu solicitud para el evento "' . $evento->nombre . '". ' . $colaMensaje
        );

        // Avisar a TODOS los administradores
        foreach (User::role('admin')->get() as $admin) {
            Notificacion::crear(
                $admin->id,
                'solicitud_registro',
                'Nueva solicitud de comprador',
                $user->name . ' solicita unirse al evento "' . $evento->nombre . '" como comprador.'
            );
        }
    }
}
