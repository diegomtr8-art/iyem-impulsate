# -*- coding: utf-8 -*-
"""Deploy 2026-09-07: UX de subidas de archivos y de registro al evento (C1-C6).

Cambios que van:
  - resources/js/Pages/Perfil/Show.vue
      C1 quitar barra de "Completitud del perfil"
      C2 limite de peso visible + validacion en el navegador (4 inputs)
      C3 errores del servidor visibles en el formulario de documentos
      C4 spinner + barra de progreso real en todas las subidas
  - resources/js/Components/TabEventos.vue
      C4.4 porcentaje en el boton de documentos del modal
      C5   explicar por que el boton de registro esta deshabilitado
      C6   aviso verde de confirmacion tras registrarse
  - app/Http/Controllers/ProveedorPerfilController.php
      limite de fotos max:4096 -> max:8192 (los limites PHP del plan son 2048M,
      el techo real siempre estuvo en las reglas max: de Laravel)

NO hay migraciones: la unica pendiente (add_destacado) ya corrio en prod (batch 39).
NO se toca .env ni composer (no cambian dependencias).

El build de Vite es todo-o-nada: se sube public/build COMPLETO y se re-copia a
$WEBROOT/build, que es lo que Hostinger sirve de verdad. Backup de ambos antes.
"""
import paramiko, os, sys, io, hashlib, tarfile, subprocess, posixpath

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'
STAMP = '20260907ux'

CODE_FILES = [
    'app/Http/Controllers/ProveedorPerfilController.php',
    'resources/js/Pages/Perfil/Show.vue',
    'resources/js/Components/TabEventos.vue',
]

# Solo este archivo se pisa en caliente: verificamos que prod siga siendo HEAD.
GUARDED = {'app/Http/Controllers/ProveedorPerfilController.php'}

os.chdir(LROOT)


def md5_bytes(b):
    return hashlib.md5(b).hexdigest()


def md5_local(p):
    with open(p, 'rb') as f:
        return md5_bytes(f.read())


def md5_head(p):
    out = subprocess.check_output(['git', 'show', 'HEAD:' + p])
    return md5_bytes(out)


# ── 0. Comprobaciones locales ────────────────────────────────────────────
manifest = 'public/build/manifest.json'
if not os.path.exists(manifest):
    sys.exit('ABORTA: falta %s. Corre npm run build antes.' % manifest)

build_files = []
for root, _dirs, files in os.walk('public/build'):
    for f in files:
        build_files.append(os.path.join(root, f).replace('\\', '/'))
print('Build local: %d archivos, manifest md5=%s' % (len(build_files), md5_local(manifest)))

for f in CODE_FILES:
    if not os.path.exists(f):
        sys.exit('ABORTA: no existe %s' % f)

# ── 1. Conexion ──────────────────────────────────────────────────────────
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
sftp = ssh.open_sftp()


def run(cmd, label=None, fatal=True):
    _in, out, err = ssh.exec_command('cd %s && %s' % (RROOT, cmd), timeout=600)
    code = out.channel.recv_exit_status()
    o = out.read().decode('utf-8', 'replace').strip()
    e = err.read().decode('utf-8', 'replace').strip()
    if label:
        print('  [%s] %s' % ('ok' if code == 0 else 'FALLO', label))
    if o:
        print('    ' + o.replace('\n', '\n    ')[:2000])
    if e:
        print('    stderr: ' + e.replace('\n', '\n    ')[:2000])
    if code != 0 and fatal:
        sftp.close(); ssh.close()
        sys.exit('ABORTA: fallo "%s" (exit %d)' % (label or cmd, code))
    return o


