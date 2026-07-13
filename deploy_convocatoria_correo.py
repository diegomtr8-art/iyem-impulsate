"""Deploy — Convocatoria+carrusel, CANIRAC obligatorio, config correo Gmail + prueba"""
import sys, paramiko, os
sys.stdout.reconfigure(encoding='utf-8', errors='replace')

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

def up(sftp, lr, rr=None):
    if rr is None: rr = lr.replace('\\', '/')
    lp = os.path.join(LROOT, lr.replace('/', os.sep))
    rp = RROOT + '/' + rr
    if not os.path.exists(lp): print('  SKIP ' + lr); return
    mkdirp(sftp, rp.rsplit('/', 1)[0]); sftp.put(lp, rp); print('  UP  ' + rr)

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
    for l in (out + '\n' + err).split('\n')[-30:]:
        if l.strip(): print('  ' + l)

def run_out(ssh, cmd):
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    return out, err

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando a Hostinger...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')
sftp = ssh.open_sftp()

# ── Migración nueva ───────────────────────────────────────────────────────────
print('\n=== Migración ===')
up(sftp, 'database/migrations/2026_06_19_111149_add_convocatoria_y_carrusel_to_eventos_table.php')

# ── Modelos ──────────────────────────────────────────────────────────────────
print('\n=== Modelos ===')
up(sftp, 'app/Models/Evento.php')

# ── Controladores ─────────────────────────────────────────────────────────────
print('\n=== Controladores ===')
up(sftp, 'app/Http/Controllers/Admin/EventoController.php')
up(sftp, 'app/Http/Controllers/LandingController.php')
up(sftp, 'app/Http/Controllers/CompletarPerfilController.php')
up(sftp, 'app/Http/Controllers/CitaPublicaController.php')
up(sftp, 'app/Http/Controllers/RestauranteroPanelController.php')

# ── Assets compilados ─────────────────────────────────────────────────────────
print('\n=== Assets compilados (public/build) ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

# ── Migración en servidor ─────────────────────────────────────────────────────
print('\n=== Ejecutar migración en servidor ===')
run(ssh, 'cd ' + RROOT + ' && php artisan migrate --force 2>&1')

# ── Configurar SMTP en .env ───────────────────────────────────────────────────
print('\n=== Configurando SMTP en .env del servidor ===')
APP_PWD = 'ldmblvwnzkvjipoc'
env_path = RROOT + '/.env'

# Leer .env actual
out, err = run_out(ssh, 'cat ' + env_path)
env_content = out

def set_env(content, key, value):
    """Reemplaza o agrega una variable en el .env"""
    lines = content.split('\n')
    found = False
    for i, line in enumerate(lines):
        if line.startswith(key + '=') or line.startswith(key + ' ='):
            lines[i] = key + '=' + value
            found = True
            break
    if not found:
        lines.append(key + '=' + value)
    return '\n'.join(lines)

env_content = set_env(env_content, 'MAIL_MAILER',       'smtp')
env_content = set_env(env_content, 'MAIL_HOST',         'smtp.gmail.com')
env_content = set_env(env_content, 'MAIL_PORT',         '587')
env_content = set_env(env_content, 'MAIL_USERNAME',     'impulsate@iyemyucatan.com')
env_content = set_env(env_content, 'MAIL_PASSWORD',     APP_PWD)
env_content = set_env(env_content, 'MAIL_ENCRYPTION',   'tls')
env_content = set_env(env_content, 'MAIL_FROM_ADDRESS', '"impulsate@iyemyucatan.com"')
env_content = set_env(env_content, 'MAIL_FROM_NAME',    '"Encuentro de Negocios Impulsate"')
env_content = set_env(env_content, 'MAIL_SCHEME',       'null')

# Escribir .env actualizado via SFTP
import io
sftp.putfo(io.BytesIO(env_content.encode('utf-8')), env_path)
print('  .env actualizado con config Gmail TLS 587')

# ── Optimización ──────────────────────────────────────────────────────────────
print('\n=== Optimización ===')
run(ssh, 'cd ' + RROOT + ' && php artisan config:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan route:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan view:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan optimize 2>&1')

# ── Prueba de correo ──────────────────────────────────────────────────────────
print('\n=== Prueba de envío de correo ===')
tinker_cmd = (
    "cd " + RROOT + " && php artisan tinker --execute=\""
    "Mail::raw('Prueba SMTP desde Impulsate - Gmail configurado correctamente. Fecha: ' . now(), "
    "function(\$m) { \$m->to('iyem.informatica@iyemyucatan.com')->subject('Prueba SMTP Impulsate OK'); });"
    "echo 'CORREO_ENVIADO_OK';\" 2>&1"
)
run(ssh, tinker_cmd)

# ── Verificar config MAIL activa ──────────────────────────────────────────────
print('\n=== Config MAIL activa (verificación) ===')
run(ssh, "cd " + RROOT + " && php artisan tinker --execute=\""
    "echo 'HOST:' . config('mail.mailers.smtp.host') . ' PORT:' . config('mail.mailers.smtp.port') . "
    "' FROM:' . config('mail.from.address');\" 2>&1")

# ── Log final ─────────────────────────────────────────────────────────────────
print('\n=== Log servidor (últimas líneas) ===')
run(ssh, 'cd ' + RROOT + ' && tail -8 storage/logs/laravel.log 2>&1')

sftp.close(); ssh.close()
print('\n=== DEPLOY + CONFIG CORREO COMPLETADO ===')
print('Sitio: https://impulsate.iyemyucatan.com/')
print('AVISO: Revoca la App Password usada y genera una nueva en:')
print('       https://myaccount.google.com/apppasswords')
