<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Exports\CitasExport;
use App\Exports\CompradoresExport;
use App\Exports\EventoCompletoExport;
use App\Exports\ProveedoresExport;
use App\Models\Evento;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Exportaciones del panel admin (Excel de evento/compradores/proveedores/
 * citas/metricas/encuestas y el reporte PDF de encuestas).
 *
 * La web las descarga directo (Excel::download). La app no puede mandar el
 * token Bearer al abrir un enlace en el navegador, asi que la API genera el
 * archivo en el disco privado y entrega una URL FIRMADA temporal a
 * GET /api/v1/exportaciones/{archivo}, que vuelve a comprobar que quien la
 * pidio sigue siendo admin (mismo patron que los documentos INE/CSF).
 */
class ExportacionesAdminService
{
    public const MINUTOS_URL = 15;

    /** Carpeta en el disco 'local' (storage/app/private), fuera de lo publico. */
    public const CARPETA = 'exportaciones';

    public const TIPOS = ['evento', 'compradores', 'proveedores', 'citas', 'metricas', 'encuestas', 'encuestas-reporte', 'encuestas-pdf'];

    public function __construct(
        private EncuestasAdminService $encuestas,
        private MetricasAdminService $metricas,
    ) {}

    /* ─────────────── Excel que tambien usa la web ─────────────── */

    public function eventoCompleto(?Evento $evento): array
    {
        if (!$evento) {
            throw new ReglaDeNegocioException('No hay un evento activo para exportar.');
        }

        return [new EventoCompletoExport($evento), 'evento_' . Str::slug($evento->nombre) . '_' . now()->format('Y-m-d') . '.xlsx'];
    }

    public function compradores(): array
    {
        return [new CompradoresExport(), 'compradores_' . now()->format('Y-m-d') . '.xlsx'];
    }

    public function proveedores(): array
    {
        return [new ProveedoresExport(), 'proveedores_' . now()->format('Y-m-d') . '.xlsx'];
    }

    public function citas(): array
    {
        return [new CitasExport(), 'citas_' . now()->format('Y-m-d') . '.xlsx'];
    }

    /* ─────────────── API: archivo + URL firmada ─────────────── */

    /**
     * Genera el archivo del $tipo y devuelve {url, nombre, expira_at}.
     * $p (ya validado): evento_id, plantilla_id, canirac segun el tipo.
     */
    public function generar(string $tipo, array $p, User $solicitante): array
    {
        $this->purgarViejos();

        $eventoId    = isset($p['evento_id']) ? (int) $p['evento_id'] : null;
        $plantillaId = isset($p['plantilla_id']) ? (int) $p['plantilla_id'] : null;
        $canirac     = $p['canirac'] ?? null;

        if ($tipo === 'encuestas-pdf') {
            [$pdf, $nombre] = $this->encuestas->reportePdf($plantillaId, $eventoId, $canirac);
            $archivo = Str::uuid() . '.pdf';
            Storage::disk('local')->put(self::CARPETA . '/' . $archivo, $pdf->output());
        } else {
            [$export, $nombre] = match ($tipo) {
                // Sin evento_id: el evento activo principal (la web usa el de la sesion).
                'evento'            => $this->eventoCompleto($eventoId ? Evento::findOrFail($eventoId) : Evento::activo()),
                'compradores'       => $this->compradores(),
                'proveedores'       => $this->proveedores(),
                'citas'             => $this->citas(),
                'metricas'          => $this->metricas->export(),
                'encuestas'         => $this->encuestas->exportEncuestas($eventoId),
                'encuestas-reporte' => $this->encuestas->exportReporte($plantillaId, $eventoId, $canirac),
            };
            $archivo = Str::uuid() . '.xlsx';
            Excel::store($export, self::CARPETA . '/' . $archivo, 'local');
        }

        $expira = now()->addMinutes(self::MINUTOS_URL);

        return [
            'url'       => $this->urlFirmada($archivo, $nombre, $solicitante, $expira),
            'nombre'    => $nombre,
            'expira_at' => $expira->toIso8601String(),
        ];
    }

    private function urlFirmada(string $archivo, string $nombre, User $solicitante, CarbonInterface $expira): string
    {
        return URL::temporarySignedRoute('api.v1.exportaciones.descargar', $expira, [
            'archivo'     => $archivo,
            'nombre'      => $nombre,
            'solicitante' => $solicitante->id,
        ]);
    }

    /** Ruta absoluta del archivo generado, o null si ya no existe (o el nombre es invalido). */
    public function ruta(string $archivo): ?string
    {
        if (!preg_match('/^[0-9a-f\-]{36}\.(xlsx|pdf)$/', $archivo)) {
            return null;
        }
        $rel = self::CARPETA . '/' . $archivo;

        return Storage::disk('local')->exists($rel) ? Storage::disk('local')->path($rel) : null;
    }

    /** Borra los archivos con mas del doble de la vigencia de la URL. */
    public function purgarViejos(): void
    {
        $disco  = Storage::disk('local');
        $limite = now()->subMinutes(self::MINUTOS_URL * 2)->getTimestamp();

        foreach ($disco->files(self::CARPETA) as $f) {
            if ($disco->lastModified($f) < $limite) {
                $disco->delete($f);
            }
        }
    }
}