# ── 2. Guard: prod no debe tener cambios ajenos en lo que vamos a pisar ──
print('\n== Verificacion pre-deploy ==')
out = run('md5sum %s' % ' '.join(sorted(GUARDED)), fatal=True)
for line in out.splitlines():
    h, f = line.split()
    if h != md5_head(f):
        sftp.close(); ssh.close()
        sys.exit('ABORTA: %s en produccion no coincide con HEAD. Alguien lo cambio; '
                 'revisa antes de pisarlo.' % f)
    print('  [ok] %s coincide con HEAD, seguro de pisar' % f)

# ── 3. Backup del build servido ──────────────────────────────────────────
print('\n== Backup ==')
run('rm -rf build_bak_%s public/build_bak_%s' % (STAMP, STAMP), 'limpiar backups previos', fatal=False)
run('cp -a build build_bak_%s' % STAMP, 'backup de $WEBROOT/build')
run('cp -a public/build public/build_bak_%s' % STAMP, 'backup de public/build')

# ── 4. Empaquetar ────────────────────────────────────────────────────────
print('\n== Empaquetado ==')
archive = os.path.join(os.environ.get('TEMP', '.'), 'citas_%s.tar.gz' % STAMP)
with tarfile.open(archive, 'w:gz') as tar:
    for f in CODE_FILES + build_files:
        tar.add(f, arcname=f)
size = os.path.getsize(archive)
print('  %s (%d archivos, %.1f KB)' % (archive, len(CODE_FILES) + len(build_files), size / 1024.0))

remote_tar = '/home/%s/citas_%s.tar.gz' % (USER, STAMP)
sftp.put(archive, remote_tar)
print('  subido a %s' % remote_tar)

# ── 5. Extraer: el build viejo se borra entero para no dejar chunks huerfanos
print('\n== Despliegue ==')
run('rm -rf public/build', 'borrar public/build remoto')
run('tar -xzf %s --overwrite' % remote_tar, 'extraer')
run('rm -f %s' % remote_tar, 'limpiar tar remoto')

# Hostinger sirve desde $WEBROOT/build, no desde public/build.
run('rm -rf build && cp -rf public/build build', 'copiar public/build -> $WEBROOT/build')

# ── 6. Cache de Laravel ──────────────────────────────────────────────────
print('\n== Cache ==')
run('php artisan optimize:clear', 'optimize:clear')
run('php artisan config:cache', 'config:cache')
run('php artisan route:cache', 'route:cache')

# ── 7. Verificacion ──────────────────────────────────────────────────────
print('\n== Verificacion post-deploy ==')
local_manifest_md5 = md5_local(manifest)
out = run('md5sum public/build/manifest.json build/manifest.json', 'md5 de los dos manifest')
ok = True
for line in out.splitlines():
    h, f = line.split()
    if h != local_manifest_md5:
        print('  [FALLO] %s = %s, esperaba %s' % (f, h, local_manifest_md5))
        ok = False
    else:
        print('  [ok] %s coincide con el local' % f)

out = run('md5sum %s' % ' '.join(CODE_FILES), 'md5 de los archivos de codigo')
for line in out.splitlines():
    h, f = line.split()
    if h != md5_local(f):
        print('  [FALLO] %s no coincide con el local' % f)
        ok = False
    else:
        print('  [ok] %s' % f)

run('grep -c "max:8192" app/Http/Controllers/ProveedorPerfilController.php',
    'reglas max:8192 en prod (deben ser 2)')
run('php artisan migrate:status 2>&1 | grep -i pending | head -5 || echo "sin migraciones pendientes"',
    'migraciones pendientes')
run('curl -s -o /dev/null -w "%{http_code}" https://impulsate.iyemyucatan.com/', 'HTTP de la portada')
run('curl -s -o /dev/null -w "%{http_code}" https://impulsate.iyemyucatan.com/login', 'HTTP de /login')

sftp.close(); ssh.close()
os.remove(archive)

print('\n' + ('DEPLOY OK' if ok else 'DEPLOY CON FALLOS - revisa arriba'))
print('Backups en el servidor: build_bak_%s y public/build_bak_%s' % (STAMP, STAMP))
sys.exit(0 if ok else 1)
