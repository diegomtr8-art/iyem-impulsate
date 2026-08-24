<?php

namespace App\Exports;

use App\Models\Cita;
use App\Models\Evento;
use App\Models\Restaurantero;
use App\Models\User;
use Illuminate\Support\Str;
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
        $sheets = [
            new EventoSheet($this->evento),
            new ProveedoresEventoSheet($this->evento),
            new CompradoresEventoSheet($this->evento),
            new CitasEventoSheet($this->evento),
            new ResumenEventoSheet($this->evento),
        ];

        // Hasta 20 hojas adicionales, una por proveedor aprobado
        $proveedores = Restaurantero::whereHas('user', function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->from('evento_usuario')
                        ->whereColumn('evento_usuario.user_id', 'users.id')
                        ->where('evento_usuario.evento_id', $this->evento->id)
                        ->where('evento_usuario.tipo', 'proveedor')
                        ->where('evento_usuario.estado', 'aprobado');
                });
            })
            ->with(['citas' => function ($q) {
                $q->where('edicion_id', $this->evento->id)->with('cliente');
            }])
            ->take(20)
            ->get();

        foreach ($proveedores as $proveedor) {
            $sheets[] = new CitasProveedorSheet($proveedor, $this->evento);
        }

        return $sheets;
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

// ── Hoja 2: Proveedores del evento (solo aprobados) ───────────────────────────
class ProveedoresEventoSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(private Evento $evento) {}

    public function title(): string { return 'Proveedores'; }

    public function collection()
    {
        return Restaurantero::whereHas('user', function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->from('evento_usuario')
                        ->whereColumn('evento_usuario.user_id', 'users.id')
                        ->where('evento_usuario.evento_id', $this->evento->id)
                        ->where('evento_usuario.tipo', 'proveedor')
                        ->where('evento_usuario.estado', 'aprobado');
                });
            })
            ->with('user')
            ->withCount(['citas as total_citas'    => fn($q) => $q->where('edicion_id', $this->evento->id)])
            ->withCount(['citas as citas_aceptadas' => fn($q) => $q->where('edicion_id', $this->evento->id)->where('estado', 'confirmada')])
            ->withCount(['citas as citas_pendientes' => fn($q) => $q->where('edicion_id', $this->evento->id)->where('estado', 'pendiente')])
            ->orderBy('nombre_restaurante')
            ->get()
            ->map(fn($r) => [
                'Empresa'             => $r->nombre_restaurante,
                'Razón Social'        => $r->razon_social ?? '—',
                'Nombre Propietario'  => $r->user?->name ?? '—',
                'Categoría'           => $r->categoria ?? '—',
                'Municipio'           => $r->municipio ?? '—',
                'RFC'                 => $r->rfc ?? '—',
                'Teléfono'            => $r->telefono ?? '—',
                'Email'               => $r->user?->email ?? '—',
                'Total Citas'         => $r->total_citas,
                'Citas Aceptadas'     => $r->citas_aceptadas,
                'Citas Pendientes'    => $r->citas_pendientes,
                'Estado en Evento'    => 'Aprobado',
                'Registro'            => $r->created_at->format('d/m/Y'),
            ]);
    }

    public function headings(): array
    {
        return ['Empresa', 'Razón Social', 'Nombre Propietario', 'Categoría', 'Municipio', 'RFC', 'Teléfono', 'Email', 'Total Citas', 'Citas Aceptadas', 'Citas Pendientes', 'Estado en Evento', 'Registro'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']]],
        ];
    }
}

