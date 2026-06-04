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
    f'ls {REMOTE_ROOT}/',
    f'ls {REMOTE_ROOT}/build/ 2>/dev/null | head -5 || echo "NO /build dir"',
    f'ls {REMOTE_ROOT}/public/build/ 2>/dev/null | head -5 || echo "NO /public/build dir"',
    f'head -5 {REMOTE_ROOT}/build/manifest.json 2>/dev/null || echo "NO build/manifest"',
    f'head -5 {REMOTE_ROOT}/public/build/manifest.json 2>/dev/null || echo "NO public/build/manifest"',
    f'head -8 {REMOTE_ROOT}/index.php 2>/dev/null || echo "NO index.php in root"',
    f'head -8 {REMOTE_ROOT}/public/index.php 2>/dev/null || echo "NO public/index.php"',
    f'grep -n "HOME\|dashboard" {REMOTE_ROOT}/app/Providers/RouteServiceProvider.php 2>/dev/null || grep -rn "HOME" {REMOTE_ROOT}/bootstrap/app.php 2>/dev/null | head -5',
    f'grep -n "dashboard\|HOME" {REMOTE_ROOT}/routes/web.php 2>/dev/null | head -10',
    f'curl -s https://lightcyan-mallard-509513.hostingersite.com/ 2>/dev/null | grep -o "Welcome to your\\|IYEM\\|Networking\\|Jetstream" | head -5',
]

for cmd in checks:
    print(f'\n=== {cmd[:80]} ===')
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    if out:
        print(out[:500])
    if err and 'Warning' not in err and 'Permission' not in err:
        print('ERR:', err[:200])

ssh.close()
