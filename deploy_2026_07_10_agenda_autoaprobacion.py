"""Deploy 2026-07-10: Auto-aprobacion de proveedores/compradores al completar perfil
(reemplaza revision manual), wizard comprador 2 pasos + es_restaurantero, lista movil
compacta en directorio de proveedores, y modulo Agenda completo (propuestas por correo
con aceptar/rechazar via token). Incluye backfill de perfil_completo para cuentas ya
registradas con datos incompletos, y fix del gate de EnsureProfileComplete para que
proveedores puros se midan por Restaurantero.perfil_completo (no el flag viejo de users)."""
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
    for l in out.split('\n')[-25:]:
        if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-10:]:
            if l.strip(): print('  ERR: ' + l)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando a Hostinger (impulsate.iyemyucatan.com)...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')
sftp = ssh.open_sftp()

print('\n=== Backend PHP: Controllers (nuevos + modificados) ===')
up(sftp, 'app/Http/Controllers/Admin/AgendaController.php')
up(sftp, 'app/Http/Controllers/AgendaPublicaController.php')
up(sftp, 'app/Http/Controllers/CompletarPerfilController.php')
up(sftp, 'app/Http/Controllers/EventoRegistroController.php')
up(sftp, 'app/Http/Controllers/ProveedorPerfilController.php')

print('\n=== Backend PHP: Middleware ===')
up(sftp, 'app/Http/Middleware/EnsureProfileComplete.php')
up(sftp, 'app/Http/Middleware/HandleInertiaRequests.php')

print('\n=== Backend PHP: Modelos (nuevos + modificados) ===')
up(sftp, 'app/Models/AgendaPropuesta.php')
up(sftp, 'app/Models/AgendaPropuestaCita.php')
up(sftp, 'app/Models/Restaurantero.php')
up(sftp, 'app/Models/User.php')

print('\n=== Rutas ===')
up(sftp, 'routes/web.php')

print('\n=== Migraciones nuevas ===')
up(sftp, 'database/migrations/2026_07_10_121934_add_es_restaurantero_to_users_table.php')
up(sftp, 'database/migrations/2026_07_10_122523_create_agendas_propuestas_table.php')
up(sftp, 'database/migrations/2026_07_10_122524_create_agenda_propuesta_citas_table.php')
up(sftp, 'database/migrations/2026_07_10_142403_recalcular_perfil_completo_compradores_existentes.php')

print('\n=== Seeders ===')
up(sftp, 'database/seeders/PlantillasCorreoSeeder.php')

print('\n=== Assets compilados (JS/CSS) ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

print('\n=== Migrate + Seed plantilla + Optimize ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan migrate --force 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan db:seed --class=PlantillasCorreoSeeder --force 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan config:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan route:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan view:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan optimize 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan queue:restart 2>&1')

sftp.close(); ssh.close()
print('\nDeploy completado.')
