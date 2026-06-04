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
    ("Total citas", r'echo \App\Models\Cita::count();'),
    ("Citas edicion 1", r'echo \App\Models\Cita::where("edicion_id",1)->count();'),
    ("Citas sin edicion", r'echo \App\Models\Cita::whereNull("edicion_id")->count();'),
    ("Edicion activa", r'echo \App\Models\Edicion::activa()?->id ?? "ninguna";'),
    ("Sample citas", r'\App\Models\Cita::take(5)->get(["id","cliente_id","edicion_id","estado"])->each(fn($c) => print($c->id.",".$c->cliente_id.",".$c->edicion_id.",".$c->estado."\n"));'),
]

for label, code in checks:
    cmd = f"cd {REMOTE_ROOT} && php artisan tinker --execute='{code}'"
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    print(f'{label}: {out or "(vacío)"}')
    if err and 'INFO' not in err and 'Psy' not in err:
        print(f'  ERR: {err[:200]}')

ssh.close()
