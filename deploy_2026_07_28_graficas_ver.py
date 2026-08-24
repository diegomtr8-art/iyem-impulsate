"""Deploy 2026-07-28: Modulo Graficas + Ver Respuesta de Encuestas
Alcance acotado: SOLO estas 2 vistas nuevas dentro de Admin/Encuestas.
- routes/web.php: 2 rutas nuevas (encuestas.graficas, encuestas.ver)
- EncuestaAdminController.php: 2 metodos nuevos (graficas, verRespuesta)
- Frontend: Graficas.vue, VerRespuesta.vue nuevas + botones en Index.vue

El resto del working tree (Agenda flujo comprador, Citas hora/duracion manual,
Metricas rediseno, Plantilla Impulsate) NO se toca en este deploy - fue
verificado por hash antes de este script que esos archivos ya estan 100% live
en produccion (idem al deploy de encuestas de mas temprano hoy), por lo que
subir el build completo del working tree es seguro (no revierte nada).
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

def updir(sftp, ld, rd):
    ldir = os.path.join(LROOT, ld.replace('/', os.sep))
    rdir = RROOT + '/' + rd; n = 0
    for root, dirs, files in os.walk(ldir):
        dirs[:] = [d for d in dirs if d not in ['.git', '__pycache__', 'node_modules']]
        for f in files:
            lf = os.path.join(root, f)
            rel = os.path.relpath(lf, ldir).replace('\\', '/')
            rp = rdir + '/' + rel
            mkdirp(sftp, rp.rsplit('/', 1)[0]); sftp.put(lf, rp); n += 1
    print('  ' + str(n) + ' archivos -> ' + rd)

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
print('Conectando a Hostinger (impulsate.iyemyucatan.com)...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')
sftp = ssh.open_sftp()

print('\n=== Backend PHP: Graficas + Ver Respuesta ===')
up(sftp, 'app/Http/Controllers/Admin/EncuestaAdminController.php')
up(sftp, 'routes/web.php')

print('\n=== Assets compilados (JS/CSS) — a AMBAS rutas ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

print('\n=== Limpiar caches Laravel + optimize ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan route:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan config:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan view:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan optimize 2>&1')

print('\n=== Verificacion ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan route:list 2>&1 | grep -i encuesta')

sftp.close(); ssh.close()
print('\nDeploy completado.')
