"""Deploy parte 2: Solo assets compilados + artisan commands"""
import paramiko, os, time

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

def new_conn():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
    return c

def updir_chunked(ld, rd):
    """Sube directorio reconectando cada N archivos."""
    ldir = os.path.join(LROOT, ld.replace('/', os.sep))
    rdir = RROOT + '/' + rd
    all_files = []
    for root, dirs, files in os.walk(ldir):
        dirs[:] = [d for d in dirs if d not in ['.git', '__pycache__', 'node_modules']]
        for f in files:
            lf = os.path.join(root, f)
            rel = os.path.relpath(lf, ldir).replace('\\', '/')
            all_files.append((lf, rdir + '/' + rel))

    CHUNK = 20
    total = len(all_files)
    print(f'  Total archivos: {total}')
    for i in range(0, total, CHUNK):
        batch = all_files[i:i+CHUNK]
        ssh = new_conn(); sftp = ssh.open_sftp()
        for lf, rp in batch:
            try:
                mkdirp(sftp, rp.rsplit('/', 1)[0])
                sftp.put(lf, rp)
            except Exception as ex:
                print(f'  ERROR {rp}: {ex}')
        sftp.close(); ssh.close()
        print(f'  [{min(i+CHUNK, total)}/{total}] -> {rd}')
        time.sleep(1)

def run(cmd):
    ssh = new_conn()
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    for l in (out + '\n' + err).split('\n')[-20:]:
        if l.strip(): print('  ' + l)
    ssh.close()

print('=== Assets compilados ===')
updir_chunked('public/build', 'public/build')
updir_chunked('public/build', 'build')

print('\n=== Migrate + Seed + Optimize ===')
run(f'cd {RROOT} && /usr/bin/php artisan migrate --force 2>&1')
run(f'cd {RROOT} && /usr/bin/php artisan db:seed --class=PlantillasCorreoSeeder --force 2>&1')
run(f'cd {RROOT} && /usr/bin/php artisan config:clear && /usr/bin/php artisan route:clear && /usr/bin/php artisan view:clear 2>&1')
run(f'cd {RROOT} && /usr/bin/php artisan optimize 2>&1')
run(f'cd {RROOT} && /usr/bin/php artisan queue:restart 2>&1')

print('\nDeploy completado.')
