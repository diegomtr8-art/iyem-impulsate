"""Deploy COMPLETO — impulsate.iyemyucatan.com (deploy inicial / full refresh)"""
import paramiko, os, subprocess, sys

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'

SKIP_DIRS = {'.git', '__pycache__', 'node_modules', 'citas_db'}
# index.php raíz del servidor tiene paths especiales (./vendor en vez de ../vendor) — no sobrescribir
# .env tiene credenciales de producción — no sobrescribir
SKIP_ROOT_FILES = {'index.php', '.env'}


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
    if not os.path.exists(lp): print('  SKIP (no existe) ' + lr); return
    mkdirp(sftp, rp.rsplit('/', 1)[0])
    sftp.put(lp, rp)
    print('  UP  ' + rr)


def updir(sftp, ld, rd, skip_files=None):
    ldir = os.path.join(LROOT, ld.replace('/', os.sep))
    if not os.path.isdir(ldir): print('  SKIP dir (no existe) ' + ld); return
    rdir = RROOT + '/' + rd; n = 0
    for root, dirs, files in os.walk(ldir):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
        for f in files:
            if skip_files and f in skip_files: continue
            lf = os.path.join(root, f)
            rel = os.path.relpath(lf, ldir).replace('\\', '/')
            rp = rdir + '/' + rel
            mkdirp(sftp, rp.rsplit('/', 1)[0])
            sftp.put(lf, rp); n += 1
    print('  ' + str(n) + ' archivos -> ' + rd)
    return n


def run(ssh, cmd, show_lines=20):
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    for l in (out.split('\n') or [])[-show_lines:]:
        if l.strip(): print('    ' + l)
    if err:
        for l in err.split('\n')[-8:]:
            if l.strip(): print('    ERR: ' + l)


# ─── 0. Build assets ────────────────────────────────────────────────────────
print('\n=== [0/7] Build assets (npm run build) ===')
result = subprocess.run(
    ['npm', 'run', 'build'],
    cwd=LROOT, capture_output=True, text=True, shell=True
)
if result.returncode != 0:
    print('ERROR en npm run build:')
    print(result.stderr[-500:])
    sys.exit(1)
print('  Build OK')

# ─── Conexión SSH ────────────────────────────────────────────────────────────
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('\nConectando a Hostinger...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')
sftp = ssh.open_sftp()

# ─── 1. PHP / Lógica ─────────────────────────────────────────────────────────
print('\n=== [1/7] app/ (controladores, modelos, middleware) ===')
updir(sftp, 'app', 'app')

print('\n=== [2/7] bootstrap/ config/ database/ lang/ routes/ ===')
updir(sftp, 'bootstrap', 'bootstrap')
updir(sftp, 'config', 'config')
updir(sftp, 'database', 'database')
updir(sftp, 'lang', 'lang')
updir(sftp, 'routes', 'routes')

print('\n=== [3/7] resources/views/ (Blade) ===')
updir(sftp, 'resources/views', 'resources/views')

print('\n=== [4/7] Archivos raíz ===')
for f in ['.htaccess', 'artisan', 'composer.json', 'composer.lock',
          'tailwind.config.js', 'vite.config.js']:
    if f not in SKIP_ROOT_FILES:
        up(sftp, f)

print('\n=== [5/7] public/ (sin index.php) ===')
updir(sftp, 'public', 'public', skip_files={'index.php'})

print('\n=== [6/7] Assets compilados -> public/build/ Y build/ ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

sftp.close()

# ─── 7. Comandos del servidor ────────────────────────────────────────────────
print('\n=== [7/7] Servidor: composer + artisan ===')
print('  composer install --no-dev ...')
run(ssh, f'cd {RROOT} && composer install --no-dev --optimize-autoloader 2>&1 | tail -5')

print('  php artisan migrate --force ...')
run(ssh, f'cd {RROOT} && php artisan migrate --force 2>&1')

print('  storage:link ...')
run(ssh, f'cd {RROOT} && php artisan storage:link 2>&1')

print('  config/route/view:clear ...')
run(ssh, f'cd {RROOT} && php artisan config:clear && php artisan route:clear && php artisan view:clear 2>&1')

print('  optimize ...')
run(ssh, f'cd {RROOT} && php artisan optimize 2>&1')

print('  últimas líneas del log ...')
run(ssh, f'cd {RROOT} && tail -5 storage/logs/laravel.log 2>&1')

ssh.close()
print('\n╔══════════════════════════════════════════════════╗')
print('║  DEPLOY COMPLETO — impulsate.iyemyucatan.com    ║')
print('╚══════════════════════════════════════════════════╝')
print('  URL: https://impulsate.iyemyucatan.com/')
print()
