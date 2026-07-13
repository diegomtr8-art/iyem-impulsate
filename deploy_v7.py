"""Deploy V7 — aviso privacidad universal, roles amigables, compradores, camara/asociacion"""
import sys, paramiko, os
sys.stdout.reconfigure(encoding='utf-8', errors='replace')

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

def run(ssh, cmd):
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    for l in out.split('\n')[-20:]:
        if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-8:]:
            if l.strip(): print('  ERR: ' + l)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando a Hostinger...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')
sftp = ssh.open_sftp()

# ── Assets compilados (DOS ubicaciones) ──────────────────────────────────────
print('\n=== Assets compilados ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

# ── Rutas ─────────────────────────────────────────────────────────────────────
print('\n=== Rutas ===')
up(sftp, 'routes/web.php')

# ── Bootstrap ─────────────────────────────────────────────────────────────────
print('\n=== Bootstrap ===')
up(sftp, 'bootstrap/app.php')

# ── Middleware ────────────────────────────────────────────────────────────────
print('\n=== Middleware ===')
up(sftp, 'app/Http/Middleware/EnsureAvisoAceptado.php')
up(sftp, 'app/Http/Middleware/HandleInertiaRequests.php')

# ── Modelos ───────────────────────────────────────────────────────────────────
print('\n=== Modelos ===')
up(sftp, 'app/Models/User.php')
up(sftp, 'app/Models/Cita.php')

# ── Controladores ────────────────────────────────────────────────────────────
print('\n=== Controladores ===')
up(sftp, 'app/Http/Controllers/AvisoAceptacionController.php')
up(sftp, 'app/Http/Controllers/Auth/SocialAuthController.php')
up(sftp, 'app/Http/Controllers/CompletarPerfilController.php')
up(sftp, 'app/Http/Controllers/CitaPublicaController.php')
up(sftp, 'app/Http/Controllers/Admin/SuperAdmin/UsuariosGestionController.php')

# ── Actions Fortify ───────────────────────────────────────────────────────────
print('\n=== Actions ===')
up(sftp, 'app/Actions/Fortify/CreateNewUser.php')

# ── Migraciones ───────────────────────────────────────────────────────────────
print('\n=== Migraciones ===')
up(sftp, 'database/migrations/2026_06_18_100000_add_acepta_aviso_at_to_users_table.php')
up(sftp, 'database/migrations/2026_06_18_200000_add_camara_asociacion_to_users_table.php')

# ── Seeders ───────────────────────────────────────────────────────────────────
print('\n=== Seeders ===')
up(sftp, 'database/seeders/DatabaseSeeder.php')
up(sftp, 'database/seeders/DemoDataSeeder.php')

# ── Vistas Blade ─────────────────────────────────────────────────────────────
print('\n=== Vistas Blade ===')
up(sftp, 'resources/views/mail/cita-agendada.blade.php')

sftp.close()

# ── Backup antes de borrar eventos ───────────────────────────────────────────
print('\n=== Backup BD (antes de borrar eventos) ===')
run(ssh, 'cd ' + RROOT + ' && php artisan tinker --execute="'
    'echo DB::getConfig(\'database\').\' \'.DB::getConfig(\'username\').\' \'.DB::getConfig(\'host\');" 2>&1')
run(ssh, 'mysqldump -u $(php ' + RROOT + '/artisan tinker --execute="echo config(\'database.connections.mysql.username\');" 2>/dev/null) '
    '-p$(php ' + RROOT + '/artisan tinker --execute="echo config(\'database.connections.mysql.password\');" 2>/dev/null) '
    '$(php ' + RROOT + '/artisan tinker --execute="echo config(\'database.connections.mysql.database\');" 2>/dev/null) '
    'eventos citas evento_usuario > /tmp/backup_eventos_v7.sql 2>&1 && echo "Backup OK: /tmp/backup_eventos_v7.sql" || echo "WARN: backup via artisan falló, continuando"')

# ── Migraciones en servidor ───────────────────────────────────────────────────
print('\n=== Migraciones en servidor ===')
run(ssh, 'cd ' + RROOT + ' && php artisan migrate --force 2>&1')

# ── Borrado de eventos (CONFIRMADO por usuario) ──────────────────────────────
print('\n=== Borrado de eventos, citas y registros ===')
run(ssh, 'cd ' + RROOT + ' && php artisan tinker --execute="'
    "DB::statement('SET FOREIGN_KEY_CHECKS=0;');"
    "DB::table('evento_usuario')->truncate();"
    "DB::table('citas')->truncate();"
    "DB::table('eventos')->truncate();"
    "DB::statement('SET FOREIGN_KEY_CHECKS=1;');"
    "echo 'Tablas limpiadas: eventos='.DB::table('eventos')->count()."
    "' citas='.DB::table('citas')->count()."
    "' evento_usuario='.DB::table('evento_usuario')->count();"
    '" 2>&1')

# ── Optimización ─────────────────────────────────────────────────────────────
print('\n=== Optimizacion ===')
run(ssh, 'cd ' + RROOT + ' && php artisan config:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan route:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan view:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan optimize 2>&1')

print('\n=== Log (ultimas lineas) ===')
run(ssh, 'cd ' + RROOT + ' && tail -5 storage/logs/laravel.log 2>&1')

ssh.close()
print('\n=== DEPLOY V7 COMPLETADO ===')
print('URL: https://impulsate.iyemyucatan.com/')
