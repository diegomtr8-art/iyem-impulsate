<?php

namespace App\Exports;

use App\Models\EncuestaSatisfaccion;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EncuestasExport implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(private ?int $eventoId = null) {}

    public function title(): string
    {
        return 'Encuestas';
    }

    public function collection()
    {
        $preguntas = config('encuestas.preguntas');

        $query = EncuestaSatisfaccion::with(['evento', 'user', 'respuestas'])
            ->whereNotNull('completada_at');

        if ($this->eventoId) {
            $query->where('evento_id', $this->eventoId);
        }

        return $query->orderBy('evento_id')->orderBy('tipo')->get()->map(function ($enc) use ($preguntas) {
            $respuestasMap = $enc->respuestas->pluck('respuesta', 'pregunta');

            $row = [
                'Evento'        => $enc->evento?->nombre ?? '—',
                'Participante'  => $enc->user?->name ?? '—',
                'Email'         => $enc->user?->email ?? '—',
                'Tipo'          => ucfirst($enc->tipo),
                'Fecha respuesta' => $enc->completada_at?->format('d/m/Y H:i'),
            ];

            foreach ($preguntas as $pregunta) {
                $row[$pregunta['texto']] = $respuestasMap[$pregunta['texto']] ?? '—';
            }

            return $row;
        });
    }

    public function headings(): array
    {
        $preguntas = config('encuestas.preguntas');
        $base = ['Evento', 'Participante', 'Email', 'Tipo', 'Fecha respuesta'];
        foreach ($preguntas as $p) {
            $base[] = $p['texto'];
        }
        return $base;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']],
            ],
        ];
    }
}
