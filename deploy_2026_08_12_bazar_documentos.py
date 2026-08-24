"""Deploy 2026-08-12: Bazar - Solicitudes + Documentos (INE/CSF)
Alcance acotado: SOLO esta feature.
- Migracion: ine_path/csf_path/csf_fecha en users
- User.php: fillable
- CompletarPerfilController.php: metodo subirDocumentos
- EventoRegistroController.php: gate de documentos en registrarBazar()
- EventoSolicitudesController.php: soporte tipo='expositor'
- routes/web.php: SOLO se sube una copia aislada (produccion actual + 1 linea nueva de
  perfil/documentos) -- el routes/web.php real del working tree tiene 4 rutas mas
  (metricas.exportar, usuarios.genero, agenda.show, torre.config.mesas) que pertenecen a
  otras features pendientes NO aprobadas para este deploy.
- Frontend: DocumentosLegalesForm.vue (nuevo), Profile/Show.vue, Admin/Eventos/Solicitudes.vue

Verificado por hash antes de este script que el resto del working tree pendiente (Agenda,
Citas, Metricas, TorreControl, CorreoMasivo, Plantillas, Encuestas, Restauranteros, Evento.php,
EncuestaEnvioService, Bazar evaluacion/publico, Exports) ya esta 100% desplegado en produccion
(22 archivos, hashes identicos), por lo que un build completo del working tree es seguro de
subir sin revertir nada. Las 4 rutas fuera de alcance quedan excluidas manualmente del
routes/web.php que se sube.
"""
import paramiko, os

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
LROOT = 'C:/xampp/htdocs/citas'
SCRATCH = 'C:/Users/DIEGOM~1/AppData/Local/Temp/claude/C--xampp-htdocs-citas/d3f14cb5-d72a-497c-ae18-557d15c6fe8c/scratchpad/deploy_bazar_docs'

def mkdirp(sftp, p):
    dirs, path = [], p
    while True:
        try: sftp.stat(path); break
        except FileNotFoundError: dirs.append(path); path = path.rsplit('/', 1)[0]
        if not path: break
    for d in reversed(dirs):
        try: sftp.mkdir(d)
        except Exception: pass

def up(sftp, lr, rr=None, base=LROOT):
    if rr is None: rr = lr.replace('\\', '/')
    lp = os.path.join(base, lr.replace('/', os.sep))
    rp = RROOT + '/' + rr
    if not os.path.exists(lp): print('  SKIP (no existe local) ' + lr); return
    mkdirp(sftp, rp.rsplit('/', 1)[0]); sftp.put(lp, rp); print('  UP  ' + rr)

def updir(sftp, ld, rd):
    ldir = os.path.join(LROOT, ld.replace('/', os.sep))
    rdir = RROOT + '/' + rd; n = 0
    for root, dirs, files in os.walk(ldir):
        dirs[:] = [d for d in dirs if d not in ['.git', '__pycache__', 'node_modules']]
        for f in files:
            lf = os.path.join(root, f)
            rel = os.path.relpath(lf, ldir).replace('\\', '/')
            rp = rdir + '/' + rel
            mkdirp(sftp, rp.rsplit('/', 1)[0]); sftp.put(lf, rp); n += 1
    print('  ' + str(n) + ' archivos -> ' + rd)

def run(ssh, cmd):
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode('utf-8', 'replace').strip()
    err = e.read().decode('utf-8', 'replace').strip()
    for l in out.split('\n')[-30:]:
        if l.strip(): print('  ' + l)
    if err:
        for l in err.split('\n')[-15:]:
            if l.strip(): print('  ERR: ' + l)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print('Conectando a Hostinger (impulsate.iyemyucatan.com)...')
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
print('Conectado OK')
sftp = ssh.open_sftp()

print('\n=== Backup remoto de routes/web.php ===')
run(ssh, f'cp {RROOT}/routes/web.php {RROOT}/routes/web.php.bak-20260812')

print('\n=== Backend PHP: Bazar Solicitudes + Documentos ===')
up(sftp, 'app/Models/User.php')
up(sftp, 'app/Http/Controllers/EventoRegistroController.php')
up(sftp, 'app/Http/Controllers/CompletarPerfilController.php')
up(sftp, 'app/Http/Controllers/Admin/EventoSolicitudesController.php')

print('\n=== routes/web.php (version aislada: prod actual + 1 linea nueva) ===')
up(sftp, 'web.php', 'routes/web.php', base=SCRATCH)

print('\n=== Migracion nueva ===')
up(sftp, 'database/migrations/2026_08_12_101212_add_documentos_legales_to_users_table.php')

print('\n=== Frontend fuente (Vue) ===')
up(sftp, 'resources/js/Pages/Profile/Partials/DocumentosLegalesForm.vue')
up(sftp, 'resources/js/Pages/Profile/Show.vue')
up(sftp, 'resources/js/Pages/Admin/Eventos/Solicitudes.vue')

print('\n=== Assets compilados (JS/CSS) -- a AMBAS rutas ===')
updir(sftp, 'public/build', 'public/build')
updir(sftp, 'public/build', 'build')

print('\n=== Migrar BD + limpiar caches Laravel + optimize ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan migrate --force 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan route:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan config:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan view:clear 2>&1')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan optimize 2>&1')

print('\n=== Verificacion: rutas ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan route:list 2>&1 | grep -i "perfil.documentos\\|metricas.exportar\\|usuarios.genero\\|agenda.show\\|torre.config.mesas"')

print('\n=== Verificacion: migracion ===')
run(ssh, f'cd {RROOT} && /usr/bin/php artisan migrate:status 2>&1 | tail -3')

sftp.close(); ssh.close()
print('\nDeploy completado.')
