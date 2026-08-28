# -*- coding: utf-8 -*-
"""DESPLIEGUE Fase 1 + Fase 2 a produccion.

Orden: respaldo BD -> respaldo archivos -> down -> subir -> migrate -> up.
Sube UNICAMENTE la lista blanca de abajo. Cualquier otro archivo modificado
en local se reporta pero NO se sube: no se cuela trabajo a medias de otros
sprints en un despliegue de seguridad.
"""
import paramiko, sys, io, os, hashlib, posixpath, time

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST  = '195.35.38.222'
PORT  = 65002
USER  = 'u489236361'
KEY   = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = r'C:\xampp\htdocs\citas'
STAMP = '20260827-fase1y2'
BK    = '~/backups/' + STAMP

FASE1 = [
    '.htaccess',
    'app/Http/Controllers/CompletarPerfilController.php',
    'app/Http/Controllers/DocumentoController.php',
    'app/Http/Controllers/Admin/BazarEvaluacionController.php',
    'database/migrations/2026_08_27_100000_mover_documentos_a_disco_privado.php',
]
FASE2 = [
    'app/Models/Cita.php',
    'app/Models/Evento.php',
    'app/Models/AgendaPropuestaCita.php',
    'app/Http/Middleware/EnsureAvisoAceptado.php',
    'app/Http/Middleware/EnsureRolSeleccionado.php',
    'app/Http/Middleware/EnsureProfileComplete.php',
    'app/Http/Controllers/AgendaPublicaController.php',
    'app/Http/Controllers/RestauranteroCitasController.php',
    'app/Http/Controllers/CitaPublicaController.php',
    'app/Http/Controllers/EncuestaController.php',
    'app/Http/Controllers/EventoRegistroController.php',
    'app/Http/Controllers/Admin/AgendaController.php',
    'app/Http/Controllers/Admin/EventoController.php',
    'app/Http/Controllers/Admin/CitaAdminController.php',
    'app/Http/Controllers/Admin/PantallaTvController.php',
    'database/migrations/2026_08_27_110000_add_cita_id_to_agenda_propuesta_citas.php',
    'database/migrations/2026_08_27_120000_add_tv_token_to_eventos_table.php',
]
# routes/web.php lleva cambios de las dos fases
ARCHIVOS = FASE1 + FASE2 + ['routes/web.php']

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY, timeout=30)
sftp = ssh.open_sftp()


def run(cmd, label=None, mostrar=True):
    if label:
        print('\n--- %s ---' % label)
    _i, o, e = ssh.exec_command(cmd, timeout=900)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    if mostrar and out:
        print(out)
    if err:
        print('[stderr] ' + err)
    return out


def md5_remoto(rel):
    return run("cd %s && md5sum %s 2>/dev/null | cut -d' ' -f1" % (RROOT, rel), mostrar=False)


def md5_local(rel):
    p = os.path.join(LROOT, rel.replace('/', os.sep))
    if not os.path.exists(p):
        return None
    return hashlib.md5(open(p, 'rb').read()).hexdigest()


def mkdirs(rdir):
    partes, acum = rdir.strip('/').split('/'), ''
    for p in partes:
        acum += '/' + p
        try:
            sftp.stat(acum)
        except IOError:
            sftp.mkdir(acum)


def subir(rel):
    lpath = os.path.join(LROOT, rel.replace('/', os.sep))
    rpath = posixpath.join(RROOT, rel)
    mkdirs(posixpath.dirname(rpath))
    sftp.put(lpath, rpath)


def subir_dir(rel_local, rel_remoto):
    base = os.path.join(LROOT, rel_local.replace('/', os.sep))
    n = 0
    for root, _dirs, files in os.walk(base):
        sub = os.path.relpath(root, base).replace(os.sep, '/')
        rdir = posixpath.join(RROOT, rel_remoto) if sub == '.' else posixpath.join(RROOT, rel_remoto, sub)
        mkdirs(rdir)
        for f in files:
            sftp.put(os.path.join(root, f), posixpath.join(rdir, f))
            n += 1
    return n


# ═══════════ 0. Comprobacion de alcance ═══════════
print('=' * 66)
print('0. COMPROBACION DE ALCANCE')
print('=' * 66)
faltan = [r for r in ARCHIVOS if md5_local(r) is None]
if faltan:
    print('!! ABORTADO: no existen en local: %s' % faltan)
    sys.exit(1)

cambios = [r for r in ARCHIVOS if md5_local(r) != md5_remoto(r)]
print('  archivos de la lista blanca que cambian: %d de %d' % (len(cambios), len(ARCHIVOS)))
for r in cambios:
    print('     ~ ' + r)

# Modificados en local que NO van en este despliegue
FUERA = ['app/Exports/CompradoresExport.php',
         'app/Http/Controllers/Admin/ExportController.php',
         'app/Http/Controllers/Admin/RestauranteroAdminController.php',
         'app/Http/Middleware/HandleInertiaRequests.php',
         'app/Models/Restaurantero.php',
         'app/Http/Controllers/Admin/CitaAdminController.php',
         'database/seeders/PlantillasCorreoSeeder.php']
