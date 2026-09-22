<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentosUsuarioResource;
use App\Http\Resources\PerfilCompradorResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CambioRolService;
use App\Services\DocumentosUsuarioService;
use App\Services\OnboardingService;
use App\Services\PerfilCompradorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Perfil del comprador, documentos y roles. Misma logica que
 * CompletarPerfilController / SwitchRoleController (via servicios).
 * El perfil de NEGOCIO del proveedor es Fase 3.
 */
class PerfilController extends Controller
{
    public function __construct(
        private PerfilCompradorService $perfil,
        private OnboardingService $onboarding,
        private DocumentosUsuarioService $documentos,
    ) {}

    public function show(Request $request)
    {
        Gate::authorize('view', $request->user());

        return new PerfilCompradorResource($request->user());
    }

    /** POST /mi-panel/perfil de la web. Se envia el formulario completo. */
    public function update(Request $request)
    {
        $user = $request->user();
        Gate::authorize('update', $user);

        $request->validate(PerfilCompradorService::reglasActualizar($request->input('camara_asociacion')));

        $this->perfil->actualizar($user, $this->datos($request, [
            'name', 'telefono', 'curp', 'rfc', 'municipio', 'genero', 'nombre_empresa',
            'sitio_web', 'necesidades', 'camara_asociacion', 'nombre_establecimiento',
        ]));

        return (new PerfilCompradorResource($user->fresh()))
            ->additional(['message' => 'Perfil actualizado correctamente.']);
    }

    /** POST /completar-perfil de la web. */
    public function completar(Request $request)
    {
        $user = $request->user();
        Gate::authorize('update', $user);

        // En la web /completar-perfil pasa por EnsureAvisoAceptado y EnsureRolSeleccionado.
        $this->onboarding->exigirAvisoYRol($user);

        $request->validate(PerfilCompradorService::reglasCompletar($request->input('camara_asociacion')));

        $this->perfil->completar($user, $this->datos($request, [
            'telefono', 'curp', 'rfc', 'municipio', 'nombre_empresa',
            'camara_asociacion', 'nombre_establecimiento', 'necesidades',
        ]));

        return $this->estado($user, '¡Perfil completado! Ya puedes acceder a todas las funciones.');
    }

    public function documentos(Request $request)
    {
        Gate::authorize('verDocumentos', $request->user());

        return new DocumentosUsuarioResource($request->user());
    }

    public function subirDocumentos(Request $request)
    {
        $user = $request->user();
        Gate::authorize('update', $user);

        // En la web /perfil/documentos pasa por EnsureAvisoAceptado y EnsureRolSeleccionado.
        $this->onboarding->exigirAvisoYRol($user);

        $request->validate(DocumentosUsuarioService::reglas(), DocumentosUsuarioService::mensajes());

        $this->documentos->subir(
            $user,
            [
                'ine' => $request->hasFile('ine') ? $request->file('ine') : null,
                'csf' => $request->hasFile('csf') ? $request->file('csf') : null,
            ],
            $request->input('csf_fecha')
        );

        return (new DocumentosUsuarioResource($user->fresh()))
            ->additional(['message' => 'Documentos actualizados correctamente.']);
    }

    public function cambiarRol(Request $request, CambioRolService $cambioRol)
    {
        $user = $request->user();

        // En la web /switch-role pasa por los tres middlewares de onboarding.
        $this->onboarding->exigirOnboardingCompleto($user);

        $nuevo = $cambioRol->alternar($user);

        return $this->estado($user, $nuevo === 'proveedor'
            ? 'Ahora estás en modo proveedor.'
            : 'Ahora estás en modo comprador.');
    }

    public function agregarRol(Request $request)
    {
        $request->validate(['rol' => 'required|in:' . implode(',', OnboardingService::ROLES_AGREGABLES)]);

        $this->onboarding->agregarRol($request->user(), $request->input('rol'));

        return $this->estado($request->user(), 'Rol agregado correctamente. ¡Bienvenido!');
    }

    /** Campos presentes + es_restaurantero como bool, igual que $request->boolean() en la web. */
    private function datos(Request $request, array $campos): array
    {
        return [
            ...$request->only($campos),
            'es_restaurantero' => $request->boolean('es_restaurantero'),
        ];
    }

    private function estado(User $user, string $mensaje)
    {
        $user = $user->fresh();

        return response()->json([
            'data' => [
                'user'       => new UserResource($user),
                'onboarding' => $user->onboarding(),
            ],
            'message' => $mensaje,
        ]);
    }
}