// ── Hoja 3: Compradores del evento (solo aprobados) ───────────────────────────
class CompradoresEventoSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(private Evento $evento) {}

    public function title(): string { return 'Compradores'; }

    public function collection()
    {
        $aprobadosIds = \DB::table('evento_usuario')
            ->where('evento_id', $this->evento->id)
            ->where('tipo', 'comprador')
            ->where('estado', 'aprobado')
            ->pluck('user_id');

        return User::role('cliente')
            ->whereIn('id', $aprobadosIds)
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
                'Municipio'          => $u->municipio ?? '—',
                'CURP'               => $u->curp ?? '—',
                'Total Citas'        => $u->total_citas,
                'Citas Confirmadas'  => $u->confirmadas,
                'Citas Pendientes'   => $u->pendientes,
                'Estado en Evento'   => 'Aprobado',
                'Registro'           => $u->created_at->format('d/m/Y'),
            ]);
    }

    public function headings(): array
    {
        return ['Nombre', 'Email', 'Teléfono', 'Municipio', 'CURP', 'Total Citas', 'Citas Confirmadas', 'Citas Pendientes', 'Estado en Evento', 'Registro'];
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

        $totalProveedores = \DB::table('evento_usuario')
            ->where('evento_id', $e->id)
            ->where('tipo', 'proveedor')
            ->where('estado', 'aprobado')
            ->count();

        $totalCompradores = \DB::table('evento_usuario')
            ->where('evento_id', $e->id)
            ->where('tipo', 'comprador')
            ->where('estado', 'aprobado')
            ->count();

        $topProveedores = Restaurantero::whereHas('user', function ($q) use ($e) {
                $q->whereExists(function ($sub) use ($e) {
                    $sub->from('evento_usuario')
                        ->whereColumn('evento_usuario.user_id', 'users.id')
                        ->where('evento_usuario.evento_id', $e->id)
                        ->where('evento_usuario.tipo', 'proveedor')
                        ->where('evento_usuario.estado', 'aprobado');
                });
            })
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
            ['Total Proveedores Aprobados', $totalProveedores],
            ['Total Compradores Aprobados', $totalCompradores],
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

// ── Hoja por proveedor: Citas individuales ────────────────────────────────────
class CitasProveedorSheet implements FromArray, WithTitle, WithStyles
{
    public function __construct(
        private Restaurantero $restaurantero,
        private Evento $evento
    ) {}

    public function title(): string
    {
        $nombre = $this->restaurantero->nombre_restaurante ?? 'Proveedor';
        return Str::limit(preg_replace('/[\/\\\?\*\[\]:]/', '', $nombre), 28);
    }

    public function array(): array
    {
        $rows = [
            ['CITAS DE: ' . ($this->restaurantero->nombre_restaurante ?? '—')],
            ['Evento: ' . $this->evento->nombre],
            [''],
            ['#', 'Comprador', 'Email', 'Teléfono', 'Fecha', 'Hora Inicio', 'Hora Fin', 'Estado', 'Notas'],
        ];

        $citas = $this->restaurantero->citas()
            ->where('edicion_id', $this->evento->id)
            ->with('cliente')
            ->orderBy('inicio')
            ->get();

        foreach ($citas as $i => $cita) {
            $rows[] = [
                $i + 1,
                $cita->cliente?->name ?? '—',
                $cita->cliente?->email ?? '—',
                $cita->cliente?->telefono ?? '—',
                $cita->inicio?->format('d/m/Y') ?? '—',
                $cita->inicio?->format('H:i') ?? '—',
                $cita->fin?->format('H:i') ?? '—',
                ucfirst($cita->estado),
                $cita->notas ?? '—',
            ];
        }

        if (count($rows) === 4) {
            $rows[] = ['Sin citas registradas en este evento'];
        }

        $rows[] = [''];
        $rows[] = ['Total citas:', $citas->count()];
        $rows[] = ['Confirmadas:', $citas->where('estado', 'confirmada')->count()];
        $rows[] = ['Pendientes:', $citas->where('estado', 'pendiente')->count()];
        $rows[] = ['Rechazadas:', $citas->where('estado', 'rechazada')->count()];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
                  'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '8B1028']]],
            4 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                  'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4A0510']]],
        ];
    }
}
