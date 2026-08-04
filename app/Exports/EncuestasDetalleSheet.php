<?php

namespace App\Exports;

use App\Models\EncuestaPlantilla;
use App\Models\EncuestaSatisfaccion;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EncuestasDetalleSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private EncuestaPlantilla $plantilla,
        private ?int $eventoId,
        private ?string $tipo, // null=todos, 'comprador', 'proveedor'
        private string $titulo,
        private ?string $canirac = null // 'si' | 'no' | null
    ) {}

    public function title(): string
    {
        return Str::limit($this->titulo, 31, '');
    }

    public function columnWidths(): array
    {
        return ['A' => 25, 'B' => 25, 'C' => 30, 'D' => 14, 'E' => 12, 'F' => 20];
    }

    public function collection()
    {
        $preguntas = $this->plantilla->preguntas ?? [];

        $query = EncuestaSatisfaccion::with(['evento', 'user', 'respuestas'])
            ->where('es_prueba', false)
            ->whereNotNull('completada_at')
            ->when($this->eventoId, fn ($q) => $q->where('evento_id', $this->eventoId))
            ->when($this->tipo, fn ($q) => $q->where('tipo', $this->tipo))
            ->when($this->canirac === 'si', fn ($q) =>
                $q->whereHas('user', fn ($u) => $u->where('camara_asociacion', 'CANIRAC'))
            )
            ->when($this->canirac === 'no', fn ($q) =>
                $q->whereHas('user', fn ($u) => $u->where(
                    fn ($u2) => $u2->whereNull('camara_asociacion')->orWhere('camara_asociacion', '!=', 'CANIRAC')
                ))
            )
            ->orderBy('tipo')
            ->orderBy('completada_at');

        return $query->get()->map(function ($enc) use ($preguntas) {
            $respuestasMap = $enc->respuestas->pluck('respuesta', 'pregunta');

            $row = [
                'Evento'          => $enc->evento?->nombre ?? '—',
                'Participante'    => $enc->user?->name ?? '—',
                'Email'           => $enc->user?->email ?? '—',
                'Tipo'            => ucfirst($enc->tipo),
                'Canirac'         => $enc->user?->camara_asociacion === 'CANIRAC' ? 'Sí' : 'No',
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
        $preguntas = $this->plantilla->preguntas ?? [];
        $base      = ['Evento', 'Participante', 'Email', 'Tipo', 'Canirac', 'Fecha respuesta'];
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
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8B1028']],
            ],
        ];
    }
}
