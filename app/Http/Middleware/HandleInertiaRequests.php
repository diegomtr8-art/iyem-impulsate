<?php

namespace App\Http\Middleware;

use App\Models\Evento;
use Illuminate\Http\Request;
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
                        'id', 'name', 'email', 'telefono', 'sitio_web',
                        'active_role', 'rol_seleccionado', 'necesidades',
                        'perfil_completo', 'email_verified_at', 'profile_photo_url',
                    ]),
                    [
                        'roles'           => $request->user()->getRoleNames(),
                        'is_admin'        => $request->user()->hasRole('admin'),
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
            'eventoActivo' => fn () => Evento::activo()?->only([
                'id', 'nombre', 'sector_economico', 'max_citas_por_comprador',
                'fecha_hora_inicio', 'fecha_hora_fin',
                'fecha_hora_inicio_proveedores', 'fecha_hora_inicio_compradores',
            ]),
            'registradoEnEvento' => fn () => $request->user() && Evento::activo()
                ? [
                    'comprador' => Evento::activo()?->compradores()->where('user_id', $request->user()->id)->exists(),
                    'proveedor' => Evento::activo()?->proveedores()->where('user_id', $request->user()->id)->exists(),
                ]
                : ['comprador' => false, 'proveedor' => false],
        ];
    }
}
