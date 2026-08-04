<?php

namespace App\Exports;

use App\Models\EncuestaPlantilla;
use App\Models\EncuestaRespuesta;
use App\Models\EncuestaSatisfaccion;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EncuestasResumenSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    private array $boldRows = [1, 10];

    public function __construct(
        private EncuestaPlantilla $plantilla,
        private ?int $eventoId,
        private ?string $canirac = null // 'si' | 'no' | null
    ) {}

    public function title(): string
    {
        return 'Resumen';
    }

    public function columnWidths(): array
    {
        return ['A' => 55, 'B' => 35, 'C' => 12, 'D' => 12];
    }

    public function array(): array
    {
        $encuestaIds = EncuestaSatisfaccion::where('es_prueba', false)
            ->whereNotNull('completada_at')
            ->when($this->eventoId, fn ($q) => $q->where('evento_id', $this->eventoId))
            ->when($this->canirac === 'si', fn ($q) =>
                $q->whereHas('user', fn ($u) => $u->where('camara_asociacion', 'CANIRAC'))
            )
            ->when($this->canirac === 'no', fn ($q) =>
                $q->whereHas('user', fn ($u) => $u->where(
                    fn ($u2) => $u2->whereNull('camara_asociacion')->orWhere('camara_asociacion', '!=', 'CANIRAC')
                ))
            )
            ->pluck('id');

        $caniracRespondidos   = EncuestaSatisfaccion::whereIn('id', $encuestaIds)
            ->whereHas('user', fn ($q) => $q->where('camara_asociacion', 'CANIRAC'))
            ->count();
        $noCaniracRespondidos = EncuestaSatisfaccion::whereIn('id', $encuestaIds)
            ->whereHas('user', fn ($q) => $q->where(
                fn ($q2) => $q2->whereNull('camara_asociacion')->orWhere('camara_asociacion', '!=', 'CANIRAC')
            ))
            ->count();

        $totalRespondidas = $encuestaIds->count();
        $totalEnviadas    = EncuestaSatisfaccion::where('es_prueba', false)
            ->when($this->eventoId, fn ($q) => $q->where('evento_id', $this->eventoId))
            ->count();

        $rows = [
            ['REPORTE DE ENCUESTAS DE SATISFACCIÓN', '', '', ''],
            ['Plantilla:', $this->plantilla->nombre, '', ''],
            ['Total enviadas:', $totalEnviadas, '', ''],
            ['Total respondidas:', $totalRespondidas, '', ''],
            ['Tasa de respuesta:', ($totalEnviadas > 0 ? round($totalRespondidas * 100 / $totalEnviadas, 1) : 0) . '%', '', ''],
            ['Generado:', now()->format('d/m/Y H:i'), '', ''],
            ['Miembros Canirac respondidos:', $caniracRespondidos, '', ''],
            ['No Canirac respondidos:', $noCaniracRespondidos, '', ''],
            ['', '', '', ''],
            ['PREGUNTA', 'OPCIÓN DE RESPUESTA', 'CANTIDAD', '% DEL TOTAL'],
        ];

        $preguntas = $this->plantilla->preguntas ?? [];

        foreach ($preguntas as $pregunta) {
            if ($pregunta['tipo'] === 'texto') {
                $textos = EncuestaRespuesta::whereIn('encuesta_satisfaccion_id', $encuestaIds)
                    ->where('pregunta', $pregunta['texto'])
                    ->whereNotNull('respuesta')
                    ->where('respuesta', '!=', '')
                    ->pluck('respuesta');

                $this->boldRows[] = count($rows) + 1;
                $rows[] = [$pregunta['texto'], '(Texto libre — ' . $textos->count() . ' respuestas)', '', ''];
                foreach ($textos as $txt) {
                    $rows[] = ['', '  • ' . $txt, '', ''];
                }
                $rows[] = ['', '', '', ''];
                continue;
            }

            $grupo = EncuestaRespuesta::whereIn('encuesta_satisfaccion_id', $encuestaIds)
                ->where('pregunta', $pregunta['texto'])
                ->selectRaw('respuesta, COUNT(*) as total')
                ->groupBy('respuesta')
                ->orderByDesc('total')
                ->get();

            $totalPregunta = $grupo->sum('total');
            $primerRow     = true;
            $this->boldRows[] = count($rows) + 1;

            foreach ($grupo as $g) {
                $pct = $totalPregunta > 0 ? round($g->total * 100 / $totalPregunta, 1) : 0;
                $rows[] = [
                    $primerRow ? $pregunta['texto'] : '',
                    $g->respuesta ?? '(sin respuesta)',
                    $g->total,
                    $pct . '%',
                ];
                $primerRow = false;
            }

            if ($grupo->isEmpty()) {
                $rows[] = [$pregunta['texto'], '(sin respuestas)', 0, '0%'];
            }

            $rows[] = ['', '', '', ''];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8B1028']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle('A10:D10')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8B1028']],
        ]);

        foreach (array_unique($this->boldRows) as $row) {
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        }

        return [];
    }
}
