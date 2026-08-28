# -*- coding: utf-8 -*-
"""AUDITORIA POST-DESPLIEGUE. Solo lectura: peticiones GET y consultas."""
import paramiko, sys, io, urllib.request, ssl

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

BASE  = 'https://impulsate.iyemyucatan.com/'
HOST  = '195.35.38.222'
PORT  = 65002
USER  = 'u489236361'
KEY   = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'

_op = urllib.request.build_opener(urllib.request.HTTPRedirectHandler())


def http(path, seguir=True):
    """(codigo, bytes). Con seguir=False devuelve 30x sin seguirlo."""
    class NoRedir(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k):
            return None
    op = urllib.request.build_opener() if seguir else urllib.request.build_opener(NoRedir)
    try:
        r = op.open(urllib.request.Request(BASE + path, headers={'User-Agent': 'auditoria-post'}), timeout=30)
        return r.status, len(r.read(3000))
    except urllib.error.HTTPError as e:
        return e.code, 0
    except Exception as e:
        return 'ERR', str(e)[:60]


ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY, timeout=30)


def sh(cmd):
    _i, o, e = ssh.exec_command(cmd, timeout=300)
    return o.read().decode('utf-8', 'replace').strip()


fallos, avisos = [], []


def check(cond, texto, critico=True):
    print('  %s  %s' % ('OK  ' if cond else ('FALLA' if critico else 'AVISO'), texto))
    if not cond:
        (fallos if critico else avisos).append(texto)


print('=' * 70)
print('AUDITORIA POST-DESPLIEGUE  ·  impulsate.iyemyucatan.com')
print('=' * 70)

# ─────────── 1. El sitio sigue funcionando ───────────
print('\n[1] EL SITIO FUNCIONA')
asset = sh("cd %s && ls -1 public/build/assets/*.js 2>/dev/null | head -1" % RROOT).replace('public/', '', 1)
img = sh("cd %s && find storage/app/public -type f \\( -name '*.jpg' -o -name '*.png' -o -name '*.webp' \\) "
         "-not -path '*documentos*' 2>/dev/null | head -1" % RROOT).replace('storage/app/public/', 'storage/', 1)
for ruta, etiqueta in [('', 'portada'), ('login', 'login'), ('register', 'registro'),
                       ('proveedores', 'directorio de proveedores'), ('robots.txt', 'robots.txt')]:
    c = http(ruta)[0]
    check(c == 200, '%-32s -> %s' % (etiqueta, c))
if asset:
    c = http(asset)[0]
    check(c == 200, '%-32s -> %s' % ('JS compilado', c))
if img:
    c = http(img)[0]
    check(c == 200, '%-32s -> %s' % ('imagen en /storage', c))

# ─────────── 2. Exposicion de archivos (A2) ───────────
print('\n[2] EXPOSICION DE ARCHIVOS')
SENSIBLES = ['.env', '.env.example', '.git/config', 'composer.json', 'composer.lock',
             'package.json', 'phpunit.xml', 'artisan', 'citas_db',
             '_rollback_ev9_20260826.sql', 'database/database.sqlite',
             'storage/logs/laravel.log', 'DEPLOY.md',
             'app/Http/Controllers/CompletarPerfilController.php',
             'routes/web.php', 'config/app.php',
             'routes/web.php.bak-20260820', 'app/Models/User.php.bak-20260817',
             'opcache_reset.php', 'public/opcache_reset.php',
             'deploy_convocatoria_correo.py']
for f in SENSIBLES:
    c = http(f)[0]
    check(c in (403, 404), '%-52s -> %s' % (f, c))

# ─────────── 3. Documentos de identidad (INE / CSF) ───────────
print('\n[3] DOCUMENTOS DE IDENTIDAD')
muestra = sh("cd %s && /usr/bin/php artisan tinker --execute='"
             r'$u=App\Models\User::whereNotNull("ine_path")->first(); echo $u? ($u->id."|".$u->ine_path):"";'
             "' 2>&1 | tail -1" % RROOT)
print('     usuario de muestra: %s' % (muestra or '(ninguno)'))
if '|' in muestra:
    uid, ruta = muestra.split('|', 1)
    check(not ruta.endswith('ine.pdf') and not ruta.endswith('ine.jpg'),
          'la ruta ya NO es predecible (%s)' % ruta.split('/')[-1][:28])
    for url in ['storage/documentos/%s/ine.pdf' % uid,
                'storage/documentos/%s/ine.jpg' % uid,
                'storage/' + ruta]:
        c = http(url)[0]
        check(c in (403, 404), 'URL publica vieja %-34s -> %s' % (url.split('/')[-1], c))
    c = http('documentos/%s/ine' % uid, seguir=False)[0]
    check(c in (302, 401, 403), 'ruta nueva /documentos/%s/ine exige sesion -> %s' % (uid, c))

