<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La plantilla 'agenda_propuesta' se creó para el flujo anterior (dirigida al
     * proveedor, con {{nombre_proveedor}}). Ahora la propuesta se envía al comprador.
     * Solo actualiza el contenido si sigue siendo el default de fábrica (no toca
     * plantillas que un admin ya haya personalizado desde la UI).
     */
    public function up(): void
    {
        DB::table('plantillas_correo')
            ->where('clave', 'agenda_propuesta')
            ->where('contenido', 'like', '%nombre_proveedor%')
            ->update([
                'nombre'            => 'Propuesta de agenda al comprador',
                'tipo_destinatario' => 'comprador',
                'contenido'         => $this->contenido(),
                'updated_at'        => now(),
            ]);
    }

    public function down(): void
    {
        // No revierte contenido: el flujo anterior (proveedor) ya no existe en el código.
    }

    private function contenido(): string
    {
        return <<<'HTML'
<div style="background:#f9fafb;padding:40px 20px;font-family:'Segoe UI',Arial,sans-serif;">
<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
  <div style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
    <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:800;">Encuentro de Negocios Impulsate</h1>
    <p style="color:#fbc4cd;margin:8px 0 0;font-size:14px;">Instituto Yucateco de Emprendedores</p>
  </div>
  <div style="padding:0;text-align:center;">
    <div style="display:inline-block;background:#fef2f4;border:1px solid #f5a8b5;border-radius:999px;padding:8px 20px;margin:24px auto 0;font-size:13px;font-weight:700;color:#8b1028;">
      📅 Nueva propuesta de agenda
    </div>
  </div>
  <div style="padding:32px 40px;">
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
};
