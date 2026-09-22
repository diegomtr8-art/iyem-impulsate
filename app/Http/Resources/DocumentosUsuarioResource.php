<?php

namespace App\Http\Resources;

use App\Services\DocumentosUsuarioService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Estado de INE / CSF de un usuario. La ruta interna del archivo NUNCA sale:
 * solo una URL firmada que caduca a los 10 minutos y que sirve el archivo
 * unicamente si quien la pidio sigue siendo el dueño o un admin.
 */
class DocumentosUsuarioResource extends JsonResource
{
    public const MINUTOS_URL = 10;

    public function toArray(Request $request): array
    {
        $ine = !empty($this->ine_path);
        $csf = !empty($this->csf_path);

        return [
            'ine' => [
                'subido'       => $ine,
                'url_temporal' => $ine ? $this->urlTemporal($request, 'ine') : null,
            ],
            'csf' => [
                'subido'       => $csf,
                'fecha'        => $this->csf_fecha ? \Carbon\Carbon::parse($this->csf_fecha)->toDateString() : null,
                'vigente'      => $csf && DocumentosUsuarioService::csfVigente($this->resource),
                'url_temporal' => $csf ? $this->urlTemporal($request, 'csf') : null,
            ],
            'url_expira_en_minutos' => self::MINUTOS_URL,
        ];
    }

    private function urlTemporal(Request $request, string $tipo): ?string
    {
        $solicitante = $request->user();
        if (!$solicitante) {
            return null;
        }

        return URL::temporarySignedRoute(
            'api.v1.documentos.ver',
            now()->addMinutes(self::MINUTOS_URL),
            ['user' => $this->id, 'tipo' => $tipo, 'solicitante' => $solicitante->id]
        );
    }
}
