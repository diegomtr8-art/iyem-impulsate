import paramiko
import os

HOST = '195.35.38.222'
PORT = 65002
USER = 'u489236361'
KEY_PATH = r'C:\Users\Diego Martinez\.ssh\id_deploy'
REMOTE_ROOT = '/home/u489236361/domains/lightcyan-mallard-509513.hostingersite.com/public_html'
LOCAL_BUILD = r'C:\xampp\htdocs\citas\public\build'

def sftp_mkdir_p(sftp, remote_path):
    dirs = []
    path = remote_path
    while True:
        try:
            sftp.stat(path)
            break
        except FileNotFoundError:
            dirs.append(path)
            path = path.rsplit('/', 1)[0]
            if not path:
                break
    for d in reversed(dirs):
        try:
            sftp.mkdir(d)
        except Exception:
            pass

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY_PATH)
sftp = ssh.open_sftp()

print('Uploading build to root-level build/ ...')
count = 0
for root, dirs, files in os.walk(LOCAL_BUILD):
    for fname in files:
        local_file = os.path.join(root, fname)
        rel = os.path.relpath(local_file, LOCAL_BUILD).replace('\\', '/')
        remote_file = f'{REMOTE_ROOT}/build/{rel}'
        sftp_mkdir_p(sftp, remote_file.rsplit('/', 1)[0])
        sftp.put(local_file, remote_file)
        count += 1
        if count % 10 == 0:
            print(f'  {count} files...')

print(f'Done: {count} files uploaded to root build/')
sftp.close()

stdin, stdout, stderr = ssh.exec_command(f'ls {REMOTE_ROOT}/build/assets/ | wc -l')
print(f'Files in root build/assets/: {stdout.read().decode().strip()}')

ssh.close()
print('Fix complete.')
