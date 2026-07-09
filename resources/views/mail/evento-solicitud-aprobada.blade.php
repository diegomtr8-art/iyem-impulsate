<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>¡Solicitud aprobada!</title></head>
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
    <tr><td style="padding:32px 40px;text-align:center;">

        <div style="font-size:56px;margin-bottom:16px;">🎉</div>

        <h2 style="color:#111827;font-size:22px;font-weight:800;margin:0 0 8px;">
            ¡Fuiste aprobado!
        </h2>

        <p style="color:#374151;font-size:15px;margin:0 0 8px;">
            Hola, <strong>{{ $nombreUsuario }}</strong>.
        </p>

        <p style="color:#374151;font-size:15px;margin:0 0 24px;">
            Tu solicitud
            @if($tipo === 'proveedor') como <strong>proveedor</strong>
            @else como <strong>comprador</strong>
            @endif
            para el evento <strong style="color:#8b1028;">{{ $evento->nombre }}</strong>
            fue <strong style="color:#15803d;">aprobada</strong>. ✓
        </p>

        @if($tipo === 'proveedor')
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;
                      margin-bottom:28px;text-align:left;">
            <tr><td style="padding:16px 20px;">
                <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#166534;">¿Qué sigue?</p>
                <p style="margin:0;font-size:13px;color:#374151;line-height:1.6;">
                    Tu perfil ya aparece en el directorio de proveedores del evento.
                    Los compradores pueden agendar citas contigo. Asegúrate de tener tus
                    horarios actualizados en tu panel.
                </p>
            </td></tr>
        </table>
        <a href="{{ url('/') }}"
           style="display:inline-block;background:#8b1028;color:#fff;padding:14px 32px;
                  border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;">
            Ir a la plataforma
        </a>
        @else
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;
                      margin-bottom:28px;text-align:left;">
            <tr><td style="padding:16px 20px;">
                <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#166534;">¿Qué sigue?</p>
                <p style="margin:0;font-size:13px;color:#374151;line-height:1.6;">
                    Ya puedes ver el directorio de proveedores y agendar tus citas
                    de networking con los proveedores que te interesen.
                </p>
            </td></tr>
        </table>
        <a href="{{ url('/') }}"
           style="display:inline-block;background:#8b1028;color:#fff;padding:14px 32px;
                  border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;">
            Ir a la plataforma
        </a>
        @endif

    </td></tr>

    <!-- Pie de página -->
    <tr><td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
        <p style="margin:0 0 4px;font-size:12px;color:#9ca3af;">
            Encuentro de Negocios Impulsate · impulsate@iyemyucatan.com
        </p>
        <p style="margin:0;font-size:11px;color:#d1d5db;">
            Instituto Yucateco de Emprendedores · Mérida, Yucatán
        </p>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
