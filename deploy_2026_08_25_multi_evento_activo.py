"""Deploy 2026-08-25 (b): varios eventos activos simultáneamente.

Antes, activar un evento desactivaba todos los demás (`Evento::query()->update(['activa' => false])`)
y `Evento::activo()` hacía un `first()` SIN orden, así que con varias filas activas habría
devuelto un evento arbitrario. Cambios:

Modelo (app/Models/Evento.php)
  - queryActivos(): consulta base de activos vigentes CON orden determinista
    (más próximo primero; los que no tienen fecha, al final).
  - activos(): colección de todos los activos simultáneos.
  - activo(): sigue devolviendo uno, pero ahora es siempre el más próximo.
  - contextoAdmin(): evento elegido con el selector (sesión) validado contra los
    activos; si no hay o dejó de ser válido, cae al principal.
  - registrarProveedorEnEvento(): variante con evento explícito.

Admin
  - activar() ya no desactiva los demás; archivar() limpia el contexto si aplica.
  - contexto(): nueva acción que guarda el evento elegido en sesión.
    Su ruta va ANTES de POST /{evento} o el comodín la capturaría.
  - Agenda, Citas, Exportar y alta de proveedor operan sobre contextoAdmin().

Frontend
  - SelectorEvento.vue (nuevo): selector visible solo si hay 2+ activos.
    Integrado en Agenda, Citas y Exportar.
  - TabEventos.vue y Admin/Eventos/Index.vue: la tarjeta del evento activo se
    envuelve en un v-for cuya variable sombrea la anterior, de modo que las ~90
    referencias internas siguen funcionando sin tocarlas.

Verificado con SQLite en memoria: orden por proximidad, exclusión de vencidos y
archivados, y las tres rutas de contextoAdmin (elección válida, id inválido,
evento archivado).

Assets: build completo del working tree (regla all-or-nothing, ver memoria).
Layout del servidor: docroot public_html/, estáticos en public_html/build/assets/,
manifest leído desde public_html/public/build/manifest.json -> se actualizan ambos.
"""
import paramiko, os, sys, io, hashlib, json

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'
STAMP = '20260825c'

PHP_FILES = [
    'app/Models/Evento.php',
    'app/Http/Controllers/Admin/EventoController.php',
    'app/Http/Controllers/Admin/AgendaController.php',
    'app/Http/Controllers/Admin/CitaAdminController.php',
    'app/Http/Controllers/Admin/ExportController.php',
    'app/Http/Controllers/Admin/RestauranteroAdminController.php',
    'app/Http/Middleware/HandleInertiaRequests.php',
    'app/Exports/CompradoresExport.php',
    'routes/web.php',
]

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, PORT, USER, key_filename=KEY_PATH, timeout=60)
sftp = ssh.open_sftp()


def run(cmd):
    _, o, e = ssh.exec_command(cmd)
    return (o.read().decode('utf-8', 'replace') + e.read().decode('utf-8', 'replace')).strip()


def md5_local(path):
    return hashlib.md5(open(path, 'rb').read()).hexdigest()


print('=== 1. BACKUP ===')
for rel in PHP_FILES:
    rp = RROOT + '/' + rel
    run('mkdir -p ~/backups/%s' % STAMP)
    run('cp %s ~/backups/%s/$(basename %s)' % (rp, STAMP, rp))
print('  %d archivos respaldados en ~/backups/%s' % (len(PHP_FILES), STAMP))

print()
print('=== 2. SUBIR PHP + verificar md5 y sintaxis ===')
for rel in PHP_FILES:
    lp = os.path.join(LROOT, rel.replace('/', os.sep))
    rp = RROOT + '/' + rel
    sftp.put(lp, rp)
    if run('md5sum %s' % rp).split()[0] != md5_local(lp):
        sys.exit('ABORTADO: %s no subió íntegro' % rel)
    lint = run('cd %s && php -l %s' % (RROOT, rel))
    if 'No syntax errors' not in lint:
        sys.exit('ABORTADO: error de sintaxis en %s\n%s' % (rel, lint))
    print('  %-58s OK' % rel)

