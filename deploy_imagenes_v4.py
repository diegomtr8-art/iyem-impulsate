"""Subir assets + archivos modificados + reset y demo con imagenes"""
import paramiko, os, sys
sys.stdout.reconfigure(encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
sftp = ssh.open_sftp()

def mkdirp(p):
    dirs, path = [], p
    while True:
        try: sftp.stat(path); break
        except FileNotFoundError: dirs.append(path); path = path.rsplit('/', 1)[0]
        if not path: break
    for d in reversed(dirs):
        try: sftp.mkdir(d)
        except: pass

def up(lr, rr=None):
    if rr is None: rr = lr.replace('\\', '/')
    lp = os.path.join(LROOT, lr.replace('/', os.sep))
    rp = RROOT + '/' + rr
    if not os.path.exists(lp): print('  SKIP ' + lr); return
    mkdirp(rp.rsplit('/', 1)[0]); sftp.put(lp, rp); print('  UP  ' + rr)

def updir(ld, rd):
    ldir = os.path.join(LROOT, ld.replace('/', os.sep))
    rdir = RROOT + '/' + rd; n = 0
    for root, dirs, files in os.walk(ldir):
        dirs[:] = [d for d in dirs if d not in ['.git', '__pycache__', 'node_modules']]
        for f in files:
            lf = os.path.join(root, f)
            rel = os.path.relpath(lf, ldir).replace('\\', '/')
            rp = rdir + '/' + rel
            mkdirp(rp.rsplit('/', 1)[0]); sftp.put(lf, rp); n += 1
    print('  ' + str(n) + ' archivos -> ' + rd)

def run(cmd, label=None):
    if label: print('\n  >> ' + label)
    _, o, e = ssh.exec_command(cmd)
    code = o.channel.recv_exit_status()
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    for l in out.split('\n'):
        if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-3:]:
            if l.strip(): print('  ERR: ' + l)
    return code

# ── Assets ────────────────────────────────────────────────────────────────────
print('\n=== Assets compilados ===')
updir('public/build', 'public/build')
updir('public/build', 'build')

# ── PHP modificados ───────────────────────────────────────────────────────────
print('\n=== Archivos PHP modificados ===')
up('app/Http/Middleware/HandleInertiaRequests.php')
up('database/seeders/DemoDataSeeder.php')

sftp.close()

# ── Limpiar + Demo con imágenes ───────────────────────────────────────────────
CD = 'cd ' + RROOT + ' && '

print('\n=== LimpiarYResetearSeeder ===')
code = run(CD + 'php artisan db:seed --class=LimpiarYResetearSeeder --force 2>&1', 'Limpiar')
if code != 0:
    print('  FALLO'); ssh.close(); exit(1)

print('\n=== DemoDataSeeder (con descarga de imagenes) ===')
print('  Este paso tarda ~2 minutos mientras descarga las imagenes...')
code = run(CD + 'php artisan db:seed --class=DemoDataSeeder --force 2>&1', 'DemoDataSeeder')
if code != 0:
    print('  FALLO (code ' + str(code) + ')'); ssh.close(); exit(1)

print('\n=== Cache ===')
run(CD + 'php artisan optimize:clear 2>&1', 'optimize:clear')
run(CD + 'php artisan optimize 2>&1', 'optimize')

# Verificar archivos descargados
print('\n=== Verificando imagenes descargadas ===')
run('ls ' + RROOT + '/storage/app/public/eventos/ 2>/dev/null | head -5', 'eventos/')
run('ls ' + RROOT + '/storage/app/public/restauranteros/logos/ 2>/dev/null | head -5', 'logos/')
run('ls ' + RROOT + '/storage/app/public/restauranteros/productos/ 2>/dev/null | head -5', 'productos/')

ssh.close()
print('\n=== COMPLETADO ===')
print('URL: https://impulsate.iyemyucatan.com')
print('Admin:       impulsate@iyemyucatan.com / password')
print('Proveedores: proveedor1..10@demo.impulsate.test / password')
print('Compradores: comprador1..15@demo.impulsate.test / password')
