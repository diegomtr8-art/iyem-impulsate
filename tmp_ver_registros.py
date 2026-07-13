import paramiko, sys
sys.stdout.reconfigure(encoding='utf-8', errors='replace')
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('195.35.38.222', port=65002, username='u489236361',
            key_filename='C:/Users/Diego Martinez/.ssh/id_deploy', timeout=30)
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'

cmd = r"""cd """ + RROOT + r""" && php artisan tinker --execute="
\$rows = DB::table('evento_usuario')
    ->join('eventos', 'eventos.id', '=', 'evento_usuario.evento_id')
    ->join('users',   'users.id',   '=', 'evento_usuario.user_id')
    ->select('eventos.nombre as evento','users.name as usuario','users.email','evento_usuario.tipo','evento_usuario.estado')
    ->orderBy('evento_usuario.tipo')
    ->get();
echo 'Total: ' . \$rows->count() . PHP_EOL;
foreach(\$rows as \$r) {
    echo '[' . \$r->tipo . '] ' . \$r->estado . ' — ' . \$r->usuario . ' <' . \$r->email . '>' . PHP_EOL;
}
" 2>&1"""

_, o, e = ssh.exec_command(cmd)
print(o.read().decode('utf-8', 'replace'))
print(e.read().decode('utf-8', 'replace'))
ssh.close()
