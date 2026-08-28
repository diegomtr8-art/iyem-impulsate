<?php

namespace App\Http\Middleware;

use App\Models\Evento;
use App\Models\Publicidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? array_merge(
                    $request->user()->only([
                        'id', 'name', 'email', 'telefono', 'curp', 'rfc',
                        'municipio', 'nombre_empresa', 'sitio_web',
                        'active_role', 'rol_seleccionado', 'necesidades',
                        'perfil_completo', 'es_restaurantero', 'email_verified_at', 'profile_photo_url',
                        'acepta_aviso_at', 'camara_asociacion', 'nombre_establecimiento',
                        'ine_path', 'csf_path', 'csf_fecha',
                    ]),
                    [
                        'roles'           => $request->user()->getRoleNames(),
                        'is_admin'        => $request->user()->hasRole('admin'),
                        'is_super_admin'  => $request->user()->hasRole('super-admin'),
                        'is_restaurantero'=> $request->user()->hasRole('restaurantero'),
                        'is_cliente'      => $request->user()->hasRole('cliente'),
                        'tiene_dual_rol'  => $request->user()->tieneDualRol(),
                        'active_role'     => $request->user()->active_role ?? 'comprador',
                    ]
                ) : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'pendientesAprobacion' => fn () => $request->user()?->hasRole('admin')
                ? \App\Models\Restaurantero::where('aprobado', false)
                    ->where('rechazado', false)
                    ->count()
                : 0,
            // Evento sobre el que operan las pantallas de gestión del admin.
            'eventoContexto' => function () use ($request) {
                if (!$request->user()?->hasRole('admin') && !$request->user()?->hasRole('super-admin')) {
                    return null;
                }
                return Evento::contextoAdmin()?->only(['id', 'nombre', 'tipo_evento']);
            },
            'eventoActivo' => fn () => Evento::activo()?->only([
                'id', 'nombre', 'sector_economico', 'max_citas_por_comprador',
                'fecha_hora_inicio', 'fecha_hora_fin',
                'fecha_hora_inicio_proveedores', 'fecha_hora_fin_proveedores',
                'fecha_hora_inicio_compradores', 'fecha_hora_fin_compradores',
                'tiempo_entre_citas_minutos',
            ]),
            'eventosSidebar' => fn () => Evento::orderByDesc('activa')
                ->orderByDesc('fecha_hora_inicio')
                ->limit(6)
                ->get()
                ->map(fn ($e) => array_merge(
                    $e->only([
                        'id', 'nombre', 'sector_economico', 'activa', 'descripcion',
                        'fecha_hora_inicio', 'fecha_hora_fin', 'tipo_evento',
                    ]),
                    ['imagen_url' => $e->imagen_url]
                )),
            'registradoEnEvento' => function () use ($request) {
                $evento = Evento::activo();
                if (!$evento || !$request->user()) {
                    return ['comprador' => null, 'proveedor' => null];
                }
                $registros = DB::table('evento_usuario')
                    ->where('evento_id', $evento->id)
                    ->where('user_id', $request->user()->id)
                    ->get()
                    ->keyBy('tipo');

                return [
                    'comprador' => $registros->has('comprador') ? [
                        'estado'         => $registros['comprador']->estado,
                        'motivo_rechazo' => $registros['comprador']->motivo_rechazo,
                    ] : null,
                    'proveedor' => $registros->has('proveedor') ? [
                        'estado'         => $registros['proveedor']->estado,
                        'motivo_rechazo' => $registros['proveedor']->motivo_rechazo,
                    ] : null,
                ];
            },
            'publicidadActiva' => function () use ($request) {
                $user = $request->user();
                if (!$user) return null;
                $esAdminOSuperAdmin = $user->hasRole('admin') || $user->hasRole('super-admin');
                if ($esAdminOSuperAdmin) return null;
                $pub = Publicidad::vigente()->first();
                if (!$pub) return null;
                return [
                    'id'                   => $pub->id,
                    'titulo'               => $pub->titulo,
                    'imagen_url'           => $pub->imagen_url,
                    'enlace'               => $pub->enlace,
                    'abre_en_nueva_pestana'=> $pub->abre_en_nueva_pestana,
                ];
            },
            'registro_evento' => function () use ($request) {
                $evento = Evento::activo();
                if (!$evento || !$request->user()) return null;
                $reg = DB::table('evento_usuario')
                    ->where('evento_id', $evento->id)
                    ->where('user_id', $request->user()->id)
                    ->first();
                return $reg ? [
                    'estado'         => $reg->estado,
                    'tipo'           => $reg->tipo,
                    'motivo_rechazo' => $reg->motivo_rechazo,
                ] : null;
            },
        ];
    }
}
