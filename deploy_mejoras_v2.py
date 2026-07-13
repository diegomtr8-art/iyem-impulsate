"""Deploy: Mejoras V2 — fechas fin ventanas, registro dual, TabEventos, switch rol fix, modal solicitudes"""
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

print('\n=== Migración ===')
up(sftp, 'database/migrations/2026_06_08_200000_add_fecha_fin_ventanas_to_eventos_table.php')

print('\n=== Modelos y Middleware ===')
up(sftp, 'app/Models/Evento.php')
up(sftp, 'app/Http/Middleware/HandleInertiaRequests.php')

print('\n=== Controladores ===')
up(sftp, 'app/Http/Controllers/EventoRegistroController.php')
up(sftp, 'app/Http/Controllers/Auth/SwitchRoleController.php')
up(sftp, 'app/Http/Controllers/CitaPublicaController.php')
up(sftp, 'app/Http/Controllers/RestauranteroPanelController.php')
up(sftp, 'app/Http/Controllers/Admin/EventoController.php')
up(sftp, 'app/Http/Controllers/Admin/EventoSolicitudesController.php')

print('\n=== Rutas ===')
up(sftp, 'routes/web.php')

sftp.close()

print('\n=== Comandos Artisan ===')
run(ssh, 'cd ' + RROOT + ' && php artisan migrate --force 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan config:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan route:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan view:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan optimize 2>&1')

print('\n=== Verificación de rutas nuevas ===')
run(ssh, 'cd ' + RROOT + ' && php artisan route:list --path=admin/eventos 2>&1 | grep -E "PATCH|update"')
run(ssh, 'cd ' + RROOT + ' && tail -5 storage/logs/laravel.log 2>&1')

ssh.close()
print('\n=== DEPLOY COMPLETADO ===')
print('URL: https://lightcyan-mallard-509513.hostingersite.com/')
print()
print('Cambios desplegados:')
print('  - Migración: fecha_hora_fin_proveedores, fecha_hora_fin_compradores')
print('  - Registro dual: proveedor con rol cliente se registra en ambos')
print('  - Componentes: CuentaRegresiva.vue, TabEventos.vue')
print('  - Paneles: tabs Mis Citas/Eventos en comprador; Mi Panel/Mis Citas/Eventos en proveedor')
print('  - Admin Eventos: campos fechas fin + modal edición + PATCH route')
print('  - Solicitudes: modal perfil completo + mini-confirm + loading por tarjeta')
print('  - Fix switch rol: Inertia::location() + session()->save()')
