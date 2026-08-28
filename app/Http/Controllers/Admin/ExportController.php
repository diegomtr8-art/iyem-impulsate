<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CitasExport;
use App\Exports\CompradoresExport;
use App\Exports\EventoCompletoExport;
use App\Exports\ProveedoresExport;
use App\Http\Controllers\Controller;
use App\Models\Evento;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function index()
    {
        $evento = Evento::contextoAdmin();
        return Inertia::render('Admin/Exportar/Index', [
            'evento' => $evento,
        ]);
    }

    public function eventoCompleto(Evento $evento)
    {
        $slug     = Str::slug($evento->nombre);
        $filename = "evento_{$slug}_" . now()->format('Y-m-d') . '.xlsx';
        return Excel::download(new EventoCompletoExport($evento), $filename);
    }

    public function eventoCompletoActivo()
    {
        $evento = Evento::contextoAdmin();
        if (!$evento) {
            return back()->withErrors(['error' => 'No hay un evento activo para exportar.']);
        }
        $slug     = Str::slug($evento->nombre);
        $filename = "evento_{$slug}_" . now()->format('Y-m-d') . '.xlsx';
        return Excel::download(new EventoCompletoExport($evento), $filename);
    }

    public function compradores()
    {
        return Excel::download(new CompradoresExport(), 'compradores_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function proveedores()
    {
        return Excel::download(new ProveedoresExport(), 'proveedores_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function citas()
    {
        return Excel::download(new CitasExport(), 'citas_' . now()->format('Y-m-d') . '.xlsx');
    }
}
