# -*- coding: utf-8 -*-
"""Verificacion POST-DEPLOY (solo lectura + opcache reset)."""
import paramiko, sys, io

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)


def run(cmd, label=None):
    if label: print('\n--- %s ---' % label)
    _in, out, err = ssh.exec_command(cmd, timeout=300)
    o = out.read().decode('utf-8', 'replace').strip()
    e = err.read().decode('utf-8', 'replace').strip()
    if o: print(o)
    if e: print('[stderr] ' + e)
    return o


run('cd %s && /usr/bin/php artisan optimize:clear 2>&1 | tail -3' % RROOT,
    'RESET OPCACHE')

print('\n--- Plantilla nueva ---')
t = (r'$p = DB::table("plantillas_correo")->where("clave","evento_solicitud_recibida")->first();'
     r'echo $p ? ("existe | activo=".$p->activo." | asunto=".$p->asunto) : "NO EXISTE";')
run("cd %s && /usr/bin/php artisan tinker --execute='%s' 2>&1" % (RROOT, t))

print('\n--- El BAZAR sigue intacto (tipo=expositor) ---')
t2 = (r'foreach(DB::table("evento_usuario")->where("tipo","expositor")->select("estado",DB::raw("count(*) t"))'
      r'->groupBy("estado")->get() as $r){ echo "expositor/".$r->estado.": ".$r->t."\n"; }')
run("cd %s && /usr/bin/php artisan tinker --execute='%s' 2>&1" % (RROOT, t2))

print('\n--- Migracion NO aplicada (correcto, no se subio) ---')
run('cd %s && /usr/bin/php artisan migrate:status 2>&1 | tail -4' % RROOT)

print('\n--- HTTP ---')
for url, esperado in [('/', '200'), ('/proveedores', '200'), ('/login', '200'),
                      ('/register', '200'), ('/mi-panel', '302'), ('/admin/eventos', '302')]:
    run('curl -s -o /dev/null -w "%s -> %%{http_code} (esperado %s)\\n" https://impulsate.iyemyucatan.com%s'
        % (url, esperado, url))

print('\n--- Errores nuevos en el log tras el deploy ---')
run('cd %s && tail -c 4000 storage/logs/laravel.log 2>/dev/null | grep -o "2026-08-26[^]]*" | tail -5 || echo "(sin entradas de hoy)"' % RROOT)

ssh.close()
