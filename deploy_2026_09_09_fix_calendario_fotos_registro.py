# -*- coding: utf-8 -*-
"""Deploy 2026-09-09: 3 bugs reportados por Diego.

  1. resources/js/Pages/Restaurantero/Panel.vue
     El feed del calendario apuntaba a /restaurantero/panel/eventos (404). La ruta
     real es /proveedor/panel/eventos: FullCalendar tiraba "Request failed" en la
     consola cada vez que se pintaba el panel. Ahora usa route().

  2. resources/js/Pages/Perfil/Show.vue + ProveedorPerfilController.php
     Fotos de producto que "no se guardaban":
       - accept="image/*" dejaba elegir HEIC/GIF/AVIF que el backend rechaza
         (mimes:jpg,jpeg,png,webp) -> 422.
       - submitProveedor no tenia onError ni pintaba formProveedor.errors: ese 422
         era invisible y NO se guardaba nada del perfil.
       - una fila sin nombre con foto tiraba la imagen en silencio y corria los
         indices de productos_top.

  3. resources/js/Components/TabEventos.vue
     El aviso de "solicitud registrada" era texto fijo y se mostraba tambien cuando
     el servidor respondia "Ya tienes una solicitud registrada" (que NO manda
     correo). Ahora se usa el flash del servidor.

NO hay migraciones. NO se toca .env ni composer.

El build de Vite es todo-o-nada: se sube public/build COMPLETO y se re-copia a
$WEBROOT/build, que es lo que Hostinger sirve. Backup de ambos antes.
"""
import paramiko, os, sys, io, hashlib, tarfile

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'
STAMP = '20260909fix'

CODE_FILES = [
    'app/Http/Controllers/ProveedorPerfilController.php',
    'resources/js/Pages/Restaurantero/Panel.vue',
    'resources/js/Pages/Perfil/Show.vue',
    'resources/js/Components/TabEventos.vue',
]

# Estado de produccion verificado a mano ANTES de editar (diff limpio: prod solo
# se diferencia del local en los cambios de hoy). Si prod ya no coincide con esto,
# alguien lo toco entre medias y no se pisa.
PROD_BASELINE = {
    'app/Http/Controllers/ProveedorPerfilController.php': '002f87a02acb65ef561a594d696c2a33',
    'resources/js/Pages/Restaurantero/Panel.vue':         '7995daddfc004f15a55275b7cad0a0aa',
    'resources/js/Pages/Perfil/Show.vue':                 '3af82e8ad1d10b673bf8750919b7469f',
    'resources/js/Components/TabEventos.vue':             '5bc3e9b50c9c41a7201e09714f3288a4',
}

os.chdir(LROOT)


def md5_local(p):
    with open(p, 'rb') as f:
        return hashlib.md5(f.read()).hexdigest()


# -- 0. Comprobaciones locales -------------------------------------------
manifest = 'public/build/manifest.json'
if not os.path.exists(manifest):
    sys.exit('ABORTA: falta %s. Corre npm run build antes.' % manifest)

build_files = []
for root, _dirs, files in os.walk('public/build'):
    for f in files:
        build_files.append(os.path.join(root, f).replace('\\', '/'))
print('Build local: %d archivos, manifest md5=%s' % (len(build_files), md5_local(manifest)))

for f in CODE_FILES:
    if not os.path.exists(f):
        sys.exit('ABORTA: no existe %s' % f)

# -- 1. Conexion ---------------------------------------------------------
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
sftp = ssh.open_sftp()


def run(cmd, label=None, fatal=True):
    _in, out, err = ssh.exec_command('cd %s && %s' % (RROOT, cmd), timeout=600)
    code = out.channel.recv_exit_status()
    o = out.read().decode('utf-8', 'replace').strip()
    e = err.read().decode('utf-8', 'replace').strip()
    if label:
        print('  [%s] %s' % ('ok' if code == 0 else 'FALLO', label))
    if o:
        print('    ' + o.replace('\n', '\n    ')[:2000])
    if e:
        print('    stderr: ' + e.replace('\n', '\n    ')[:2000])
    if code != 0 and fatal:
        sftp.close(); ssh.close()
        sys.exit('ABORTA: fallo "%s" (exit %d)' % (label or cmd, code))
    return o


