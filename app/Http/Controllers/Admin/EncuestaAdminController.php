<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EncuestaSatisfaccion;
use App\Models\Evento;
use App\Exports\EncuestasExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class EncuestaAdminController extends Controller
{
    public function index(Request $request)
    {
        $eventoId = $request->get('evento_id');

        $query = EncuestaSatisfaccion::with(['evento', 'user', 'respuestas'])
            ->orderByDesc('created_at');

        if ($eventoId) {
            $query->where('evento_id', $eventoId);
        }

        $encuestas = $query->paginate(50)->through(fn ($e) => [
            'id'            => $e->id,
            'evento'        => $e->evento?->only(['id', 'nombre']),
            'usuario'       => $e->user?->only(['id', 'name', 'email']),
            'tipo'          => $e->tipo,
            'completada'    => $e->completada(),
            'completada_at' => $e->completada_at?->format('d/m/Y H:i'),
            'created_at'    => $e->created_at->format('d/m/Y'),
        ]);

        $eventos = Evento::orderByDesc('created_at')->get(['id', 'nombre']);

        return Inertia::render('Admin/Encuestas/Index', [
            'encuestas'      => $encuestas,
            'eventos'        => $eventos,
            'filtroEvento'   => $eventoId,
        ]);
    }

    public function exportar(Request $request)
    {
        $eventoId = $request->get('evento_id');
        $suffix   = $eventoId ? "_evento_{$eventoId}" : '_todos';
        $filename = "encuestas{$suffix}_" . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new EncuestasExport($eventoId), $filename);
    }
}
