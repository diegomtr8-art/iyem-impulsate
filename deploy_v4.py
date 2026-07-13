"""Deploy V4 — sidebar eventos, imagen evento, encuestas satisfacción, reset datos, seed demo"""
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

def run(ssh, cmd, label=None):
    if label: print('\n  >> ' + label)
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    for l in out.split('\n')[-20:]:
        if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-5:]:
            if l.strip(): print('  ERR: ' + l)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando a Hostinger...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')
sftp = ssh.open_sftp()

# ── Assets compilados (DOBLE PATH — crítico) ─────────────────────────────────
print('\n=== Assets compilados (public/build + build) ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

# ── Rutas ─────────────────────────────────────────────────────────────────────
print('\n=== Rutas ===')
up(sftp, 'routes/web.php')
up(sftp, 'routes/api.php')

# ── Modelos ───────────────────────────────────────────────────────────────────
print('\n=== Modelos ===')
up(sftp, 'app/Models/Evento.php')
up(sftp, 'app/Models/EncuestaSatisfaccion.php')
up(sftp, 'app/Models/EncuestaRespuesta.php')

# ── Controladores ─────────────────────────────────────────────────────────────
print('\n=== Controladores ===')
up(sftp, 'app/Http/Controllers/Admin/EventoController.php')
up(sftp, 'app/Http/Controllers/Admin/EncuestaAdminController.php')
up(sftp, 'app/Http/Controllers/EncuestaController.php')
up(sftp, 'app/Http/Controllers/NotificacionController.php')

# ── Middleware ─────────────────────────────────────────────────────────────────
print('\n=== Middleware ===')
up(sftp, 'app/Http/Middleware/HandleInertiaRequests.php')

# ── Mail ──────────────────────────────────────────────────────────────────────
print('\n=== Mail ===')
up(sftp, 'app/Mail/EncuestaSatisfaccionMail.php')
up(sftp, 'resources/views/mail/encuesta-satisfaccion.blade.php')

# ── Exports ───────────────────────────────────────────────────────────────────
print('\n=== Exports ===')
up(sftp, 'app/Exports/EncuestasExport.php')

# ── Config ────────────────────────────────────────────────────────────────────
print('\n=== Config ===')
up(sftp, 'config/encuestas.php')

# ── Migraciones ───────────────────────────────────────────────────────────────
print('\n=== Migraciones ===')
up(sftp, 'database/migrations/2026_06_10_100000_add_imagen_to_eventos_table.php')
up(sftp, 'database/migrations/2026_06_10_200000_create_encuestas_satisfaccion_table.php')

# ── Seeders ───────────────────────────────────────────────────────────────────
print('\n=== Seeders ===')
up(sftp, 'database/seeders/LimpiarYResetearSeeder.php')
up(sftp, 'database/seeders/DemoDataSeeder.php')
up(sftp, 'database/seeders/DatabaseSeeder.php')

sftp.close()

# ── Comandos Artisan ──────────────────────────────────────────────────────────
print('\n=== Comandos Artisan ===')
CD = 'cd ' + RROOT + ' && '

run(ssh, CD + 'php artisan storage:link 2>&1', 'storage:link')
run(ssh, CD + 'php artisan migrate --force 2>&1', 'migrate --force')
run(ssh, CD + 'php artisan config:clear 2>&1', 'config:clear')
run(ssh, CD + 'php artisan route:clear 2>&1', 'route:clear')
run(ssh, CD + 'php artisan view:clear 2>&1', 'view:clear')
run(ssh, CD + 'php artisan optimize 2>&1', 'optimize')
run(ssh, CD + 'tail -5 storage/logs/laravel.log 2>&1', 'últimas líneas del log')

ssh.close()
print('\n=== DEPLOY V4 COMPLETADO ===')
print('URL: https://impulsate.iyemyucatan.com')
print('')
print('PRÓXIMOS PASOS (confirmar con el usuario antes de ejecutar):')
print('  php artisan db:seed --class=LimpiarYResetearSeeder --force  ← BORRA DATOS REALES')
print('  php artisan db:seed --class=DemoDataSeeder --force')
