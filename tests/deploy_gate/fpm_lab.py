"""Destructive only to uniquely named LOCAL lab containers and /tmp/nvx-fpm-*.
Run on Linux with Docker, git, Python and Caddy 2.11.4. Never run on the VPS.
Tests actual FPM scoreboard, OPCache in the same workers and guarded file swap.
"""
import concurrent.futures as cf
import hashlib, http.client, io, json, os, pathlib, shutil, socket, stat, struct, subprocess, tempfile, time, uuid, zipfile

ROOT=pathlib.Path(__file__).resolve().parents[2]
assert os.environ.get('NOVIXFIN_LOCAL_LAB')=='YES', 'Explicit local-lab opt-in required'
CADDY=pathlib.Path(os.environ['CADDY_EXE']).resolve()
PREFIX='nvx-fpm-'+uuid.uuid4().hex[:10]
LAB=pathlib.Path(tempfile.mkdtemp(prefix=PREFIX+'-'))
APP=LAB/'app';APP.mkdir();CONTROL=LAB/'control';CONTROL.mkdir();CONTROL.chmod(0o777)
OPS=LAB/'ops';OPS.mkdir();OPS.chmod(0o750);os.chown(OPS,0,33)
FILES=['actions/sheets/_bootstrap.php','actions/sheets/load.php','actions/sheets/save.php','actions/sheets/snapshot.php','actions/sheets/restore.php','public/scripts/sheet.js']
PHP_IMAGE=os.environ.get('LAB_PHP_IMAGE','novixfin-fpm-lab:8.3.36'); DB_IMAGE='mariadb:11.8.8'
PHP=PREFIX+'-php';DB=PREFIX+'-db'; containers=[];proxy=None
EVIDENCE={'lab':PREFIX,'php_image':PHP_IMAGE,'db_image':DB_IMAGE,'checks':[]}
def check(label): EVIDENCE['checks'].append(label); print('PASS '+label,flush=True)
def run(args,**kw): return subprocess.run(args,check=True,stdout=subprocess.PIPE,stderr=subprocess.PIPE,**kw)
def docker(*args): return run(['docker',*args]).stdout.decode()
def sql(q): return docker('exec',DB,'mariadb','-uroot','--batch','--skip-column-names','-e',q).strip()
def until(fn,seconds=15):
    end=time.monotonic()+seconds
    while time.monotonic()<end:
        result=fn()
        if result: return result
        time.sleep(.05)
    raise RuntimeError('deadline reached: abort, never treat elapsed time as drained')
def rec(kind,data=b''):
    return struct.pack('!BBHHBB',1,kind,1,len(data),0,0)+data

def fcgi(script,query='',port=39091,method='GET'):
    params={'GATEWAY_INTERFACE':'CGI/1.1','REQUEST_METHOD':method,'SCRIPT_FILENAME':script,'SCRIPT_NAME':'/__novixfin_fpm_status' if port==39092 else '/__admin','QUERY_STRING':query,'SERVER_PROTOCOL':'HTTP/1.1','SERVER_NAME':'localhost','SERVER_PORT':'80','REMOTE_ADDR':'127.0.0.1','CONTENT_LENGTH':'0'}
    def size(n): return bytes([n]) if n<128 else struct.pack('!I',n|0x80000000)
    body=b''.join(size(len(k.encode()))+size(len(v.encode()))+k.encode()+v.encode() for k,v in params.items())
    with socket.create_connection(('127.0.0.1',port),timeout=20) as s:
        s.sendall(rec(1,struct.pack('!HB5x',1,0))+rec(4,body)+rec(4)+rec(5))
        def exact(n):
            b=b''
            while len(b)<n:
                x=s.recv(n-len(b))
                if not x: raise RuntimeError('truncated FastCGI response')
                b+=x
            return b
        out=b'';errors=b''
        while True:
            _,kind,_,length,pad,_=struct.unpack('!BBHHBB',exact(8)); data=exact(length)
            if pad:exact(pad)
            if kind==6:out+=data
            elif kind==7:errors+=data
            elif kind==3:break
        if errors: raise RuntimeError(errors.decode(errors='replace'))
        return out.split(b'\r\n\r\n',1)[1]
def admin(query): return json.loads(fcgi('/opt/lab/control.php',query))
def status(): return json.loads(fcgi('/__novixfin_fpm_status','json&full',39092))
def req(path,method='GET',host='127.0.0.1:38127'):
    c=http.client.HTTPConnection('127.0.0.1',38127,timeout=20);c.request(method,path,headers={'Host':host});r=c.getresponse();b=r.read();c.close();return r.status,b

