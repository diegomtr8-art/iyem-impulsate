<?php

namespace App\Exports;

use App\Models\EncuestaPlantilla;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EncuestasReporteExport implements WithMultipleSheets
{
    public function __construct(
        private EncuestaPlantilla $plantilla,
        private ?int $eventoId = null,
        private ?string $canirac = null
    ) {}

    public function sheets(): array
    {
        return [
            new EncuestasResumenSheet($this->plantilla, $this->eventoId, $this->canirac),
            new EncuestasDetalleSheet($this->plantilla, $this->eventoId, null, 'Todas las Respuestas', $this->canirac),
            new EncuestasDetalleSheet($this->plantilla, $this->eventoId, 'comprador', 'Compradores', $this->canirac),
            new EncuestasDetalleSheet($this->plantilla, $this->eventoId, 'proveedor', 'Proveedores', $this->canirac),
        ];
    }
}
