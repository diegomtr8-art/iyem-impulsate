"""Deploy: Mejoras V3 — slots dinámicos, perfil IYEM, landing limpia, registro independiente"""
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
    for l in out.split('\n')[-15:]:
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

print('\n=== Assets compilados (public/build) ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

print('\n=== Migraciones nuevas ===')
up(sftp, 'database/migrations/2026_06_08_200000_add_fecha_fin_ventanas_to_eventos_table.php')
up(sftp, 'database/migrations/2026_06_08_210000_add_rfc_municipio_nombre_empresa_to_users_table.php')
up(sftp, 'database/migrations/2026_06_08_220000_add_iyem_fields_to_restauranteros_table.php')

print('\n=== Modelos ===')
up(sftp, 'app/Models/User.php')
up(sftp, 'app/Models/Restaurantero.php')
up(sftp, 'app/Models/Evento.php')

print('\n=== Middleware ===')
up(sftp, 'app/Http/Middleware/HandleInertiaRequests.php')

print('\n=== Controladores ===')
up(sftp, 'app/Http/Controllers/Admin/EventoController.php')
up(sftp, 'app/Http/Controllers/Admin/EventoSolicitudesController.php')
up(sftp, 'app/Http/Controllers/Auth/SwitchRoleController.php')
up(sftp, 'app/Http/Controllers/CitaPublicaController.php')
up(sftp, 'app/Http/Controllers/CompletarPerfilController.php')
up(sftp, 'app/Http/Controllers/EventoRegistroController.php')
up(sftp, 'app/Http/Controllers/LandingController.php')
up(sftp, 'app/Http/Controllers/ProveedorPerfilController.php')
up(sftp, 'app/Http/Controllers/RestauranteroPanelController.php')
up(sftp, 'app/Http/Controllers/RestauranteroPublicoController.php')

print('\n=== Rutas ===')
up(sftp, 'routes/web.php')

sftp.close()

print('\n=== Comandos Artisan ===')
run(ssh, 'cd ' + RROOT + ' && php artisan migrate --force 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan config:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan route:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan view:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan optimize 2>&1')
run(ssh, 'cd ' + RROOT + ' && tail -5 storage/logs/laravel.log 2>&1')

ssh.close()
print('\n=== DEPLOY COMPLETADO ===')
print('URL: https://lightcyan-mallard-509513.hostingersite.com/')
print()
print('Cambios desplegados:')
print('  - Slots calendario dinámicos según tiempo_entre_citas_minutos del evento')
print('  - Validación múltiplo de 5 en tiempo entre citas')
print('  - Duración de cita sincronizada con el evento')
print('  - Al activar evento se sincronizan duraciones de servicios')
print('  - MAX_CITAS fallback corregido (12→3); límite dinámico')
print('  - Teléfono del proveedor oculto en vista pública')
print('  - Registro independiente por rol (sin auto-dual)')
print('  - TabEventos: usuarios dual-rol ven dos botones separados')
print('  - Título dinámico Proveedores del Evento en /restauranteros')
print('  - Perfil comprador: rfc, municipio, nombre_empresa')
print('  - Perfil proveedor: 12 campos del formulario IYEM')
print('  - Landing limpia: sin sección proveedores ni links')
print('  - Migración: rfc/municipio/nombre_empresa en users')
print('  - Migración: campos IYEM en restauranteros')