# -- 2. Guard: prod debe seguir en el estado que verificamos -------------
print('\n== Verificacion pre-deploy ==')
out = run('md5sum %s' % ' '.join(CODE_FILES), fatal=True)
for line in out.splitlines():
    h, f = line.split()
    if h != PROD_BASELINE[f]:
        sftp.close(); ssh.close()
        sys.exit('ABORTA: %s en produccion (%s) ya no es el estado verificado (%s). '
                 'Alguien lo cambio; revisa antes de pisarlo.' % (f, h, PROD_BASELINE[f]))
    print('  [ok] %s coincide con el estado verificado' % f)

# -- 3. Backup del build servido -----------------------------------------
print('\n== Backup ==')
run('rm -rf build_bak_%s public/build_bak_%s' % (STAMP, STAMP), 'limpiar backups previos', fatal=False)
run('cp -a build build_bak_%s' % STAMP, 'backup de $WEBROOT/build')
run('cp -a public/build public/build_bak_%s' % STAMP, 'backup de public/build')

# -- 4. Empaquetar -------------------------------------------------------
print('\n== Empaquetado ==')
archive = os.path.join(os.environ.get('TEMP', '.'), 'citas_%s.tar.gz' % STAMP)
with tarfile.open(archive, 'w:gz') as tar:
    for f in CODE_FILES + build_files:
        tar.add(f, arcname=f)
size = os.path.getsize(archive)
print('  %s (%d archivos, %.1f KB)' % (archive, len(CODE_FILES) + len(build_files), size / 1024.0))

remote_tar = '/home/%s/citas_%s.tar.gz' % (USER, STAMP)
sftp.put(archive, remote_tar)
print('  subido a %s' % remote_tar)

# -- 5. Extraer: el build viejo se borra entero, sin chunks huerfanos ----
print('\n== Despliegue ==')
run('rm -rf public/build', 'borrar public/build remoto')
run('tar -xzf %s --overwrite' % remote_tar, 'extraer')
run('rm -f %s' % remote_tar, 'limpiar tar remoto')

# Hostinger sirve desde $WEBROOT/build, no desde public/build.
run('rm -rf build && cp -rf public/build build', 'copiar public/build -> $WEBROOT/build')

# -- 6. Cache de Laravel -------------------------------------------------
print('\n== Cache ==')
run('php artisan optimize:clear', 'optimize:clear')
run('php artisan config:cache', 'config:cache')
run('php artisan route:cache', 'route:cache')

# -- 7. Verificacion -----------------------------------------------------
print('\n== Verificacion post-deploy ==')
ok = True
local_manifest_md5 = md5_local(manifest)
out = run('md5sum public/build/manifest.json build/manifest.json', 'md5 de los dos manifest')
for line in out.splitlines():
    h, f = line.split()
    if h != local_manifest_md5:
        print('  [FALLO] %s = %s, esperaba %s' % (f, h, local_manifest_md5))
        ok = False
    else:
        print('  [ok] %s coincide con el local' % f)

out = run('md5sum %s' % ' '.join(CODE_FILES), 'md5 de los archivos de codigo')
for line in out.splitlines():
    h, f = line.split()
    if h != md5_local(f):
        print('  [FALLO] %s no coincide con el local' % f)
        ok = False
    else:
        print('  [ok] %s' % f)

# El chunk del panel debe pedir la ruta por nombre, no la URL vieja en 404.
run('grep -l "restaurantero.panel.eventos" build/assets/Panel-*.js || echo "NO ENCONTRADO"',
    'chunk del panel usa route(restaurantero.panel.eventos)', fatal=False)
run('grep -c "restaurantero/panel/eventos" build/assets/Panel-*.js || echo "0 (bien: ya no esta la URL vieja)"',
    'la URL vieja ya no aparece', fatal=False)
run('php artisan migrate:status 2>&1 | grep -i pending | head -5 || echo "sin migraciones pendientes"',
    'migraciones pendientes', fatal=False)
run('curl -s -o /dev/null -w "%{http_code}" https://impulsate.iyemyucatan.com/', 'HTTP de la portada')
run('curl -s -o /dev/null -w "%{http_code}" https://impulsate.iyemyucatan.com/login', 'HTTP de /login')
run('curl -s -o /dev/null -w "%{http_code}" https://impulsate.iyemyucatan.com/proveedor/panel/eventos',
    'feed del calendario (302 = ruta viva, pide login)')

sftp.close(); ssh.close()
os.remove(archive)

print('\n' + ('DEPLOY OK' if ok else 'DEPLOY CON FALLOS - revisa arriba'))
print('Backups en el servidor: build_bak_%s y public/build_bak_%s' % (STAMP, STAMP))
sys.exit(0 if ok else 1)
