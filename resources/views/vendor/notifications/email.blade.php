<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Notificación — Encuentro de Negocios Impulsate' }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f9fafb;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;padding:40px 20px;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);max-width:100%;">

                <!-- Header -->
                <tr>
                    <td style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
                        <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:800;letter-spacing:-0.5px;">
                            Encuentro de Negocios Impulsate
                        </h1>
                        <p style="color:#fbc4cd;margin:8px 0 0;font-size:14px;">
                            Instituto Yucateco de Emprendedores
                        </p>
                    </td>
                </tr>

                <!-- Icon -->
                <tr>
                    <td style="padding:32px 40px 0;text-align:center;">
                        <div style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;background:#fff7f7;border:2px solid #fbc4cd;border-radius:16px;margin-bottom:16px;">
                            <span style="font-size:32px;">🔒</span>
                        </div>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:8px 40px 32px;">
                        {{-- Greeting --}}
                        @if (! empty($greeting))
                            <h2 style="margin:0 0 12px;font-size:22px;font-weight:800;color:#111827;">
                                {{ $greeting }}
                            </h2>
                        @else
                            <h2 style="margin:0 0 12px;font-size:22px;font-weight:800;color:#111827;">
                                {{ $level === 'error' ? '¡Ups!' : 'Hola,' }}
                            </h2>
                        @endif

                        {{-- Intro Lines --}}
                        @foreach ($introLines as $line)
                            <p style="margin:0 0 16px;font-size:15px;color:#6b7280;line-height:1.6;">
                                {{ $line }}
                            </p>
                        @endforeach

                        {{-- Action Button --}}
                        @isset($actionText)
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $actionUrl }}"
                                           style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#8b1028,#710d21);color:#ffffff;text-decoration:none;border-radius:12px;font-size:15px;font-weight:700;letter-spacing:0.3px;box-shadow:0 4px 12px rgba(139,16,40,0.3);">
                                            {{ $actionText }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        @endisset

                        {{-- Outro Lines --}}
                        @foreach ($outroLines as $line)
                            <p style="margin:0 0 16px;font-size:14px;color:#9ca3af;line-height:1.6;">
                                {{ $line }}
                            </p>
                        @endforeach

                        {{-- Salutation --}}
                        @if (! empty($salutation))
                            <p style="margin:24px 0 0;font-size:14px;color:#374151;font-weight:600;">
                                {{ $salutation }}
                            </p>
                        @else
                            <p style="margin:24px 0 0;font-size:14px;color:#374151;">
                                Saludos,<br>
                                <strong style="color:#8b1028;">Encuentro de Negocios Impulsate</strong>
                            </p>
                        @endif
                    </td>
                </tr>

                {{-- URL fallback --}}
                @isset($actionText)
                    <tr>
                        <td style="padding:0 40px 24px;">
                            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 18px;">
                                <p style="margin:0 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;font-weight:600;">
                                    Si el botón no funciona, copia este enlace:
                                </p>
                                <p style="margin:0;font-size:12px;color:#6b7280;word-break:break-all;">
                                    <a href="{{ $actionUrl }}" style="color:#8b1028;text-decoration:none;">
                                        {{ $actionUrl }}
                                    </a>
                                </p>
                            </div>
                        </td>
                    </tr>
                @endisset

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
