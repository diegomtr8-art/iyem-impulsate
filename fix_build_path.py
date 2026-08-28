# Fix: sube public/build al path correcto public/build/ en el servidor
import sys, io, os, time
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
import paramiko, urllib.request, urllib.error

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY  = 'C:/Users/Diego Martinez/.ssh/id_deploy'
ROOT = '/home/u489236361/domains/lightcyan-mallard-509513.hostingersite.com/public_html'
BASE = 'https://lightcyan-mallard-509513.hostingersite.com'
LOCAL_BUILD = os.path.join('C:', os.sep, 'xampp', 'htdocs', 'citas', 'public', 'build')

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY, timeout=30)
sftp = ssh.open_sftp()

def sftp_mkdir_p(sftp, rpath):
    dirs, p = [], rpath
    while True:
        try: sftp.stat(p); break
        except FileNotFoundError:
            dirs.append(p); p = p.rsplit('/', 1)[0]
            if not p: break
    for d in reversed(dirs):
        try: sftp.mkdir(d)
        except: pass

remote_build = ROOT + '/public/build'
count = 0
print('Subiendo assets a public/build/ ...')
for root_dir, dirs, files in os.walk(LOCAL_BUILD):
    dirs[:] = [d for d in dirs if d not in ['.git', '__pycache__']]
    for fname in files:
        local_file = os.path.join(root_dir, fname)
        rel = os.path.relpath(local_file, LOCAL_BUILD).replace(os.sep, '/')
        remote_path = remote_build + '/' + rel
        sftp_mkdir_p(sftp, remote_path.rsplit('/', 1)[0])
        sftp.put(local_file, remote_path)
        count += 1
print(str(count) + ' archivos subidos')
sftp.close()

def run(cmd):
    stdin, stdout, stderr = ssh.exec_command(cmd, timeout=30)
    return stdout.read().decode('utf-8', errors='replace').strip()

print()
print('Limpiando view cache...')
print(run('cd ' + ROOT + ' && php artisan view:clear 2>&1'))
print('Verificando manifest...')
out = run('grep -i PantallaTv ' + ROOT + '/public/build/manifest.json 2>/dev/null | head -2')
print('PantallaTv en manifest: ' + ('OK - encontrado' if 'PantallaTv' in out else 'FALTA'))
out = run('grep -i RegisterProveedor ' + ROOT + '/public/build/manifest.json 2>/dev/null | head -1')
print('RegisterProveedor en manifest: ' + ('OK - encontrado' if 'RegisterProveedor' in out else 'FALTA'))
ssh.close()

print()
print('Re-testing endpoints...')
time.sleep(1)
for path, label in [('/register/proveedor', 'Registro Proveedor'), ('/tv/<token-generado-por-evento-ver-/admin/tv>', 'Pantalla TV')]:
    try:
        r = urllib.request.urlopen(urllib.request.Request(BASE + path, headers={'User-Agent': 'Test'}), timeout=12)
        print('  OK  200  ' + label)
    except urllib.error.HTTPError as e:
        print('  FAIL ' + str(e.code) + '  ' + label)
