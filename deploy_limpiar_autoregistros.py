"""Deploy — Limpieza de auto-registros en evento_usuario + confirmar fixes"""
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

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando a Hostinger...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')
sftp = ssh.open_sftp()

# ── 1. Subir controladores corregidos (confirmar que están actualizados) ───────
print('\n=== [1/5] Controladores del fix (re-confirmar en producción) ===')
up(sftp, 'app/Http/Controllers/Auth/SeleccionarRolController.php')
up(sftp, 'app/Http/Controllers/Auth/RegistroProveedorController.php')
up(sftp, 'app/Http/Controllers/CompletarPerfilController.php')

# ── 2. Ver estado actual de evento_usuario ANTES de limpiar ───────────────────
print('\n=== [2/5] Estado ANTES de limpiar ===')
run(ssh, r"""cd """ + RROOT + r""" && php artisan tinker --execute="
\$rows = DB::table('evento_usuario')
    ->join('eventos', 'eventos.id', '=', 'evento_usuario.evento_id')
    ->join('users',   'users.id',   '=', 'evento_usuario.user_id')
    ->select('eventos.nombre as evento','users.name as usuario','users.email','evento_usuario.tipo','evento_usuario.estado','evento_usuario.created_at')
    ->orderBy('evento_usuario.evento_id')
    ->orderBy('evento_usuario.tipo')
    ->get();
echo 'Total registros en evento_usuario: ' . \$rows->count() . PHP_EOL;
foreach(\$rows as \$r) {
    echo '  [' . \$r->tipo . '] ' . \$r->usuario . ' <' . \$r->email . '> estado=' . \$r->estado . ' evento=' . \$r->evento . PHP_EOL;
}
" 2>&1""")

# ── 3. Limpiar TODOS los registros de evento_usuario ─────────────────────────
print('\n=== [3/5] Limpiando evento_usuario (todos los registros auto-generados) ===')
run(ssh, r"""cd """ + RROOT + r""" && php artisan tinker --execute="
\$total = DB::table('evento_usuario')->count();
DB::table('evento_usuario')->delete();
echo 'Registros eliminados: ' . \$total . PHP_EOL;
echo 'Tabla evento_usuario ahora tiene: ' . DB::table('evento_usuario')->count() . ' registros.' . PHP_EOL;
" 2>&1""")

# ── 4. Confirmar que quedó vacía ───────────────────────────────────────────────
print('\n=== [4/5] Verificación DESPUÉS de limpiar ===')
run(ssh, r"""cd """ + RROOT + r""" && php artisan tinker --execute="
\$count = DB::table('evento_usuario')->count();
echo 'evento_usuario count: ' . \$count . PHP_EOL;
\$evento = \App\Models\Evento::where('activa', true)->first();
echo 'Evento activo: ' . (\$evento ? \$evento->nombre . ' (id=' . \$evento->id . ')' : 'ninguno') . PHP_EOL;
" 2>&1""")

# ── 5. Optimizar caché ────────────────────────────────────────────────────────
print('\n=== [5/5] Optimización ===')
run(ssh, 'cd ' + RROOT + ' && php artisan config:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan route:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan view:clear 2>&1')
run(ssh, 'cd ' + RROOT + ' && php artisan optimize 2>&1')

sftp.close(); ssh.close()
print('\n' + '='*60)
print('LISTO — Auto-registros eliminados + fixes activos en producción')
print('URL: https://impulsate.iyemyucatan.com/')
print('='*60)
