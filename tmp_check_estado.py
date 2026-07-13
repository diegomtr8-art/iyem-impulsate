import paramiko, sys
sys.stdout.reconfigure(encoding='utf-8', errors='replace')
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('195.35.38.222', port=65002, username='u489236361',
            key_filename='C:/Users/Diego Martinez/.ssh/id_deploy', timeout=30)
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'

cmd = r"""cd """ + RROOT + r""" && php artisan tinker --execute="
// evento_usuario
\$eu = DB::table('evento_usuario')->get();
echo '== evento_usuario total: ' . \$eu->count() . PHP_EOL;
foreach(\$eu as \$r) {
    echo '  [' . \$r->tipo . '] estado=' . \$r->estado . ' user_id=' . \$r->user_id . PHP_EOL;
}

// Evento activo
\$ev = \App\Models\Evento::where('activa', true)->first();
echo '== Evento activo id=' . (\$ev ? \$ev->id : 'ninguno') . ' nombre=' . (\$ev ? \$ev->nombre : '') . PHP_EOL;

// Restauranteros con edicion_id del evento activo
if(\$ev) {
    \$prov = DB::table('restauranteros')->where('edicion_id', \$ev->id)->count();
    echo '== Restauranteros con edicion_id=' . \$ev->id . ': ' . \$prov . PHP_EOL;
}

// Total restauranteros
\$total = DB::table('restauranteros')->count();
echo '== Total restauranteros en BD: ' . \$total . PHP_EOL;

// Columnas del evento activo
if(\$ev) {
    \$cols = DB::getSchemaBuilder()->getColumnListing('eventos');
    \$contadores = ['num_proveedores', 'proveedores_count', 'contador_proveedores', 'max_proveedores'];
    foreach(\$contadores as \$c) {
        if(in_array(\$c, \$cols)) {
            echo '== Columna ' . \$c . ' en evento: ' . \$ev->{\$c} . PHP_EOL;
        }
    }
}
" 2>&1"""

_, o, e = ssh.exec_command(cmd)
print(o.read().decode('utf-8', 'replace'))
print(e.read().decode('utf-8', 'replace'))
ssh.close()
