"""Backup DB + LimpiarYResetearSeeder + DemoDataSeeder en produccion"""
import paramiko, os, re, sys
from datetime import datetime

# Forzar UTF-8 en stdout para caracteres especiales
sys.stdout.reconfigure(encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando a Hostinger...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')

CD = 'cd ' + RROOT + ' && '

def run(cmd, label=None, show_all=False):
    if label: print('\n  >> ' + label)
    _, o, e = ssh.exec_command(cmd)
    exit_code = o.channel.recv_exit_status()
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    lines = out.split('\n')
    for l in (lines if show_all else lines[-30:]):
        if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-5:]:
            if l.strip(): print('  ERR: ' + l)
    return out, exit_code

# ── Leer credenciales de .env ─────────────────────────────────────────────────
print('\n=== Leyendo credenciales de .env ===')
env_raw, _ = run('cat ' + RROOT + '/.env')
db_db   = re.search(r'^DB_DATABASE=(.+)$', env_raw, re.M)
db_user = re.search(r'^DB_USERNAME=(.+)$', env_raw, re.M)
db_pass = re.search(r'^DB_PASSWORD=(.+)$', env_raw, re.M)
db_host = re.search(r'^DB_HOST=(.+)$',     env_raw, re.M)

DB_DB   = db_db.group(1).strip()   if db_db   else ''
DB_USER = db_user.group(1).strip() if db_user else ''
DB_PASS = db_pass.group(1).strip() if db_pass else ''
DB_HOST = db_host.group(1).strip() if db_host else '127.0.0.1'

print('  DB: ' + DB_DB + ' @ ' + DB_HOST + ' user=' + DB_USER)

# ── Backup via archivo .my.cnf temporal (evita problemas con chars especiales) ─
ts = datetime.now().strftime('%Y%m%d_%H%M%S')
backup_remote = '/home/u489236361/backup_impulsate_' + ts + '.sql'
backup_local  = 'C:/xampp/htdocs/citas/backup_impulsate_' + ts + '.sql'
cnf_path = '/home/u489236361/.mybackup_tmp.cnf'

print('\n=== Backup mysqldump ===')

# Escribir cnf temporal con la contrasena
sftp = ssh.open_sftp()
cnf_content = '[client]\nuser=' + DB_USER + '\npassword=' + DB_PASS + '\nhost=' + DB_HOST + '\n'
with sftp.open(cnf_path, 'w') as f:
    f.write(cnf_content)
run('chmod 600 ' + cnf_path)

dump_cmd = 'mysqldump --defaults-file=' + cnf_path + ' ' + DB_DB + ' > ' + backup_remote + ' 2>&1'
out, code = run(dump_cmd, 'mysqldump')
if code != 0:
    print('  Error en mysqldump (code ' + str(code) + '): ' + out[:200])

# Limpiar cnf temporal
run('rm -f ' + cnf_path)

size_out, _ = run('wc -c ' + backup_remote)
print('  Tamano remote: ' + size_out)

print('  Descargando backup -> ' + backup_local)
sftp.get(backup_remote, backup_local)
sftp.close()
local_size = os.path.getsize(backup_local)
print('  Guardado local: ' + str(local_size) + ' bytes')

if local_size < 5000:
    print('\n  ADVERTENCIA: El backup es muy pequeno (' + str(local_size) + ' bytes). Puede estar vacio.')
    print('  Presiona Ctrl+C para cancelar o espera 5 segundos...')
    import time; time.sleep(5)
else:
    print('  Backup OK: ' + str(local_size // 1024) + ' KB')

# ── Ejecutar seeders ───────────────────────────────────────────────────────────
print('\n=== LimpiarYResetearSeeder ===')
out, code = run(CD + 'php artisan db:seed --class=LimpiarYResetearSeeder --force 2>&1',
    'LimpiarYResetearSeeder', show_all=True)
if code != 0:
    print('  FALLO (code ' + str(code) + ')')
    ssh.close()
    exit(1)

print('\n=== DemoDataSeeder ===')
out, code = run(CD + 'php artisan db:seed --class=DemoDataSeeder --force 2>&1',
    'DemoDataSeeder', show_all=True)
if code != 0:
    print('  FALLO (code ' + str(code) + ')')
    ssh.close()
    exit(1)

print('\n=== Optimize final ===')
run(CD + 'php artisan optimize:clear 2>&1', 'optimize:clear')
run(CD + 'php artisan optimize 2>&1', 'optimize')

ssh.close()
print('\n=== SEEDERS COMPLETADOS ===')
print('Backup local: ' + backup_local)
print('URL: https://impulsate.iyemyucatan.com')
print('Admin: impulsate@iyemyucatan.com / password')