print('\n  archivos modificados en local FUERA del alcance:')
divergentes = []
for r in FUERA:
    if r in ARCHIVOS:
        continue
    lm, rm = md5_local(r), md5_remoto(r)
    if lm is None:
        continue
    estado = 'identico al servidor' if lm == rm else 'DIFIERE - NO se sube'
    if lm != rm:
        divergentes.append(r)
    print('     %-58s %s' % (r, estado))

# ═══════════ 1. Respaldos ═══════════
print('\n' + '=' * 66)
print('1. RESPALDOS')
print('=' * 66)
run('mkdir -p %s' % BK, mostrar=False)

print('  volcando la base de datos...')
dump = (
    "cd {r} && DBN=$(grep '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '\"') "
    "&& DBU=$(grep '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '\"') "
    "&& DBP=$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '\"') "
    "&& mysqldump --single-transaction --no-tablespaces -u\"$DBU\" -p\"$DBP\" \"$DBN\" "
    "| gzip > {b}/db-antes.sql.gz && echo DUMP_OK"
).format(r=RROOT, b=BK)
res = run(dump, mostrar=False)
if 'DUMP_OK' not in res:
    print('!! ABORTADO: fallo el respaldo de la base de datos. No se despliega sin respaldo.')
    sys.exit(1)
run('ls -lh %s/db-antes.sql.gz' % BK, 'respaldo de BD')

print('  respaldando los archivos que se van a sobrescribir...')
for r in cambios:
    run('cd %s && mkdir -p %s/codigo/$(dirname %s) && cp %s %s/codigo/%s 2>/dev/null'
        % (RROOT, BK, r, r, BK, r), mostrar=False)
run('cd %s && cp -r public/build %s/build-antes 2>/dev/null; echo listo' % (RROOT, BK), mostrar=False)
run('du -sh %s' % BK, 'tamano total del respaldo')

# ═══════════ 2. Mantenimiento ═══════════
print('\n' + '=' * 66)
print('2. MODO MANTENIMIENTO')
print('=' * 66)
run('cd %s && /usr/bin/php artisan down --render="errors::503" 2>&1 | tail -2' % RROOT, 'artisan down')

try:
    # ═══════════ 3. Subir ═══════════
    print('\n' + '=' * 66)
    print('3. SUBIR CODIGO')
    print('=' * 66)
    for r in ARCHIVOS:
        subir(r)
        print('  + ' + r)

    print('\n  subiendo public/build completo (el manifest es todo-o-nada)...')
    n = subir_dir('public/build', 'public/build')
    print('  %d archivos -> public/build' % n)
    try:
        sftp.stat(posixpath.join(RROOT, 'build'))
        n2 = subir_dir('public/build', 'build')
        print('  %d archivos -> build (espejo que ya existia)' % n2)
    except IOError:
        print('  (no existe build/ en la raiz remota; nada que espejar)')

    # ═══════════ 4. Migraciones ═══════════
    print('\n' + '=' * 66)
    print('4. MIGRACIONES')
    print('=' * 66)
    run('cd %s && /usr/bin/php artisan migrate --force 2>&1 | tail -25' % RROOT, 'artisan migrate')

    # ═══════════ 5. Caches ═══════════
    run('cd %s && /usr/bin/php artisan optimize:clear 2>&1 | tail -8' % RROOT, 'optimize:clear')

finally:
    # ═══════════ 6. Levantar SIEMPRE ═══════════
    print('\n' + '=' * 66)
    print('6. LEVANTAR EL SITIO')
    print('=' * 66)
    run('cd %s && /usr/bin/php artisan up 2>&1 | tail -2' % RROOT, 'artisan up')

# ═══════════ 7. Comprobacion ═══════════
print('\n' + '=' * 66)
print('7. COMPROBACION POST-DESPLIEGUE')
print('=' * 66)
run('cd %s && /usr/bin/php artisan migrate:status 2>&1 | tail -5' % RROOT, 'ultimas migraciones')
run('cd %s && ls -la storage/app/private/documentos 2>&1 | head -5' % RROOT, 'disco privado de documentos')
run('cd %s && find storage/app/public/documentos -type f 2>/dev/null | wc -l' % RROOT,
    'documentos que quedan en publico (debe ser 0)')
run('cd %s && find storage/app/private/documentos -type f 2>/dev/null | wc -l' % RROOT,
    'documentos ya en privado')
t = (r'$e=App\Models\Evento::contextoAdmin(); echo $e ? ("evento ".$e->id." tv_token=".substr($e->tokenTv(),0,12)."...") : "sin contexto";')
run("cd %s && /usr/bin/php artisan tinker --execute='%s' 2>&1 | tail -3" % (RROOT, t), 'token nuevo de la pantalla TV')
run('cd %s && tail -25 storage/logs/laravel.log 2>/dev/null | grep -iE "ERROR|Exception" | tail -5 || echo "(sin errores recientes)"' % RROOT, 'log')

if divergentes:
    print('\n!! OJO: estos siguen distintos en local y NO se subieron:')
    for r in divergentes:
        print('   - ' + r)

sftp.close()
ssh.close()
print('\n=== DESPLIEGUE TERMINADO ===')
print('Respaldo completo en: %s' % BK)
