<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\PlantillaCorreo;
use App\Models\Restaurantero;
use App\Services\Admin\CorreoMasivoService;
use App\Services\Admin\PlantillasCorreoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Correo masivo SOLO super-admin (web: /admin/correo-masivo). Mismos filtros
 * y envio en cola que la web (CorreoMasivoService). Diferencia con la web: el
 * envio real exige `total_esperado` (el total que mostro la vista previa de
 * destinatarios) para que un cambio en la lista no dispare correos de mas.
 */
class CorreoMasivoController extends Controller
{
    public function __construct(
        private CorreoMasivoService $correo,
        private PlantillasCorreoService $plantillas,
    ) {}

    public function formulario()
    {
        Gate::authorize('super-admin');

        return response()->json(['data' => [
            'plantillas' => PlantillaCorreo::where('activo', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'asunto', 'contenido', 'tipo_destinatario'])
                ->map(fn ($p) => $p->only(['id', 'nombre', 'asunto', 'contenido', 'tipo_destinatario'])),
            'eventos'    => Evento::orderByDesc('fecha_hora_inicio')->get(['id', 'nombre'])->map(fn ($e) => $e->only(['id', 'nombre'])),
            'categorias' => Restaurantero::categoriasActivas(),
            'filtros'    => [
                'tipo'             => CorreoMasivoService::TIPOS,
                'estado_proveedor' => CorreoMasivoService::ESTADOS_PROVEEDOR,
                'estado_evento'    => CorreoMasivoService::ESTADOS_EVENTO,
                'canirac'          => ['si', 'no'],
            ],
        ]]);
    }

    /** Cuantos recibirian el correo y los primeros 20 (web: "Ver destinatarios"). */
    public function destinatarios(Request $request)
    {
        Gate::authorize('super-admin');
        $users = $this->correo->destinatarios($this->filtros($request));

        return response()->json([
            'data' => $users->take(20)->map(fn ($u) => CorreoMasivoService::filaDestinatario($u))->values(),
            'meta' => ['total' => $users->count(), 'mostrados' => min(20, $users->count())],
        ]);
    }

    /** HTML del correo con variables de ejemplo (no envia nada). */
    public function vistaPrevia(Request $request)
    {
        Gate::authorize('super-admin');
        $d = $request->validate(CorreoMasivoService::REGLAS_CORREO);

        return response()->json(['data' => $this->plantillas->vistaPrevia($d['asunto'], $d['contenido'])]);
    }

    /** Un solo correo de prueba, asunto con "[PRUEBA]". */
    public function prueba(Request $request)
    {
        Gate::authorize('super-admin');
        $d = $request->validate(array_merge(['email_prueba' => ['required', 'email']], CorreoMasivoService::REGLAS_CORREO));

        $this->correo->prueba($d['email_prueba'], $d['asunto'], $d['contenido']);

        return response()->json(['data' => null, 'message' => "Correo de prueba enviado a {$d['email_prueba']}."]);
    }

    public function enviar(Request $request)
    {
        Gate::authorize('super-admin');
        $d = $request->validate(array_merge(CorreoMasivoService::REGLAS_CORREO, [
            'total_esperado' => ['required', 'integer', 'min:1'],
        ]));
        $filtros = $this->filtros($request);

        $total = $this->correo->destinatarios($filtros)->count();
        if ($total !== (int) $d['total_esperado']) {
            throw new ReglaDeNegocioException(
                "La lista cambió: ahora son {$total} destinatario(s). Revisa los destinatarios y vuelve a confirmar.",
                'total_esperado'
            );
        }

        $n = $this->correo->enviar($filtros, $d['asunto'], $d['contenido']);

        return response()->json(['data' => ['encolados' => $n], 'message' => "Correos en cola para {$n} destinatarios."]);
    }

    private function filtros(Request $request): array
    {
        return $request->validate([
            'tipo'             => ['nullable', Rule::in(CorreoMasivoService::TIPOS)],
            'categoria'        => ['nullable', 'string', 'max:100'],
            'evento_id'        => ['nullable', 'integer', 'exists:eventos,id'],
            'estado_evento'    => ['nullable', Rule::in(CorreoMasivoService::ESTADOS_EVENTO)],
            'estado_proveedor' => ['nullable', Rule::in(CorreoMasivoService::ESTADOS_PROVEEDOR)],
            'canirac'          => ['nullable', Rule::in(['si', 'no'])],
        ]);
    }
}
