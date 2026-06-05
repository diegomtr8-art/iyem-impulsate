<?php

namespace App\Exports;

use App\Models\Cita;
use App\Models\Evento;
use App\Models\Restaurantero;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EventoCompletoExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private Evento $evento) {}

    public function sheets(): array
    {
        return [
            new EventoSheet($this->evento),
            new ProveedoresEventoSheet($this->evento),
            new CompradoresEventoSheet($this->evento),
            new CitasEventoSheet($this->evento),
            new ResumenEventoSheet($this->evento),
        ];
    }
}

// ── Hoja 1: Datos del evento ──────────────────────────────────────────────────
class EventoSheet implements FromArray, WithTitle, WithStyles
{
    public function __construct(private Evento $evento) {}

    public function title(): string { return 'Evento'; }

    public function array(): array
    {
        $e = $this->evento;
        return [
            ['Campo', 'Valor'],
            ['Nombre', $e->nombre],
            ['Sector Económico', $e->sector_economico ?? '—'],
            ['Descripción', $e->descripcion ?? '—'],
            ['Estado', $e->activa ? 'Activo' : 'Archivado'],
            ['Inicio Proveedores', $e->fecha_hora_inicio_proveedores?->format('d/m/Y H:i') ?? '—'],
            ['Inicio Compradores', $e->fecha_hora_inicio_compradores?->format('d/m/Y H:i') ?? '—'],
            ['Inicio del Evento', $e->fecha_hora_inicio?->format('d/m/Y H:i') ?? '—'],
            ['Fin del Evento', $e->fecha_hora_fin?->format('d/m/Y H:i') ?? '—'],
            ['Máx. Citas por Comprador', $e->max_citas_por_comprador ?? 3],
            ['Tiempo entre Citas (min)', $e->tiempo_entre_citas_minutos ?? 30],
            ['Fecha de exportación', now()->format('d/m/Y H:i')],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']]],
        ];
    }
}

// ── Hoja 2: Proveedores del evento ────────────────────────────────────────────
class ProveedoresEventoSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(private Evento $evento) {}

    public function title(): string { return 'Proveedores'; }

    public function collection()
    {
        return Restaurantero::where('edicion_id', $this->evento->id)
            ->with('user')
            ->withCount(['citas as total_citas' => fn($q) => $q->where('edicion_id', $this->evento->id)])
            ->withCount(['citas as citas_aceptadas' => fn($q) => $q->where('edicion_id', $this->evento->id)->where('estado', 'confirmada')])
            ->withCount(['citas as citas_pendientes' => fn($q) => $q->where('edicion_id', $this->evento->id)->where('estado', 'pendiente')])
            ->orderBy('nombre_restaurante')
            ->get()
            ->map(fn($r) => [
                'Empresa'           => $r->nombre_restaurante,
                'Categoría'         => $r->categoria ?? '—',
                'Municipio'         => $r->municipio ?? '—',
                'RFC'               => $r->rfc ?? '—',
                'Teléfono'          => $r->telefono ?? '—',
                'Email'             => $r->user?->email ?? '—',
                'Total Citas'       => $r->total_citas,
                'Citas Aceptadas'   => $r->citas_aceptadas,
                'Citas Pendientes'  => $r->citas_pendientes,
                'Aprobado'          => $r->aprobado ? 'Sí' : 'No',
                'Registro'          => $r->created_at->format('d/m/Y'),
            ]);
    }

    public function headings(): array
    {
        return ['Empresa', 'Categoría', 'Municipio', 'RFC', 'Teléfono', 'Email', 'Total Citas', 'Citas Aceptadas', 'Citas Pendientes', 'Aprobado', 'Registro'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']]],
        ];
    }
}

