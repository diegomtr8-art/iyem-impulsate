"""Deploy: Landing renovada + notificaciones 1h + fix 404 proveedor"""
import paramiko, os

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/lightcyan-mallard-509513.hostingersite.com/public_html'
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
    if not os.path.exists(lp): print('  SKIP ' + lr); return
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
    for l in out.split('\n')[-10:]:
        if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-3:]:
            if l.strip(): print('  ERR: ' + l)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando a Hostinger...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')
sftp = ssh.open_sftp()

print('\n=== Assets compilados ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

print('\n=== PHP modificados ===')
up(sftp, 'app/Http/Controllers/RestauranteroPublicoController.php')
up(sftp, 'app/Console/Commands/EnviarRecordatorios.php')
up(sftp, 'app/Jobs/RecordatorioCita24h.php')
up(sftp, 'app/Jobs/RecordatorioCita2h.php')
up(sftp, 'app/Mail/RecordatorioCita.php')
up(sftp, 'app/Http/Controllers/NotificacionController.php')
up(sftp, 'routes/console.php')
up(sftp, 'database/migrations/2026_06_07_100000_add_recordatorio_1h_enviado_to_citas_table.php')

sftp.close()

print('\n=== Artisan ===')
run(ssh, 'cd ' + RROOT + ' && php artisan migrate --force 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan config:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan route:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan view:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan optimize 2>&1')

ssh.close()
print('\n=== DEPLOY COMPLETADO ===')
print('URL: https://lightcyan-mallard-509513.hostingersite.com/')
print()
print('Cambios desplegados:')
print('  - Landing renovada: navbar scroll-aware, stats bar guinda, 4 secciones nuevas')
print('  - Landing: 0 emojis, solo SVGs HeroIcons, footer 4 cols con Sitios Aliados')
print('  - Fix 404: proveedor puede ver su propio perfil aunque no este activo')
print('  - Notificaciones 1h: comprador + proveedor + admin')
print('  - Admin recibe notificaciones de TODAS las citas (24h, 2h, 1h, 30m)')
print('  - Campana de notificaciones: emojis reemplazados por SVGs')
print('  - Scheduler: cada 5 minutos (antes: 15)')
