<?php

namespace Database\Seeders;

use App\Models\PlantillaCorreo;
use Illuminate\Database\Seeder;

class PlantillasBazarSeeder extends Seeder
{
    public function run(): void
    {
        $plantillas = [
            [
                'clave'             => 'bazar_solicitud_recibida',
                'nombre'            => 'Bazar — Solicitud recibida',
                'asunto'            => 'Recibimos tu solicitud para {{nombre_evento}}',
                'tipo_destinatario' => 'proveedor',
                'es_sistema'        => true,
                'contenido'         => $this->tplSolicitudRecibida(),
            ],
            [
                'clave'             => 'bazar_seleccionado',
                'nombre'            => 'Bazar — Participación aprobada',
                'asunto'            => '¡Felicidades! Fuiste seleccionado para {{nombre_evento}}',
                'tipo_destinatario' => 'proveedor',
                'es_sistema'        => true,
                'contenido'         => $this->tplSeleccionado(),
            ],
            [
                'clave'             => 'bazar_rechazado',
                'nombre'            => 'Bazar — Participación no seleccionada',
                'asunto'            => 'Resultado de tu solicitud para {{nombre_evento}}',
                'tipo_destinatario' => 'proveedor',
                'es_sistema'        => true,
                'contenido'         => $this->tplRechazado(),
            ],
        ];

        foreach ($plantillas as $data) {
            // firstOrCreate (no updateOrCreate): las plantillas son editables desde la UI de
            // admin, así que re-correr este seeder no debe pisar el contenido que un admin
            // ya haya personalizado. Solo crea las que falten.
            PlantillaCorreo::firstOrCreate(
                ['clave' => $data['clave']],
                array_merge($data, ['activo' => true])
            );
        }

        $this->command->info('  ✅ ' . count($plantillas) . ' plantillas de bazar registradas.');
    }

    private function wrap(string $badge, string $cuerpo): string
    {
        return <<<HTML
<div style="background:#f9fafb;padding:40px 20px;font-family:'Segoe UI',Arial,sans-serif;">
<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
  <div style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
    <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:800;">Bazar / Exposición Impulsate</h1>
    <p style="color:#fbc4cd;margin:8px 0 0;font-size:14px;">Instituto Yucateco de Emprendedores</p>
  </div>
  <div style="padding:0;text-align:center;">
    <div style="display:inline-block;background:#fef2f4;border:1px solid #f5a8b5;border-radius:999px;padding:8px 20px;margin:24px auto 0;font-size:13px;font-weight:700;color:#8b1028;">
      {$badge}
    </div>
  </div>
  <div style="padding:32px 40px;">
    {$cuerpo}
    <p style="margin:24px 0 0;font-size:14px;color:#9ca3af;">Si tienes alguna duda, responde a este correo o visita el sistema en línea.</p>
  </div>
  <div style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#9ca3af;">Bazar / Exposición Impulsate · Instituto Yucateco de Emprendedores</p>
    <p style="margin:4px 0 0;font-size:11px;color:#d1d5db;">Este es un correo automático, por favor no responder directamente.</p>
  </div>
</div>
</div>
HTML;
    }

    private function tplSolicitudRecibida(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Hemos recibido tu solicitud de participación para el evento <strong>{{nombre_evento}}</strong>.</p>
<p style="margin:0 0 16px;font-size:14px;color:#6b7280;">Tu registro fue recibido correctamente. Recuerda que esto <strong>no garantiza un espacio</strong> en el evento, ya que todas las solicitudes serán evaluadas por nuestro equipo y se seleccionarán los participantes con base en los criterios establecidos.</p>
<p style="margin:0;font-size:14px;color:#6b7280;">Te notificaremos el resultado de tu evaluación a la brevedad.</p>
HTML;
        return $this->wrap('📋 Solicitud recibida', $cuerpo);
    }

    private function tplSeleccionado(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">¡Tenemos excelentes noticias! Después de evaluar todas las solicitudes recibidas, te informamos que <strong style="color:#16a34a;">has sido seleccionado(a)</strong> para participar como expositor en <strong>{{nombre_evento}}</strong>.</p>
<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:20px 24px;margin-bottom:24px;">
  <p style="margin:0;font-size:14px;color:#374151;">En los próximos días recibirás información sobre los siguientes pasos, logística del evento y detalles de tu espacio asignado.</p>
</div>
<p style="margin:0;font-size:15px;color:#374151;">¡Bienvenido(a) a Impulsate!</p>
HTML;
        return $this->wrap('🎉 ¡Fuiste seleccionado!', $cuerpo);
    }

    private function tplRechazado(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Agradecemos tu interés en participar en <strong>{{nombre_evento}}</strong> y el tiempo que dedicaste a completar tu solicitud.</p>
<p style="margin:0 0 16px;font-size:14px;color:#6b7280;">Después de revisar y evaluar todas las solicitudes recibidas, en esta ocasión <strong>tu solicitud no fue seleccionada</strong> para ocupar un espacio en el evento. La demanda fue alta y tuvimos que tomar decisiones difíciles.</p>
<p style="margin:0 0 20px;font-size:14px;color:#6b7280;">Puedes consultar el detalle de tu evaluación y los criterios con los que fuiste calificado(a) haciendo clic en el siguiente botón:</p>
<div style="text-align:center;margin-bottom:24px;">
  <a href="{{url_evaluacion}}" style="display:inline-block;background:linear-gradient(135deg,#8b1028,#45060f);color:#ffffff;font-weight:700;font-size:15px;padding:14px 32px;border-radius:12px;text-decoration:none;">
    Ver mi evaluación
  </a>
</div>
<p style="margin:0;font-size:14px;color:#6b7280;">Te invitamos a seguir mejorando y a participar en futuros eventos de la plataforma Impulsate.</p>
HTML;
        return $this->wrap('📋 Resultado de tu solicitud', $cuerpo);
    }
}
