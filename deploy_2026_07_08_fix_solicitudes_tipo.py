"""Deploy 2026-07-08: Fix bug tipo en EventoSolicitudesController (aprobar/rechazar/revertir/eliminar
afectaba ambas filas de evento_usuario para usuarios con doble rol) + resto de cambios pendientes
en el working tree (crédito a negociar, plantillas correo, correo masivo, categorías, logística,
perfil completo, exports, etc.)."""
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
    for l in out.split('\n')[-20:]:
        if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-8:]:
            if l.strip(): print('  ERR: ' + l)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando a Hostinger (impulsate.iyemyucatan.com)...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')
sftp = ssh.open_sftp()

print('\n=== Backend PHP: Controllers ===')
up(sftp, 'app/Exports/CompradoresExport.php')
up(sftp, 'app/Exports/ProveedoresExport.php')
up(sftp, 'app/Http/Controllers/Admin/EventoController.php')
up(sftp, 'app/Http/Controllers/Admin/EventoSolicitudesController.php')
up(sftp, 'app/Http/Controllers/Admin/RestauranteroAdminController.php')
up(sftp, 'app/Http/Controllers/Admin/SuperAdmin/PlantillasCorreoController.php')
up(sftp, 'app/Http/Controllers/Admin/SuperAdmin/CategoriasController.php')
up(sftp, 'app/Http/Controllers/Admin/SuperAdmin/CorreoMasivoController.php')
up(sftp, 'app/Http/Controllers/CitaPublicaController.php')
up(sftp, 'app/Http/Controllers/ProveedorPerfilController.php')
up(sftp, 'app/Http/Controllers/RestauranteroPanelController.php')
up(sftp, 'app/Http/Controllers/RestauranteroPublicoController.php')

print('\n=== Backend PHP: Mailables + Modelos ===')
up(sftp, 'app/Mail/EventoSolicitudAprobadaMail.php')
up(sftp, 'app/Mail/EventoSolicitudRechazadaMail.php')
up(sftp, 'app/Models/Restaurantero.php')
up(sftp, 'app/Models/Categoria.php')

print('\n=== Rutas ===')
up(sftp, 'routes/web.php')

print('\n=== Vistas blade ===')
up(sftp, 'resources/views/app.blade.php')
up(sftp, 'resources/views/mail/proveedor-aprobado.blade.php')
up(sftp, 'resources/views/mail/proveedor-rechazado.blade.php')
up(sftp, 'resources/views/mail/evento-solicitud-aprobada.blade.php')
up(sftp, 'resources/views/mail/evento-solicitud-rechazada.blade.php')

print('\n=== Migraciones nuevas ===')
up(sftp, 'database/migrations/2026_06_22_130321_add_credito_factura_y_perfil_completo_to_restauranteros_table.php')
up(sftp, 'database/migrations/2026_06_24_150902_add_logistica_to_restauranteros_table.php')
up(sftp, 'database/migrations/2026_06_29_155605_add_credito_a_negociar_to_restauranteros_table.php')
up(sftp, 'database/migrations/2026_07_01_214318_create_categorias_table.php')

print('\n=== Seeders ===')
up(sftp, 'database/seeders/PlantillasCorreoSeeder.php')
up(sftp, 'database/seeders/CategoriasSeeder.php')

print('\n=== Assets compilados (JS/CSS) ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

print('\n=== Migrate + Optimize ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan migrate --force 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan config:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan route:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan view:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan optimize 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan queue:restart 2>&1')

sftp.close(); ssh.close()
print('\nDeploy completado.')
