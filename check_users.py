import paramiko

HOST = '195.35.38.222'
PORT = 65002
USER = 'u489236361'
KEY_PATH = r'C:\Users\Diego Martinez\.ssh\id_deploy'
REMOTE_ROOT = '/home/u489236361/domains/lightcyan-mallard-509513.hostingersite.com/public_html'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH)

checks = [
    ("Clientes con citas (id, name, email, n_citas)",
     r'\App\Models\Cita::with("cliente")->get()->groupBy("cliente_id")->map(fn($g,$id)=>["id"=>$id,"name"=>$g->first()->cliente->name,"email"=>$g->first()->cliente->email,"citas"=>$g->count()])->each(fn($u)=>print($u["id"]." ".$u["name"]." <".$u["email"]."> citas=".$u["citas"]."\n"));'),
    ("Roles de usuarios",
     r'\App\Models\User::with("roles")->take(10)->get()->each(fn($u)=>print($u->id." ".$u->email." roles=".implode(",",$u->roles->pluck("name")->toArray())."\n"));'),
]

for label, code in checks:
    cmd = f"cd {REMOTE_ROOT} && php artisan tinker --execute='{code}'"
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    print(f'{label}:')
    print(f'  {out or "(vacío)"}')
    if err and 'INFO' not in err and 'Psy' not in err:
        print(f'  ERR: {err[:200]}')

ssh.close()
