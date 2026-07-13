import paramiko

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
RROOT_LC = '/home/u489236361/domains/lightcyan-mallard-509513.hostingersite.com/public_html'
RROOT_IMP = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'

def run(ssh, cmd):
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    if out:
        for l in out.split('\n'):
            if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-3:]:
            if l.strip(): print('  ERR: ' + l)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename='C:/Users/Diego Martinez/.ssh/id_deploy', timeout=30)
print('Conectado')

print('\n=== lightcyan build dirs ===')
run(ssh, 'ls -la ' + RROOT_LC + '/public/build/manifest.json 2>&1')
run(ssh, 'ls -la ' + RROOT_LC + '/build/manifest.json 2>&1')

print('\n=== impulsate estructura ===')
run(ssh, 'ls ' + RROOT_IMP + '/ 2>&1')

print('\n=== impulsate build ===')
run(ssh, 'ls -la ' + RROOT_IMP + '/build/manifest.json 2>&1')
run(ssh, 'ls -la ' + RROOT_IMP + '/public/build/manifest.json 2>&1')

print('\n=== .env APP_URL en lightcyan ===')
run(ssh, 'grep APP_URL ' + RROOT_LC + '/.env 2>&1')

print('\n=== .env APP_URL en impulsate ===')
run(ssh, 'grep APP_URL ' + RROOT_IMP + '/.env 2>&1')

ssh.close()
print('Listo')
