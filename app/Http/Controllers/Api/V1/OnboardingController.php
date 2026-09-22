<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

/** Aviso de privacidad y seleccion de rol (AvisoAceptacionController / SeleccionarRolController en la web). */
class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $onboarding) {}

    /** El texto vive en la pagina web (AvisoPrivacidad.vue); la app lo abre por URL. */
    public function avisoPrivacidad(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'titulo'        => 'Aviso de Privacidad',
                'url'           => route('aviso.privacidad'),
                'terminos_url'  => route('terminos.condiciones'),
                'aceptado'      => !is_null($user->acepta_aviso_at),
                'aceptado_at'   => $user->acepta_aviso_at?->toIso8601String(),
            ],
        ]);
    }

    public function aceptarAviso(Request $request)
    {
        $request->validate(OnboardingService::reglasAviso(), OnboardingService::mensajesAviso());

        $this->onboarding->aceptarAviso($request->user());

        return $this->estado($request->user(), 'Aviso de Privacidad aceptado.');
    }

    public function seleccionarRol(Request $request)
    {
        $user = $request->user();

        // En la web /seleccionar-rol pasa por EnsureAvisoAceptado.
        $this->onboarding->exigirAviso($user);

        $request->validate([
            'rol' => 'required|in:' . implode(',', OnboardingService::ROLES_SELECCIONABLES),
        ]);

        $this->onboarding->seleccionarRol($user, $request->input('rol'));

        return $this->estado($user, '¡Bienvenido a Impulsate!');
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
