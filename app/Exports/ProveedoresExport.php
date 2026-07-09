<?php

namespace App\Exports;

use App\Models\Restaurantero;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProveedoresExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    public function collection()
    {
        return Restaurantero::with('user')
            ->withCount('citas')
            ->orderBy('nombre_restaurante')
            ->get()
            ->map(function ($r) {

                $productos = collect($r->productos_top ?? [])
                    ->filter(fn($p) => filled($p['nombre'] ?? null))
                    ->map(fn($p) => $p['nombre'])
                    ->join(', ') ?: '—';

                $capacidades = collect($r->productos_top ?? [])
                    ->filter(fn($p) => isset($p['capacidad_cantidad']) && $p['capacidad_cantidad'] !== null)
                    ->map(fn($p) =>
                        ($p['nombre'] ?? '?') . ': '
                        . number_format((float)$p['capacidad_cantidad'], 0, '.', ',')
                        . ' ' . ($p['capacidad_unidad'] ?? '')
                        . '/mes'
                    )
                    ->join(' | ') ?: '—';

                $plazo = ($r->credito_tiempo_cantidad && $r->credito_tiempo_unidad)
                    ? "{$r->credito_tiempo_cantidad} {$r->credito_tiempo_unidad}"
                    : '—';

                $bool = fn($val) => is_null($val) ? '—' : ($val ? 'Sí' : 'No');

                return [
                    'Empresa'               => $r->nombre_restaurante ?? '—',
                    'Categoría'             => $r->categoria ?? '—',
                    'Razón Social'          => $r->razon_social ?? '—',
                    'Representante'         => $r->nombre_representante ?? '—',
                    'CURP'                  => $r->curp_representante ?? '—',
                    'Municipio'             => $r->municipio ?? '—',
                    'Dom. en Yucatán'       => $bool($r->domicilio_en_yucatan),
                    'Teléfono'              => $r->telefono ?? '—',
                    'Email'                 => $r->user?->email ?? '—',
                    'Sitio Web'             => $r->sitio_web ?? '—',
                    'RFC'                   => $r->rfc ?? '—',
                    'Núm. Empleados'        => $r->num_empleados ?? '—',
                    'Inicio Operaciones'    => $r->fecha_inicio_operaciones ?? '—',
                    'Mercado Meta'          => $r->mercado_meta ?? '—',
                    'Vida Anaquel'          => $r->tiempo_vida_anaquel ?? '—',
                    'Req. Refrigeración'    => $bool($r->requiere_refrigeracion),
                    'Req. Congelación'      => $bool($r->requiere_congelacion),
                    'Productos / Servicios' => $productos,
                    'Capacidades Producción'=> $capacidades,
                    'Entrega Domicilio'     => $bool($r->entrega_domicilio),
                    'Cobertura'             => $r->cobertura_entrega ? ucfirst($r->cobertura_entrega) : '—',
                    'Forma Entrega'         => $r->forma_entrega ? ucfirst($r->forma_entrega) : '—',
                    'Acepta Crédito'        => $bool($r->acepta_credito),
                    'Crédito Máx. (MXN)'   => $r->credito_monto_maximo
                                                ? number_format((float)$r->credito_monto_maximo, 2, '.', ',')
                                                : '—',
                    'Plazo Crédito'         => $plazo,
                    'Contraentrega'         => $bool($r->pago_contraentrega),
                    'Factura'               => $bool($r->factura),
                    'Régimen Fiscal'        => $r->regimen_fiscal ?? '—',
                    'Total Citas'           => $r->citas_count,
                    'Aprobado'              => $bool($r->aprobado),
                    'Activo'                => $bool($r->activo),
                    'Perfil Completo'       => $bool($r->perfil_completo),
                    'Sol. Aprobación'       => $r->solicitado_aprobacion_at?->format('d/m/Y H:i') ?? '—',
                    'Registro'              => $r->created_at->format('d/m/Y'),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Empresa', 'Categoría', 'Razón Social', 'Representante', 'CURP',
            'Municipio', 'Dom. Yucatán', 'Teléfono', 'Email', 'Sitio Web',
            'RFC', 'Núm. Empleados', 'Inicio Ops.', 'Mercado Meta', 'Vida Anaquel',
            'Req. Refrig.', 'Req. Congel.',
            'Productos / Servicios', 'Cap. Producción',
            'Entrega Domicilio', 'Cobertura', 'Forma Entrega',
            'Acepta Crédito', 'Crédito Máx. (MXN)', 'Plazo Crédito',
            'Contraentrega', 'Factura', 'Régimen Fiscal',
            'Total Citas', 'Aprobado', 'Activo', 'Perfil Completo',
            'Sol. Aprobación', 'Registro',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 18,
            'C' => 26,
            'D' => 24,
            'E' => 20,
            'F' => 16,
            'G' => 13,
            'H' => 15,
            'I' => 30,
            'J' => 26,
            'K' => 14,
            'L' => 13,
            'M' => 16,
            'N' => 32,
            'O' => 22,
            'P' => 13,
            'Q' => 13,
            'R' => 36,
            'S' => 36,
            'T' => 15,
            'U' => 13,
            'V' => 14,
            'W' => 14,
            'X' => 18,
            'Y' => 15,
            'Z' => 13,
            'AA'=> 10,
            'AB'=> 28,
            'AC'=> 12,
            'AD'=> 10,
            'AE'=> 10,
            'AF'=> 15,
            'AG'=> 18,
            'AH'=> 12,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        for ($row = 2; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FDF2F4'],
                    ],
                ]);
            }
        }

        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'E5E7EB'],
                ],
            ],
        ]);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['rgb' => '6B0D1E'],
                ],
            ],
        ]);

        return [
            1 => [
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size'  => 10,
                    'name'  => 'Calibri',
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '8B1028'],
                ],
                'alignment' => [
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'wrapText'   => true,
                ],
            ],
            "A2:{$lastCol}{$lastRow}" => [
                'font' => ['size' => 9, 'name' => 'Calibri'],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
            "AC2:AH{$lastRow}" => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            "A2:A{$lastRow}" => [
                'font' => ['bold' => true, 'size' => 9],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $ws      = $event->sheet->getDelegate();
                $lastCol = $ws->getHighestColumn();
                $lastRow = $ws->getHighestRow();

                $ws->getRowDimension(1)->setRowHeight(32);

                for ($r = 2; $r <= $lastRow; $r++) {
                    $ws->getRowDimension($r)->setRowHeight(18);
                }

                $ws->freezePane('A2');
                $ws->setAutoFilter("A1:{$lastCol}1");
                $ws->setTitle('Proveedores');
                $ws->getTabColor()->setARGB('FF8B1028');
            },
        ];
    }
}
