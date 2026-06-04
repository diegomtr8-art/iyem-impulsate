<?php

namespace App\Exports;

use App\Models\Cita;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CitasExport implements FromCollection, WithHeadings, WithStyles
{
    public function collection()
    {
        return Cita::with(['cliente', 'restaurantero'])
            ->orderByDesc('inicio')
            ->get()
            ->map(fn($c) => [
                'ID'         => $c->id,
                'Comprador'  => $c->cliente?->name ?? '—',
                'Proveedor'  => $c->restaurantero?->nombre_restaurante ?? '—',
                'Fecha'      => $c->inicio?->format('d/m/Y') ?? '—',
                'Hora Inicio'=> $c->inicio?->format('H:i') ?? '—',
                'Hora Fin'   => $c->fin?->format('H:i') ?? '—',
                'Estado'     => $c->estado,
                'Notas'      => $c->notas ?? '—',
                'Creada'     => $c->created_at->format('d/m/Y H:i'),
            ]);
    }

    public function headings(): array
    {
        return ['ID', 'Comprador', 'Proveedor', 'Fecha', 'Hora Inicio', 'Hora Fin', 'Estado', 'Notas', 'Creada'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']]],
        ];
    }
}
