<?php

namespace App\Http\Controllers\Admin\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\PlantillaCorreoMail;
use App\Models\PlantillaCorreo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class PlantillasCorreoController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/SuperAdmin/Plantillas/Index', [
            'plantillas' => PlantillaCorreo::orderBy('nombre')->get()->map(fn ($p) => [
                'id'               => $p->id,
                'clave'            => $p->clave,
                'nombre'           => $p->nombre,
                'asunto'           => $p->asunto,
                'tipo_destinatario'=> $p->tipo_destinatario,
                'es_sistema'       => $p->es_sistema,
                'activo'           => $p->activo,
            ]),
        ]);
    }

    public function edit(PlantillaCorreo $plantilla)
    {
        return Inertia::render('Admin/SuperAdmin/Plantillas/Edit', [
            'plantilla' => $plantilla,
            'placeholders' => $this->placeholdersParaClave($plantilla->clave),
        ]);
    }

    public function update(Request $request, PlantillaCorreo $plantilla)
    {
        $data = $request->validate([
            'asunto'   => ['required', 'string', 'max:255'],
            'contenido'=> ['required', 'string'],
        ]);

        $plantilla->update($data);

        return back()->with('success', 'Plantilla actualizada correctamente.');
    }

    public function toggle(PlantillaCorreo $plantilla)
    {
        $plantilla->update(['activo' => !$plantilla->activo]);
        $estado = $plantilla->activo ? 'activada' : 'desactivada';
        return back()->with('success', "Plantilla {$estado}.");
    }

    public function restablecer(PlantillaCorreo $plantilla)
    {
        // Solo restaura asunto/contenido al valor del seeder (no aplica para plantillas de sistema personalizadas)
        // Se llama al seeder de esa clave en modo "solo actualizar"
        \Artisan::call('db:seed', ['--class' => 'PlantillasCorreoSeeder', '--force' => true]);
        return back()->with('success', 'Todas las plantillas restablecidas a sus valores por defecto.');
    }

    public function enviar(Request $request, PlantillaCorreo $plantilla)
    {
        $data = $request->validate([
            'destinatarios' => ['required', 'array', 'min:1'],
            'destinatarios.*' => ['email'],
        ]);

        $enviados = 0;
        foreach ($data['destinatarios'] as $email) {
            Mail::to($email)->queue(new PlantillaCorreoMail($plantilla, []));
            $enviados++;
        }

        return back()->with('success', "Correo enviado a {$enviados} destinatario(s). (Procesando en cola)");
    }

    private function placeholdersParaClave(string $clave): array
    {
        $comunes = [
            '{{nombre_usuario}}' => 'Nombre del usuario destinatario',
            '{{nombre_proveedor}}' => 'Nombre del proveedor / empresa',
            '{{fecha_cita}}' => 'Fecha de la cita (ej: 15 de junio de 2026)',
            '{{hora_cita}}' => 'Hora de la cita (ej: 10:30)',
        ];

        $extra = match($clave) {
            'proveedor_aprobado', 'proveedor_rechazado' => [
                '{{nombre_empresa}}' => 'Nombre de la empresa del proveedor',
            ],
            'encuesta_satisfaccion' => [
                '{{nombre_evento}}' => 'Nombre del evento',
                '{{enlace_encuesta}}' => 'URL de la encuesta',
            ],
            default => [],
        };

        return array_merge($comunes, $extra);
    }
}
