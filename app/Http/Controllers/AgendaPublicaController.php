<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\AgendaPropuesta;
use App\Services\AgendaPropuestaService;
use Inertia\Inertia;

class AgendaPublicaController extends Controller
{
    public function ver(string $token)
    {
        $propuesta = AgendaPropuesta::where('token', $token)
            ->with([
                'comprador:id,name,nombre_empresa',
                'citas.proveedor:id,nombre_restaurante,logo_path,descripcion',
                'evento:id,nombre,fecha_hora_inicio',
            ])
            ->firstOrFail();

        return Inertia::render('Agenda/Responder', [
            'propuesta' => $propuesta,
            'token'     => $token,
        ]);
    }

    /**
     * Los correos ya enviados apuntan a GET /agenda/aceptar|rechazar.
     * Ese GET ya no ejecuta nada: manda al usuario a la pagina de la propuesta,
     * donde confirma con un boton (POST). Evita que los escaneres de correo
     * acepten agendas solos.
     */
    public function verDesdeEnlace(string $token)
    {
        return redirect()->route('agenda.ver', $token);
    }

    public function aceptar(string $token, AgendaPropuestaService $agendas)
    {
        $propuesta = AgendaPropuesta::where('token', $token)
            ->with(['citas.proveedor', 'comprador', 'evento'])
            ->firstOrFail();

        try {
            // Mismo camino que la app (POST /api/v1/agendas/{id}/aceptar).
            $aceptada = $agendas->aceptar($propuesta);
        } catch (ReglaDeNegocioException $e) {
            abort(422, $e->getMessage());
        }

        if (!$aceptada) {
            return Inertia::render('Agenda/Gracias', [
                'tipo'         => $propuesta->fresh()->estado,
                'propuesta'    => $propuesta->fresh(['citas.proveedor', 'comprador', 'evento']),
                'yaRespondida' => true,
            ]);
        }

        return Inertia::render('Agenda/Gracias', [
            'tipo'         => 'aceptada',
            'propuesta'    => $propuesta->load('citas.proveedor', 'evento'),
            'yaRespondida' => false,
        ]);
    }

    public function rechazar(string $token, AgendaPropuestaService $agendas)
    {
        $propuesta = AgendaPropuesta::where('token', $token)
            ->with(['comprador', 'evento'])
            ->firstOrFail();

        // Mismo camino que la app (POST /api/v1/agendas/{id}/rechazar).
        if (!$agendas->rechazar($propuesta)) {
            return Inertia::render('Agenda/Gracias', [
                'tipo'         => $propuesta->fresh()->estado,
                'propuesta'    => $propuesta->fresh(['comprador', 'evento']),
                'yaRespondida' => true,
            ]);
        }

        return Inertia::render('Agenda/Gracias', [
            'tipo'         => 'rechazada',
            'propuesta'    => $propuesta,
            'yaRespondida' => false,
        ]);
    }
}
