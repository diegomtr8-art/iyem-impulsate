<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactoController extends Controller
{
    public function enviar(Request $request)
    {
        $data = $request->validate([
            'nombre'  => 'required|string|max:100',
            'correo'  => 'required|email|max:150',
            'asunto'  => 'required|string|max:200',
            'mensaje' => 'required|string|max:2000',
        ]);

        $cuerpo = "Nombre: {$data['nombre']}\nCorreo: {$data['correo']}\nAsunto: {$data['asunto']}\n\nMensaje:\n{$data['mensaje']}";

        Mail::raw($cuerpo, function ($m) use ($data) {
            $m->to('impulsate@iyemyucatan.com')
              ->subject("Contacto Impúlsate: {$data['asunto']}")
              ->replyTo($data['correo'], $data['nombre']);
        });

        return response()->json(['ok' => true]);
    }
}
