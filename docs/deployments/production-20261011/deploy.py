import sys,json,hashlib,getpass,shlex,datetime,os
from pathlib import Path
ROOT=Path.cwd();TMP=ROOT/'.audit-temp/production-20261011';OUT=ROOT/'docs/deployments/production-20261011';OUT.mkdir(parents=True,exist_ok=True)
sys.path.insert(0,str(ROOT/'.audit-temp/deployment-remediation-20261011/ssh-tools'))
import paramiko
package=ROOT/'.audit-temp/deployment-remediation-20261011/package'
archive_hash=hashlib.sha256((package/'release.tar.gz').read_bytes()).hexdigest();assert archive_hash==(package/'release.sha256').read_text().split()[0]
manifest=json.loads((package/'manifest.json').read_bytes())
assert all(hashlib.sha256((package/'stage'/n).read_bytes()).hexdigest()==h for n,h in manifest.items())
baseline=json.loads((ROOT/'docs/audits/browser-six-20261011/full-source-after.json').read_bytes())
# The reviewed lint-scope correction was verified after the full browser run.
remediation=json.loads((ROOT/'docs/audits/deployment-remediation-20261011/maintained-files.json').read_bytes())
baseline['eslint.config.js']=remediation['eslint.config.js']
assert all(hashlib.sha256((ROOT/n).read_bytes()).hexdigest()==h for n,h in baseline.items()),'Source changed since tested release'
preserve=json.loads((ROOT/'docs/audits/deployment-remediation-20261011/production-file-checksums.json').read_bytes())
plan={'archive_sha256':archive_hash,'manifest':manifest,'preserve':{n:preserve[n] for n in ['api/config.php','api/core/reference_catalog_v1.json']},'tables':list(json.loads((ROOT/'docs/audits/browser-six-20261011/snapshot-after.json').read_bytes())['tables'])}
(OUT/'plan.json').write_text(json.dumps(plan,indent=2))
client=paramiko.SSHClient();client.load_host_keys(str(ROOT/'.audit-temp/production-20260928/known_hosts'))
client.connect('192.168.22.132',username='pushpendra',password=getpass.getpass('SSH password: '),look_for_keys=False,allow_agent=False,timeout=15)
remote='/gpsr/webserver/cgidocs/raghava/shodhanakg/.deployment-backups/release-20261011-'+datetime.datetime.now().strftime('%H%M%S')
(OUT/'remote-path.txt').write_text(remote+'\n')
try:
 sftp=client.open_sftp();sftp.mkdir(remote,mode=0o700)
 for source,name in [(TMP/'server_deploy.py','server_deploy.py'),(OUT/'plan.json','plan.json'),(package/'release.tar.gz','release.tar.gz')]:
  sftp.put(str(source),remote+'/'+name);sftp.chmod(remote+'/'+name,0o600)
 _,stdout,stderr=client.exec_command('python3 '+shlex.quote(remote+'/server_deploy.py')+' deploy',timeout=300)
 output=stdout.read();errors=stderr.read();code=stdout.channel.recv_exit_status();(OUT/'deploy.log').write_bytes(output+b'\n'+errors)
 for name in ['deployment.json','rollback.json','before-files.json','backup.json','kg-before.json','kg-after.json','health.json','chat-smoke.json']:
  try:sftp.get(remote+'/'+name,str(OUT/name))
  except FileNotFoundError:pass
 (OUT/'deploy-exit.json').write_text(json.dumps({'exit_code':code}));print('Deployment exit:',code,flush=True);print(output.decode(),errors.decode());sys.exit(code)
finally:client.close()
