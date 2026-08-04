<?php

namespace App\Exports;

use App\Models\EncuestaPlantilla;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EncuestasReporteExport implements WithMultipleSheets
{
    public function __construct(
        private EncuestaPlantilla $plantilla,
        private ?int $eventoId = null
    ) {}

    public function sheets(): array
    {
        return [
            new EncuestasResumenSheet($this->plantilla, $this->eventoId),
            new EncuestasDetalleSheet($this->plantilla, $this->eventoId, null, 'Todas las Respuestas'),
            new EncuestasDetalleSheet($this->plantilla, $this->eventoId, 'comprador', 'Compradores'),
            new EncuestasDetalleSheet($this->plantilla, $this->eventoId, 'proveedor', 'Proveedores'),
        ];
    }
}
