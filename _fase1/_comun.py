# -*- coding: utf-8 -*-
"""Conexion y helpers compartidos por los pasos de la Fase 1."""
import paramiko, sys, io, urllib.request, os

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

HOST  = '195.35.38.222'
PORT  = 65002
USER  = 'u489236361'
KEY   = 'C:/Users/Diego Martinez/.ssh/id_deploy'
RROOT = '/home/u489236361/domains/impulsate.iyemyucatan.com/public_html'
BASE  = 'https://impulsate.iyemyucatan.com/'
LROOT = r'C:\xampp\htdocs\citas'
STAMP = '20260827-fase1'


def conectar():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, port=PORT, username=USER, key_filename=KEY, timeout=30)
    return ssh


def hacer_run(ssh):
    def run(cmd, label=None, mostrar=True):
        if label:
            print('\n--- %s ---' % label)
        _i, o, e = ssh.exec_command(cmd, timeout=600)
        out = o.read().decode('utf-8', 'replace').strip()
        err = e.read().decode('utf-8', 'replace').strip()
        if mostrar:
            print(out if out else '(sin salida)')
        if err:
            print('[stderr] ' + err)
        return out
    return run


def http(path, leer=2000):
    """Devuelve (codigo, bytes_leidos). Nunca lanza."""
    try:
        req = urllib.request.Request(BASE + path, headers={'User-Agent': 'fase1-verificacion'})
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, len(r.read(leer))
    except urllib.error.HTTPError as e:
        return e.code, 0
    except Exception as e:
        return 'ERR', str(e)[:70]
