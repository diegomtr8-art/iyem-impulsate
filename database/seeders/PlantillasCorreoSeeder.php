<?php

namespace Database\Seeders;

use App\Models\PlantillaCorreo;
use Illuminate\Database\Seeder;

class PlantillasCorreoSeeder extends Seeder
{
    public function run(): void
    {
        $plantillas = [
            [
                'clave'              => 'cita_agendada',
                'nombre'             => 'Cita Agendada',
                'asunto'             => 'Solicitud de cita registrada — pendiente de confirmación',
                'tipo_destinatario'  => 'ambos',
                'es_sistema'         => true,
                'contenido'          => $this->tplCitaAgendada(),
            ],
            [
                'clave'              => 'cita_aceptada',
                'nombre'             => 'Cita Aceptada',
                'asunto'             => '✅ Tu cita fue aceptada — Impulsate',
                'tipo_destinatario'  => 'comprador',
                'es_sistema'         => true,
                'contenido'          => $this->tplCitaAceptada(),
            ],
            [
                'clave'              => 'cita_rechazada',
                'nombre'             => 'Cita Rechazada',
                'asunto'             => 'Tu solicitud de cita no fue aceptada — Impulsate',
                'tipo_destinatario'  => 'comprador',
                'es_sistema'         => true,
                'contenido'          => $this->tplCitaRechazada(),
            ],
            [
                'clave'              => 'cita_confirmada',
                'nombre'             => 'Cita Confirmada',
                'asunto'             => '🎉 ¡Tu cita está confirmada! — Impulsate',
                'tipo_destinatario'  => 'comprador',
                'es_sistema'         => true,
                'contenido'          => $this->tplCitaConfirmada(),
            ],
            [
                'clave'              => 'cita_cancelada',
                'nombre'             => 'Cita Cancelada',
                'asunto'             => 'Cita cancelada — Impulsate',
                'tipo_destinatario'  => 'ambos',
                'es_sistema'         => true,
                'contenido'          => $this->tplCitaCancelada(),
            ],
            [
                'clave'              => 'cita_reagendada',
                'nombre'             => 'Cita Reagendada',
                'asunto'             => '📅 Tu cita fue reagendada — Impulsate',
                'tipo_destinatario'  => 'comprador',
                'es_sistema'         => true,
                'contenido'          => $this->tplCitaReagendada(),
            ],
            [
                'clave'              => 'proveedor_aprobado',
                'nombre'             => 'Proveedor Aprobado',
                'asunto'             => '🎉 ¡Tu perfil de proveedor fue aprobado! — Impulsate',
                'tipo_destinatario'  => 'proveedor',
                'es_sistema'         => true,
                'contenido'          => $this->tplProveedorAprobado(),
            ],
            [
                'clave'              => 'proveedor_rechazado',
                'nombre'             => 'Proveedor Rechazado',
                'asunto'             => 'Actualización sobre tu solicitud de proveedor — Impulsate',
                'tipo_destinatario'  => 'proveedor',
                'es_sistema'         => true,
                'contenido'          => $this->tplProveedorRechazado(),
            ],
            [
                'clave'              => 'recordatorio_cita',
                'nombre'             => 'Recordatorio de Cita',
                'asunto'             => '⏰ Recordatorio: tienes una cita mañana — Impulsate',
                'tipo_destinatario'  => 'ambos',
                'es_sistema'         => true,
                'contenido'          => $this->tplRecordatorioCita(),
            ],
            [
                'clave'              => 'encuesta_satisfaccion',
                'nombre'             => 'Encuesta de Satisfacción',
                'asunto'             => '📋 ¿Cómo fue tu experiencia en el evento? — Impulsate',
                'tipo_destinatario'  => 'ambos',
                'es_sistema'         => true,
                'contenido'          => $this->tplEncuestaSatisfaccion(),
            ],
            [
                'clave'              => 'evento_solicitud_aprobada',
                'nombre'             => 'Solicitud al Evento Aprobada',
                'asunto'             => '🎉 ¡Fuiste aprobado al evento {{nombre_evento}}! — Impulsate',
                'tipo_destinatario'  => 'ambos',
                'es_sistema'         => true,
                'contenido'          => $this->tplEventoSolicitudAprobada(),
            ],
            [
                'clave'              => 'evento_solicitud_rechazada',
                'nombre'             => 'Solicitud al Evento Rechazada',
                'asunto'             => 'Resultado de tu solicitud — {{nombre_evento}} · Impulsate',
                'tipo_destinatario'  => 'ambos',
                'es_sistema'         => true,
                'contenido'          => $this->tplEventoSolicitudRechazada(),
            ],
            [
                'clave'              => 'agenda_propuesta',
                'nombre'             => 'Propuesta de agenda al comprador',
                'asunto'             => 'IMPULSATE: Tu propuesta de agenda — {{nombre_evento}}',
                'tipo_destinatario'  => 'comprador',
                'es_sistema'         => true,
                'contenido'          => $this->tplAgendaPropuesta(),
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

        $this->command->info('  ✅ ' . count($plantillas) . ' plantillas de correo registradas.');
    }

    private function wrap(string $titulo, string $badge, string $cuerpo): string
    {
        return <<<HTML
<div style="background:#f9fafb;padding:40px 20px;font-family:'Segoe UI',Arial,sans-serif;">
<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
  <div style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
    <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:800;">Encuentro de Negocios Impulsate</h1>
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
    <p style="margin:0;font-size:12px;color:#9ca3af;">Encuentro de Negocios Impulsate · Instituto Yucateco de Emprendedores</p>
    <p style="margin:4px 0 0;font-size:11px;color:#d1d5db;">Este es un correo automático, por favor no responder directamente.</p>
  </div>
</div>
</div>
HTML;
    }

    private function tplCitaAgendada(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Tu solicitud de cita ha sido registrada exitosamente. La cita quedará confirmada una vez que el proveedor la acepte.</p>
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:20px 24px;margin-bottom:24px;">
  <p style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Proveedor</p>
  <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">{{nombre_proveedor}}</p>
  <p style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Fecha y hora</p>
  <p style="margin:0;font-size:16px;font-weight:700;color:#8b1028;">{{fecha_cita}} a las {{hora_cita}} hrs</p>
</div>
HTML;
        return $this->wrap('Cita Agendada', '📋 Solicitud registrada — pendiente de confirmación', $cuerpo);
    }

    private function tplCitaAceptada(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">¡Buenas noticias! El proveedor <strong>{{nombre_proveedor}}</strong> ha aceptado tu solicitud de cita.</p>
<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:20px 24px;margin-bottom:24px;">
  <p style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Fecha y hora confirmada</p>
  <p style="margin:0;font-size:16px;font-weight:700;color:#16a34a;">{{fecha_cita}} a las {{hora_cita}} hrs</p>
</div>
HTML;
        return $this->wrap('Cita Aceptada', '✅ Tu cita fue aceptada', $cuerpo);
    }

    private function tplCitaRechazada(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Lamentablemente, el proveedor <strong>{{nombre_proveedor}}</strong> no pudo aceptar tu solicitud de cita para el <strong>{{fecha_cita}}</strong>.</p>
<p style="margin:0 0 16px;font-size:14px;color:#6b7280;">Puedes intentar agendar otra cita con este u otro proveedor disponible en la plataforma.</p>
HTML;
        return $this->wrap('Cita Rechazada', '❌ Solicitud no aceptada', $cuerpo);
    }

    private function tplCitaConfirmada(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Tu cita con <strong>{{nombre_proveedor}}</strong> está oficialmente confirmada. ¡Te esperamos!</p>
<div style="background:#fff7f7;border:1px solid #fbc4cd;border-radius:12px;padding:16px 20px;margin-bottom:24px;">
  <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Lugar de la cita</p>
  <p style="margin:0;font-size:14px;color:#374151;font-weight:600;">Av. Industrias No Contaminantes Tab 13613, Col. Sodzil Norte, Mérida, Yucatán</p>
  <p style="margin:4px 0 0;font-size:13px;color:#6b7280;">{{fecha_cita}} a las {{hora_cita}} hrs</p>
</div>
HTML;
        return $this->wrap('Cita Confirmada', '🎉 ¡Tu cita está confirmada!', $cuerpo);
    }

    private function tplCitaCancelada(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Te informamos que la cita con <strong>{{nombre_proveedor}}</strong> programada para el <strong>{{fecha_cita}} a las {{hora_cita}} hrs</strong> ha sido cancelada.</p>
<p style="margin:0;font-size:14px;color:#6b7280;">Puedes agendar una nueva cita cuando lo desees desde la plataforma.</p>
HTML;
        return $this->wrap('Cita Cancelada', '🚫 Cita cancelada', $cuerpo);
    }

    private function tplCitaReagendada(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Tu cita con <strong>{{nombre_proveedor}}</strong> ha sido reagendada a una nueva fecha y hora.</p>
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:20px 24px;margin-bottom:24px;">
  <p style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Nueva fecha y hora</p>
  <p style="margin:0;font-size:16px;font-weight:700;color:#8b1028;">{{fecha_cita}} a las {{hora_cita}} hrs</p>
</div>
HTML;
        return $this->wrap('Cita Reagendada', '📅 Cita reagendada', $cuerpo);
    }

    private function tplProveedorAprobado(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">¡Felicidades! Tu perfil como proveedor de <strong>{{nombre_empresa}}</strong> ha sido revisado y aprobado en la plataforma.</p>
<p style="margin:0 0 16px;font-size:14px;color:#6b7280;">Completa la información de tu negocio y podrás registrarte a los eventos disponibles. ¡Te esperamos!</p>
HTML;
        return $this->wrap('Proveedor Aprobado', '🎉 ¡Tu perfil fue aprobado!', $cuerpo);
    }

    private function tplProveedorRechazado(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Hemos revisado tu solicitud como proveedor para <strong>{{nombre_empresa}}</strong> y en este momento no podemos aprobarla.</p>
<p style="margin:0;font-size:14px;color:#6b7280;">Si tienes dudas sobre este proceso, contáctanos respondiendo a este correo.</p>
HTML;
        return $this->wrap('Solicitud Revisada', '📋 Actualización sobre tu solicitud', $cuerpo);
    }

    private function tplRecordatorioCita(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Te recordamos que mañana tienes una cita de networking programada.</p>
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:20px 24px;margin-bottom:24px;">
  <p style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Proveedor</p>
  <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">{{nombre_proveedor}}</p>
  <p style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Fecha y hora</p>
  <p style="margin:0;font-size:16px;font-weight:700;color:#8b1028;">{{fecha_cita}} a las {{hora_cita}} hrs</p>
</div>
<div style="background:#fff7f7;border:1px solid #fbc4cd;border-radius:12px;padding:16px 20px;">
  <p style="margin:0;font-size:14px;color:#374151;font-weight:600;">Av. Industrias No Contaminantes Tab 13613, Col. Sodzil Norte, C.P. 97110, Mérida, Yucatán</p>
</div>
HTML;
        return $this->wrap('Recordatorio de Cita', '⏰ Tienes una cita mañana', $cuerpo);
    }

    private function tplEncuestaSatisfaccion(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Gracias por participar en <strong>{{nombre_evento}}</strong>. Tu opinión es muy importante para nosotros.</p>
<p style="margin:0 0 24px;font-size:14px;color:#6b7280;">Tómate 3 minutos para completar nuestra encuesta de satisfacción y ayúdanos a mejorar el próximo evento.</p>
<div style="text-align:center;margin-bottom:24px;">
  <a href="{{enlace_encuesta}}" style="display:inline-block;background:linear-gradient(135deg,#8b1028,#45060f);color:#ffffff;font-weight:700;font-size:15px;padding:14px 32px;border-radius:12px;text-decoration:none;">
    Responder encuesta
  </a>
</div>
HTML;
        return $this->wrap('Encuesta de Satisfacción', '📋 Cuéntanos tu experiencia', $cuerpo);
    }

    private function tplEventoSolicitudAprobada(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">¡Excelentes noticias! Tu solicitud para participar en <strong>{{nombre_evento}}</strong> ha sido <strong style="color:#16a34a;">aprobada</strong>.</p>
<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:20px 24px;margin-bottom:24px;">
  <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#16a34a;">¿Qué sigue?</p>
  <ul style="margin:0;padding-left:20px;font-size:14px;color:#374151;line-height:1.8;">
    <li>Revisa la información del evento en tu panel</li>
    <li>Prepara tu presentación y material de apoyo</li>
    <li>Confirma tu asistencia si aún no lo has hecho</li>
  </ul>
</div>
<div style="text-align:center;margin-bottom:24px;">
  <a href="https://impulsate.iyemyucatan.com" style="display:inline-block;background:linear-gradient(135deg,#8b1028,#45060f);color:#ffffff;font-weight:700;font-size:15px;padding:14px 32px;border-radius:12px;text-decoration:none;">
    Ver mi panel
  </a>
</div>
HTML;
        return $this->wrap('Solicitud Aprobada', '🎉 ¡Tu solicitud fue aprobada!', $cuerpo);
    }

    private function tplEventoSolicitudRechazada(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">Hemos revisado tu solicitud para participar en <strong>{{nombre_evento}}</strong> y lamentablemente no fue aprobada en esta ocasión.</p>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:12px;padding:20px 24px;margin-bottom:24px;">
  <p style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Motivo</p>
  <p style="margin:0;font-size:15px;color:#374151;">{{motivo_rechazo}}</p>
</div>
<p style="margin:0 0 24px;font-size:14px;color:#6b7280;">Si consideras que hay información adicional que pueda cambiar esta decisión, puedes actualizar tu perfil y volver a postularte desde tu panel.</p>
<div style="text-align:center;margin-bottom:24px;">
  <a href="https://impulsate.iyemyucatan.com" style="display:inline-block;background:linear-gradient(135deg,#8b1028,#45060f);color:#ffffff;font-weight:700;font-size:15px;padding:14px 32px;border-radius:12px;text-decoration:none;">
    Ver mi panel
  </a>
</div>
HTML;
        return $this->wrap('Solicitud Revisada', '⚠️ Resultado de tu solicitud', $cuerpo);
    }

    private function tplAgendaPropuesta(): string
    {
        $cuerpo = <<<HTML
<p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_comprador}}</strong></p>
<p style="margin:0 0 16px;font-size:15px;color:#374151;">El equipo de IMPULSATE preparó una propuesta de agenda de citas con proveedores para tu participación en <strong>{{nombre_evento}}</strong> el <strong>{{fecha_evento}}</strong>.</p>
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:20px 24px;margin-bottom:24px;">
  <p style="margin:0 0 12px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Tu agenda propuesta</p>
  <p style="margin:0;font-size:14px;color:#374151;line-height:1.9;">{{lista_citas}}</p>
</div>
<p style="margin:0 0 20px;font-size:15px;color:#374151;">¿Aceptas esta propuesta de agenda? Si aceptas, las citas se crearán automáticamente en el sistema.</p>
<div style="text-align:center;margin-bottom:16px;">
  <a href="{{url_aceptar}}" style="display:inline-block;background:linear-gradient(135deg,#16a34a,#15803d);color:#ffffff;font-weight:700;font-size:15px;padding:14px 32px;border-radius:12px;text-decoration:none;margin:0 8px 8px;">
    ✅ Aceptar agenda
  </a>
  <a href="{{url_rechazar}}" style="display:inline-block;background:#ffffff;border:2px solid #d1d5db;color:#6b7280;font-weight:700;font-size:15px;padding:12px 30px;border-radius:12px;text-decoration:none;margin:0 8px 8px;">
    ❌ No acepto
  </a>
</div>
<p style="margin:0;font-size:13px;color:#9ca3af;">Este enlace es de un solo uso. Si tienes dudas, escríbenos a impulsate@iyemyucatan.com</p>
HTML;
        return $this->wrap('Propuesta de Agenda', '📅 Nueva propuesta de agenda', $cuerpo);
    }
}
