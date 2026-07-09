<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Solicitud no aprobada</title></head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

    <!-- Encabezado guinda -->
    <tr><td style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:24px;font-weight:800;">Encuentro de Negocios Impulsate</h1>
        <p style="color:#fbc4cd;margin:8px 0 0;font-size:14px;">Instituto Yucateco de Emprendedores</p>
    </td></tr>

    <!-- Cuerpo -->
    <tr><td style="padding:32px 40px;">

        <div style="font-size:48px;text-align:center;margin-bottom:16px;">⚠️</div>

        <h2 style="color:#111827;font-size:20px;font-weight:800;margin:0 0 8px;text-align:center;">
            Solicitud no aprobada
        </h2>

        <p style="color:#374151;font-size:15px;margin:0 0 20px;">
            Hola, <strong>{{ $nombreUsuario }}</strong>.<br><br>
            Tu solicitud para participar
            @if($tipo === 'proveedor') como <strong>proveedor</strong>
            @else como <strong>comprador</strong>
            @endif
            en el evento <strong style="color:#8b1028;">{{ $evento->nombre }}</strong>
            no fue aprobada en esta ocasión.
        </p>

        <!-- Motivo -->
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;margin-bottom:24px;">
            <tr><td style="padding:16px 20px;">
                <p style="margin:0 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:1px;
                           color:#9ca3af;font-weight:600;">Motivo del administrador</p>
                <p style="margin:0;font-size:14px;color:#374151;line-height:1.6;">{{ $motivo }}</p>
            </td></tr>
        </table>

        @if($tipo === 'proveedor')
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;margin-bottom:24px;">
            <tr><td style="padding:16px 20px;">
                <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#166534;">¿Qué puedo hacer?</p>
                <p style="margin:0;font-size:13px;color:#374151;line-height:1.6;">
                    Puedes actualizar tu perfil de proveedor con la información faltante o corregir
                    lo señalado en el motivo, y <strong>volver a postularte al evento</strong> desde tu panel.
                </p>
            </td></tr>
        </table>
        <div style="text-align:center;margin-bottom:24px;">
            <a href="{{ url('/') }}"
               style="display:inline-block;background:#8b1028;color:#fff;padding:14px 28px;
                      border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;">
                Ir a la plataforma
            </a>
        </div>
        @else
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;margin-bottom:24px;">
            <tr><td style="padding:16px 20px;">
                <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#166534;">¿Qué puedo hacer?</p>
                <p style="margin:0;font-size:13px;color:#374151;line-height:1.6;">
                    Puedes actualizar tu información de perfil y <strong>volver a postularte al evento</strong>
                    desde tu panel de usuario.
                </p>
            </td></tr>
        </table>
        <div style="text-align:center;margin-bottom:24px;">
            <a href="{{ url('/') }}"
               style="display:inline-block;background:#8b1028;color:#fff;padding:14px 28px;
                      border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;">
                Ir a la plataforma
            </a>
        </div>
        @endif

    </td></tr>

    <!-- Pie de página -->
    <tr><td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
        <p style="margin:0;font-size:12px;color:#9ca3af;">
            Encuentro de Negocios Impulsate · impulsate@iyemyucatan.com
        </p>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