def gate(blocked):
    marker=LAB/'paused.flag'
    if blocked:marker.touch()
    elif marker.exists():marker.unlink()
    if proxy:return  # Keep the same Caddy generation and gauges while toggling.
    barrier='@paused {\n path /actions/sheets/*\n file {\n root '+str(LAB)+'\n try_files paused.flag\n }\n }\n handle @paused {\n respond "planilha indisponivel" 503\n }'
    config='''{\n admin 127.0.0.1:38128\n metrics\n auto_https off\n}\nhttp://127.0.0.1:38127 {\n root * /srv/novixfin/public\n BARRIER\n @actions path /actions/*\n handle @actions {\n root * /srv/novixfin\n php_fastcgi 127.0.0.1:39091\n }\n @public path /public/*\n handle @public {\n uri strip_prefix /public\n php_fastcgi 127.0.0.1:39091\n file_server\n }\n handle {\n respond "other route" 200\n }\n}\nhttp://other.test:38127 {\n respond "other host" 200\n}\n'''.replace('BARRIER',barrier)
    path=LAB/'Caddyfile';path.write_text(config)
    run([str(CADDY),'validate','--config',str(path),'--adapter','caddyfile'])


def assert_gate():
    for method in ('GET','POST','PUT','PATCH','DELETE','OPTIONS','HEAD'):
        for endpoint in ('load.php','save.php','snapshot.php','restore.php'):
            assert req('/actions/sheets/'+endpoint,method)[0]==503
    assert req('/')[0]==200 and req('/actions/sheets/save.php','POST','other.test:38127')[0]==200

def proxy_inflight():
    c=http.client.HTTPConnection('127.0.0.1',38128,timeout=5);c.request('GET','/metrics');r=c.getresponse();body=r.read().decode();c.close();assert r.status==200
    lines=[x for x in body.splitlines() if x.startswith('caddy_http_requests_in_flight{') and 'handler="subroute"' in x]
    assert lines,'missing proxy metric: '+repr([x for x in body.splitlines() if 'flight' in x or 'reverse' in x][:20])
    return sum(float(x.rsplit(' ',1)[1]) for x in lines)

def pids(s): return {p['pid'] for p in s['processes']}
def drained(s):
    return s['pool']=='www' and s['start time']==generation and pids(s)==workers and s['listen queue']==0 and s['active processes']==0 and len(s['processes'])==5 and all(p['state']=='Idle' for p in s['processes'])
def require_drained():
    s=status()
    if proxy_inflight()!=0 or not drained(s): raise RuntimeError('not drained: '+json.dumps(s))
    return s

def baseline(f):
    if os.environ.get('LAB_BASELINE_ZIP'):
        with zipfile.ZipFile(os.environ['LAB_BASELINE_ZIP']) as z:return z.read(f)
    return subprocess.check_output(['git','show','2a2f976:'+f],cwd=ROOT)

def swap(candidate):
    require_drained()
    expected={}
    for f in FILES:
        data=(ROOT/f).read_bytes() if candidate else baseline(f)
        p=APP/f;p.write_bytes(data);p.chmod(0o664);os.chown(p,1000,1000)
        expected[f]=hashlib.sha256(data).hexdigest()
        assert stat.S_IMODE(p.stat().st_mode)==0o664 and (p.stat().st_uid,p.stat().st_gid)==(1000,1000)
    actual=admin('op=hashes')['hashes'];assert actual==expected
    for f in FILES[:-1]: docker('exec',PHP,'php','-l','/srv/novixfin/'+f)
    return expected

def all_worker_probe(expected):
    token=uuid.uuid4().hex
    with cf.ThreadPoolExecutor(max_workers=5) as ex:
        futures=[ex.submit(admin,'op=probe&hold='+token) for _ in range(5)]
        until(lambda:len(list(CONTROL.glob('hold-'+token+'-*')))==5)
        (CONTROL/('release-'+token)).touch()
        results=[f.result() for f in futures]
    assert {r['pid'] for r in results}==workers and all(r['sapi']=='fpm-fcgi' and r['value']==expected for r in results),results
    until(lambda:drained(status()));return results

