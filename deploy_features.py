# Deploy completo de 12 features a Hostinger.
import paramiko
import os
import sys

HOST        = '195.35.38.222'
PORT        = 65002
USER        = 'u489236361'
KEY_PATH    = 'C:/Users/Diego Martinez/.ssh/id_deploy'
REMOTE_ROOT = '/home/u489236361/domains/lightcyan-mallard-509513.hostingersite.com/public_html'
LOCAL_ROOT  = 'C:/xampp/htdocs/citas'

# === helpers ===============================================================
def sftp_mkdir_p(sftp, remote_path):
    dirs, path = [], remote_path
    while True:
        try:
            sftp.stat(path); break
        except FileNotFoundError:
            dirs.append(path)
            path = path.rsplit('/', 1)[0]
            if not path: break
    for d in reversed(dirs):
        try: sftp.mkdir(d)
        except Exception: pass

def up(sftp, local_rel, remote_rel=None):
    if remote_rel is None:
        remote_rel = local_rel.replace('\\', '/')
    local_path  = os.path.join(LOCAL_ROOT, local_rel.replace('/', os.sep))
    remote_path = f'{REMOTE_ROOT}/{remote_rel}'
    if not os.path.exists(local_path):
        print(f'  SKIP (not found): {local_rel}')
        return
    sftp_mkdir_p(sftp, remote_path.rsplit('/', 1)[0])
    sftp.put(local_path, remote_path)
    print(f'  UP  {remote_rel}')

def up_dir(sftp, local_dir_rel, remote_dir_rel):
    local_dir  = os.path.join(LOCAL_ROOT, local_dir_rel.replace('/', os.sep))
    remote_dir = f'{REMOTE_ROOT}/{remote_dir_rel}'
    count = 0
    for root, dirs, files in os.walk(local_dir):
        # Excluir directorios innecesarios
        dirs[:] = [d for d in dirs if d not in ['.git', '__pycache__', 'node_modules']]
        for fname in files:
            local_file = os.path.join(root, fname)
            rel = os.path.relpath(local_file, local_dir).replace('\\', '/')
            remote_path = f'{remote_dir}/{rel}'
            sftp_mkdir_p(sftp, remote_path.rsplit('/', 1)[0])
            sftp.put(local_file, remote_path)
            count += 1
    print(f'  {count} archivos en {remote_dir_rel}/')
    return count

def ssh_run(ssh, cmd, label=None):
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode('utf-8', errors='replace').strip()
    err = stderr.read().decode('utf-8', errors='replace').strip()
    tag = label or cmd.split('&&')[-1].strip()[:60]
    print(f'  $ {tag}')
    if out:
        for line in out.split('\n')[:6]:
            print(f'      {line}')
    if err:
        # Filtrar mensajes informativos de Laravel/Composer
        noise = ('INFO', 'Discovered', 'Compiling', 'Publishing', 'Loading', 'No changes')
        filtered = [l for l in err.split('\n') if not any(l.startswith(n) or n in l for n in noise)]
        if filtered:
            for line in filtered[:5]:
                print(f'      ERR: {line}')

# === archivos PHP modificados/nuevos ========================================
PHP_FILES = [
    # Models
    'app/Models/User.php',
    'app/Models/Cita.php',
    'app/Models/Restaurantero.php',
    'app/Models/Notificacion.php',
    # Controllers — Auth
    'app/Http/Controllers/Auth/RegistroProveedorController.php',
    'app/Http/Controllers/Auth/SwitchRoleController.php',
    'app/Http/Controllers/Auth/SocialAuthController.php',
    # Controllers — General
    'app/Http/Controllers/CompletarPerfilController.php',
    'app/Http/Controllers/ProveedorPerfilController.php',
    'app/Http/Controllers/RestauranteroCitasController.php',
    'app/Http/Controllers/NotificacionController.php',
    'app/Http/Controllers/CitaPublicaController.php',
    'app/Http/Controllers/RestauranteroPanelController.php',
    # Controllers — Admin
    'app/Http/Controllers/Admin/RestauranteroAdminController.php',
    'app/Http/Controllers/Admin/CitaAdminController.php',
    'app/Http/Controllers/Admin/ExportController.php',
    'app/Http/Controllers/Admin/PantallaTvController.php',
    # Middleware
    'app/Http/Middleware/HandleInertiaRequests.php',
    'app/Http/Middleware/EnsureProfileComplete.php',
    'app/Http/Middleware/EnsureIsAdmin.php',
    # Mail
    'app/Mail/CitaAgendada.php',
    'app/Mail/CitaAceptada.php',
    'app/Mail/CitaRechazada.php',
    'app/Mail/CitaReagendada.php',
    'app/Mail/CitaConfirmada.php',
    'app/Mail/CitaCancelada.php',
    'app/Mail/ProveedorAprobado.php',
    'app/Mail/ProveedorRechazado.php',
    'app/Mail/RecordatorioCita.php',
    # Jobs
    'app/Jobs/RecordatorioCita24h.php',
    'app/Jobs/RecordatorioCita2h.php',
    # Commands
    'app/Console/Commands/EnviarRecordatorios.php',
    # Actions/Fortify
    'app/Actions/Fortify/CreateNewUser.php',
    # Exports
    'app/Exports/CompradoresExport.php',
    'app/Exports/ProveedoresExport.php',
    'app/Exports/CitasExport.php',
    # Bootstrap / Config / Routes
    'bootstrap/app.php',
    'config/fortify.php',
    'config/mail.php',
    'routes/web.php',
    'routes/api.php',
    'routes/console.php',
    # Composer
    'composer.json',
    'composer.lock',
]

