import paramiko

HOST = '195.35.38.222'
PORT = 65002
USER = 'u489236361'
KEY_PATH = r'C:\Users\Diego Martinez\.ssh\id_deploy'
REMOTE_ROOT = '/home/u489236361/domains/lightcyan-mallard-509513.hostingersite.com/public_html'
URL = 'https://lightcyan-mallard-509513.hostingersite.com'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH)

checks = [
    # Confirm manifest is now correct in public/build/
    f'head -3 {REMOTE_ROOT}/public/build/manifest.json',
    # Check the homepage
    f'curl -s -o /dev/null -w "%{{http_code}}" {URL}/',
    f'curl -s {URL}/ | grep -o "IYEM\\|Networking\\|Jetstream\\|Welcome to your" | head -3',
    # Check admin page (should redirect to login)
    f'curl -s -o /dev/null -w "%{{http_code}}" {URL}/admin/',
    # Check /dashboard redirect (302 expected since auth required)
    f'curl -s -o /dev/null -w "%{{http_code}}" -L {URL}/dashboard',
    # Check routes are cached correctly
    f'php -r "echo json_encode(array_slice(array_keys(require \"{REMOTE_ROOT}/bootstrap/cache/routes-v7.php\"), 0, 5));" 2>/dev/null | head -3 || echo "route cache check skipped"',
]

labels = [
    'public/build/manifest.json (should have new hash Cv1xO9wg):',
    'HTTP status homepage (should be 200):',
    'Content keywords on homepage:',
    'HTTP status /admin/ (should be 302 redirect to login):',
    'HTTP status /dashboard following redirect (should be 302 to login):',
    'Route cache sample:',
]

for label, cmd in zip(labels, checks):
    print(f'\n{label}')
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    print(f'  {out[:300] if out else "(empty)"}')

ssh.close()
