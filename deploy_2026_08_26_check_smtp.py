# -*- coding: utf-8 -*-
"""Solo lectura: fecha real del ultimo error SMTP en produccion."""
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


# Cada bloque de error empieza con [YYYY-MM-DD HH:MM:SS]. Buscamos la linea de
# encabezado que contiene 'Failed to authenticate' y extraemos su fecha.
run('cd %s && grep -o "^\\[2026-[0-9-]* [0-9:]*\\].*Failed to authenticate" storage/logs/laravel.log '
    '| grep -o "^\\[2026-[0-9-]*" | sort -u | tail -10' % RROOT,
    'FECHAS CON ERROR "Failed to authenticate" (unicas, ultimas 10)')

run('cd %s && grep -o "^\\[2026-[0-9-]* [0-9:]*\\].*Failed to authenticate" storage/logs/laravel.log | tail -1 | cut -c1-60' % RROOT,
    'ULTIMO error de autenticacion SMTP')

run('cd %s && grep -o "^\\[2026-[0-9-]*" storage/logs/laravel.log | sort -u | tail -6' % RROOT,
    'ULTIMAS FECHAS con CUALQUIER entrada en el log')

run('cd %s && grep -c "^\\[2026-08-1[89]\\|^\\[2026-08-2" storage/logs/laravel.log' % RROOT,
    'Entradas del 18-ago en adelante (cualquier tipo)')

run('cd %s && grep "^\\[2026-08-1[89]\\|^\\[2026-08-2" storage/logs/laravel.log | grep -c "Failed to authenticate" || echo 0' % RROOT,
    'De esas, errores de auth SMTP (esperado 0 si el fix del 18-ago aguanta)')

run('cd %s && grep -E "^MAIL_PASSWORD" .env | sed "s/=.*/=***OCULTO***/"' % RROOT, 'MAIL_PASSWORD presente?')
run('cd %s && ls -la .env.bak-20260818-smtp 2>/dev/null || echo "(sin backup del 18-ago)"' % RROOT)

ssh.close()
