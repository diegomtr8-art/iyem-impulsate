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

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Connecting...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH)
sftp = ssh.open_sftp()

# Build assets
print('\n--- Build assets ---')
upload_dir(sftp,
    os.path.join(LOCAL_ROOT, 'public', 'build'),
    f'{REMOTE_ROOT}/public/build')

# PHP / migrations / models / controllers
print('\n--- PHP files ---')
files_to_upload = [
    'routes/web.php',
    'app/Models/Edicion.php',
    'app/Models/Cita.php',
    'app/Models/Restaurantero.php',
    'app/Http/Controllers/CitaPublicaController.php',
    'app/Http/Controllers/RestauranteroPublicoController.php',
    'app/Http/Controllers/Admin/EdicionController.php',
    'database/migrations/2026_05_22_100000_create_ediciones_table.php',
    'database/migrations/2026_05_22_100001_add_edicion_id_to_citas_table.php',
    'database/migrations/2026_05_22_100002_add_edicion_id_to_restauranteros_table.php',
]
for rel_path in files_to_upload:
    local_file = os.path.join(LOCAL_ROOT, rel_path.replace('/', os.sep))
    remote_file = f'{REMOTE_ROOT}/{rel_path}'
    upload_file(sftp, local_file, remote_file)

sftp.close()

print('\n--- Artisan commands ---')
cmds = [
    'php artisan migrate --force',
    'php artisan route:clear',
    'php artisan route:cache',
    'php artisan cache:clear',
    'php artisan view:clear',
    'php artisan optimize',
]
for cmd in cmds:
    stdin, stdout, stderr = ssh.exec_command(f'cd {REMOTE_ROOT} && {cmd}')
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    print(f'  $ {cmd}')
    if out:
        print(f'    {out[:500]}')
    if err and 'INFO' not in err:
        print(f'    ERR: {err[:300]}')

ssh.close()
print('\nDeploy completado.')
