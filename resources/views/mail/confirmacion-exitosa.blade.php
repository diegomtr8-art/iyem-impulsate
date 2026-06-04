<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Impulsate</title>
<style>body{margin:0;padding:0;background:#f9fafb;font-family:'Segoe UI',Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;}</style>
</head>
<body>
<div style="text-align:center;max-width:480px;padding:40px 24px;">
    <img src="{{ asset('images/logo_impulsate.png') }}" alt="Impulsate" style="height:56px;margin-bottom:24px;">
    <div style="background:#fff;border-radius:16px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
        <div style="font-size:48px;margin-bottom:16px;">✅</div>
        <h2 style="color:#111827;font-size:20px;font-weight:800;margin:0 0 12px;">Respuesta registrada</h2>
        <p style="color:#6b7280;font-size:15px;margin:0 0 24px;">{{ $mensaje }}</p>
        <a href="{{ url('/') }}" style="display:inline-block;background:#8b1028;color:#fff;padding:12px 24px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;">
            Ir al inicio
        </a>
    </div>
    <p style="color:#9ca3af;font-size:12px;margin-top:24px;">Encuentro de Negocios Impulsate · Gobierno del Estado de Yucatán</p>
</div>
</body>
</html>
