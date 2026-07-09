<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Perfil Aprobado</title></head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <tr><td style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:24px;font-weight:800;">Encuentro de Negocios Impulsate</h1>
        <p style="color:#fbc4cd;margin:8px 0 0;font-size:14px;">Instituto Yucateco de Emprendedores</p>
    </td></tr>
    <tr><td style="padding:32px 40px;text-align:center;">
        <div style="font-size:56px;margin-bottom:16px;">🎉</div>
        <h2 style="color:#111827;font-size:22px;font-weight:800;margin:0 0 12px;">¡Perfil aprobado!</h2>
        <p style="color:#6b7280;font-size:15px;margin:0 0 24px;">
            Hola, <strong>{{ $restaurantero->nombre_restaurante }}</strong>.<br>
            Tu perfil de proveedor ha sido <strong style="color:#15803d;">aprobado</strong> en la plataforma. Completa la información de tu negocio y podrás registrarte a los eventos disponibles.
        </p>
        <a href="{{ url('/') }}"
           style="display:inline-block;background:#8b1028;color:#fff;padding:14px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;">
            Ir a la plataforma
        </a>
    </td></tr>
    <tr><td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
        <p style="margin:0;font-size:12px;color:#9ca3af;">Encuentro de Negocios Impulsate · impulsate@iyemyucatan.com</p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
