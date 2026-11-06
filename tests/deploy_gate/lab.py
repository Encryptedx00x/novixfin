"""Local-only Caddy/PHP-CGI/MariaDB deployment-gate rehearsal.
Requires CADDY_EXE, PHP_CGI, PHP_EXE, MARIADB_EXE and LAB_DIR.
Connects ONLY to 127.0.0.1:33316/novixfin_test and creates a uniquely named probe.
"""
import hashlib, http.client, os, pathlib, shutil, stat, subprocess, threading, time, uuid, zipfile, io

ROOT = pathlib.Path(__file__).resolve().parents[2]
LAB = pathlib.Path(os.environ['LAB_DIR']).resolve()
assert 'sheet-drain-lab' in LAB.name and LAB.is_dir()
APP = LAB / 'app'; APP.mkdir(exist_ok=True)
assert not any(APP.iterdir()), 'LAB/app must be empty'
CADDY=os.environ['CADDY_EXE']; CGI=os.environ['PHP_CGI']; PHP=os.environ['PHP_EXE']; MYSQL=os.environ['MARIADB_EXE']
FILES=['actions/sheets/_bootstrap.php','actions/sheets/load.php','actions/sheets/save.php','actions/sheets/snapshot.php','actions/sheets/restore.php','public/scripts/sheet.js']
run=lambda cmd, **kw: subprocess.run(cmd,check=True,**kw)
archive=subprocess.check_output(['git','archive','--format=zip','HEAD'],cwd=ROOT)
with zipfile.ZipFile(io.BytesIO(archive)) as z: z.extractall(APP)
for f in FILES:
    (APP/f).write_bytes(subprocess.check_output(['git','show','2a2f976:'+f],cwd=ROOT))
name='probe_'+uuid.uuid4().hex[:12]; lock='novix_lab_'+uuid.uuid4().hex[:12]
release=LAB/'release.signal'
sqlbase=[MYSQL,'--protocol=tcp','-h','127.0.0.1','-P','33316','-u','root','--batch','--skip-column-names','novixfin_test']
def sql(q): return subprocess.check_output(sqlbase+['-e',q],text=True,stderr=subprocess.DEVNULL).strip()
sql('CREATE TABLE '+name+' (id INT PRIMARY KEY, val INT NOT NULL) ENGINE=InnoDB')
sql('INSERT INTO '+name+' VALUES (1,0)')
cache=APP/'public/lab_cache.php'
cache.write_text("""<?php
header('Content-Type: application/json');
$files=FILES;
$result=[];
foreach ($files as $f) {
 if (isset($_GET['invalidate'])) { $result[$f]=opcache_invalidate($f, true); }
 else { $result[$f]=opcache_is_script_cached($f); }
}
echo json_encode(['enabled'=>function_exists('opcache_get_status') && opcache_get_status() !== false,'files'=>$result]);
""".replace('FILES', '['+','.join("'"+(APP/f).as_posix()+"'" for f in FILES)+']'),encoding='utf-8')
slow=APP/'actions/sheets/lab_slow.php'
slow.write_text("""<?php
$p=new PDO('mysql:host=127.0.0.1;port=33316;dbname=novixfin_test','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->query(\"SELECT GET_LOCK('LOCKNAME',0)\");
while (!file_exists('RELEASE')) { usleep(50000); clearstatcache(); }
$p->exec('UPDATE TABLENAME SET val=val+1 WHERE id=1');
$p->query(\"SELECT RELEASE_LOCK('LOCKNAME')\");
echo 'done';
""".replace('LOCKNAME',lock).replace('TABLENAME',name).replace('RELEASE',release.as_posix()),encoding='utf-8')
root=APP.as_posix(); admin='127.0.0.1:38128'; port=38127

