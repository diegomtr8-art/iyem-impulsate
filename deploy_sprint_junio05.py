"""Deploy Sprint Junio-05 — Eventos, selector de rol, exportaciones, reset-data"""
import paramiko, os

HOST='195.35.38.222'; PORT=65002; USER='u489236361'
KEY_PATH='C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT='/home/u489236361/domains/lightcyan-mallard-509513.hostingersite.com/public_html'
LROOT='C:/xampp/htdocs/citas'

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

def rm(sftp, rr):
    rp = RROOT + '/' + rr
    try: sftp.remove(rp); print('  RM  ' + rr)
    except FileNotFoundError: print('  --  ' + rr + ' (no existía)')

def updir(sftp, ld, rd):
    ldir = os.path.join(LROOT, ld.replace('/', os.sep))
    rdir = RROOT + '/' + rd; n = 0
    for root, dirs, files in os.walk(ldir):
        dirs[:] = [d for d in dirs if d not in ['.git','__pycache__','node_modules']]
        for f in files:
            lf = os.path.join(root, f)
            rel = os.path.relpath(lf, ldir).replace('\\', '/')
            rp = rdir + '/' + rel
            mkdirp(sftp, rp.rsplit('/', 1)[0]); sftp.put(lf, rp); n += 1
    print('  ' + str(n) + ' files -> ' + rd)

def run(ssh, cmd, label=''):
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    if label: print('  [' + label + ']')
    for l in (out + '\n' + err).split('\n')[-8:]:
        if l.strip(): print('    ' + l)

# ── Archivos PHP nuevos/modificados ──────────────────────────────────────────
PHP_FILES = [
    # Modelos
    'app/Models/Evento.php',
    'app/Models/Cita.php',
    'app/Models/Restaurantero.php',
    'app/Models/User.php',
    # Controladores admin
    'app/Http/Controllers/Admin/EventoController.php',
    'app/Http/Controllers/Admin/ExportController.php',
    'app/Http/Controllers/Admin/CitaAdminController.php',
    'app/Http/Controllers/Admin/RestauranteroAdminController.php',
    'app/Http/Controllers/Admin/PantallaTvController.php',
    'app/Http/Controllers/Admin/TorreControlController.php',
    # Controladores auth
    'app/Http/Controllers/Auth/SeleccionarRolController.php',
    'app/Http/Controllers/Auth/SocialAuthController.php',
    # Controladores generales
    'app/Http/Controllers/CitaPublicaController.php',
    'app/Http/Controllers/CompletarPerfilController.php',
    'app/Http/Controllers/EventoRegistroController.php',
    'app/Http/Controllers/RestauranteroPublicoController.php',
    'app/Http/Controllers/RestauranteroPanelController.php',
    'app/Http/Controllers/RestauranteroCitasController.php',
    # Middleware
    'app/Http/Middleware/EnsureRolSeleccionado.php',
    'app/Http/Middleware/HandleInertiaRequests.php',
    # Mail
    'app/Mail/CitaAgendada.php',
    'resources/views/mail/cita-agendada.blade.php',
    # Exports
    'app/Exports/EventoCompletoExport.php',
    # Comandos
    'app/Console/Commands/ResetDataCommand.php',
    # Bootstrap
    'bootstrap/app.php',
    # Routes
    'routes/web.php',
    # Migraciones
    'database/migrations/2026_06_04_111551_add_sitio_web_to_users_table.php',
    'database/migrations/2026_06_04_123009_add_tv_control_fields_to_citas_table.php',
    'database/migrations/2026_06_04_200000_add_sitio_web_to_users_table.php',
    'database/migrations/2026_06_04_300000_add_tv_control_fields_to_citas_table.php',
    'database/migrations/2026_06_05_100000_rename_ediciones_to_eventos_and_add_fields.php',
    'database/migrations/2026_06_05_200000_create_evento_usuario_table.php',
    'database/migrations/2026_06_05_300000_add_rol_seleccionado_to_users_table.php',
]

# ── Archivos a eliminar del servidor ─────────────────────────────────────────
FILES_TO_DELETE = [
    'app/Models/Edicion.php',
    'app/Http/Controllers/Admin/EdicionController.php',
    'app/Http/Controllers/Auth/RegistroProveedorController.php',
]

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando a Hostinger...'); ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30); print('OK\n')
sftp = ssh.open_sftp()

print('=== Subiendo archivos PHP ===')
for f in PHP_FILES: up(sftp, f)

print('\n=== Eliminando archivos obsoletos ===')
for f in FILES_TO_DELETE: rm(sftp, f)

print('\n=== Subiendo assets compilados ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

sftp.close()

print('\n=== Migraciones ===')
run(ssh, 'cd ' + RROOT + ' && php artisan migrate --force --no-ansi 2>&1', 'migrate')

print('\n=== Limpiar cachés ===')
run(ssh, 'cd ' + RROOT + ' && php artisan optimize:clear --no-ansi 2>&1', 'optimize:clear')
run(ssh, 'cd ' + RROOT + ' && php artisan config:cache --no-ansi 2>&1', 'config:cache')
run(ssh, 'cd ' + RROOT + ' && php artisan route:cache --no-ansi 2>&1', 'route:cache')
run(ssh, 'cd ' + RROOT + ' && php artisan view:cache --no-ansi 2>&1', 'view:cache')

print('\n=== Verificación ===')
run(ssh, 'cd ' + RROOT + ' && php artisan migrate:status --no-ansi 2>&1 | tail -10', 'migrate:status')

ssh.close()
print('\nDONE — https://lightcyan-mallard-509513.hostingersite.com/')
