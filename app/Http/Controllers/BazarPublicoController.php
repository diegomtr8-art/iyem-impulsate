<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BazarPublicoController extends Controller
{
    public function evaluacion(string $token)
    {
        $registro = DB::table('evento_usuario as eu')
            ->join('users as u', 'u.id', '=', 'eu.user_id')
            ->join('eventos as e', 'e.id', '=', 'eu.evento_id')
            ->where('eu.token_evaluacion', $token)
            ->select([
                'u.name', 'e.nombre as nombre_evento', 'e.id as evento_id',
                'eu.puntaje_total', 'eu.user_id', 'eu.notas_rechazo',
            ])
            ->first();

        abort_if(!$registro, 404, 'Enlace de evaluación no encontrado o inválido.');

        $criterios = DB::table('evento_criterios as c')
            ->leftJoin('evento_evaluaciones as ev', function ($j) use ($registro) {
                $j->on('ev.criterio_id', '=', 'c.id')
                  ->where('ev.user_id', $registro->user_id)
                  ->where('ev.evento_id', $registro->evento_id);
            })
            ->where('c.evento_id', $registro->evento_id)
            ->orderBy('c.orden')
            ->select(['c.nombre', 'c.porcentaje', 'ev.puntaje'])
            ->get()
            ->map(fn ($c) => [
                'nombre'            => $c->nombre,
                'porcentaje'        => $c->porcentaje,
                'puntaje'           => $c->puntaje ?? 0,
                'puntaje_maximo'    => 100,
                'puntaje_ponderado' => round(($c->puntaje ?? 0) / 100 * $c->porcentaje, 2),
            ]);

        return Inertia::render('Bazar/EvaluacionPublica', [
            'nombre_usuario' => $registro->name,
            'nombre_evento'  => $registro->nombre_evento,
            'puntaje_total'  => $registro->puntaje_total,
            'criterios'      => $criterios,
            'notas_rechazo'  => $registro->notas_rechazo,
        ]);
    }
}
