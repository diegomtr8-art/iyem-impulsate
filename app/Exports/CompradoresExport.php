<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompradoresExport implements FromCollection, WithHeadings, WithStyles
{
    public function collection()
    {
        return User::role('cliente')
            ->withCount('citasComoCliente')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'Nombre'       => $u->name,
                'Email'        => $u->email,
                'Teléfono'     => $u->telefono ?? '—',
                'CURP'         => $u->curp ?? '—',
                'Necesidades'  => $u->necesidades ?? '—',
                'Total Citas'  => $u->citas_como_cliente_count,
                'Registro'     => $u->created_at->format('d/m/Y'),
            ]);
    }

    public function headings(): array
    {
        return ['Nombre', 'Email', 'Teléfono', 'CURP', 'Necesidades', 'Total Citas', 'Registro'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']], 'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true]],
        ];
    }
}