try:
    source=pathlib.Path(os.environ['LAB_SOURCE_ZIP']).read_bytes() if os.environ.get('LAB_SOURCE_ZIP') else subprocess.check_output(['git','archive','--format=zip','HEAD'],cwd=ROOT)
    with zipfile.ZipFile(io.BytesIO(source)) as z:z.extractall(APP)
    assert not (APP/'.env').exists()
    # Baseline is a lab reference, not a replacement for the VPS exact backup.
    for f in FILES:(APP/f).write_bytes(baseline(f))
    (APP/'probe.php').write_text("<?php return 'A';\n")
    for f in ('fpm_request.php','fpm_sheets_admin.php'):
        shutil.copyfile(ROOT/'scripts/deploy'/f,OPS/f);(OPS/f).chmod(0o640);os.chown(OPS/f,0,33)
    (OPS/'control.key').write_text(uuid.uuid4().hex+uuid.uuid4().hex);(OPS/'control.key').chmod(0o640);os.chown(OPS/'control.key',0,33)
    fpm=LAB/'fpm.conf';fpm.write_text('''[global]\ndaemonize = no\nerror_log = /proc/self/fd/2\n[www]\nuser = www-data\ngroup = www-data\nlisten = 127.0.0.1:39091\npm = static\npm.max_children = 5\npm.max_requests = 0\npm.status_path = /__novixfin_fpm_status\npm.status_listen = 127.0.0.1:39092\nclear_env = no\ncatch_workers_output = yes\nrequest_terminate_timeout = 0\n''')
    ini=LAB/'opcache.ini';ini.write_text('opcache.enable=1\nopcache.validate_timestamps=0\nopcache.file_update_protection=0\nopcache.enable_cli=0\n')
    php_control='''<?php
header('Content-Type: application/json');
$files=FILES; $op=$_GET['op']??'';
$out=['sapi'=>PHP_SAPI,'pid'=>getmypid(),'enabled'=>ini_get('opcache.enable')];
if($op==='hashes'){foreach($files as $f){$out['hashes'][$f]=hash_file('sha256','/srv/novixfin/'.$f);}}
if($op==='cached'){ $out['cached']=opcache_is_script_cached('/srv/novixfin/probe.php'); }
if($op==='invalidate'){foreach(array_merge($files,['probe.php']) as $f){if(str_ends_with($f,'.php')){$out['invalidated'][$f]=opcache_invalidate('/srv/novixfin/'.$f,true);}}}
if($op==='probe'){
 $out['value']=require '/srv/novixfin/probe.php';
 if(isset($_GET['hold'])){$t=$_GET['hold'];if(!preg_match('/^[a-f0-9]{32}$/D',$t)){exit;}
 file_put_contents('/control/hold-'.$t.'-'.getmypid(),'ready');
 while(!file_exists('/control/release-'.$t)){usleep(20000);clearstatcache();}}
}
echo json_encode($out);
'''.replace('FILES',json.dumps(FILES))
    controlfile=LAB/'control.php';controlfile.write_text(php_control)
    slow=APP/'actions/sheets/__lab_slow.php';slow.write_text('''<?php
ignore_user_abort(true);
$t=$_GET['token']; if(!preg_match('/^[a-f0-9]{32}$/D',$t)){http_response_code(400);exit;}
file_put_contents('/control/started-'.$t.'-'.getmypid(),'ready');
if(($_GET['finish']??'')==='1'){echo 'response finished';fastcgi_finish_request();}
while(!file_exists('/control/release-'.$t)){usleep(20000);clearstatcache();}
$p=new PDO('mysql:host=127.0.0.1;port=33317;dbname=novixfin_test','root','');
$p->exec('UPDATE fpm_probe SET value=value+1');echo 'write finished';
''')
    docker('run','-d','--name',DB,'--label','novixfin.lab='+PREFIX,'--network','host','--tmpfs','/var/lib/mysql:rw,size=512m','-e','MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1',DB_IMAGE,'--bind-address=127.0.0.1','--port=33317','--innodb-use-native-aio=0')
    containers.append(DB)
    until(lambda: subprocess.run(['docker','exec',DB,'mariadb-admin','-uroot','ping'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL).returncode==0,120)
    sql('CREATE DATABASE novixfin_app; CREATE DATABASE novixfin_test; CREATE TABLE novixfin_test.fpm_probe (value INT NOT NULL); INSERT INTO novixfin_test.fpm_probe VALUES(0);')
    docker('run','-d','--name',PHP,'--label','novixfin.lab='+PREFIX,'--network','host','--mount','type=bind,src='+str(APP)+',dst=/srv/novixfin','--mount','type=bind,src='+str(CONTROL)+',dst=/control','--mount','type=bind,src='+str(controlfile)+',dst=/opt/lab/control.php,readonly','--mount','type=bind,src='+str(OPS)+',dst=/opt/ops,readonly','--mount','type=bind,src='+str(fpm)+',dst=/opt/lab/fpm.conf,readonly','--mount','type=bind,src='+str(ini)+',dst=/usr/local/etc/php/conf.d/zz-lab.ini,readonly','-e','DB_HOST=127.0.0.1','-e','DB_PORT=33317','-e','DB_DATABASE=novixfin_app','-e','DB_USERNAME=root',PHP_IMAGE,'php-fpm','--fpm-config','/opt/lab/fpm.conf')
    containers.append(PHP)
    def ready():
        try:return status()
        except (OSError,ValueError,RuntimeError):return False
    s=until(ready);workers=pids(s);generation=s['start time'];assert len(workers)==5
    cli_status=json.loads(docker('exec',PHP,'php','/opt/ops/fpm_request.php','status','tcp://127.0.0.1:39092'));assert pids(cli_status)==workers and all('request uri' not in x for x in cli_status['processes'])
    denied=json.loads(fcgi('/opt/ops/fpm_sheets_admin.php','op=inspect'));assert not denied['ok']
    EVIDENCE.update(workers=sorted(workers),generation=generation,php=docker('exec',PHP,'php','-v').splitlines()[0],caddy=run([str(CADDY),'version'],text=True).stdout.strip())
    for db in ('novixfin_app','novixfin_test'):docker('exec','-e','DB_DATABASE='+db,'-w','/srv/novixfin',PHP,'php','scripts/migrate.php','up')
    gate(False);proxy=subprocess.Popen([str(CADDY),'run','--config',str(LAB/'Caddyfile'),'--adapter','caddyfile'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    def proxyready():
        try:return req('/')[0]==200
        except OSError:return False
    until(proxyready)
    assert req('/actions/sheets/load.php')[0] in (301,302,303)
    assert proxy_inflight()==0
    EVIDENCE['probe_A']=all_worker_probe('A');assert admin('op=cached')['cached']
    check('FPM real: 5 workers executaram A e o script está no OPcache')
    token=uuid.uuid4().hex
    with cf.ThreadPoolExecutor(max_workers=6) as ex:
        futures=[ex.submit(fcgi,'/srv/novixfin/actions/sheets/__lab_slow.php','token='+token+'&finish=1') for _ in range(5)]
        for f in futures:assert b'response finished' in f.result()
        busy=status();assert busy['active processes']==5 and all(p['state']=='Finishing' for p in busy['processes']),busy
        sixth=ex.submit(fcgi,'/srv/novixfin/actions/sheets/__lab_slow.php','token='+token+'&finish=1')
        queued=until(lambda: (x if (x:=status())['listen queue']>0 else False))
        gate(True);assert_gate()
        try:require_drained();raise AssertionError('guard allowed busy pool')
        except RuntimeError:pass
        assert sql('SELECT value FROM novixfin_test.fpm_probe')=='0'
        EVIDENCE['finishing']=busy;EVIDENCE['queued']=queued
        (CONTROL/('release-'+token)).touch();sixth.result();idle=until(lambda:(x if drained(x:=status()) else False))
    assert sql('SELECT value FROM novixfin_test.fpm_probe')=='6'
    EVIDENCE['drained']=idle;check('barreira 28 métodos/endpoints; FPM Finishing + fila impedem troca; Idle e fila=0 liberam')
    # Partial request body admitted before reload must be counted even with idle FPM.
    gate(False)
    bodytoken=uuid.uuid4().hex
    upload=socket.create_connection(('127.0.0.1',38127));upload.sendall(('POST /actions/sheets/__lab_slow.php?token='+bodytoken+' HTTP/1.1\r\nHost: 127.0.0.1:38127\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: 100\r\n\r\nx=').encode())
    until(lambda:proxy_inflight()>0)
    gate(True);assert_gate()
    assert proxy_inflight()>0
    try:require_drained();raise AssertionError('guard allowed old upload')
    except RuntimeError:pass
    upload.close();(CONTROL/('release-'+bodytoken)).touch();until(lambda:proxy_inflight()==0 and drained(status()))
    probe_value=int(sql('SELECT value FROM novixfin_test.fpm_probe'));assert probe_value in (6,7)
    check('corpo HTTP antigo incompleto bloqueia drenagem por métrica do proxy')
    # A connection with incomplete headers must use the newly loaded barrier.
    gate(False);headers=socket.create_connection(('127.0.0.1',38127));headers.sendall(b'POST /actions/sheets/save.php HTTP/1.1\r\nHost: 127.0.0.1:38127\r\n')
    gate(True);headers.sendall(b'Content-Length: 0\r\n\r\n');headers.settimeout(5);raw=headers.recv(4096);headers.close();assert b' 503 ' in raw,raw
    check('cabeçalhos incompletos antes da ativação entram na nova barreira')
    # A disconnected client also leaves a visible executing PHP worker.
    gate(False);token=uuid.uuid4().hex
    sock=socket.create_connection(('127.0.0.1',38127));sock.sendall(('POST /actions/sheets/__lab_slow.php?token='+token+' HTTP/1.1\r\nHost: 127.0.0.1:38127\r\nContent-Length: 0\r\n\r\n').encode())
    until(lambda:list(CONTROL.glob('started-'+token+'-*')));sock.close();gate(True)
    active=status();assert active['active processes']>0 and not drained(active);EVIDENCE['disconnect']=active
    (CONTROL/('release-'+token)).touch();until(lambda:drained(status()));assert int(sql('SELECT value FROM novixfin_test.fpm_probe'))==probe_value+1
    check('cliente desconectado: worker ativo observado até escrita concluída')
    EVIDENCE['candidate_hashes']=swap(True)
    probe=APP/'probe.php';original_stat=probe.stat();probe.write_text("<?php return 'B';\n");os.utime(probe,ns=(original_stat.st_atime_ns,original_stat.st_mtime_ns))
    assert admin('op=probe')['value']=='A'
    docker('exec',PHP,'php','-d','opcache.enable_cli=1','-r',"opcache_invalidate('/srv/novixfin/probe.php',true);")
    assert admin('op=probe')['value']=='A';check('controle negativo: disco B e CLI invalidado ainda servem A pelo FPM')
    def ops_invalidate(hashes):
        path=OPS/'sheets-manifest.json';path.write_text(json.dumps({'root':'/srv/novixfin','sha256':hashes}));path.chmod(0o640);os.chown(path,0,33)
        result=json.loads(docker('exec',PHP,'php','/opt/ops/fpm_request.php','admin','tcp://127.0.0.1:39091','/opt/ops/fpm_sheets_admin.php','invalidate','/opt/ops/control.key'))
        assert result['ok'] and result['sapi']=='fpm-fcgi' and result['pid'] in workers
        assert all(not f.get('cached_after',False) for f in result['files'].values())
        return result
    EVIDENCE['fpm_admin_invalidation']=ops_invalidate(EVIDENCE['candidate_hashes'])
    invalid=admin('op=invalidate');assert invalid['sapi']=='fpm-fcgi' and all(invalid['invalidated'].values());assert not admin('op=cached')['cached']
    EVIDENCE['probe_B']=all_worker_probe('B');check('invalidação no FPM: os mesmos 5 workers executam B sem reinício')
    gate(False)
    output=docker('exec','-e','HTTP_BASE=http://127.0.0.1:38127','-w','/srv/novixfin',PHP,'php','tests/sheets_http_persist_test.php')
    assert 'OK: 33' in output;EVIDENCE['http']=output;check('33 verificações HTTP/MariaDB após troca dos seis arquivos')
    gate(True);require_drained();target=APP/FILES[0];target.write_bytes(target.read_bytes()+b'\nBROKEN-HASH\n');assert admin('op=hashes')['hashes']!=EVIDENCE['candidate_hashes']
    EVIDENCE['rollback_hashes']=swap(False);EVIDENCE['rollback_invalidation']=ops_invalidate(EVIDENCE['rollback_hashes']);probe.write_text("<?php return 'A';\n");assert all(admin('op=invalidate')['invalidated'].values());EVIDENCE['rollback_workers']=all_worker_probe('A')
    assert_gate();assert int(sql('SELECT value FROM novixfin_test.fpm_probe'))==probe_value+1
    check('falha de hash: rollback preserva gravações posteriores, FPM executa A e barreira fica ativa para backend legado')
    assert pids(status())==workers and status()['start time']==generation
    EVIDENCE['result']='PASS isolated FPM; production bootstrap not authorized or proven'
finally:
    for p in CONTROL.glob('started-*'):
        (CONTROL/('release-'+p.name.split('-')[1])).touch()
    if proxy:proxy.terminate();proxy.wait(timeout=5)
    for c in reversed(containers):
        EVIDENCE[c+'_logs']=docker('logs',c)
        # Only this run's uniquely named/labelled containers can be removed.
        label=docker('inspect','--format','{{ index .Config.Labels "novixfin.lab" }}',c).strip();assert label==PREFIX
        subprocess.run(['docker','rm','-fv',c],stdout=subprocess.DEVNULL,check=True)
    (LAB/'evidence.json').write_text(json.dumps(EVIDENCE,indent=2))
    print('EVIDENCE '+str(LAB/'evidence.json'),flush=True)
