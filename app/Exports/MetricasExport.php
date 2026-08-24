<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MetricasExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private array $data) {}

    public function sheets(): array
    {
        $d = $this->data;

        return [
            new ResumenMetricasSheet($d),
            new NoIdentificadosSheet(collect($d['noIdentificados'] ?? [])->all()),
            new RankingMetricasSheet(
                'Municipios - Proveedores',
                ['Municipio', 'Total'],
                collect($d['topMunicipiosProveedores'] ?? [])->map(fn ($i) => [$i->municipio, $i->total])->all()
            ),
            new RankingMetricasSheet(
                'Municipios - Compradores',
                ['Municipio', 'Total'],
                collect($d['topMunicipiosCompradores'] ?? [])->map(fn ($i) => [$i->municipio, $i->total])->all()
            ),
            new RankingMetricasSheet(
                'Categorías - Proveedores',
                ['Categoría', 'Total'],
                collect($d['topCategoriasProveedores'] ?? [])->map(fn ($i) => [$i->categoria, $i->total])->all()
            ),
            new RankingMetricasSheet(
                'Necesidades - Compradores',
                ['Necesidad', 'Total'],
                collect($d['topNecesidadesCompradores'] ?? [])->map(fn ($i) => [$i['necesidad'], $i['total']])->all()
            ),
            new RankingMetricasSheet(
                'Proveedores más visitados',
                ['Proveedor', 'Visitas'],
                collect($d['proveedoresTop'] ?? [])->map(fn ($i) => [$i->nombre, $i->total])->all()
            ),
        ];
    }
}

// ── Hoja 1: Resumen general ─────────────────────────────────────────────
class ResumenMetricasSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(private array $d) {}

    public function title(): string { return 'Resumen'; }

    public function array(): array
    {
        $d = $this->d;
        $pct = fn ($val, $tot) => $tot > 0 ? round($val / $tot * 100) . '%' : '0%';

        return [
            ['MÉTRICAS IMPULSATE — Resumen general', ''],
            ['Generado', now()->format('d/m/Y H:i')],
            [''],
            ['VISITAS', ''],
            ['Total de visitas', $d['totalVisitas'] ?? 0],
            ['Visitas hoy', $d['visitasHoy'] ?? 0],
            ['Visitas esta semana', $d['visitasSemana'] ?? 0],
            [''],
            ['GÉNERO (personas únicas, dual-rol cuenta una vez)', ''],
            ['Hombres', ($d['generoHombre'] ?? 0) . ' (' . $pct($d['generoHombre'] ?? 0, $d['generoTotal'] ?? 0) . ')'],
            ['Mujeres', ($d['generoMujer'] ?? 0) . ' (' . $pct($d['generoMujer'] ?? 0, $d['generoTotal'] ?? 0) . ')'],
            ['No identificado', ($d['generoNoIdentif'] ?? 0) . ' (' . $pct($d['generoNoIdentif'] ?? 0, $d['generoTotal'] ?? 0) . ')'],
            ['Total', $d['generoTotal'] ?? 0],
            [''],
            ['FORMALIZACIÓN (RFC)', ''],
            ['Proveedores con RFC', $d['proveedorConRFC'] ?? 0],
            ['Proveedores sin RFC', $d['proveedorSinRFC'] ?? 0],
            ['Compradores con RFC', $d['compradorConRFC'] ?? 0],
            ['Compradores sin RFC', $d['compradorSinRFC'] ?? 0],
            ['Total con RFC', ($d['rfcConRFC'] ?? 0) . ' (' . $pct($d['rfcConRFC'] ?? 0, $d['rfcTotal'] ?? 0) . ')'],
            ['Total sin RFC', ($d['rfcSinRFC'] ?? 0) . ' (' . $pct($d['rfcSinRFC'] ?? 0, $d['rfcTotal'] ?? 0) . ')'],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 44, 'B' => 24];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8B1028']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        foreach (['A4', 'A9', 'A15'] as $cell) {
            $sheet->getStyle($cell)->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '8B1028']],
            ]);
        }

        $sheet->getStyle('A1:B21')->applyFromArray([
            'font'      => ['size' => 10, 'name' => 'Calibri'],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2')->applyFromArray(['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '6B7280']]]);

        return [];
    }
}

// ── Hoja 2: Usuarios con género no identificado ─────────────────────────
class NoIdentificadosSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(private array $lista) {}

    public function title(): string { return 'No identificados'; }

    public function array(): array
    {
        $rows = [['Nombre', 'Roles']];
        foreach ($this->lista as $item) {
            $roles = is_array($item) ? ($item['roles'] ?? []) : $item->roles;
            $nombre = is_array($item) ? $item['name'] : $item->name;
            $rows[] = [$nombre, is_array($roles) ? implode(', ', $roles) : (string) $roles];
        }
        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 36, 'B' => 28];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8B1028']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            "A2:B{$lastRow}" => [
                'font' => ['size' => 9, 'name' => 'Calibri'],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ],
        ];
    }
}

// ── Hojas de rankings (municipios / categorías / necesidades / top visitas) ──
class RankingMetricasSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(
        private string $tituloHoja,
        private array $headings,
        private array $filas
    ) {}

    public function title(): string
    {
        return mb_substr($this->tituloHoja, 0, 31);
    }

    public function array(): array
    {
        return array_merge([$this->headings], $this->filas);
    }

    public function columnWidths(): array
    {
        return ['A' => 38, 'B' => 14];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max($sheet->getHighestRow(), 1);
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8B1028']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            "A2:B{$lastRow}" => [
                'font' => ['size' => 9, 'name' => 'Calibri'],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ],
            "B2:B{$lastRow}" => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
