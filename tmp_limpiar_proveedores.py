import paramiko, sys
sys.stdout.reconfigure(encoding='utf-8', errors='replace')
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('195.35.38.222', port=65002, username='u489236361',
            key_filename='C:/Users/Diego Martinez/.ssh/id_deploy', timeout=30)
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'

cmd = r"""cd """ + RROOT + r""" && php artisan tinker --execute="
// 1. Borrar todos los registros de evento_usuario
\$eu = DB::table('evento_usuario')->count();
DB::table('evento_usuario')->delete();
echo 'evento_usuario eliminados: ' . \$eu . PHP_EOL;
echo 'evento_usuario restantes: ' . DB::table('evento_usuario')->count() . PHP_EOL;

// 2. Quitar edicion_id del evento activo en restauranteros
\$ev = \App\Models\Evento::where('activa', true)->first();
if(\$ev) {
    \$n = DB::table('restauranteros')->where('edicion_id', \$ev->id)->count();
    DB::table('restauranteros')->where('edicion_id', \$ev->id)->update(['edicion_id' => null]);
    echo 'Restauranteros con edicion_id desvinculados: ' . \$n . PHP_EOL;
    \$restantes = DB::table('restauranteros')->where('edicion_id', \$ev->id)->count();
    echo 'Restauranteros aun con edicion_id=' . \$ev->id . ': ' . \$restantes . PHP_EOL;
} else {
    echo 'No hay evento activo.' . PHP_EOL;
}

echo 'LISTO' . PHP_EOL;
" 2>&1"""

_, o, e = ssh.exec_command(cmd)
print(o.read().decode('utf-8', 'replace'))
print(e.read().decode('utf-8', 'replace'))
ssh.close()
