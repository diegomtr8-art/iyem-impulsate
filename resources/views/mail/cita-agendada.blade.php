<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cita Agendada</title>
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
                        @if($destinatario === 'proveedor')
                        <div style="display:inline-block;background:#fef3c7;border:1px solid #fcd34d;border-radius:999px;padding:8px 20px;margin:24px auto 0;font-size:13px;font-weight:700;color:#92400e;">
                            ⏳ Nueva solicitud pendiente de tu confirmación
                        </div>
                        @else
                        <div style="display:inline-block;background:#eff6ff;border:1px solid #bfdbfe;border-radius:999px;padding:8px 20px;margin:24px auto 0;font-size:13px;font-weight:700;color:#1e40af;">
                            📋 Solicitud registrada — pendiente de confirmación
                        </div>
                        @endif
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:32px 40px;">

                        <p style="margin:0 0 8px;font-size:16px;color:#374151;">
                            @if($destinatario === 'proveedor')
                                Hola, <strong>{{ $cita->restaurantero->nombre_restaurante ?? 'Proveedor' }}</strong>
                            @else
                                Hola, <strong>{{ $cita->cliente->name ?? 'Cliente' }}</strong>
                            @endif
                        </p>

                        @if($destinatario === 'proveedor')
                            <p style="margin:0 0 16px;font-size:15px;color:#374151;">
                                Has recibido una nueva solicitud de cita de networking. Debes aceptar o rechazar esta solicitud para confirmarla.
                            </p>
                            <p style="margin:0 0 24px;font-size:14px;color:#6b7280;">
                                Ingresa a tu panel de proveedor para gestionar esta solicitud.
                            </p>
                        @else
                            <p style="margin:0 0 16px;font-size:15px;color:#374151;">
                                Tu solicitud de cita ha sido registrada exitosamente.
                            </p>
                            <p style="margin:0 0 24px;font-size:14px;color:#6b7280;">
                                La cita quedará confirmada una vez que el proveedor la acepte. Te notificaremos por correo en cuanto haya una respuesta.
                            </p>
                        @endif

                        <!-- Detalles card -->
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:24px;">
                            <tr>
                                <td style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                                    <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Proveedor</p>
                                    <p style="margin:0;font-size:16px;font-weight:700;color:#111827;">{{ $cita->restaurantero->nombre_restaurante ?? 'N/A' }}</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                                    <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Cliente</p>
                                    <p style="margin:0;font-size:16px;font-weight:700;color:#111827;">{{ $cita->cliente->name ?? 'N/A' }}</p>
                                    <p style="margin:4px 0 0;font-size:13px;color:#6b7280;">{{ $cita->cliente->email ?? '' }}</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                                    <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Fecha y hora</p>
                                    <p style="margin:0;font-size:16px;font-weight:700;color:#8b1028;">
                                        {{ \Carbon\Carbon::parse($cita->inicio)->locale('es')->isoFormat('dddd D [de] MMMM, YYYY') }}
                                    </p>
                                    <p style="margin:4px 0 0;font-size:14px;color:#374151;">
                                        {{ \Carbon\Carbon::parse($cita->inicio)->format('H:i') }} – {{ \Carbon\Carbon::parse($cita->fin)->format('H:i') }} hrs
                                    </p>
                                </td>
                            </tr>
                            @if($cita->notas)
                            <tr>
                                <td style="padding:20px 24px;">
                                    <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Notas</p>
                                    <p style="margin:0;font-size:14px;color:#374151;font-style:italic;">"{{ $cita->notas }}"</p>
                                </td>
                            </tr>
                            @endif
                        </table>

                        <!-- Ubicación -->
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#fff7f7;border:1px solid #fbc4cd;border-radius:12px;overflow:hidden;margin-bottom:24px;">
                            <tr>
                                <td style="padding:16px 20px;">
                                    <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Lugar de la cita</p>
                                    <p style="margin:0;font-size:14px;color:#374151;font-weight:600;">Av. Industrias No Contaminantes Tab 13613</p>
                                    <p style="margin:2px 0 0;font-size:13px;color:#6b7280;">Col. Sodzil Norte, C.P. 97110, Mérida, Yucatán</p>
                                    <p style="margin:6px 0 0;font-size:13px;color:#6b7280;">Horario de atención: Lunes a Viernes, 9:00 am – 4:00 pm</p>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0;font-size:14px;color:#9ca3af;">
                            Si tienes alguna duda, responde a este correo o visita el sistema en línea.
                        </p>
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
