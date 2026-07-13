"""Limpiar + DemoDataSeeder (sin backup, ya existe uno reciente)"""
import paramiko, sys
sys.stdout.reconfigure(encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('OK')
CD = 'cd ' + RROOT + ' && '

def run(cmd, label=None):
    if label: print('\n  >> ' + label)
    _, o, e = ssh.exec_command(cmd)
    code = o.channel.recv_exit_status()
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    for l in out.split('\n'):
        if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-3:]:
            if l.strip(): print('  ERR: ' + l)
    return code

print('\n=== LimpiarYResetearSeeder ===')
code = run(CD + 'php artisan db:seed --class=LimpiarYResetearSeeder --force 2>&1', 'Limpiar')
if code != 0:
    print('  FALLO — abortando'); ssh.close(); exit(1)

print('\n=== DemoDataSeeder ===')
code = run(CD + 'php artisan db:seed --class=DemoDataSeeder --force 2>&1', 'Demo')
if code != 0:
    print('  FALLO (code ' + str(code) + ')'); ssh.close(); exit(1)

ssh.close()
print('\n=== TODO LISTO ===')
print('Admin:       impulsate@iyemyucatan.com / password')
print('Proveedores: proveedor1..10@demo.impulsate.test / password')
print('Compradores: comprador1..15@demo.impulsate.test / password')
