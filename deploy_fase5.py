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

# All files changed in Fase 5
SINGLE_FILES = [
    # Config / build
    ('tailwind.config.js',                                          'tailwind.config.js'),
    ('resources/js/app.js',                                         'resources/js/app.js'),
    # New component
    ('resources/js/Components/ThemeToggle.vue',                     'resources/js/Components/ThemeToggle.vue'),
    # Layouts
    ('resources/js/Layouts/AppLayout.vue',                          'resources/js/Layouts/AppLayout.vue'),
    ('resources/js/Layouts/AdminLayout.vue',                        'resources/js/Layouts/AdminLayout.vue'),
    # Auth pages
    ('resources/js/Pages/Auth/Login.vue',                           'resources/js/Pages/Auth/Login.vue'),
    ('resources/js/Pages/Auth/Register.vue',                        'resources/js/Pages/Auth/Register.vue'),
    # Public pages
    ('resources/js/Pages/Welcome.vue',                              'resources/js/Pages/Welcome.vue'),
    ('resources/js/Pages/Restauranteros/Index.vue',                 'resources/js/Pages/Restauranteros/Index.vue'),
    # User & Proveedor pages
    ('resources/js/Pages/User/Dashboard.vue',                       'resources/js/Pages/User/Dashboard.vue'),
    ('resources/js/Pages/Restaurantero/Panel.vue',                  'resources/js/Pages/Restaurantero/Panel.vue'),
    # Admin pages
    ('resources/js/Pages/Admin/Dashboard.vue',                      'resources/js/Pages/Admin/Dashboard.vue'),
    ('resources/js/Pages/Admin/Metricas.vue',                       'resources/js/Pages/Admin/Metricas.vue'),
    ('resources/js/Pages/Admin/Citas/Index.vue',                    'resources/js/Pages/Admin/Citas/Index.vue'),
    ('resources/js/Pages/Admin/Usuarios/Index.vue',                 'resources/js/Pages/Admin/Usuarios/Index.vue'),
    ('resources/js/Pages/Admin/Calendario/Index.vue',               'resources/js/Pages/Admin/Calendario/Index.vue'),
    ('resources/js/Pages/Admin/Restauranteros/Index.vue',           'resources/js/Pages/Admin/Restauranteros/Index.vue'),
    ('resources/js/Pages/Admin/Restauranteros/Show.vue',            'resources/js/Pages/Admin/Restauranteros/Show.vue'),
    # PHP backend
    ('app/Http/Controllers/CitaPublicaController.php',              'app/Http/Controllers/CitaPublicaController.php'),
    ('app/Http/Controllers/Auth/SocialAuthController.php',          'app/Http/Controllers/Auth/SocialAuthController.php'),
    ('config/services.php',                                         'config/services.php'),
    ('routes/web.php',                                              'routes/web.php'),
]

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Connecting to Hostinger...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH)
sftp = ssh.open_sftp()

print('\n--- PHP/Vue files ---')
for local_rel, remote_rel in SINGLE_FILES:
    local_path = os.path.join(LOCAL_ROOT, local_rel.replace('/', os.sep))
    upload_file(sftp, local_path, f'{REMOTE_ROOT}/{remote_rel}')

print('\n--- Build assets (public/build/) ---')
count = upload_dir(sftp, os.path.join(LOCAL_ROOT, 'public', 'build'), f'{REMOTE_ROOT}/build')
print(f'  {count} asset files uploaded')

sftp.close()

print('\n--- SSH commands ---')
cmds = [
    'cd ' + REMOTE_ROOT + ' && composer require laravel/socialite --no-interaction 2>&1 | tail -5',
    'cd ' + REMOTE_ROOT + ' && php artisan cache:clear',
    'cd ' + REMOTE_ROOT + ' && php artisan config:cache',
    'cd ' + REMOTE_ROOT + ' && php artisan route:cache',
    'cd ' + REMOTE_ROOT + ' && php artisan view:clear',
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
print('\nDeploy Fase 5 completado.')
print('Sitio: https://lightcyan-mallard-509513.hostingersite.com/')
