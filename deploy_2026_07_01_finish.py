"""Continua el deploy tras el error de encoding: seed plantillas + optimize + opcache reset."""
import paramiko, sys

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'

def run(ssh, cmd):
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    for l in out.split('\n')[-20:]:
        if l.strip():
            sys.stdout.buffer.write(('  ' + l + '\n').encode('utf-8', 'replace'))
    if err:
        for l in err.split('\n')[-8:]:
            if l.strip():
                sys.stdout.buffer.write(('  ERR: ' + l + '\n').encode('utf-8', 'replace'))
    sys.stdout.flush()

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')

print('\n=== Seed PlantillasCorreo + Optimize ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan db:seed --class=PlantillasCorreoSeeder --force 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan config:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan route:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan view:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan optimize 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan queue:restart 2>&1')

ssh.close()
print('\nDeploy completado.')
