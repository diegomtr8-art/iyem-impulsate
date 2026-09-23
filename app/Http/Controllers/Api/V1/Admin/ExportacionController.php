<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\ExportacionesAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Exportaciones Excel y reporte PDF de encuestas para la app: se genera el
 * archivo y se devuelve una URL FIRMADA temporal (15 min) que la app abre con
 * el navegador / expo-sharing sin mandar el token.
 */
class ExportacionController extends Controller
{
    public function __construct(private ExportacionesAdminService $exportaciones) {}

    /** Que se puede exportar y con que parametros. */
    public function index()
    {
        Gate::authorize('admin');

        return response()->json(['data' => [
            ['tipo' => 'evento',            'formato' => 'xlsx', 'parametros' => ['evento_id'],                             'descripcion' => 'Evento completo: evento, proveedores, compradores, citas, resumen y una hoja por proveedor. Sin evento_id, el evento activo principal.'],
            ['tipo' => 'compradores',       'formato' => 'xlsx', 'parametros' => [],                                        'descripcion' => 'Todos los compradores.'],
            ['tipo' => 'proveedores',       'formato' => 'xlsx', 'parametros' => [],                                        'descripcion' => 'Todos los proveedores con su perfil de negocio.'],
            ['tipo' => 'citas',             'formato' => 'xlsx', 'parametros' => [],                                        'descripcion' => 'Todas las citas.'],
            ['tipo' => 'metricas',          'formato' => 'xlsx', 'parametros' => [],                                        'descripcion' => 'Métricas del panel (visitas, top, género y RFC).'],
            ['tipo' => 'encuestas',         'formato' => 'xlsx', 'parametros' => ['evento_id'],                             'descripcion' => 'Encuestas enviadas (sin las de prueba).'],
            ['tipo' => 'encuestas-reporte', 'formato' => 'xlsx', 'parametros' => ['plantilla_id', 'evento_id', 'canirac'], 'descripcion' => 'Reporte de encuestas (resumen y detalle) de la plantilla; sin plantilla_id, la activa.'],
            ['tipo' => 'encuestas-pdf',     'formato' => 'pdf',  'parametros' => ['plantilla_id', 'evento_id', 'canirac'], 'descripcion' => 'Reporte PDF de encuestas; sin plantilla_id, la activa.'],
        ], 'meta' => ['minutos_url' => ExportacionesAdminService::MINUTOS_URL]]);
    }

    public function generar(Request $request, string $tipo)
    {
        Gate::authorize('admin');
        $p = $request->validate([
            'evento_id'    => ['nullable', 'integer', 'exists:eventos,id'],
            'plantilla_id' => ['nullable', 'integer', 'exists:encuesta_plantillas,id'],
            'canirac'      => ['nullable', Rule::in(['si', 'no'])],
        ]);

        return response()->json([
            'data'    => $this->exportaciones->generar($tipo, $p, $request->user()),
            'message' => 'Archivo generado. El enlace caduca en ' . ExportacionesAdminService::MINUTOS_URL . ' minutos.',
        ], 201);
    }

    /**
     * Descarga por URL firmada (sin token). La firma cubre archivo, nombre y
     * solicitante; ademas se vuelve a comprobar que el solicitante siga
     * siendo admin (p.ej. si le quitaron el rol despues de generar el enlace).
     */
    public function descargar(Request $request, string $archivo)
    {
        $solicitante = User::find((int) $request->query('solicitante'));
        abort_unless($solicitante && $solicitante->hasRole('admin'), 403, 'No tienes permiso para descargar este archivo.');

        $ruta = $this->exportaciones->ruta($archivo);
        abort_unless($ruta, 404, 'El archivo ya no existe. Genera la exportación de nuevo.');

        return response()->download($ruta, (string) $request->query('nombre', $archivo));
    }
}
