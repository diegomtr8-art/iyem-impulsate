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
    ("Fecha más reciente", r'echo \App\Models\Cita::max("inicio");'),
    ("Fecha más antigua", r'echo \App\Models\Cita::min("inicio");'),
    ("Citas futuras (después de hoy)", r'echo \App\Models\Cita::where("inicio",">=",now())->count();'),
    ("Citas por mes", r'\App\Models\Cita::selectRaw("DATE_FORMAT(inicio,\"%Y-%m\") as mes, count(*) as total")->groupBy("mes")->orderBy("mes","desc")->take(6)->get()->each(fn($r)=>print($r->mes." => ".$r->total."\n"));'),
    ("Unique cliente_ids with citas", r'echo \App\Models\Cita::distinct()->count("cliente_id");'),
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
