<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\MetricasAdminService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/** La logica vive en MetricasAdminService (compartido con la API v1). */
class MetricasController extends Controller
{
    public function __construct(private MetricasAdminService $metricas) {}

    public function index()
    {
        return Inertia::render('Admin/Metricas', $this->metricas->datos());
    }

    public function actualizarGenero(Request $request, User $user)
    {
        $request->validate([
            'genero' => 'required|in:hombre,mujer',
        ]);

        $this->metricas->actualizarGenero($user, $request->genero);

        return back()->with('success', 'Género actualizado.');
    }

    public function exportar()
    {
        [$export, $filename] = $this->metricas->export();

        return Excel::download($export, $filename);
    }
}
