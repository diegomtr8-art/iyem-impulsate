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
    for root, dirs, files in os.walk(local_dir):
        for fname in files:
            local_file = os.path.join(root, fname)
            rel = os.path.relpath(local_file, local_dir).replace('\\', '/')
            upload_file(sftp, local_file, f'{remote_dir}/{rel}')

SINGLE_FILES = [
    ('resources/js/Layouts/AppLayout.vue',              'resources/js/Layouts/AppLayout.vue'),
    ('resources/js/Layouts/AdminLayout.vue',            'resources/js/Layouts/AdminLayout.vue'),
    ('resources/js/Pages/Welcome.vue',                  'resources/js/Pages/Welcome.vue'),
    ('database/seeders/RolesAndPermissionsSeeder.php',  'database/seeders/RolesAndPermissionsSeeder.php'),
]

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Connecting...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH)
sftp = ssh.open_sftp()

print('\n--- PHP/Vue files ---')
for local_rel, remote_rel in SINGLE_FILES:
    upload_file(sftp, os.path.join(LOCAL_ROOT, local_rel.replace('/', os.sep)), f'{REMOTE_ROOT}/{remote_rel}')

print('\n--- Build assets (root build/) ---')
upload_dir(sftp, os.path.join(LOCAL_ROOT, 'public', 'build'), f'{REMOTE_ROOT}/build')

sftp.close()

print('\n--- Artisan commands ---')
cmds = [
    'php artisan db:seed --class=RolesAndPermissionsSeeder --force',
    'php artisan cache:clear',
    'php artisan config:cache',
    'php artisan route:cache',
    'php artisan view:clear',
    'php artisan optimize',
]
for cmd in cmds:
    stdin, stdout, stderr = ssh.exec_command(f'cd {REMOTE_ROOT} && {cmd}')
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    print(f'  $ {cmd}')
    if out:
        print(f'    {out[:200]}')
    if err and 'INFO' not in err:
        print(f'    ERR: {err[:200]}')

ssh.close()
print('\nDeploy Fase 4 completado.')
