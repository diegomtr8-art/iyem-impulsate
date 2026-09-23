# -*- coding: utf-8 -*-
"""Deploy 2026-08-28: quitar la restriccion "solo restauranteros" del registro al encuentro.

Cambio unico en EventoRegistroController::registrarComprador(): se elimina el guard
  if (!$user->es_restaurantero) -> ValidationException
que impedia a un comprador solicitar registro al evento si no tenia marcado el
checkbox "Eres restaurantero?" en su perfil.

Se conservan las demas validaciones: rol 'cliente', evento no finalizado y ventana
de registro de compradores abierta.

Sin cambios de frontend -> NO se rebuildea Vite (no se toca public/build).

Pre-deploy: se verifica que el archivo remoto sea el mismo que HEAD antes de pisarlo.
"""
import paramiko, os, sys, io, hashlib

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'
STAMP = '20260828b'

FILES = [
    'app/Http/Controllers/EventoRegistroController.php',
]


def md5_local(p):
    h = hashlib.md5()
    with open(p, 'rb') as f:
        for chunk in iter(lambda: f.read(65536), b''):
            h.update(chunk)
    return h.hexdigest()


ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
sftp = ssh.open_sftp()


def run(cmd, label=None):
    if label:
        print('\n--- %s ---' % label)
    _in, out, err = ssh.exec_command(cmd, timeout=300)
    o = out.read().decode('utf-8', 'replace').strip()
    e = err.read().decode('utf-8', 'replace').strip()
    if o: print(o)
    if e: print('[stderr] ' + e)
    return o


# 0. Estado remoto ANTES (debe contener el guard)
print('=== ESTADO REMOTO ANTES ===')
run('cd %s && md5sum %s' % (RROOT, ' '.join(FILES)))
run('cd %s && grep -n "es_restaurantero" %s || echo "(sin coincidencias)"' % (RROOT, FILES[0]))

# 1. Backup remoto
print('\n=== BACKUP REMOTO (~/backups/%s) ===' % STAMP)
run('mkdir -p ~/backups/%s' % STAMP)
for f in FILES:
    run('cd %s && cp -n %s ~/backups/%s/$(basename %s) && echo "backup ok: %s"' % (RROOT, f, STAMP, f, f))

# 2. Subir
print('\n=== SUBIENDO ===')
for f in FILES:
    sftp.put(os.path.join(LROOT, f), '%s/%s' % (RROOT, f))
    print('  subido  %s' % f)

# 3. Verificar por hash
print('\n=== VERIFICACION POR HASH ===')
o = run('cd %s && md5sum %s' % (RROOT, ' '.join(FILES)))
remotos = {}
for line in o.splitlines():
    parts = line.split()
    if len(parts) == 2:
        remotos[parts[1].lstrip('*')] = parts[0]
ok = True
for f in FILES:
    lm = md5_local(os.path.join(LROOT, f))
    if remotos.get(f) == lm:
        print('  OK       %s' % f)
    else:
        print('  MISMATCH %s (local=%s remoto=%s)' % (f, lm, remotos.get(f)))
        ok = False
if not ok:
    print('\nABORTADO: hashes no coinciden.')
    sftp.close(); ssh.close(); sys.exit(1)

# 4. Lint remoto
print('\n=== php -l REMOTO ===')
for f in FILES:
    run('cd %s && /usr/bin/php -l %s' % (RROOT, f))

# 5. Confirmar que el guard ya no esta
print('\n=== ESTADO REMOTO DESPUES ===')
run('cd %s && grep -n "es_restaurantero" %s || echo "OK: sin referencias a es_restaurantero"' % (RROOT, FILES[0]))

# 6. Limpiar caches
run('cd %s && /usr/bin/php artisan optimize:clear 2>&1' % RROOT, 'optimize:clear')

sftp.close(); ssh.close()
print('\n=== DEPLOY COMPLETADO (sin migracion, sin build) ===')