def config(blocked):
    barrier='@sheet_pause path /actions/sheets/*\n    handle @sheet_pause {\n        respond "planilha indisponivel" 503\n    }' if blocked else ''
    return '''{\n    admin ADMIN\n    auto_https off\n}\nhttp://127.0.0.1:PORT {\n    root * ROOT/public\n    BARRIER\n    @actions path /actions/*\n    handle @actions {\n        root * ROOT\n        php_fastcgi 127.0.0.1:39091\n    }\n    @publicPath path /public/*\n    handle @publicPath {\n        uri strip_prefix /public\n        try_files {path} {path}/ /index.php?{query}\n        php_fastcgi 127.0.0.1:39091\n        file_server\n    }\n    handle {\n        respond "OUTRAS ROTAS OK" 200\n    }\n}\nhttp://other.test:PORT {\n    respond "OUTRO DOMINIO OK" 200\n}\n'''.replace('ADMIN',admin).replace('PORT',str(port)).replace('ROOT',root).replace('BARRIER',barrier)
configfile=LAB/'Caddyfile'
def setconfig(blocked, initial=False):
    configfile.write_text(config(blocked),encoding='utf-8')
    run([CADDY,'validate','--config',str(configfile),'--adapter','caddyfile'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    if not initial: run([CADDY,'reload','--config',str(configfile),'--adapter','caddyfile'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
def req(path,method='GET',host='127.0.0.1:38127'):
    c=http.client.HTTPConnection('127.0.0.1',port,timeout=20); c.request(method,path,headers={'Host':host}); r=c.getresponse(); b=r.read(); c.close(); return r.status,b

def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
def copy_six(candidate):
    for f in FILES:
        p=APP/f
        data=(ROOT/f).read_bytes() if candidate else subprocess.check_output(['git','show','2a2f976:'+f],cwd=ROOT)
        mode=stat.S_IMODE(p.stat().st_mode); p.write_bytes(data); assert stat.S_IMODE(p.stat().st_mode)==mode
        assert sha(p)==hashlib.sha256(data).hexdigest()
    print('6 arquivos: hashes e modos locais conferidos',flush=True)

procs=[]
try:
    setconfig(False,True)
    env=os.environ.copy(); env.update(DB_HOST='127.0.0.1',DB_PORT='33316',DB_DATABASE='novixfin_app',DB_USERNAME='root',DB_PASSWORD='')
    flags=getattr(subprocess,'CREATE_NO_WINDOW',0)
    procs.append(subprocess.Popen([CGI,'-d','zend_extension=C:/Users/Encryptedx/AppData/Local/php83/ext/php_opcache.dll','-d','opcache.enable=1','-d','opcache.enable_cli=1','-d','opcache.validate_timestamps=0','-d','opcache.file_update_protection=0','-b','127.0.0.1:39091'],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,creationflags=flags))
    procs.append(subprocess.Popen([CADDY,'run','--config',str(configfile),'--adapter','caddyfile'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,creationflags=flags))
    for _ in range(100):
        try:
            if req('/')[0]==200: break
        except OSError: time.sleep(.1)
    else: raise RuntimeError('Caddy não iniciou')
    import json
    assert req('/actions/sheets/load.php')[0] in (301,302,303)
    result=[]
    def slow_request(): result.append(req('/actions/sheets/lab_slow.php','POST'))
    t=threading.Thread(target=slow_request); t.start()
    for _ in range(100):
        if sql("SELECT IS_USED_LOCK('"+lock+"') IS NOT NULL")=='1': break
        time.sleep(.1)
    else: raise RuntimeError('não observou lock da requisição antiga')
    print('requisição antiga: lock no MariaDB observado',flush=True)
    setconfig(True)
    for method in ('GET','POST','PUT','PATCH','DELETE','OPTIONS','HEAD'):
        for endpoint in ('load.php','save.php','snapshot.php','restore.php'):
            assert req('/actions/sheets/'+endpoint,method)[0]==503,(method,endpoint)
    for path in ('/actions/sheets//save.php','/actions/sheets/./save.php','/actions/sheets/%73ave.php'):
        assert req(path,'POST')[0]==503,path
    assert req('/')[0]==200 and req('/actions/sheets/save.php','POST','other.test:38127')[0]==200
    assert sql("SELECT IS_USED_LOCK('"+lock+"') IS NOT NULL")=='1'
    assert sql('SELECT val FROM '+name+' WHERE id=1')=='0'
    print('barreira: 28 combinações HTTP 503; outras rotas/domínio preservados; lock ainda ativo',flush=True)
    # A troca é recusada enquanto o lock da requisição instrumentada existir.
    def drained(): return sql("SELECT IS_USED_LOCK('"+lock+"') IS NULL")=='1'
    assert not drained()
    release.write_text('go',encoding='ascii'); t.join(15)
    assert not t.is_alive() and result[0][0]==200 and drained()
    assert sql('SELECT val FROM '+name+' WHERE id=1')=='1'
    assert req('/actions/accounts/toggle.php','POST')[0] in (301,302,303)
    print('requisição anterior concluiu, lock liberado e banco=1; troca permitida',flush=True)
    copy_six(True)
    cache_state=json.loads(req('/public/lab_cache.php?invalidate=1')[1]); assert cache_state['enabled'], cache_state
    assert all(cache_state['files'].values()), cache_state
    print('opcache CGI isolado ativo: chamadas de invalidação aceitas; persistência de cache antigo não comprovada',flush=True)
    setconfig(False)
    testenv=env.copy(); testenv['HTTP_BASE']='http://127.0.0.1:38127'
    run([PHP,str(ROOT/'tests/sheets_http_persist_test.php')],cwd=ROOT,env=testenv)
    print('candidato persistiu por HTTP após desbloqueio',flush=True)
    # A client can disappear while PHP still owns the lock and continues executing.
    import socket
    release.unlink()
    sock=socket.create_connection(('127.0.0.1',port),timeout=5)
    sock.sendall(b'POST /actions/sheets/lab_slow.php HTTP/1.1\r\nHost: 127.0.0.1:38127\r\nContent-Length: 0\r\n\r\n')
    for _ in range(100):
        if not drained(): break
        time.sleep(.1)
    else: raise RuntimeError('segunda requisição não iniciou')
    sock.close()
    setconfig(True)
    assert req('/actions/sheets/save.php','POST')[0]==503 and not drained()
    release.write_text('go',encoding='ascii')
    for _ in range(100):
        if drained(): break
        time.sleep(.1)
    assert drained() and sql('SELECT val FROM '+name+' WHERE id=1')=='2'
    print('cliente desconectado: PHP seguiu ativo e gravou; contagem só no proxy seria insuficiente',flush=True)

    assert req('/actions/sheets/save.php','POST')[0]==503 and drained()
    # Simulate a corrupt deploy: fail the integrity check before reopening.
    target=APP/FILES[0]; target.write_bytes(target.read_bytes()+b'\nFAILURE-INJECTION\n')
    assert sha(target)!=sha(ROOT/FILES[0]); print('falha injetada: hash divergente, publicação abortada',flush=True)
    copy_six(False)
    cache_state=json.loads(req('/public/lab_cache.php?invalidate=1')[1]); assert cache_state['enabled'] and all(cache_state['files'].values())
    # Old backend accepts revisionless writes. Keep gate closed; never claim safe reopen.
    assert req('/actions/sheets/save.php','POST')[0]==503
    assert sql('SELECT val FROM '+name+' WHERE id=1')=='2'
    print('rollback isolado: arquivos anteriores restaurados; barreira mantida por backend legado',flush=True)
    print('PHP-CGI local: remoção de bytecode anterior não comprovada; FPM e instrumentação real não reproduzidos',flush=True)
finally:
    for p in reversed(procs): p.terminate()
    for p in procs:
        try: p.wait(timeout=5)
        except subprocess.TimeoutExpired: p.kill()
    sql('DROP TABLE IF EXISTS '+name)
