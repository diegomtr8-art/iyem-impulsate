# -*- coding: utf-8 -*-
"""LIMPIEZA DEL SERVIDOR (Fase 1, apartados A4 y B2).

  * Archiva los *.bak fuera del docroot y los retira (hoy Apache los sirve
    como texto plano: es el codigo fuente completo).
  * Retira opcache_reset.php de la raiz y de public/.
  * Saca del docroot _rollback_ev9_20260826.sql y database/database.sqlite.

Nada se retira sin haberse archivado antes en ~/backups/. Si el tar no queda
bien, el script aborta sin tocar el docroot.
"""
import paramiko, sys, io

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST  = '195.35.38.222'
PORT  = 65002
USER  = 'u489236361'
KEY   = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
STAMP = '20260827-fase1y2'
BK    = '~/backups/' + STAMP

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY, timeout=30)


def run(cmd, label=None, mostrar=True):
    if label:
        print('\n--- %s ---' % label)
    _i, o, e = ssh.exec_command(cmd, timeout=600)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    if mostrar:
        print(out if out else '(sin salida)')
    if err:
        print('[stderr] ' + err)
    return out


run('mkdir -p %s' % BK, mostrar=False)

# ── salvaguarda: la BD real debe ser MySQL para poder retirar el sqlite ──
db = run("cd %s && grep -E '^DB_CONNECTION' .env" % RROOT, 'motor de BD en produccion')
if 'mysql' not in db.lower():
    print('\n!! ABORTADO: DB_CONNECTION no es mysql. database.sqlite podria estar en uso.')
    sys.exit(1)

# ═══════════ 1. archivos .bak ═══════════
print('\n' + '=' * 60)
print('1. ARCHIVOS .bak (codigo fuente servido como texto plano)')
print('=' * 60)
FIND = "find . -name '*.bak*' -not -path './node_modules/*' -not -path './vendor/*'"
antes = run('cd %s && %s | wc -l' % (RROOT, FIND), 'cuantos hay')

if antes.strip() not in ('0', ''):
    run("cd %s && %s -print0 | tar -czf %s/baks-rescatados.tar.gz --null -T - && echo TAR_OK"
        % (RROOT, FIND, BK), 'archivar en el tar')
    # verificar que el tar es legible y trae la cuenta correcta ANTES de borrar
    dentro = run("tar -tzf %s/baks-rescatados.tar.gz 2>/dev/null | wc -l" % BK, mostrar=False)
    print('  archivos dentro del tar: %s (esperados: %s)' % (dentro, antes))
    if not dentro.strip().isdigit() or int(dentro) < int(antes):
        print('!! ABORTADO: el tar no cuadra. No se retira nada.')
        sys.exit(1)
    run('ls -lh %s/baks-rescatados.tar.gz' % BK, 'tar verificado')
    run('cd %s && %s -delete && echo RETIRADOS' % (RROOT, FIND), 'retirar del docroot')
    run('cd %s && %s | wc -l' % (RROOT, FIND), 'deben quedar 0')

# ═══════════ 2. opcache_reset.php ═══════════
print('\n' + '=' * 60)
print('2. opcache_reset.php')
print('=' * 60)
run("cd %s && cp -n opcache_reset.php %s/opcache_reset.raiz.php 2>/dev/null; "
    "cp -n public/opcache_reset.php %s/opcache_reset.public.php 2>/dev/null; "
    "rm -f opcache_reset.php public/opcache_reset.php && echo RETIRADOS"
    % (RROOT, BK, BK), 'archivar y retirar')
run("cd %s && ls opcache_reset.php public/opcache_reset.php 2>&1 | head -3" % RROOT, 'deben no existir')

# ═══════════ 3. dumps y sqlite ═══════════
print('\n' + '=' * 60)
print('3. DUMPS Y SQLITE EN EL DOCROOT')
print('=' * 60)
run("cd %s && for f in _rollback_ev9_20260826.sql database/database.sqlite citas_db; do "
    "if [ -f \"$f\" ]; then mv \"$f\" %s/ && echo \"movido: $f\"; fi; done; echo FIN"
    % (RROOT, BK), 'mover fuera del docroot')
run("cd %s && ls *.sql *.sqlite database/*.sqlite 2>/dev/null || echo '(no queda ninguno)'" % RROOT,
    'verificar')

# ═══════════ 4. cierre ═══════════
run("ls -la %s/" % BK, 'contenido del respaldo')
run("cd %s && /usr/bin/php artisan optimize:clear 2>&1 | tail -3" % RROOT, 'limpiar caches')
ssh.close()
print('\n=== LIMPIEZA TERMINADA ===')
