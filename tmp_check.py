import paramiko, sys

HOST="195.35.38.222"; PORT=65002; USER="u489236361"
KEY_PATH="C:/Users/Diego Martinez/.ssh/id_deploy"
RROOT="/home/u489236361/domains/lightcyan-mallard-509513.hostingersite.com/public_html"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH, timeout=30)

_, out, _ = ssh.exec_command("grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' " + RROOT + "/.env")
env_lines = out.read().decode("utf-8","replace").strip().split("\n")
env = {}
for line in env_lines:
    if "=" in line:
        k,v = line.split("=",1)
        env[k]=v.strip()

h = env.get("DB_HOST","localhost")
u = env.get("DB_USERNAME","")
p = env.get("DB_PASSWORD","")
d = env.get("DB_DATABASE","")

q1 = "mysql -h{} -u{} -p'{}' {} 2>/dev/null -e \"SELECT id,name,email,email_verified_at FROM users WHERE email='fmam1295@gmail.com';\"".format(h,u,p,d)
_, o1, _ = ssh.exec_command(q1)
print("=== Usuario ===")
print(o1.read().decode("utf-8","replace"))

q2 = "mysql -h{} -u{} -p'{}' {} 2>/dev/null -e \"SELECT r.id,r.nombre_restaurante,r.activo FROM restauranteros r JOIN users u ON r.user_id=u.id WHERE u.email='fmam1295@gmail.com';\"".format(h,u,p,d)
_, o2, _ = ssh.exec_command(q2)
print("=== Restaurantero ===")
print(o2.read().decode("utf-8","replace"))

ssh.close()
