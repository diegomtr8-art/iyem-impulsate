import paramiko
import os
import stat

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
    remote_dir = remote_path.rsplit('/', 1)[0]
    sftp_mkdir_p(sftp, remote_dir)
    sftp.put(local_path, remote_path)
    print(f'  UP {remote_path}')

def upload_dir(sftp, local_dir, remote_dir):
    for root, dirs, files in os.walk(local_dir):
        for fname in files:
            local_file = os.path.join(root, fname)
            rel = os.path.relpath(local_file, local_dir).replace('\\', '/')
            remote_file = f'{remote_dir}/{rel}'
            upload_file(sftp, local_file, remote_file)

# Files changed in Fase 3
SINGLE_FILES = [
    ('app/Http/Controllers/CitaPublicaController.php',         'app/Http/Controllers/CitaPublicaController.php'),
    ('app/Http/Controllers/RestauranteroPanelController.php',  'app/Http/Controllers/RestauranteroPanelController.php'),
    ('routes/web.php',                                         'routes/web.php'),
    ('resources/js/Pages/Welcome.vue',                         'resources/js/Pages/Welcome.vue'),
    ('resources/js/Pages/User/Dashboard.vue',                  'resources/js/Pages/User/Dashboard.vue'),
    ('resources/js/Pages/Restauranteros/Show.vue',             'resources/js/Pages/Restauranteros/Show.vue'),
    ('resources/js/Pages/Restaurantero/Panel.vue',             'resources/js/Pages/Restaurantero/Panel.vue'),
]

print('Connecting...')
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH)
sftp = ssh.open_sftp()

print('\n--- Uploading changed PHP/Vue files ---')
for local_rel, remote_rel in SINGLE_FILES:
    local_path = os.path.join(LOCAL_ROOT, local_rel.replace('/', os.sep))
    remote_path = f'{REMOTE_ROOT}/{remote_rel}'
    upload_file(sftp, local_path, remote_path)

print('\n--- Uploading public/build to root build/ (server serves from public_html root) ---')
upload_dir(sftp, os.path.join(LOCAL_ROOT, 'public', 'build'), f'{REMOTE_ROOT}/build')

sftp.close()

print('\n--- Running artisan commands ---')
commands = [
    'php artisan cache:clear',
    'php artisan config:clear',
    'php artisan view:clear',
    'php artisan route:clear',
    'php artisan optimize',
]
for cmd in commands:
    full_cmd = f'cd {REMOTE_ROOT} && {cmd}'
    stdin, stdout, stderr = ssh.exec_command(full_cmd)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    print(f'  $ {cmd}')
    if out:
        print(f'    {out}')
    if err:
        print(f'    ERR: {err}')

ssh.close()
print('\nDeploy Fase 3 completado.')
