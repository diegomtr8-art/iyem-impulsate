<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CitasExport;
use App\Exports\CompradoresExport;
use App\Exports\ProveedoresExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
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
