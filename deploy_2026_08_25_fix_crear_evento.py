"""Deploy 2026-08-25: Fix "no deja crear Evento (Encuentro de Negocios)".

Causa raiz: Admin/Eventos/Index.vue no renderizaba form.errors. Cuando la
validacion del backend fallaba (422) el boton "Crear evento" no hacia nada
visible. Las reglas que bloqueaban: convocatoria_url (required|url) y el
encadenado after_or_equal de las ventanas de proveedores/compradores.

Cambios:
  - resources/js/Pages/Admin/Eventos/Index.vue: panel de errores en crear y
    editar, boton con estado processing, clearErrors al abrir.
  - app/Http/Controllers/Admin/EventoController.php: mensajes y atributos de
    validacion en espanol natural.
  - routes/web.php: agrega 4 rutas que el JS ya desplegado usa y no existian
    en produccion (metricas.exportar, usuarios.genero, agenda.show,
    config.mesas). Sus controladores ya estan desplegados e identicos.

Assets: build completo del working tree (npm run build). Se suben TODOS los
assets nuevos y el manifest, siguiendo la regla all-or-nothing documentada en
memoria (un manifest parcial revierte features previas silenciosamente).

Nota de layout del servidor: el docroot es public_html/ y los estaticos se
sirven desde public_html/build/assets/, pero Laravel lee el manifest desde
public_html/public/build/manifest.json. Se actualizan AMBOS manifests para que
apunten al mismo build.
"""
import paramiko, os, sys, io, hashlib, json

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'
STAMP = '20260825'

PHP_FILES = [
    'app/Http/Controllers/Admin/EventoController.php',
    'routes/web.php',
]

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, PORT, USER, key_filename=KEY_PATH, timeout=60)
sftp = ssh.open_sftp()


def run(cmd):
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace')
    err = e.read().decode('utf-8', 'replace')
    return (out + err).strip()


def md5_local(path):
    return hashlib.md5(open(path, 'rb').read()).hexdigest()


print('=== 1. BACKUP de archivos PHP a reemplazar ===')
for rel in PHP_FILES:
    rp = RROOT + '/' + rel
    run('mkdir -p ~/backups/%s' % STAMP)
    run('cp %s ~/backups/%s/$(basename %s)' % (rp, STAMP, rp))
    print(' ', rel)

print()
print('=== 2. SUBIR archivos PHP ===')
for rel in PHP_FILES:
    lp = os.path.join(LROOT, rel.replace('/', os.sep))
    rp = RROOT + '/' + rel
    sftp.put(lp, rp)
    remoto = run('md5sum %s' % rp).split()[0]
    ok = 'OK' if remoto == md5_local(lp) else 'MD5 NO COINCIDE'
    print('  %-55s %s' % (rel, ok))
    if ok != 'OK':
        sys.exit('ABORTADO: %s no subio integro' % rel)

print()
print('=== 3. SUBIR assets del build (solo los que faltan; los hashes son inmutables) ===')
LASSETS = os.path.join(LROOT, 'public', 'build', 'assets')
RASSETS = RROOT + '/build/assets'
existentes = set(sftp.listdir(RASSETS))
locales = os.listdir(LASSETS)
nuevos = [f for f in locales if f not in existentes]
print('  locales=%d  ya-en-servidor=%d  a-subir=%d' % (len(locales), len(locales) - len(nuevos), len(nuevos)))
for i, f in enumerate(nuevos, 1):
    sftp.put(os.path.join(LASSETS, f), RASSETS + '/' + f)
    if i % 25 == 0 or i == len(nuevos):
        print('    %d/%d' % (i, len(nuevos)))

print()
print('=== 4. VERIFICAR que el manifest nuevo esta completo contra el servidor ===')
manifest_local = os.path.join(LROOT, 'public', 'build', 'manifest.json')
m = json.load(open(manifest_local, encoding='utf-8'))
disponibles = set(sftp.listdir(RASSETS))
faltantes = []
for k, v in m.items():
    if v['file'].split('/')[-1] not in disponibles:
        faltantes.append(v['file'])
    for c in v.get('css', []):
        if c.split('/')[-1] not in disponibles:
            faltantes.append(c)
if faltantes:
    for f in faltantes:
        print('  FALTA', f)
    sys.exit('ABORTADO: el manifest referencia %d assets que no estan en el servidor' % len(faltantes))
print('  manifest: %d entradas, 0 assets faltantes' % len(m))

print()
print('=== 5. SUBIR manifest (backup + ambas ubicaciones) ===')
for rp in [RROOT + '/build/manifest.json', RROOT + '/public/build/manifest.json']:
    run('mkdir -p ~/backups/%s' % STAMP)
    run('cp %s ~/backups/%s/$(basename %s)' % (rp, STAMP, rp))
    sftp.put(manifest_local, rp)
    remoto = run('md5sum %s' % rp).split()[0]
    print('  %-70s %s' % (rp.replace(RROOT, ''), 'OK' if remoto == md5_local(manifest_local) else 'MD5 NO COINCIDE'))

print()
print('=== 6. LIMPIAR Y RECACHEAR (routes/web.php cambio) ===')
for cmd in ['php artisan optimize:clear', 'php artisan config:cache', 'php artisan route:cache', 'php artisan view:cache']:
    print('  $ %s' % cmd)
    print('    ' + run('cd %s && %s 2>&1' % (RROOT, cmd)).replace('\n', '\n    '))

print()
print('=== 7. VERIFICACION POST-DEPLOY ===')
ev = m['resources/js/Pages/Admin/Eventos/Index.vue']['file']
print('  JS de Eventos vigente:', ev)
print('  ', run('ls -la %s/build/%s' % (RROOT, ev)))
for t in ['No se pudo crear el evento', 'Creando', 'clearErrors']:
    print('   marcador "%s": %s ocurrencia(s)' % (t, run("grep -c '%s' %s/build/%s" % (t, RROOT, ev))))
print('  rutas nuevas registradas:')
print('    ' + run("cd %s && php artisan route:list --name=metricas.exportar --name=usuarios.genero 2>&1 | tail -5" % RROOT).replace('\n', '\n    '))
print('    ' + run("cd %s && php artisan route:list 2>&1 | grep -E 'agenda/{agenda}|config/mesas|metricas/exportar|usuarios/{user}/genero'" % RROOT).replace('\n', '\n    '))
print('  HTTP:')
print('    ' + run("curl -s -o /dev/null -w 'landing=%%{http_code}' https://impulsate.iyemyucatan.com/"))
print('    ' + run("curl -s -o /dev/null -w 'login=%%{http_code}' https://impulsate.iyemyucatan.com/login"))
print('    ' + run("curl -s -o /dev/null -w 'admin/eventos=%%{http_code} (302 a login es lo esperado sin sesion)' https://impulsate.iyemyucatan.com/admin/eventos"))
print('    ' + run("curl -s https://impulsate.iyemyucatan.com/ | grep -o 'build/assets/app-[A-Za-z0-9_-]*[.]js' | head -1"))

sftp.close(); ssh.close()
print()
print('=== DEPLOY COMPLETADO ===')
