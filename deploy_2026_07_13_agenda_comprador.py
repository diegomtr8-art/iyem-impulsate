"""Deploy 2026-07-13: Inversion del flujo de Agenda a comprador.
Antes: admin elegia UN proveedor y asignaba compradores a slots (correo al proveedor).
Ahora: admin elige UN comprador y asigna proveedores a slots (correo al comprador).
Incluye migracion de esquema (agendas_propuestas.user_id, agenda_propuesta_citas.restaurantero_id),
migracion de datos que actualiza en sitio la plantilla 'agenda_propuesta' (solo si sigue con el
contenido default de fabrica, para no pisar personalizaciones de admin), y las 4 vistas Vue
del modulo (Index, Crear, Responder, Gracias). Verificado: producciOn tenia 0 filas en ambas
tablas de agenda antes de este deploy (confirmado por SSH), asi que el ALTER TABLE no pierde datos.
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

print('\n=== Backend PHP: Controllers ===')
up(sftp, 'app/Http/Controllers/Admin/AgendaController.php')
up(sftp, 'app/Http/Controllers/AgendaPublicaController.php')

print('\n=== Backend PHP: Modelos ===')
up(sftp, 'app/Models/AgendaPropuesta.php')
up(sftp, 'app/Models/AgendaPropuestaCita.php')

print('\n=== Seeders ===')
up(sftp, 'database/seeders/PlantillasCorreoSeeder.php')

print('\n=== Migraciones nuevas ===')
up(sftp, 'database/migrations/2026_07_13_105653_update_agendas_propuestas_for_comprador_flow.php')
up(sftp, 'database/migrations/2026_07_13_111328_update_agenda_propuesta_plantilla_a_comprador.php')

print('\n=== Assets compilados (JS/CSS) ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

print('\n=== Migrate + Optimize + Queue restart ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan migrate --force 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan config:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan route:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan view:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan optimize 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan queue:restart 2>&1')

sftp.close(); ssh.close()
print('\nDeploy completado.')
