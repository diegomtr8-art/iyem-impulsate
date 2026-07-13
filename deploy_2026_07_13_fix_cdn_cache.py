"""Fix: el panel admin quedo en blanco tras el deploy de agenda por caché CDN de
Hostinger sirviendo una copia vieja/corrupta de NotificacionCampana-CvfMZzyJ.js
(el origen ya tenia el archivo correcto, verificado con curl directo, pero algun
nodo CDN seguia sirviendo una version distinta). Se forzo un cambio real minimo
(console.debug) en NotificacionCampana.vue para que Vite genere hashes nuevos
(nunca antes cacheados en ningun edge), evitando depender de una purga manual."""
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

print('\n=== Assets compilados (con hashes nuevos, nunca cacheados) ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

print('\n=== Limpiar caches Laravel + OPcache ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan config:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan route:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan view:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan optimize 2>&1')

sftp.close(); ssh.close()
print('\nFix desplegado.')
