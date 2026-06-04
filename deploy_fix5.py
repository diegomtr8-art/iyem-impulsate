import paramiko
import os

HOST = '195.35.38.222'
PORT = 65002
USER = 'u489236361'
KEY_PATH = r'C:\Users\Diego Martinez\.ssh\id_deploy'
REMOTE_ROOT = '/home/u489236361/domains/lightcyan-mallard-509513.hostingersite.com/public_html'
LOCAL_ROOT = r'C:\xampp\htdocs\citas'

def sftp_mkdir_p(sftp, remote_path):
    dirs = []
    path = remote_path
    while True:
        try:
            sftp.stat(path)
            break
        except FileNotFoundError:
            dirs.append(path)
            path = path.rsplit('/', 1)[0]
            if not path:
                break
    for d in reversed(dirs):
        try:
            sftp.mkdir(d)
        except Exception:
            pass

def upload_file(sftp, local_path, remote_path):
    sftp_mkdir_p(sftp, remote_path.rsplit('/', 1)[0])
    sftp.put(local_path, remote_path)
    print(f'  UP {remote_path.replace(REMOTE_ROOT, "")}')

def upload_dir(sftp, local_dir, remote_dir):
    count = 0
    for root, dirs, files in os.walk(local_dir):
        for fname in files:
            local_file = os.path.join(root, fname)
            rel = os.path.relpath(local_file, local_dir).replace('\\', '/')
            upload_file(sftp, local_file, f'{remote_dir}/{rel}')
            count += 1
    return count

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Connecting to Hostinger...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH)
sftp = ssh.open_sftp()

# CORRECCIÓN: Hostinger sirve desde public_html/public/ como DocumentRoot.
# El build DEBE ir a public/build/, no a build/ directamente.
# deploy_fase5.py subió a REMOTE_ROOT/build (incorrecto) — este script lo corrige.
print('\n--- Build assets -> public_html/public/build/ (path correcto) ---')
count = upload_dir(
    sftp,
    os.path.join(LOCAL_ROOT, 'public', 'build'),
    f'{REMOTE_ROOT}/public/build'
)
print(f'  {count} asset files uploaded to /public/build/')

sftp.close()

print('\n--- Clearing caches ---')
cmds = [
    'cd ' + REMOTE_ROOT + ' && php artisan view:clear',
    'cd ' + REMOTE_ROOT + ' && php artisan cache:clear',
    'cd ' + REMOTE_ROOT + ' && php artisan optimize',
]
for cmd in cmds:
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    label = cmd.split('&&')[-1].strip()
    print(f'  $ {label}')
    if out:
        print(f'    {out[:300]}')
    if err and 'INFO' not in err and 'Discovered' not in err:
        print(f'    ERR: {err[:300]}')

ssh.close()
print('\nFix deploy completado.')
print('Sitio: https://lightcyan-mallard-509513.hostingersite.com/')