// ── Hoja 3: Compradores del evento ────────────────────────────────────────────
class CompradoresEventoSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(private Evento $evento) {}

    public function title(): string { return 'Compradores'; }

    public function collection()
    {
        return User::role('cliente')
            ->withCount([
                'citasComoCliente as total_citas' => fn($q) => $q->where('edicion_id', $this->evento->id),
                'citasComoCliente as confirmadas'  => fn($q) => $q->where('edicion_id', $this->evento->id)->where('estado', 'confirmada'),
                'citasComoCliente as pendientes'   => fn($q) => $q->where('edicion_id', $this->evento->id)->where('estado', 'pendiente'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'Nombre'             => $u->name,
                'Email'              => $u->email,
                'Teléfono'           => $u->telefono ?? '—',
                'CURP'               => $u->curp ?? '—',
                'Total Citas'        => $u->total_citas,
                'Citas Confirmadas'  => $u->confirmadas,
                'Citas Pendientes'   => $u->pendientes,
                'Registro'           => $u->created_at->format('d/m/Y'),
            ]);
    }

    public function headings(): array
    {
        return ['Nombre', 'Email', 'Teléfono', 'CURP', 'Total Citas', 'Citas Confirmadas', 'Citas Pendientes', 'Registro'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']]],
        ];
    }
}

// ── Hoja 4: Citas del evento ──────────────────────────────────────────────────
class CitasEventoSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(private Evento $evento) {}

    public function title(): string { return 'Citas'; }

    public function collection()
    {
        return Cita::where('edicion_id', $this->evento->id)
            ->with(['cliente', 'restaurantero'])
            ->orderByDesc('inicio')
            ->get()
            ->map(fn($c) => [
                'ID'                => $c->id,
                'Comprador'         => $c->cliente?->name ?? '—',
                'Email Comprador'   => $c->cliente?->email ?? '—',
                'Proveedor'         => $c->restaurantero?->nombre_restaurante ?? '—',
                'Fecha'             => $c->inicio?->format('d/m/Y'),
                'Hora Inicio'       => $c->inicio?->format('H:i'),
                'Hora Fin'          => $c->fin?->format('H:i'),
                'Estado'            => ucfirst($c->estado),
                'Mesa'              => $c->mesa ?? '—',
                'Notas'             => $c->notas ?? '—',
                'Creada'            => $c->created_at->format('d/m/Y H:i'),
            ]);
    }

    public function headings(): array
    {
        return ['ID', 'Comprador', 'Email Comprador', 'Proveedor', 'Fecha', 'Hora Inicio', 'Hora Fin', 'Estado', 'Mesa', 'Notas', 'Creada'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']]],
        ];
    }
}

// ── Hoja 5: Resumen / KPIs ────────────────────────────────────────────────────
class ResumenEventoSheet implements FromArray, WithTitle, WithStyles
{
    public function __construct(private Evento $evento) {}

    public function title(): string { return 'Resumen'; }

    public function array(): array
    {
        $e = $this->evento;
        $citas = Cita::where('edicion_id', $e->id);

        $totalCitas        = (clone $citas)->count();
        $citasPendientes   = (clone $citas)->where('estado', 'pendiente')->count();
        $citasConfirmadas  = (clone $citas)->where('estado', 'confirmada')->count();
        $citasCanceladas   = (clone $citas)->where('estado', 'cancelada')->count();
        $citasCompletadas  = (clone $citas)->where('estado', 'completada')->count();
        $citasRechazadas   = (clone $citas)->where('estado', 'rechazada')->count();
        $totalProveedores  = Restaurantero::where('edicion_id', $e->id)->count();
        $totalCompradores  = User::role('cliente')
            ->whereHas('citasComoCliente', fn($q) => $q->where('edicion_id', $e->id))
            ->count();

        // Top 10 proveedores por citas
        $topProveedores = Restaurantero::where('edicion_id', $e->id)
            ->withCount(['citas as total' => fn($q) => $q->where('edicion_id', $e->id)])
            ->orderByDesc('total')
            ->take(10)
            ->get();

        $rows = [
            ['Métrica', 'Valor'],
            ['Evento', $e->nombre],
            ['Total de Citas', $totalCitas],
            ['Citas Pendientes', $citasPendientes],
            ['Citas Confirmadas', $citasConfirmadas],
            ['Citas Completadas', $citasCompletadas],
            ['Citas Canceladas', $citasCanceladas],
            ['Citas Rechazadas', $citasRechazadas],
            ['Total Proveedores', $totalProveedores],
            ['Total Compradores', $totalCompradores],
            ['', ''],
            ['Top 10 Proveedores por Citas', ''],
            ['Proveedor', 'Total Citas'],
        ];

        foreach ($topProveedores as $p) {
            $rows[] = [$p->nombre_restaurante, $p->total];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1  => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']]],
            12 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4B5563']]],
            13 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']]],
        ];
    }
}
