"""Deploy 2026-07-20: TODO lo pendiente en el working tree.
1) Agenda admin: botones Ver/Borrar (AgendaController show/destroy cascada, Show.vue nuevo)
2) Admin Citas: hora manual + duracion manual (CitaAdminController, Citas/Index.vue)
3) Metricas: genero (dedup) + RFC + lista no-identificados + rediseno,
   Torre de Control: numero de mesas configurable (tabla config_evento)
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

print('\n=== Backend PHP ===')
up(sftp, 'app/Http/Controllers/Admin/AgendaController.php')
up(sftp, 'app/Http/Controllers/Admin/CitaAdminController.php')
up(sftp, 'app/Http/Controllers/Admin/MetricasController.php')
up(sftp, 'app/Http/Controllers/Admin/TorreControlController.php')
up(sftp, 'routes/web.php')

print('\n=== Migraciones nuevas ===')
up(sftp, 'database/migrations/2026_07_20_155153_add_genero_to_users_table.php')
up(sftp, 'database/migrations/2026_07_20_155155_create_config_evento_table.php')

print('\n=== Seeder de genero ===')
up(sftp, 'database/seeders/GeneroSeeder.php')

print('\n=== Assets compilados (JS/CSS) — a AMBAS rutas ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

print('\n=== Migraciones + seed en servidor ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan migrate --force 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan db:seed --class=GeneroSeeder --force 2>&1')

print('\n=== Limpiar caches Laravel + optimize ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan config:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan route:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan view:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan optimize 2>&1')

print('\n=== Verificacion de rutas nuevas ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan route:list 2>&1 | grep -i "genero\\|mesas\\|agenda.show"')

sftp.close(); ssh.close()
print('\nDeploy completado.')
