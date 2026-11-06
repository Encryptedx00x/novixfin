"""Fail-closed check of fresh, privately collected pool/proxy observations.
This does not activate a barrier or perform a deployment. See the runbook.
"""
import argparse, json, math, pathlib, re, time


def check(baseline, current, metrics):
    baseline=baseline.get('status',baseline);current=current.get('status',current)
    required=('pool','start time','listen queue','active processes','total processes','processes','observer pid')
    if any(key not in current for key in required): raise ValueError('incomplete pool observation')
    if current['pool']!=baseline['pool'] or current['start time']!=baseline['start time']: raise ValueError('pool generation changed')
    def identities(sample):
        procs=sample['processes'];result={p['pid']:p['start time'] for p in procs}
        if len(result)!=len(procs) or not result: raise ValueError('invalid worker identities')
        return result
    if identities(current)!=identities(baseline):raise ValueError('worker generation changed')
    if current['listen queue']!=0 or current['active processes']!=1:raise ValueError('old work or queue remains')
    if current['total processes']!=len(current['processes']):raise ValueError('inconsistent process count')
    observer=current['observer pid']
    if observer not in identities(current):raise ValueError('observer not in pool')
    for process in current['processes']:
        expected='Running' if process['pid']==observer else 'Idle'
        if process['state']!=expected:raise ValueError('worker still executing or invalid observer')
    gauges=[]
    for line in metrics.splitlines():
        if re.match(r'^caddy_http_requests_in_flight\{',line) and 'handler="subroute"' in line:
            value=float(line.rsplit(' ',1)[1]);gauges.append(value)
    if not gauges or any(not math.isfinite(x) or x!=0 for x in gauges):raise ValueError('proxy not drained or metric unavailable')
    return {'ok':True,'pool':current['pool'],'start time':current['start time'],'observer pid':observer,'old active processes':0,'listen queue':0,'workers':sorted(identities(current))}


if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('baseline',type=pathlib.Path);parser.add_argument('current',type=pathlib.Path);parser.add_argument('metrics',type=pathlib.Path)
    args=parser.parse_args()
    try:
        for path in (args.current,args.metrics):
            age=time.time()-path.stat().st_mtime
            if age<0 or age>10:raise ValueError('current observations are not fresh (10s maximum)')
        print(json.dumps(check(json.loads(args.baseline.read_text()),json.loads(args.current.read_text()),args.metrics.read_text())))
    except (ValueError,KeyError,TypeError,OSError) as error:
        print(json.dumps({'ok':False,'error':str(error)}));raise SystemExit(1)
