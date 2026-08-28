# -*- coding: utf-8 -*-
"""Solo lectura: renderiza el mailable del acuse SIN enviarlo.

Verifica que los placeholders {{nombre_usuario}}, {{nombre_evento}} y
{{tipo_participante}} se sustituyen de verdad y que no queda ninguno sin
reemplazar en el HTML final.
"""
import paramiko, sys, io

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST = '195.35.38.222'; PORT = 65002; USER = 'u489236361'
KEY_PATH = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'

PHP = r'''<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$plantilla = App\Models\PlantillaCorreo::paraClave('evento_solicitud_recibida');
if (!$plantilla) { echo "FALLO: plantilla no encontrada o inactiva\n"; exit(1); }
echo "Plantilla OK  | activo=".$plantilla->activo."\n";

$m = new App\Mail\PlantillaCorreoMail($plantilla, [
    'nombre_usuario'    => 'Diego Martinez (PRUEBA)',
    'nombre_evento'     => 'Encuentro de Negocios Impulsate Edicion Conecta',
    'tipo_participante' => 'proveedor',
]);

$html = $m->render();            // renderiza, NO envia
$asunto = $m->envelope()->subject ?? $plantilla->asunto;

echo "Asunto renderizado: ".$asunto."\n";
echo "Tamano HTML: ".strlen($html)." bytes\n";

$pendientes = [];
if (preg_match_all('/\{\{\s*[a-z_]+\s*\}\}/i', $html, $mm)) { $pendientes = array_unique($mm[0]); }
echo "Placeholders SIN reemplazar en el cuerpo: ".(count($pendientes) ? implode(', ', $pendientes) : "NINGUNO (correcto)")."\n";

foreach (['Diego Martinez (PRUEBA)' => 'nombre_usuario',
          'Edicion Conecta'         => 'nombre_evento',
          'proveedor'               => 'tipo_participante'] as $frag => $var) {
    echo (str_contains($html, $frag) ? "  OK   " : "  FALTA ").$var."\n";
}
echo "Contiene el bloque '¿Que sigue?': ".(str_contains($html, 'sigue') ? "si" : "NO")."\n";
'''

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)
sftp = ssh.open_sftp()

remoto = RROOT + '/_render_test_20260826.php'
with sftp.open(remoto, 'w') as f:
    f.write(PHP)

_in, out, err = ssh.exec_command('cd %s && /usr/bin/php _render_test_20260826.php 2>&1' % RROOT, timeout=180)
print(out.read().decode('utf-8', 'replace').strip())
e = err.read().decode('utf-8', 'replace').strip()
if e: print('[stderr] ' + e)

# limpiar el script temporal del servidor
ssh.exec_command('rm -f %s' % remoto)
_in, out, _ = ssh.exec_command('ls %s 2>&1 || echo "temporal borrado OK"' % remoto, timeout=60)
print('\n' + out.read().decode('utf-8', 'replace').strip())

sftp.close(); ssh.close()