print()
print('=== 3. SUBIR assets del build (hashes inmutables: solo los que faltan) ===')
LASSETS = os.path.join(LROOT, 'public', 'build', 'assets')
RASSETS = RROOT + '/build/assets'
existentes = set(sftp.listdir(RASSETS))
nuevos = [f for f in os.listdir(LASSETS) if f not in existentes]
print('  a subir: %d' % len(nuevos))
for i, f in enumerate(nuevos, 1):
    sftp.put(os.path.join(LASSETS, f), RASSETS + '/' + f)
    if i % 25 == 0 or i == len(nuevos):
        print('    %d/%d' % (i, len(nuevos)))

print()
print('=== 4. VERIFICAR manifest completo ANTES de publicarlo ===')
manifest_local = os.path.join(LROOT, 'public', 'build', 'manifest.json')
m = json.load(open(manifest_local, encoding='utf-8'))
disponibles = set(sftp.listdir(RASSETS))
faltantes = [v['file'] for v in m.values() if v['file'].split('/')[-1] not in disponibles]
for v in m.values():
    faltantes += [c for c in v.get('css', []) if c.split('/')[-1] not in disponibles]
if faltantes:
    for f in faltantes:
        print('  FALTA', f)
    sys.exit('ABORTADO: %d assets del manifest no están en el servidor' % len(faltantes))
print('  %d entradas, 0 faltantes' % len(m))

print()
print('=== 5. PUBLICAR manifest en ambas ubicaciones ===')
for rp in [RROOT + '/build/manifest.json', RROOT + '/public/build/manifest.json']:
    run('mkdir -p ~/backups/%s' % STAMP)
    run('cp %s ~/backups/%s/$(basename %s)' % (rp, STAMP, rp))
    sftp.put(manifest_local, rp)
    estado = 'OK' if run('md5sum %s' % rp).split()[0] == md5_local(manifest_local) else 'MD5 NO COINCIDE'
    print('  %-42s %s' % (rp.replace(RROOT, ''), estado))

print()
print('=== 6. RECACHEAR (routes/web.php cambió) ===')
for cmd in ['php artisan optimize:clear', 'php artisan config:cache', 'php artisan route:cache', 'php artisan view:cache']:
    salida = run('cd %s && %s 2>&1 | tail -1' % (RROOT, cmd))
    print('  %-28s %s' % (cmd.replace('php artisan ', ''), salida))

print()
print('=== 7. VERIFICACIÓN POST-DEPLOY ===')
print('  ruta del selector registrada:')
print('   ', run("cd %s && php artisan route:list 2>&1 | grep -i 'eventos/contexto'" % RROOT) or '  NO APARECE')

print('  estado actual de eventos en BD:')
tinker = ("foreach(DB::select('select id,nombre,activa from eventos order by id') as \\$e) "
          "echo '   ['.\\$e->id.'] '.\\$e->nombre.' activa='.\\$e->activa.PHP_EOL; "
          "echo '   activos vigentes segun el modelo: '.App\\\\Models\\\\Evento::activos()->count().PHP_EOL;")
print(run('cd %s && php artisan tinker --execute="%s" 2>&1 | tail -10' % (RROOT, tinker)))

ev = m['resources/js/Pages/Admin/Eventos/Index.vue']['file']
print('  JS de Eventos vigente:', ev)
for t in ['Eventos activos', 'seguirán activos']:
    print('   marcador "%s": %s' % (t, run("grep -c '%s' %s/build/%s" % (t, RROOT, ev))))

print('  HTTP:')
for p in ['/', '/login', '/admin/eventos']:
    print('    %-16s %s' % (p, run("curl -s -o /dev/null -w '%{http_code}' https://impulsate.iyemyucatan.com" + p)))

sftp.close(); ssh.close()
print()
print('=== DEPLOY COMPLETADO ===')