pub = sh("cd %s && find storage/app/public/documentos -type f 2>/dev/null | wc -l" % RROOT)
pri = sh("cd %s && find storage/app/private/documentos -type f 2>/dev/null | wc -l" % RROOT)
check(pub == '0', 'documentos en disco publico: %s (debe ser 0)' % pub)
check(int(pri) > 0, 'documentos en disco privado: %s' % pri)
perm = sh("cd %s && stat -c '%%a' storage/app/private/documentos" % RROOT)
check(perm.startswith('7'), 'permisos de storage/app/private/documentos: %s' % perm, critico=False)

# ─────────── 4. Respaldos .bak ───────────
print('\n[4] RESPALDOS .bak EN EL DOCROOT')
n = sh("cd %s && find . -name '*.bak*' -not -path './node_modules/*' -not -path './vendor/*' | wc -l" % RROOT)
check(n == '0', 'archivos .bak que quedan: %s' % n)

# ─────────── 5. Pantalla TV ───────────
print('\n[5] PANTALLA TV')
check(http('tv/impulsate-tv-2026')[0] == 404, 'el token viejo impulsate-tv-2026 -> 404')
check(http('api/tv/impulsate-tv-2026/publico')[0] == 404, 'API con el token viejo -> 404')
tok = sh("cd %s && /usr/bin/php artisan tinker --execute='"
         r'echo App\Models\Evento::where("activa",true)->first()?->tv_token;'
         "' 2>&1 | tail -1").strip()
if tok and len(tok) > 20:
    check(http('tv/' + tok)[0] == 200, 'el token nuevo (%s...) sirve la pantalla' % tok[:10])
    c, n2 = http('api/tv/%s/publico' % tok)
    check(c == 200, 'API con el token nuevo -> %s' % c)

# ─────────── 6. Acciones destructivas por GET (Fase 2, C3) ───────────
print('\n[6] ACCIONES DESTRUCTIVAS POR GET')
rutas = sh("cd %s && /usr/bin/php artisan route:list --json 2>/dev/null" % RROOT)
import json
try:
    R = json.loads(rutas)
    porNombre = {r.get('name'): r for r in R if r.get('name')}
    for n_, met in [('agenda.aceptar', 'GET'), ('agenda.aceptar.post', 'POST'),
                    ('agenda.rechazar.post', 'POST'),
                    ('citas.confirmar-token', 'GET'), ('citas.confirmar-token.post', 'POST'),
                    ('citas.rechazar-token.post', 'POST'), ('admin.tv.rotar-token', 'POST')]:
        r = porNombre.get(n_)
        check(r is not None and met in r['method'], 'ruta %-28s %s' % (n_, met))
    ag = porNombre.get('agenda.aceptar')
    check(ag and 'verDesdeEnlace' in (ag.get('action') or ''),
          'GET agenda.aceptar ya no ejecuta (verDesdeEnlace)')
except Exception as ex:
    avisos.append('no se pudo leer route:list (%s)' % ex)
    print('  AVISO  no se pudo leer route:list')

# ─────────── 7. Errores en el log ───────────
print('\n[7] LOG DE ERRORES (desde el despliegue)')
hoy = sh("date +%Y-%m-%d")
errs = sh("cd %s && grep -c '\\[%s' storage/logs/laravel.log 2>/dev/null || echo 0" % (RROOT, hoy))
print('     lineas de log de hoy: %s' % errs)
ult = sh("cd %s && grep '\\[%s' storage/logs/laravel.log 2>/dev/null | grep -iE 'ERROR|CRITICAL' | tail -3" % (RROOT, hoy))
print(ult if ult else '     (sin ERROR/CRITICAL hoy)')
check(not ult, 'sin errores nuevos hoy', critico=False)

# ─────────── Resumen ───────────
print('\n' + '=' * 70)
if fallos:
    print('FALLOS CRITICOS: %d' % len(fallos))
    for f in fallos:
        print('   X ' + f)
else:
    print('SIN FALLOS CRITICOS')
if avisos:
    print('\nAVISOS: %d' % len(avisos))
    for a in avisos:
        print('   ! ' + a)
print('=' * 70)
ssh.close()
