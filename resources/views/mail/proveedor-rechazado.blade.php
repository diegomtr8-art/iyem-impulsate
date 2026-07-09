<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Resultado de revisión</title></head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <tr><td style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:24px;font-weight:800;">Encuentro de Negocios Impulsate</h1>
    </td></tr>
    <tr><td style="padding:32px 40px;">
        <p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{ $restaurantero->nombre_restaurante }}</strong></p>
        <p style="margin:0 0 24px;font-size:15px;color:#6b7280;">
            Tu perfil de proveedor fue revisado. Lamentablemente, en esta ocasión no fue aprobado.
        </p>
        @if($motivo)
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;margin-bottom:24px;">
            <tr><td style="padding:16px 20px;">
                <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Motivo</p>
                <p style="margin:0;font-size:14px;color:#374151;">{{ $motivo }}</p>
            </td></tr>
        </table>
        @endif
        <p style="margin:0 0 24px;font-size:14px;color:#6b7280;">
            Puedes actualizar tu perfil y volver a solicitar la revisión.
        </p>
        <div style="text-align:center;">
            <a href="{{ url('/') }}"
               style="display:inline-block;background:#8b1028;color:#fff;padding:14px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;">
                Ir a la plataforma
            </a>
        </div>
    </td></tr>
    <tr><td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
        <p style="margin:0;font-size:12px;color:#9ca3af;">Encuentro de Negocios Impulsate · impulsate@iyemyucatan.com</p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