# === migraciones nuevas ======================================================
MIGRATIONS = [
    'database/migrations/2026_06_03_000001_add_foreign_keys_to_edicion_id_columns.php',
    'database/migrations/2026_06_03_000002_add_performance_indexes_to_citas_table.php',
    'database/migrations/2026_06_03_000003_verify_existing_users.php',
    'database/migrations/2026_06_03_200001_add_dual_role_fields_to_users_table.php',
    'database/migrations/2026_06_03_200002_add_gestion_fields_to_citas_table.php',
    'database/migrations/2026_06_03_200003_create_notificaciones_table.php',
    'database/migrations/2026_06_03_200004_add_aprobacion_to_restauranteros_table.php',
    'database/migrations/2026_06_03_200005_add_profile_fields_to_users_table.php',
]

# === vistas Blade ============================================================
BLADE_FILES = [
    'resources/views/mail/cita-agendada.blade.php',
    'resources/views/mail/cita-aceptada.blade.php',
    'resources/views/mail/cita-rechazada.blade.php',
    'resources/views/mail/cita-reagendada.blade.php',
    'resources/views/mail/cita-cancelada.blade.php',
    'resources/views/mail/cita-confirmada.blade.php',
    'resources/views/mail/confirmacion-exitosa.blade.php',
    'resources/views/mail/proveedor-aprobado.blade.php',
    'resources/views/mail/proveedor-rechazado.blade.php',
    'resources/views/mail/recordatorio-cita.blade.php',
    'resources/views/vendor/notifications/email.blade.php',
]

# === comandos SSH post-upload =================================================
SSH_CMDS = [
    ('Instalar dependencias (composer install)',
     f'cd {REMOTE_ROOT} && composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -8'),
    ('Ejecutar migraciones',
     f'cd {REMOTE_ROOT} && php artisan migrate --force 2>&1'),
    ('Limpiar caché de configuración',
     f'cd {REMOTE_ROOT} && php artisan config:clear 2>&1'),
    ('Limpiar caché de rutas',
     f'cd {REMOTE_ROOT} && php artisan route:clear 2>&1'),
    ('Limpiar caché de vistas',
     f'cd {REMOTE_ROOT} && php artisan view:clear 2>&1'),
    ('Cache configuración',
     f'cd {REMOTE_ROOT} && php artisan config:cache 2>&1'),
    ('Cache rutas',
     f'cd {REMOTE_ROOT} && php artisan route:cache 2>&1'),
    ('Cache vistas',
     f'cd {REMOTE_ROOT} && php artisan view:cache 2>&1'),
    ('Optimizar',
     f'cd {REMOTE_ROOT} && php artisan optimize 2>&1'),
    ('Verificar rutas (conteo)',
     f'cd {REMOTE_ROOT} && php artisan route:list 2>&1 | wc -l'),
]

# === main ==================================================================
def main():
    print('='*60)
    print('  DEPLOY — Impulsate 12 Features')
    print(f'  Host: {HOST}:{PORT}')
    print(f'  Destino: {REMOTE_ROOT}')
    print('='*60)

    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    print('\nConectando a Hostinger...')
    ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
    print('  OK Conectado\n')
    sftp = ssh.open_sftp()

    print('=== PHP Backend =====================================')
    for f in PHP_FILES:
        up(sftp, f)

    print('\n=== Migraciones =====================================')
    for f in MIGRATIONS:
        up(sftp, f)

    print('\n=== Vistas Blade (email templates) ==================')
    for f in BLADE_FILES:
        up(sftp, f)

    print('\n=== Assets compilados (public/build/) ===============')
    up_dir(sftp, 'public/build', 'build')

    sftp.close()
    print('\n=== Comandos SSH ====================================')
    for label, cmd in SSH_CMDS:
        print(f'\n[ {label} ]')
        ssh_run(ssh, cmd, label)

    ssh.close()
    print('\n' + '='*60)
    print('  OK DEPLOY COMPLETADO')
    print(f'  Sitio: https://lightcyan-mallard-509513.hostingersite.com/')
    print('='*60)

if __name__ == '__main__':
    main()
