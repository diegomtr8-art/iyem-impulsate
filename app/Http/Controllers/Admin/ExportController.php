<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Services\Admin\ExportacionesAdminService;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/** Los Excel los arma ExportacionesAdminService (compartido con la API v1). */
class ExportController extends Controller
{
    public function __construct(private ExportacionesAdminService $exportaciones) {}

    public function index()
    {
        $evento = Evento::contextoAdmin();
        return Inertia::render('Admin/Exportar/Index', [
            'evento' => $evento,
        ]);
    }

    public function eventoCompleto(Evento $evento)
    {
        return Excel::download(...$this->exportaciones->eventoCompleto($evento));
    }

    public function eventoCompletoActivo()
    {
        return Excel::download(...$this->exportaciones->eventoCompleto(Evento::contextoAdmin()));
    }

    public function compradores()
    {
        return Excel::download(...$this->exportaciones->compradores());
    }

    public function proveedores()
    {
        return Excel::download(...$this->exportaciones->proveedores());
    }

    public function citas()
    {
        return Excel::download(...$this->exportaciones->citas());
    }
}
