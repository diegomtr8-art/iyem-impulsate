# -*- coding: utf-8 -*-
"""Segunda verificacion (solo lectura): estado real de eventos y SMTP."""
import paramiko, sys, io

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)


def run(cmd):
    _in, out, err = ssh.exec_command(cmd, timeout=180)
    return out.read().decode('utf-8', 'replace').strip(), err.read().decode('utf-8', 'replace').strip()


print('=== TODOS los eventos ===')
t = (r'foreach(DB::table("eventos")->orderBy("id")->get() as $e){'
     r'echo $e->id." | ".$e->nombre." | tipo=".$e->tipo_evento." | activa=".$e->activa'
     r'." | ini=".($e->fecha_hora_inicio ?? "null")." | fin=".($e->fecha_hora_fin ?? "null")."\n";}')
o, e = run("cd %s && /usr/bin/php artisan tinker --execute='%s' 2>&1" % (RROOT, t))
print(o)
if e: print('ERR ' + e)

print('\n=== Inscripciones por evento/tipo/estado ===')
t2 = (r'foreach(DB::table("evento_usuario")->select("evento_id","tipo","estado",DB::raw("count(*) t"))'
      r'->groupBy("evento_id","tipo","estado")->orderBy("evento_id")->get() as $r){'
      r'echo "evento ".$r->evento_id." | ".$r->tipo." | ".$r->estado." | ".$r->t."\n";}')
o, e = run("cd %s && /usr/bin/php artisan tinker --execute='%s' 2>&1" % (RROOT, t2))
print(o)
if e: print('ERR ' + e)

print('\n=== Errores SMTP recientes en el log ===')
o, _ = run('cd %s && ls -la storage/logs/ | tail -5' % RROOT)
print(o)
o, _ = run('cd %s && grep -c "535\\|Username and Password not accepted\\|Failed to authenticate" storage/logs/laravel.log 2>/dev/null || echo 0' % RROOT)
print('coincidencias SMTP-auth en laravel.log: ' + o)
o, _ = run('cd %s && grep -n "535\\|Username and Password not accepted" storage/logs/laravel.log 2>/dev/null | tail -3' % RROOT)
print(o or '(ninguna)')
o, _ = run('cd %s && tail -c 3000 storage/logs/laravel.log 2>/dev/null | grep -o "^\\[[0-9-]* [0-9:]*\\]" | tail -3' % RROOT)
print('ultimas entradas del log: ' + (o or '(sin fecha)'))

ssh.close()
