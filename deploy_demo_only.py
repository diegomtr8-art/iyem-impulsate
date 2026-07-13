"""Subir DemoDataSeeder corregido y ejecutarlo en produccion"""
import paramiko, os, sys
sys.stdout.reconfigure(encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
sftp = ssh.open_sftp()

def up(lr, rr=None):
    if rr is None: rr = lr.replace('\\', '/')
    lp = os.path.join(LROOT, lr.replace('/', os.sep))
    rp = RROOT + '/' + rr
    sftp.put(lp, rp); print('  UP  ' + rr)

def run(cmd, label=None):
    if label: print('\n  >> ' + label)
    _, o, e = ssh.exec_command(cmd)
    o.channel.recv_exit_status()
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    for l in out.split('\n'):
        if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-3:]:
            if l.strip(): print('  ERR: ' + l)

print('\n=== Subiendo seeder corregido ===')
up('database/seeders/DemoDataSeeder.php')

sftp.close()

CD = 'cd ' + RROOT + ' && '
print('\n=== DemoDataSeeder ===')
run(CD + 'php artisan db:seed --class=DemoDataSeeder --force 2>&1', 'DemoDataSeeder')

ssh.close()
print('\n=== COMPLETADO ===')
print('Admin:       impulsate@iyemyucatan.com / password')
print('Proveedores: proveedor1..10@demo.impulsate.test / password')
print('Compradores: comprador1..15@demo.impulsate.test / password')
