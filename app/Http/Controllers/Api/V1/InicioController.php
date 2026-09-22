<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\CompletarPerfilController;
use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResumenResource;
use App\Http\Resources\EventoResource;
use App\Http\Resources\PerfilProveedorResource;
use App\Http\Resources\PublicidadResource;
use App\Http\Resources\UserResource;
use App\Models\AgendaPropuesta;
use App\Models\Cita;
use App\Models\EncuestaSatisfaccion;
use App\Models\Evento;
use App\Models\Publicidad;
use App\Models\Restaurantero;
use App\Services\DocumentosUsuarioService;
use App\Services\GestionCitasProveedorService;
use App\Services\PerfilProveedorService;
use App\Services\NotificacionService;
use App\Services\OnboardingService;
use App\Services\PerfilCompradorService;
use Illuminate\Http\Request;

class InicioController extends Controller
{
    /** Estados en los que una cita futura sigue "en pie". */
    public const ESTADOS_VIGENTES = ['pendiente', 'confirmada', 'reagendada', 'pendiente_reconfirmacion'];

    public function inicio(Request $request, NotificacionService $notificaciones)
    {
        $user = $request->user();
        $esAdmin = $user->hasRole('admin') || $user->hasRole('super-admin');

        // Proveedor en modo proveedor: sus citas recibidas. Si no, las que agendo como comprador.
        $restaurantero = $user->restaurantero;
        $comoProveedor = $user->active_role === 'proveedor' && $restaurantero && $user->hasRole('restaurantero');

        $citas = Cita::query()
            ->when($comoProveedor,
                fn ($q) => $q->where('restaurantero_id', $restaurantero->id),
                fn ($q) => $q->where('cliente_id', $user->id))
            ->where('inicio', '>=', now())
            ->whereIn('estado', self::ESTADOS_VIGENTES);

        $proxima = (clone $citas)->with(['restaurantero', 'cliente'])->orderBy('inicio')->first();

        $evento = Evento::activo();

        // Igual que publicidadActiva en HandleInertiaRequests: nunca a admins.
        $publicidad = $esAdmin ? null : Publicidad::vigente()->first();

        return response()->json([
            'data' => [
                'usuario'                 => new UserResource($user),
                'onboarding'              => $user->onboarding(),
                'vista'                   => $comoProveedor ? 'proveedor' : 'comprador',
                'proxima_cita'            => $proxima ? new CitaResumenResource($proxima) : null,
                'citas_proximas_count'    => (clone $citas)->count(),
                'evento_destacado'        => $evento ? new EventoResource($evento) : null,
                'notificaciones_sin_leer' => $notificaciones->contarNoLeidas($user),
                'publicidad'              => $publicidad ? new PublicidadResource($publicidad) : null,
                // Fase 2: pendientes del lado comprador (siempre del usuario como comprador).
                'pendientes'              => [
                    'reagendas_por_confirmar' => Cita::where('cliente_id', $user->id)
                        ->where('estado', 'pendiente_reconfirmacion')
                        ->whereNotNull('propuesta_inicio')
                        ->count(),
                    'agendas_por_responder'   => AgendaPropuesta::where('user_id', $user->id)
                        ->whereNotNull('enviada_at')
                        ->where('estado', 'pendiente')
                        ->count(),
                    'encuestas_por_responder' => EncuestaSatisfaccion::where('user_id', $user->id)
                        ->whereNull('completada_at')
                        ->where(fn ($q) => $q->where('es_prueba', false)->orWhereNull('es_prueba'))
                        ->count(),
                ],
                // Fase 3: pendientes del lado proveedor (null si la cuenta no es proveedor).
                // No depende de active_role: una cuenta con los dos roles lo recibe siempre.
                'proveedor'               => $user->hasRole('restaurantero') ? $this->pendientesProveedor($restaurantero) : null,
            ],
        ]);
    }

    private function pendientesProveedor(?Restaurantero $restaurantero): array
    {
        $perfil    = app(PerfilProveedorService::class);
        $faltantes = $perfil->faltantes($restaurantero);

        return [
            'negocio'                       => PerfilProveedorResource::negocio($restaurantero, $perfil),
            'perfil_completo'               => (bool) $restaurantero?->perfil_completo,
            'secciones_faltantes'           => collect($faltantes)->pluck('seccion')->unique()->values()->all(),
            'citas_por_responder'           => $restaurantero
                ? Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'pendiente')->count() : 0,
            'reagendas_esperando_comprador' => $restaurantero
                ? Cita::where('restaurantero_id', $restaurantero->id)->where('estado', 'pendiente_reconfirmacion')->count() : 0,
            'citas_hoy'                     => $restaurantero
                ? app(GestionCitasProveedorService::class)->kpis($restaurantero)['citas_hoy'] : 0,
        ];
    }

    /** Listas de los formularios de la web (mismos valores que validan los controladores). */
    public function catalogos()
    {
        $municipios = CompletarPerfilController::MUNICIPIOS;
        sort($municipios);

        return response()->json([
            'data' => [
                'municipios'          => array_values($municipios),
                'generos'             => [
                    ['valor' => 'mujer',  'etiqueta' => 'Femenino'],
                    ['valor' => 'hombre', 'etiqueta' => 'Masculino'],
                    ['valor' => null,     'etiqueta' => 'Prefiero no decir'],
                ],
                'camaras_asociacion'  => PerfilCompradorService::CAMARAS,
                'categorias'          => Restaurantero::categoriasActivas(),
                'roles_seleccionables'=> [
                    ['valor' => 'comprador', 'etiqueta' => 'Comprador'],
                    ['valor' => 'proveedor', 'etiqueta' => 'Proveedor'],
                    ['valor' => 'ambos',     'etiqueta' => 'Ambos'],
                ],
                'roles_agregables'    => OnboardingService::ROLES_AGREGABLES,
                'documentos'          => [
                    'tipos'              => DocumentosUsuarioService::TIPOS,
                    'formatos'           => DocumentosUsuarioService::MIMES,
                    'max_kb'             => DocumentosUsuarioService::MAX_KB,
                    'csf_vigencia_meses' => DocumentosUsuarioService::CSF_VIGENCIA_MESES,
                ],
            ],
        ]);
    }
}
