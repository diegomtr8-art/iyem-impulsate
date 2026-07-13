"""Deploy: landing page v2 — stats x4, sin propuesta de valor, FAQ accordion"""
import paramiko, os

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
    mkdirp(sftp, rp.rsplit('/', 1)[0])
    sftp.put(lp, rp)
    print('  UP  ' + rr)


def updir(sftp, ld, rd):
    ldir = os.path.join(LROOT, ld.replace('/', os.sep))
    rdir = RROOT + '/' + rd; n = 0
    for root, dirs, files in os.walk(ldir):
        dirs[:] = [d for d in dirs if d not in {'.git', '__pycache__', 'node_modules'}]
        for f in files:
            lf = os.path.join(root, f)
            rel = os.path.relpath(lf, ldir).replace('\\', '/')
            rp = rdir + '/' + rel
            mkdirp(sftp, rp.rsplit('/', 1)[0])
            sftp.put(lf, rp); n += 1
    print('  ' + str(n) + ' archivos -> ' + rd)


def run(ssh, cmd, lines=12):
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    for l in (out.split('\n') or [])[-lines:]:
        if l.strip(): print('    ' + l)
    if err:
        for l in err.split('\n')[-4:]:
            if l.strip(): print('    ERR: ' + l)


ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('OK')
sftp = ssh.open_sftp()

print('\n=== [1] Welcome.vue ===')
up(sftp, 'resources/js/Pages/Welcome.vue')

print('\n=== [2] Assets compilados ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

sftp.close()

print('\n=== [3] Optimize ===')
run(ssh, 'cd ' + RROOT + ' && php artisan view:clear && php artisan optimize 2>&1')

ssh.close()
print('\n=== DEPLOY COMPLETADO ===')
print('URL: https://impulsate.iyemyucatan.com/')
