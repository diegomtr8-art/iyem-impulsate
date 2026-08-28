<?php

namespace App\Exports;

use App\Models\Evento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

class CompradoresExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    public function collection()
    {
        $eventoId = Evento::contextoAdmin()?->id;

        $participaciones = $eventoId
            ? DB::table('evento_usuario')
                ->where('evento_id', $eventoId)
                ->where('tipo', 'comprador')
                ->pluck('estado', 'user_id')
            : collect();

        $bool = fn($val) => is_null($val) ? '—' : ($val ? 'Sí' : 'No');

        return User::role('cliente')
            ->withCount('citasComoCliente')
            ->orderBy('name')
            ->get()
            ->map(function ($u) use ($participaciones, $bool) {
                return [
                    'Nombre'                  => $u->name ?? '—',
                    'Email'                   => $u->email ?? '—',
                    'Teléfono'                => $u->telefono ?? '—',
                    'Municipio'               => $u->municipio ?? '—',
                    'CURP'                    => $u->curp ?? '—',
                    'RFC'                     => $u->rfc ?? '—',
                    'Empresa / Negocio'       => $u->nombre_empresa ?? '—',
                    'Nombre Establecimiento'  => $u->nombre_establecimiento ?? '—',
                    'Cámara / Asociación'     => $u->camara_asociacion ?? '—',
                    'Sitio Web'               => $u->sitio_web ?? '—',
                    'Necesidades'             => $u->necesidades ?? '—',
                    'Total Citas'             => $u->citas_como_cliente_count,
                    'Estado en Evento'        => $participaciones->has($u->id)
                                                    ? ucfirst($participaciones->get($u->id))
                                                    : 'No registrado',
                    'Perfil Completo'         => $bool($u->perfil_completo),
                    'Acepta Aviso'            => $u->acepta_aviso_at
                                                    ? $u->acepta_aviso_at->format('d/m/Y')
                                                    : 'No',
                    'Registro'                => $u->created_at->format('d/m/Y'),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Nombre', 'Email', 'Teléfono', 'Municipio', 'CURP', 'RFC',
            'Empresa / Negocio', 'Nombre Establecimiento', 'Cámara / Asociación',
            'Sitio Web', 'Necesidades',
            'Total Citas', 'Estado en Evento', 'Perfil Completo', 'Acepta Aviso', 'Registro',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 32,
            'C' => 15,
            'D' => 18,
            'E' => 20,
            'F' => 14,
            'G' => 28,
            'H' => 26,
            'I' => 30,
            'J' => 26,
            'K' => 36,
            'L' => 12,
            'M' => 18,
            'N' => 14,
            'O' => 14,
            'P' => 12,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        // Alternating light pink on even rows
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

        // Thin gray border all cells
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'E5E7EB'],
                ],
            ],
        ]);

        // Medium guinda border under header
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['rgb' => '6B0D1E'],
                ],
            ],
        ]);

        return [
            // Header row: guinda bg, white bold text, centered
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
            // Data rows: small Calibri, vertically centered
            "A2:{$lastCol}{$lastRow}" => [
                'font'      => ['size' => 9, 'name' => 'Calibri'],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
            // Last columns (numeric/status) centered
            "L2:P{$lastRow}" => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // Nombre column bold
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
                $ws->setTitle('Compradores');
                $ws->getTabColor()->setARGB('FF8B1028');
            },
        ];
    }
}
