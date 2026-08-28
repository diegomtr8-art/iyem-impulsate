# -*- coding: utf-8 -*-
"""DESPLIEGUE Fase 3 a produccion.

Mismo patron que 10_deploy.py: respaldo de BD (aborta si falla) -> respaldo de
los archivos que se sobrescriben -> down -> subir lista blanca -> migrate ->
optimize:clear -> up SIEMPRE (finally) -> comprobacion.

Ademas de la Fase 3, pasa la cola de sync a database. El cron de queue:work se
crea aparte, por el panel de Hostinger: esta cuenta no tiene crontab por CLI.
"""
import paramiko, sys, io, os, hashlib, posixpath

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST, PORT, USER = '195.35.38.222', 65002, 'u489236361'
KEY   = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = r'C:\xampp\htdocs\citas'
STAMP = '20260828-fase3'
BK    = '~/backups/' + STAMP

ARCHIVOS = [l.strip() for l in io.open(
    os.path.join(os.path.dirname(os.path.abspath(__file__)), 'lista_fase3.txt'),
    encoding='utf-8') if l.strip()]

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
    return hashlib.md5(open(p, 'rb').read()).hexdigest() if os.path.exists(p) else None


def mkdirs(rdir):
    acum = ''
    for p in rdir.strip('/').split('/'):
        acum += '/' + p
        try:
            sftp.stat(acum)
        except IOError:
            sftp.mkdir(acum)


def subir(rel):
    rpath = posixpath.join(RROOT, rel)
    mkdirs(posixpath.dirname(rpath))
    sftp.put(os.path.join(LROOT, rel.replace('/', os.sep)), rpath)


def subir_dir(rel_local, rel_remoto):
    base, n = os.path.join(LROOT, rel_local.replace('/', os.sep)), 0
    for root, _d, files in os.walk(base):
        sub = os.path.relpath(root, base).replace(os.sep, '/')
        rdir = posixpath.join(RROOT, rel_remoto) if sub == '.' else posixpath.join(RROOT, rel_remoto, sub)
        mkdirs(rdir)
        for f in files:
            sftp.put(os.path.join(root, f), posixpath.join(rdir, f))
            n += 1
    return n


print('=' * 68)
print('0. ALCANCE')
print('=' * 68)
faltan = [r for r in ARCHIVOS if md5_local(r) is None]
if faltan:
    print('!! ABORTADO: no existen en local: %s' % faltan)
    sys.exit(1)
cambios = [r for r in ARCHIVOS if md5_local(r) != md5_remoto(r)]
print('  %d de %d archivos difieren del servidor:' % (len(cambios), len(ARCHIVOS)))
for r in cambios:
    print('     ~ ' + r)

print('\n' + '=' * 68)
print('1. RESPALDOS')
print('=' * 68)
run('mkdir -p %s' % BK, mostrar=False)
print('  volcando la base de datos...')
dump = ("cd {r} && DBN=$(grep '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '\"') "
        "&& DBU=$(grep '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '\"') "
        "&& DBP=$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '\"') "
        "&& mysqldump --single-transaction --no-tablespaces -u\"$DBU\" -p\"$DBP\" \"$DBN\" "
        "| gzip > {b}/db-antes.sql.gz && echo DUMP_OK").format(r=RROOT, b=BK)
if 'DUMP_OK' not in run(dump, mostrar=False):
    print('!! ABORTADO: fallo el respaldo de BD. No se despliega sin respaldo.')
    sys.exit(1)
run('ls -lh %s/db-antes.sql.gz' % BK, 'respaldo de BD')

for r in cambios:
    run('cd %s && mkdir -p %s/codigo/$(dirname %s) && cp %s %s/codigo/%s 2>/dev/null'
        % (RROOT, BK, r, r, BK, r), mostrar=False)
# el .env se respalda FUERA del docroot: un .bak junto a un .php se sirve en claro
run('cd %s && cp .env %s/env-antes 2>/dev/null; echo listo' % (RROOT, BK), mostrar=False)
run('du -sh %s' % BK, 'tamano del respaldo')

print('\n' + '=' * 68)
print('2. MANTENIMIENTO')
print('=' * 68)
run('cd %s && /usr/bin/php artisan down --render="errors::503" 2>&1 | tail -2' % RROOT, 'artisan down')

try:
    print('\n' + '=' * 68)
    print('3. SUBIR CODIGO')
    print('=' * 68)
    for r in ARCHIVOS:
        subir(r)
    print('  %d archivos subidos' % len(ARCHIVOS))
    print('  subiendo public/build completo (el manifest es todo-o-nada)...')
    print('  %d archivos -> public/build' % subir_dir('public/build', 'public/build'))
    try:
        sftp.stat(posixpath.join(RROOT, 'build'))
        print('  %d archivos -> build (espejo existente)' % subir_dir('public/build', 'build'))
    except IOError:
        print('  (no hay build/ en la raiz remota)')

    print('\n' + '=' * 68)
    print('4. COLA: sync -> database')
    print('=' * 68)
    run("cd %s && sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=database/' .env "
        "&& grep '^QUEUE_CONNECTION=' .env" % RROOT, 'QUEUE_CONNECTION')

    print('\n' + '=' * 68)
    print('5. MIGRACIONES')
    print('=' * 68)
    run('cd %s && /usr/bin/php artisan migrate --force 2>&1 | tail -25' % RROOT, 'artisan migrate')

    run('cd %s && /usr/bin/php artisan optimize:clear 2>&1 | tail -8' % RROOT, 'optimize:clear')
    run('cd %s && /usr/bin/php artisan queue:restart 2>&1 | tail -2' % RROOT, 'queue:restart')

finally:
    print('\n' + '=' * 68)
    print('6. LEVANTAR EL SITIO')
    print('=' * 68)
    run('cd %s && /usr/bin/php artisan up 2>&1 | tail -2' % RROOT, 'artisan up')

print('\n' + '=' * 68)
print('7. COMPROBACION')
print('=' * 68)
run('cd %s && /usr/bin/php artisan migrate:status 2>&1 | tail -6' % RROOT, 'ultimas migraciones')

t = (r'echo "jobs=".DB::table("jobs")->count()." failed=".DB::table("failed_jobs")->count()."\n"; '
     r'foreach(["page_visits"=>"page_visits_url_idx","restauranteros"=>"restauranteros_directorio_idx",'
     r'"evento_usuario"=>"evento_usuario_estado_idx"] as $tb=>$ix){'
     r'$n=count(array_filter(DB::select("SHOW INDEX FROM $tb"), fn($i)=>$i->Key_name===$ix)); '
     r'echo "$ix -> ".($n?"creado":"FALTA")."\n";} '
     r'echo "apc_con_cita_id=".DB::table("agenda_propuesta_citas")->whereNotNull("cita_id")->count()'
     r'."/".DB::table("agenda_propuesta_citas")->count()."\n";')
run("cd %s && /usr/bin/php artisan tinker --execute='%s' 2>&1 | tail -8" % (RROOT, t), 'estado post-despliegue')

run('cd %s && grep -L ShouldQueue app/Mail/*.php || echo "(todos los Mailables son encolables)"' % RROOT,
    'Mailables sin ShouldQueue')
run('cd %s && tail -40 storage/logs/laravel.log 2>/dev/null | grep -iE "ERROR|Exception" | tail -5 '
    '|| echo "(sin errores recientes)"' % RROOT, 'log')

sftp.close()
ssh.close()
print('\n=== DESPLIEGUE TERMINADO ===  respaldo en %s' % BK)
