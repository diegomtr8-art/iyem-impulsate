# -*- coding: utf-8 -*-
"""Deploy 2026-08-26: registro al encuentro queda PENDIENTE (aprueba el admin).

Alcance acotado: solo los 3 puntos que auto-aprobaban saltandose el flujo de
solicitudes, + acuse de recibo por correo + notificar a TODOS los admins.

  - CompletarPerfilController: store() y actualizarComprador() insertan
    'pendiente' (antes 'aprobado'); guard tipo_evento='encuentro_negocios'.
  - Restaurantero::autoAprobar(): el PERFIL se sigue aprobando solo (sigue en el
    directorio publico), pero la inscripcion al EVENTO queda 'pendiente'.
  - EventoRegistroController: notifica a todos los admins + acuse por correo
    con la plantilla nueva 'evento_solicitud_recibida'.
  - PlantillasCorreoSeeder: plantilla nueva 'evento_solicitud_recibida'.

NO toca el BAZAR: registrarBazar(), BazarEvaluacionController, BazarPublicoController
ni ninguna fila con tipo='expositor' (40 en la BD).

NO incluye la migracion destructiva (revertir aprobados a pendiente) -> se sube
aparte, previa decision del usuario.

Sin cambios de frontend -> NO se rebuildea Vite (TabEventos.vue ya pinta 'pendiente').

Verificado pre-deploy: los 9 archivos backend restantes del working tree
(multi-evento activo) ya estaban IDENTICOS en produccion por md5sum.
"""
import paramiko, os, sys, io, hashlib

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'
STAMP = '20260826'

FILES = [
    'app/Http/Controllers/CompletarPerfilController.php',
    'app/Http/Controllers/EventoRegistroController.php',
    'app/Models/Restaurantero.php',
    'database/seeders/PlantillasCorreoSeeder.php',
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


# 1. Backups remotos
print('=== BACKUPS REMOTOS (~/backups/%s) ===' % STAMP)
for f in FILES:
    run('mkdir -p ~/backups/%s' % STAMP)
    run('cd %s && cp -n %s ~/backups/%s/$(basename %s) && echo "backup ok: %s"' % (RROOT, f, STAMP, f, f))

# 2. Subir archivos
print('\n=== SUBIENDO ===')
for f in FILES:
    lp = os.path.join(LROOT, f)
    rp = '%s/%s' % (RROOT, f)
    sftp.put(lp, rp)
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

# 5. Seeder de plantillas (firstOrCreate: no pisa lo que el admin haya editado)
run('cd %s && /usr/bin/php artisan db:seed --class=PlantillasCorreoSeeder --force 2>&1' % RROOT,
    'SEEDER PlantillasCorreoSeeder')

# 6. Limpiar caches
run('cd %s && /usr/bin/php artisan optimize:clear 2>&1' % RROOT, 'optimize:clear')

sftp.close(); ssh.close()
print('\n=== DEPLOY DE CODIGO COMPLETADO (sin migracion) ===')
