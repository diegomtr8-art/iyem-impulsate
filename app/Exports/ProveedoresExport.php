<?php

namespace App\Exports;

use App\Models\Restaurantero;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProveedoresExport implements FromCollection, WithHeadings, WithStyles
{
    public function collection()
    {
        return Restaurantero::with('user')
            ->withCount('citas')
            ->orderBy('nombre_restaurante')
            ->get()
            ->map(fn($r) => [
                'Empresa'     => $r->nombre_restaurante,
                'Categoría'   => $r->categoria ?? '—',
                'Municipio'   => $r->municipio ?? '—',
                'RFC'         => $r->rfc ?? '—',
                'Teléfono'    => $r->telefono ?? '—',
                'Email'       => $r->user?->email ?? '—',
                'Total Citas' => $r->citas_count,
                'Aprobado'    => $r->aprobado ? 'Sí' : 'No',
                'Activo'      => $r->activo ? 'Sí' : 'No',
                'Registro'    => $r->created_at->format('d/m/Y'),
            ]);
    }

    public function headings(): array
    {
        return ['Empresa', 'Categoría', 'Municipio', 'RFC', 'Teléfono', 'Email', 'Total Citas', 'Aprobado', 'Activo', 'Registro'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']]],
        ];
    }
}
