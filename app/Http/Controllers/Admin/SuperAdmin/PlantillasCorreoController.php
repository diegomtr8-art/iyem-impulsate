<?php

namespace App\Http\Controllers\Admin\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\PlantillaCorreoMail;
use App\Models\PlantillaCorreo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
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

    public function create()
    {
        $htmlBase = <<<HTML
<div style="background:#f9fafb;padding:40px 20px;font-family:'Segoe UI',Arial,sans-serif;">
<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
  <div style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
    <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:800;">Encuentro de Negocios Impulsate</h1>
    <p style="color:#fbc4cd;margin:8px 0 0;font-size:14px;">Instituto Yucateco de Emprendedores</p>
  </div>
  <div style="padding:32px 40px;">
    <p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
    <!-- ESCRIBE TU CONTENIDO AQUÍ -->
    <p style="margin:0;font-size:15px;color:#374151;">Mensaje del correo.</p>
  </div>
  <div style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#9ca3af;">Encuentro de Negocios Impulsate · Instituto Yucateco de Emprendedores</p>
  </div>
</div>
</div>
HTML;
        return Inertia::render('Admin/SuperAdmin/Plantillas/Create', [
            'htmlBase' => $htmlBase,
            'placeholders' => [
                '{{nombre_usuario}}' => 'Nombre del usuario destinatario',
                '{{nombre_proveedor}}' => 'Nombre del proveedor / empresa',
                '{{nombre_evento}}' => 'Nombre del evento',
                '{{fecha_cita}}' => 'Fecha de la cita',
                '{{hora_cita}}' => 'Hora de la cita',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'            => ['required', 'string', 'max:255'],
            'clave'             => ['required', 'string', 'max:100', 'unique:plantillas_correo,clave', 'regex:/^[a-z0-9_]+$/'],
            'asunto'            => ['required', 'string', 'max:255'],
            'contenido'         => ['required', 'string'],
            'tipo_destinatario' => ['required', Rule::in(['comprador', 'proveedor', 'ambos'])],
        ]);

        $data['es_sistema'] = false;
        $data['activo'] = true;

        PlantillaCorreo::create($data);

        return redirect()->route('admin.plantillas.index')->with('success', 'Plantilla creada correctamente.');
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

    public function destroy(PlantillaCorreo $plantilla)
    {
        if ($plantilla->es_sistema) {
            return back()->withErrors(['error' => 'No se pueden eliminar plantillas del sistema.']);
        }
        $plantilla->delete();
        return redirect()->route('admin.plantillas.index')->with('success', 'Plantilla eliminada.');
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
            'evento_solicitud_aprobada' => [
                '{{nombre_evento}}' => 'Nombre del evento al que fue aprobado',
            ],
            'evento_solicitud_rechazada' => [
                '{{nombre_evento}}' => 'Nombre del evento',
                '{{motivo_rechazo}}' => 'Motivo del rechazo ingresado por el administrador',
            ],
            default => [],
        };

        return array_merge($comunes, $extra);
    }
}
