#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Deploy: Control de Eventos — aprobaciones, visibilidad y exportacion"""

import subprocess, sys, os
sys.stdout.reconfigure(encoding='utf-8')

PYTHON = r"C:/Users/Diego Martinez/AppData/Local/Python/bin/python3.exe"
SSH_KEY = r"C:\Users\Diego Martinez\.ssh\id_deploy"
HOST    = "u489236361@195.35.38.222"
PORT    = "65002"
REMOTE  = "~/domains/lightcyan-mallard-509513.hostingersite.com/public_html"

def scp(local, remote_rel):
    dest = f"{HOST}:{REMOTE}/{remote_rel}"
    cmd = ["scp", "-P", PORT, "-i", SSH_KEY, "-o", "StrictHostKeyChecking=no", local, dest]
    r = subprocess.run(cmd, capture_output=True, text=True)
    status = "OK" if r.returncode == 0 else f"ERROR: {r.stderr.strip()}"
    print(f"  >> {remote_rel}  [{status}]")
    return r.returncode == 0

def ssh_cmd(cmd):
    full = f'ssh -p {PORT} -i "{SSH_KEY}" -o StrictHostKeyChecking=no {HOST} "{cmd}"'
    r = subprocess.run(full, shell=True, capture_output=True, text=True)
    output = (r.stdout + r.stderr).strip()
    if output:
        print(f"    {output}")
    return r.returncode == 0

def scp_dir(local_dir, remote_dir):
    """Sube un directorio completo recursivamente."""
    cmd = ["scp", "-r", "-P", PORT, "-i", SSH_KEY, "-o", "StrictHostKeyChecking=no",
           local_dir, f"{HOST}:{REMOTE}/{remote_dir}"]
    r = subprocess.run(cmd, capture_output=True, text=True)
    status = "OK" if r.returncode == 0 else f"ERROR: {r.stderr.strip()}"
    print(f"  >> {remote_dir}/  [{status}]")
    return r.returncode == 0

BASE = r"C:\xampp\htdocs\citas"

print("=" * 60)
print("DEPLOY - Control de Eventos (aprobaciones + export)")
print("=" * 60)

# ── 1. PHP: Controladores ────────────────────────────────────
print("\n[1/5] Subiendo controladores PHP...")
php_files = [
    (r"app\Http\Controllers\EventoRegistroController.php",
     "app/Http/Controllers/EventoRegistroController.php"),
    (r"app\Http\Controllers\RestauranteroPublicoController.php",
     "app/Http/Controllers/RestauranteroPublicoController.php"),
    (r"app\Http\Controllers\CitaPublicaController.php",
     "app/Http/Controllers/CitaPublicaController.php"),
    (r"app\Http\Middleware\HandleInertiaRequests.php",
     "app/Http/Middleware/HandleInertiaRequests.php"),
    (r"app\Http\Controllers\Admin\EventoController.php",
     "app/Http/Controllers/Admin/EventoController.php"),
    (r"app\Http\Controllers\Admin\ExportController.php",
     "app/Http/Controllers/Admin/ExportController.php"),
    (r"app\Http\Controllers\Admin\EventoSolicitudesController.php",
     "app/Http/Controllers/Admin/EventoSolicitudesController.php"),
    (r"app\Exports\EventoCompletoExport.php",
     "app/Exports/EventoCompletoExport.php"),
    (r"routes\web.php",
     "routes/web.php"),
]

ok = True
for local_rel, remote_rel in php_files:
    ok &= scp(os.path.join(BASE, local_rel), remote_rel)

# ── 2. Migración ─────────────────────────────────────────────
print("\n[2/5] Subiendo migración...")
scp(
    os.path.join(BASE, r"database\migrations\2026_06_08_093952_add_estado_to_evento_usuario_table.php"),
    "database/migrations/2026_06_08_093952_add_estado_to_evento_usuario_table.php"
)

# ── 3. Build assets ──────────────────────────────────────────
print("\n[3/5] Subiendo assets compilados (build/)...")
build_local = os.path.join(BASE, "public", "build")

# manifest.json → public/build/manifest.json (para que PHP lo lea)
scp(os.path.join(build_local, "manifest.json"), "public/build/manifest.json")

# assets/ → build/assets/ (ruta web que usa el navegador)
scp_dir(os.path.join(build_local, "assets"), "build")

# ── 4. Comandos artisan en servidor ──────────────────────────
print("\n[4/5] Ejecutando comandos en servidor...")
artisan_base = f"cd {REMOTE} && "

print("  → migrate --force")
ssh_cmd(artisan_base + "php artisan migrate --force")

print("  → optimize:clear")
ssh_cmd(artisan_base + "php artisan optimize:clear")

print("  → config:cache")
ssh_cmd(artisan_base + "php artisan config:cache")

print("  → route:cache")
ssh_cmd(artisan_base + "php artisan route:cache")

print("  → view:cache")
ssh_cmd(artisan_base + "php artisan view:cache")

# ── 5. Verificación ──────────────────────────────────────────
print("\n[5/5] Verificando rutas nuevas...")
ssh_cmd(artisan_base + "php artisan route:list --name=admin.eventos.solicitudes")

print("\n" + "=" * 60)
print("Deploy finalizado.")
print("URL: https://lightcyan-mallard-509513.hostingersite.com/")
print("Verifica:")
print("  [OK]Admin → Eventos → badge de solicitudes pendientes")
print("  [OK]Admin → Eventos → botón Descargar Excel en cualquier evento")
print("  [OK]Proveedor se registra → queda pendiente")
print("  [OK]Admin aprueba → proveedor aparece en lista pública")
print("  [OK]Comprador sin aprobación → no puede agendar")
print("=" * 60)
