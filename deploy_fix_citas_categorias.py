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
    f'{REMOTE_ROOT}/build')

# PHP files
print('\n--- PHP files ---')
files_to_upload = [
    'app/Http/Controllers/Admin/RestauranteroAdminController.php',
]
for rel_path in files_to_upload:
    local_file = os.path.join(LOCAL_ROOT, rel_path.replace('/', os.sep))
    remote_file = f'{REMOTE_ROOT}/{rel_path}'
    upload_file(sftp, local_file, remote_file)

sftp.close()

print('\n--- Fix: assign null-edicion_id citas to active edition ---')
tinker_code = (
    r'$ed = \App\Models\Edicion::activa();'
    r' if ($ed) {'
    r' $n = \App\Models\Cita::whereNull("edicion_id")->count();'
    r' \App\Models\Cita::whereNull("edicion_id")->update(["edicion_id" => $ed->id]);'
    r' echo "Assigned " . $n . " citas to edicion_id=" . $ed->id . "\n";'
    r' } else { echo "No active edition found\n"; }'
)
cmd = f'cd {REMOTE_ROOT} && php artisan tinker --execute=\'{tinker_code}\''
stdin, stdout, stderr = ssh.exec_command(cmd)
out = stdout.read().decode().strip()
err = stderr.read().decode().strip()
if out:
    print(f'    {out}')
if err and 'INFO' not in err and 'Psy Shell' not in err:
    print(f'    ERR: {err[:300]}')

print('\n--- Artisan cache ---')
cmds = [
    'php artisan route:clear',
    'php artisan route:cache',
    'php artisan view:clear',
    'php artisan cache:clear',
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
