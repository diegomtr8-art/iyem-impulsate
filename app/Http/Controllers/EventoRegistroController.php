<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\Request;

class EventoRegistroController extends Controller
{
    public function registrarComprador(Request $request, Evento $evento)
    {
        $user = $request->user();

        if (!$user->hasRole('cliente')) {
            return back()->withErrors(['error' => 'Necesitas el rol de comprador para registrarte.']);
        }

        if ($evento->fecha_hora_inicio_compradores && now()->lt($evento->fecha_hora_inicio_compradores)) {
            $apertura = $evento->fecha_hora_inicio_compradores->format('d/m/Y H:i');
            return back()->withErrors(['error' => "El agendado aún no está disponible. Apertura: {$apertura}."]);
        }

        if ($evento->fecha_hora_fin && now()->gt($evento->fecha_hora_fin)) {
            return back()->withErrors(['error' => 'Este evento ya ha finalizado.']);
        }

        // Evitar duplicado
        if ($evento->compradores()->where('user_id', $user->id)->exists()) {
            return back()->with('success', 'Ya estás registrado en este evento como comprador.');
        }

        $evento->compradores()->attach($user->id, ['tipo' => 'comprador']);

        return back()->with('success', '¡Te has registrado en el evento como comprador!');
    }

    public function registrarProveedor(Request $request, Evento $evento)
    {
        $user = $request->user();

        if (!$user->hasRole('restaurantero')) {
            return back()->withErrors(['error' => 'Necesitas el rol de proveedor para registrarte.']);
        }

        if ($evento->fecha_hora_inicio_proveedores && now()->lt($evento->fecha_hora_inicio_proveedores)) {
            $apertura = $evento->fecha_hora_inicio_proveedores->format('d/m/Y H:i');
            return back()->withErrors(['error' => "El registro de proveedores aún no está disponible. Apertura: {$apertura}."]);
        }

        if ($evento->fecha_hora_inicio_compradores && now()->gte($evento->fecha_hora_inicio_compradores)) {
            return back()->withErrors(['error' => 'El período de registro de proveedores ya cerró.']);
        }

        // Evitar duplicado
        if ($evento->proveedores()->where('user_id', $user->id)->exists()) {
            return back()->with('success', 'Ya estás registrado en este evento como proveedor.');
        }

        $evento->proveedores()->attach($user->id, ['tipo' => 'proveedor']);

        return back()->with('success', '¡Te has registrado en el evento como proveedor!');
    }
}
