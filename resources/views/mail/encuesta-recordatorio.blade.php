<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio: Encuesta de satisfacción</title>
</head>
<body style="margin:0;padding:0;background-color:#f9fafb;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 20px;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

                <!-- Header -->
                <tr>
                    <td style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
                        <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:800;letter-spacing:-0.5px;">Encuentro de Negocios Impulsate</h1>
                        <p style="color:#fbc4cd;margin:8px 0 0;font-size:14px;">Instituto Yucateco de Emprendedores</p>
                    </td>
                </tr>

                <!-- Badge -->
                <tr>
                    <td style="padding:0;text-align:center;">
                        <div style="display:inline-block;background:#fef2f4;border:1px solid #f5a8b5;border-radius:999px;padding:8px 20px;margin:24px auto 0;font-size:13px;font-weight:700;color:#8b1028;">
                            ⏰ Recordatorio de encuesta
                        </div>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:32px 40px;">
                        <p style="margin:0 0 8px;font-size:16px;color:#374151;">
                            Hola, <strong>{{ $user?->name ?? 'Participante' }}</strong>
                        </p>
                        <p style="margin:0 0 16px;font-size:15px;color:#374151;">
                            Te recordamos que aún no has respondido la encuesta de satisfacción como <strong>{{ $tipo === 'proveedor' ? 'proveedor' : 'comprador' }}</strong>{!! $evento ? ' en el evento <strong>' . e($evento->nombre) . '</strong>' : '' !!}.
                        </p>
                        <p style="margin:0 0 24px;font-size:14px;color:#6b7280;">
                            Tu opinión es muy importante para nosotros. Solo te tomará unos minutos.
                        </p>

                        <!-- CTA -->
                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                            <tr>
                                <td align="center">
                                    <a href="{{ $enlaceEncuesta }}"
                                       style="display:inline-block;background:#8b1028;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 36px;border-radius:12px;letter-spacing:0.3px;">
                                        Contestar encuesta →
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;text-align:center;">
                            O copia y pega este enlace en tu navegador:
                        </p>
                        <p style="margin:0;font-size:12px;color:#8b1028;text-align:center;word-break:break-all;">
                            {{ $enlaceEncuesta }}
                        </p>

                        <div style="margin-top:24px;padding:16px 20px;background:#f9fafb;border-radius:10px;border:1px solid #e5e7eb;">
                            <p style="margin:0;font-size:13px;color:#6b7280;">
                                Este enlace es personal y exclusivo para ti. Solo podrás responder la encuesta una vez.
                            </p>
                        </div>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#9ca3af;">
                            Encuentro de Negocios Impulsate · Instituto Yucateco de Emprendedores
                        </p>
                        <p style="margin:4px 0 0;font-size:11px;color:#d1d5db;">
                            Este es un correo automático, por favor no responder directamente.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
