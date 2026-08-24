"""Deploy 2026-07-28 (3): EncuestaSatisfaccionMail ahora usa la PlantillaCorreo
editable (clave 'encuesta_satisfaccion') en vez del Blade estático fijo.
Permite editar el correo de encuestas desde Admin > Plantillas Correo.
Alcance: 1 solo archivo PHP, sin frontend ni migraciones.
"""
import paramiko, os

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'

def mkdirp(sftp, p):
    dirs, path = [], p
    while True:
        try: sftp.stat(path); break
        except FileNotFoundError: dirs.append(path); path = path.rsplit('/', 1)[0]
        if not path: break
    for d in reversed(dirs):
        try: sftp.mkdir(d)
        except Exception: pass

def up(sftp, lr, rr=None):
    if rr is None: rr = lr.replace('\\', '/')
    lp = os.path.join(LROOT, lr.replace('/', os.sep))
    rp = RROOT + '/' + rr
    if not os.path.exists(lp): print('  SKIP (no existe local) ' + lr); return
    mkdirp(sftp, rp.rsplit('/', 1)[0]); sftp.put(lp, rp); print('  UP  ' + rr)

def run(ssh, cmd):
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    for l in out.split('\n')[-30:]:
        if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-15:]:
            if l.strip(): print('  ERR: ' + l)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')
sftp = ssh.open_sftp()

print('\n=== Backup remoto ===')
run(ssh, f'cp {RROOT}/app/Mail/EncuestaSatisfaccionMail.php {RROOT}/app/Mail/EncuestaSatisfaccionMail.php.bak-20260728b')

print('\n=== Subiendo Mailable actualizado (usa PlantillaCorreo clave encuesta_satisfaccion) ===')
up(sftp, 'app/Mail/EncuestaSatisfaccionMail.php')

print('\n=== Limpiar caches ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan config:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan view:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan optimize 2>&1')

print('\n=== Verificacion: confirmar que la plantilla encuesta_satisfaccion existe y esta activa ===')
run(ssh, f'''cd {RROOT} && /usr/bin/php artisan tinker --execute="echo App\\\\Models\\\\PlantillaCorreo::where('clave','encuesta_satisfaccion')->first()?->activo ? 'OK: plantilla activa' : 'ADVERTENCIA: no encontrada o inactiva';"''')

sftp.close(); ssh.close()
print('\nDeploy completado.')
