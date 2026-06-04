import paramiko

HOST = '195.35.38.222'
PORT = 65002
USER = 'u489236361'
KEY_PATH = r'C:\Users\Diego Martinez\.ssh\id_deploy'
REMOTE_ROOT = '/home/u489236361/domains/lightcyan-mallard-509513.hostingersite.com/public_html'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH)

checks = [
    # Ver estructura del directorio
    f'ls {REMOTE_ROOT}/ | head -20',
    f'ls {REMOTE_ROOT}/build/ 2>/dev/null | head -10 || echo "NO /build dir"',
    f'ls {REMOTE_ROOT}/public/build/ 2>/dev/null | head -10 || echo "NO /public/build dir"',
    # Ver el manifest
    f'cat {REMOTE_ROOT}/build/manifest.json 2>/dev/null | head -5 || echo "NO build/manifest"',
    f'cat {REMOTE_ROOT}/public/build/manifest.json 2>/dev/null | head -5 || echo "NO public/build/manifest"',
    # Ver index.php para saber donde esta Laravel
    f'head -10 {REMOTE_ROOT}/index.php 2>/dev/null || echo "NO index.php in root"',
    f'head -10 {REMOTE_ROOT}/public/index.php 2>/dev/null || echo "NO public/index.php"',
    # Rutas web
    f'head -60 {REMOTE_ROOT}/routes/web.php 2>/dev/null | grep -E "Route|get|post" || echo "routes not at root"',
    f'head -60 {REMOTE_ROOT}/../routes/web.php 2>/dev/null | grep -E "Route|get|post" || echo "routes not at parent"',
    # Probar la URL
    f'curl -s -L -o /dev/null -w "%{{http_code}}" https://lightcyan-mallard-509513.hostingersite.com/',
    f'curl -s https://lightcyan-mallard-509513.hostingersite.com/ | grep -o "Welcome to your\|IYEM\|Networking" | head -5',
]

for cmd in checks:
    print(f'\n$ {cmd[:80]}')
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    if out: print(out[:400])
    if err and 'Warning' not in err: print('ERR:', err[:200])

ssh.close()
