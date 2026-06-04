<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Reagendamiento de Cita</title></head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <tr><td style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:24px;font-weight:800;">Encuentro de Negocios Impulsate</h1>
        <p style="color:#fbc4cd;margin:8px 0 0;font-size:14px;">Instituto Yucateco de Emprendedores</p>
    </td></tr>
    <tr><td style="padding:0;text-align:center;">
        <div style="display:inline-block;background:#fffbeb;border:1px solid #fde68a;border-radius:999px;padding:8px 20px;margin:24px auto 0;font-size:13px;font-weight:700;color:#d97706;">
            📅 @if($destinatario==='cliente') Propuesta de reagendamiento @else Respuesta al reagendamiento @endif
        </div>
    </td></tr>
    <tr><td style="padding:32px 40px;">
        @if($destinatario === 'cliente')
            <p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{ $cita->cliente->name ?? 'Cliente' }}</strong></p>
            <p style="margin:0 0 24px;font-size:15px;color:#6b7280;">
                El proveedor <strong>{{ $cita->restaurantero->nombre_restaurante ?? '' }}</strong> ha propuesto una nueva fecha para tu cita.
                Por favor acepta o rechaza la propuesta.
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:24px;">
                <tr><td style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
                    <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Fecha original</p>
                    <p style="margin:0;font-size:14px;color:#6b7280;text-decoration:line-through;">
                        {{ \Carbon\Carbon::parse($cita->inicio)->locale('es')->isoFormat('dddd D [de] MMMM') }}
                        — {{ \Carbon\Carbon::parse($cita->inicio)->format('H:i') }} hrs
                    </p>
                </td></tr>
                <tr><td style="padding:20px 24px;">
                    <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Nueva propuesta</p>
                    <p style="margin:0;font-size:16px;font-weight:700;color:#8b1028;">
                        {{ \Carbon\Carbon::parse($cita->propuesta_inicio)->locale('es')->isoFormat('dddd D [de] MMMM, YYYY') }}
                    </p>
                    <p style="margin:4px 0 0;font-size:14px;color:#374151;">
                        {{ \Carbon\Carbon::parse($cita->propuesta_inicio)->format('H:i') }} – {{ \Carbon\Carbon::parse($cita->propuesta_fin)->format('H:i') }} hrs
                    </p>
                </td></tr>
            </table>

            @if($cita->token_confirmacion)
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                    <td width="48%" style="padding-right:8px;">
                        <a href="{{ url('/citas/'.$cita->id.'/confirmar/'.$cita->token_confirmacion) }}"
                           style="display:block;text-align:center;background:#15803d;color:#fff;padding:14px 20px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;">
                            ✅ Aceptar nueva fecha
                        </a>
                    </td>
                    <td width="4%"></td>
                    <td width="48%" style="padding-left:8px;">
                        <a href="{{ url('/citas/'.$cita->id.'/rechazar/'.$cita->token_confirmacion) }}"
                           style="display:block;text-align:center;background:#dc2626;color:#fff;padding:14px 20px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;">
                            ❌ Rechazar propuesta
                        </a>
                    </td>
                </tr>
            </table>
            @endif
        @else
            {{-- destinatario = proveedor: respuesta del comprador al reagendamiento --}}
            <p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{ $cita->restaurantero->nombre_restaurante ?? 'Proveedor' }}</strong></p>
            @if($cita->estado === 'confirmada')
                <p style="margin:0 0 24px;font-size:15px;color:#6b7280;">
                    El comprador <strong>{{ $cita->cliente->name ?? '' }}</strong> ha <strong style="color:#15803d;">aceptado</strong> tu propuesta de reagendamiento.
                </p>
            @else
                <p style="margin:0 0 24px;font-size:15px;color:#6b7280;">
                    El comprador <strong>{{ $cita->cliente->name ?? '' }}</strong> ha <strong style="color:#dc2626;">rechazado</strong> tu propuesta de reagendamiento.
                    La cita quedó cancelada.
                </p>
            @endif
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:24px;">
                <tr><td style="padding:20px 24px;">
                    <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">Fecha propuesta</p>
                    <p style="margin:0;font-size:15px;color:#374151;">
                        {{ \Carbon\Carbon::parse($cita->propuesta_inicio)->locale('es')->isoFormat('dddd D [de] MMMM, YYYY') }}
                        — {{ \Carbon\Carbon::parse($cita->propuesta_inicio)->format('H:i') }} hrs
                    </p>
                </td></tr>
            </table>
        @endif
    </td></tr>
    <tr><td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
        <p style="margin:0;font-size:12px;color:#9ca3af;">Encuentro de Negocios Impulsate · impulsate@iyemyucatan.com</p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
