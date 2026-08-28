# -*- coding: utf-8 -*-
"""Activa el Encuentro de Negocios (evento 9) en produccion.

Decision del usuario (2026-08-26): quiere el bazar (evento 8) Y el encuentro
(evento 9) activos simultaneamente. El soporte multi-evento activo ya esta
desplegado (Evento::queryActivos / activos / contextoAdmin + SelectorEvento).

NO corre la migracion de revertir-aprobados: los 5 proveedores ya aprobados del
evento 9 se quedan aprobados por decision explicita del usuario. De aqui en
adelante todo registro NUEVO entra como 'pendiente' (codigo ya desplegado).

Reversible: basta poner activa=0 de nuevo.
"""
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


print('=== ANTES ===')
t0 = (r'foreach(DB::table("eventos")->orderBy("id")->get() as $e){'
      r'echo $e->id." | ".mb_substr($e->nombre,0,45)." | ".$e->tipo_evento." | activa=".$e->activa."\n";}')
run("cd %s && /usr/bin/php artisan tinker --execute='%s' 2>&1" % (RROOT, t0))

t1 = r'echo DB::table("eventos")->where("id",9)->update(["activa"=>1,"updated_at"=>now()])." fila(s) actualizada(s)";'
run("cd %s && /usr/bin/php artisan tinker --execute='%s' 2>&1" % (RROOT, t1), 'ACTIVANDO EVENTO 9')

print('\n=== DESPUES ===')
run("cd %s && /usr/bin/php artisan tinker --execute='%s' 2>&1" % (RROOT, t0))

print('\n=== Evento::activos() ve ambos? ===')
t2 = (r'foreach(App\Models\Evento::activos() as $e){ echo $e->id." | ".mb_substr($e->nombre,0,45)'
      r'." | ".$e->tipo_evento." | inicia ".$e->fecha_hora_inicio."\n"; }'
      r'echo "-- activo() principal (mas proximo): ".App\Models\Evento::activo()?->id." (".App\Models\Evento::activo()?->tipo_evento.")\n";')
run("cd %s && /usr/bin/php artisan tinker --execute='%s' 2>&1" % (RROOT, t2))

run('cd %s && /usr/bin/php artisan optimize:clear 2>&1 | tail -3' % RROOT, 'optimize:clear')
run('cd %s && /usr/bin/php artisan optimize:clear 2>&1 | tail -3' % RROOT, 'reset opcache/cache')

print('\n=== HTTP post-cambio ===')
for url, esp in [('/', '200'), ('/proveedores', '200'), ('/mi-panel', '302'), ('/admin/eventos', '302')]:
    run('curl -s -o /dev/null -w "%s -> %%{http_code} (esperado %s)\\n" https://impulsate.iyemyucatan.com%s' % (url, esp, url))

ssh.close()
