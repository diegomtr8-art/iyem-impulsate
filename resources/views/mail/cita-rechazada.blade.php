<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Cita Rechazada</title></head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <tr><td style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:24px;font-weight:800;">Encuentro de Negocios Impulsate</h1>
        <p style="color:#fbc4cd;margin:8px 0 0;font-size:14px;">Instituto Yucateco de Emprendedores</p>
    </td></tr>
    <tr><td style="padding:0;text-align:center;">
        <div style="display:inline-block;background:#fef2f2;border:1px solid #fecaca;border-radius:999px;padding:8px 20px;margin:24px auto 0;font-size:13px;font-weight:700;color:#dc2626;">
            ❌ Cita rechazada
        </div>
    </td></tr>
    <tr><td style="padding:32px 40px;">
        @if($destinatario === 'cliente')
            <p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{ $cita->cliente->name ?? 'Cliente' }}</strong></p>
            <p style="margin:0 0 24px;font-size:15px;color:#6b7280;">
                Lamentablemente, el proveedor <strong>{{ $cita->restaurantero->nombre_restaurante ?? '' }}</strong> ha rechazado tu solicitud de cita.
                Te recomendamos buscar otro proveedor disponible.
            </p>
        @else
            <p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{ $cita->restaurantero->nombre_restaurante ?? 'Proveedor' }}</strong></p>
            <p style="margin:0 0 24px;font-size:15px;color:#6b7280;">
                Has rechazado la cita con <strong>{{ $cita->cliente->name ?? '' }}</strong>. El comprador ha sido notificado.
            </p>
        @endif

        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:24px;">
            <tr><td style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Cita con</p>
                <p style="margin:0;font-size:15px;font-weight:700;color:#111827;">
                    {{ $destinatario === 'cliente' ? ($cita->restaurantero->nombre_restaurante ?? 'N/A') : ($cita->cliente->name ?? 'N/A') }}
                </p>
            </td></tr>
            <tr><td style="padding:20px 24px;">
                <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Fecha original</p>
                <p style="margin:0;font-size:15px;color:#374151;">
                    {{ \Carbon\Carbon::parse($cita->inicio)->locale('es')->isoFormat('dddd D [de] MMMM, YYYY') }}
                    — {{ \Carbon\Carbon::parse($cita->inicio)->format('H:i') }} hrs
                </p>
            </td></tr>
        </table>
    </td></tr>
    <tr><td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
        <p style="margin:0;font-size:12px;color:#9ca3af;">Encuentro de Negocios Impulsate · impulsate@iyemyucatan.com</p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
