# -*- coding: utf-8 -*-
"""Verificacion PRE-DEPLOY (solo lectura) del cambio 'registro pendiente al encuentro'.

No sube nada, no corre nada que escriba. Comprueba:
  1. md5sum local vs remoto de los 5 archivos del cambio.
  2. md5sum de TODO el resto del working tree backend pendiente (para saber si
     colariamos features ajenas).
  3. Si la migracion ya esta aplicada.
  4. Cuantas filas afectaria la migracion destructiva.
  5. QUEUE_CONNECTION y estado de la tabla jobs/failed_jobs.
  6. Si la plantilla evento_solicitud_recibida ya existe.
"""
import paramiko, os, sys, io, hashlib

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'

DEL_CAMBIO = [
    'app/Http/Controllers/CompletarPerfilController.php',
    'app/Http/Controllers/EventoRegistroController.php',
    'app/Models/Restaurantero.php',
    'database/seeders/PlantillasCorreoSeeder.php',
]

# resto del working tree modificado (no es de este cambio)
OTROS = [
    'app/Exports/CompradoresExport.php',
    'app/Http/Controllers/Admin/AgendaController.php',
    'app/Http/Controllers/Admin/CitaAdminController.php',
    'app/Http/Controllers/Admin/EventoController.php',
    'app/Http/Controllers/Admin/ExportController.php',
    'app/Http/Controllers/Admin/RestauranteroAdminController.php',
    'app/Http/Middleware/HandleInertiaRequests.php',
    'app/Models/Evento.php',
    'routes/web.php',
]


def md5_local(p):
    h = hashlib.md5()
    with open(p, 'rb') as f:
        for chunk in iter(lambda: f.read(65536), b''):
            h.update(chunk)
    return h.hexdigest()


ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)


def run(cmd):
    _in, out, err = ssh.exec_command(cmd, timeout=180)
    o = out.read().decode('utf-8', 'replace')
    e = err.read().decode('utf-8', 'replace')
    return o.strip(), e.strip()


def comparar(titulo, lista):
    print('\n=== %s ===' % titulo)
    remotos = {}
    cmd = 'cd %s && md5sum %s 2>&1' % (RROOT, ' '.join(lista))
    o, _ = run(cmd)
    for line in o.splitlines():
        parts = line.split()
        if len(parts) == 2:
            remotos[parts[1].lstrip('*')] = parts[0]
        else:
            print('  ?? %s' % line)
    for f in lista:
        lp = os.path.join(LROOT, f)
        if not os.path.exists(lp):
            print('  LOCAL-FALTA  %s' % f); continue
        lm = md5_local(lp)
        rm = remotos.get(f)
        if rm is None:
            print('  NO-EXISTE-EN-PROD  %s' % f)
        elif rm == lm:
            print('  IDENTICO     %s' % f)
        else:
            print('  DIFIERE      %s' % f)


comparar('Archivos DEL CAMBIO (se espera DIFIERE)', DEL_CAMBIO)
comparar('Resto del working tree (se espera IDENTICO)', OTROS)

print('\n=== Migracion nueva ya aplicada? ===')
o, e = run('cd %s && /usr/bin/php artisan migrate:status 2>&1 | grep -i "revertir_aprobados\\|Pending" | head -20' % RROOT)
print(o or '(sin coincidencias -> la migracion es nueva)')
if e: print('ERR ' + e)

print('\n=== A cuantas filas afectaria la migracion ===')
tinker = (
    r'$ids = DB::table("eventos")->where("tipo_evento","encuentro_negocios")->where("activa",1)'
    r'->where(function($q){$q->whereNull("fecha_hora_fin")->orWhere("fecha_hora_fin",">=",now());})->pluck("id");'
    r'echo "eventos activos encuentro: ".$ids->implode(",")."\n";'
    r'foreach(DB::table("evento_usuario")->whereIn("evento_id",$ids)->select("tipo","estado",DB::raw("count(*) t"))'
    r'->groupBy("tipo","estado")->get() as $r){ echo $r->tipo."/".$r->estado.": ".$r->t."\n"; }'
    r'echo "TOTAL expositor en toda la BD (NO se toca): ".DB::table("evento_usuario")->where("tipo","expositor")->count()."\n";'
)
o, e = run('cd %s && /usr/bin/php artisan tinker --execute=%s 2>&1' % (RROOT, "'" + tinker + "'"))
print(o)
if e: print('ERR ' + e)

print('\n=== Cola y correo ===')
o, _ = run('cd %s && grep -E "^QUEUE_CONNECTION|^MAIL_MAILER|^MAIL_HOST|^MAIL_USERNAME" .env' % RROOT)
print(o)
o, _ = run('crontab -l 2>&1 | head -10')
print('crontab: ' + (o or '(vacio)'))

print('\n=== Plantilla evento_solicitud_recibida ya existe? ===')
o, e = run('cd %s && /usr/bin/php artisan tinker --execute=\'echo DB::table("plantillas_correo")->where("clave","evento_solicitud_recibida")->count();\' 2>&1' % RROOT)
print(o)

ssh.close()
print('\nOK verificacion terminada (no se modifico nada).')
