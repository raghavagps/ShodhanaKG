"""Deployment operations only; prepared locally and executed on the target."""
import sys,json,hashlib,os,shutil,subprocess,tarfile
from pathlib import Path
BASE=Path('/gpsr/webserver/cgidocs/raghava/shodhanakg')
WORK=Path(__file__).resolve().parent
plan=json.loads((WORK/'plan.json').read_text());manifest=plan['manifest']
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def safe(name):
 p=BASE/name
 if p.resolve()!=BASE.resolve() and BASE.resolve() not in p.resolve().parents:raise RuntimeError('Unsafe destination')
 return p
def save(name,value):(WORK/name).write_text(json.dumps(value,indent=2))
def snapshot(name):
 code='require '+json.dumps(str(BASE/'api/db.php'))+'; $out=array(); foreach(json_decode('+repr(json.dumps(plan['tables']))+',true) as $t){ $r=arcade_command("SELECT FROM `".$t."`"); $out[$t]=$r["result"]; } echo json_encode(array("tables"=>$out));'
 r=subprocess.run(['php','-r',code],capture_output=True,check=True);value=json.loads(r.stdout);save(name,value)
def request(path,payload=None):
 cmd=['curl','--silent','--show-error','--fail','--max-time','90','--resolve','webs.iiitd.edu.in:443:127.0.0.1','https://webs.iiitd.edu.in/raghava/shodhanakg/'+path]
 if payload is not None:cmd+=['-H','Content-Type: application/json','--data-binary',json.dumps(payload)]
 r=subprocess.run(cmd,capture_output=True,check=True);return r.stdout.decode()
def rollback():
 if (WORK/'previous-api').exists():
  if (BASE/'api').exists():os.rename(BASE/'api',WORK/'failed-api')
  os.rename(WORK/'previous-api',BASE/'api')
 before=json.loads((WORK/'before-files.json').read_text())
 with tarfile.open(WORK/'pre-deploy.tar.gz') as archive:
  for member in archive.getmembers():
   if not member.isfile() or member.name.startswith('api/'):continue
   dest=safe(member.name);dest.parent.mkdir(parents=True,exist_ok=True)
   with archive.extractfile(member) as src,open(str(dest)+'.restore','wb') as target:shutil.copyfileobj(src,target)
   os.chmod(str(dest)+'.restore',member.mode);os.replace(str(dest)+'.restore',dest)
  # Additional immutable assets may remain; the original index/API are restored.
 assert all(sha(safe(n))==r['sha256'] for n,r in before.items())
 save('rollback.json',{'restored':True,'verified_files':len(before)})
if sys.argv[1]=='rollback':rollback();sys.exit(0)
assert BASE.is_dir() and BASE.resolve() in WORK.parents
assert WORK.stat().st_mode & 0o077==0
assert sha(WORK/'release.tar.gz')==plan['archive_sha256']
assert not (WORK/'stage').exists() and not (WORK/'previous-api').exists()
for name,h in plan['preserve'].items():assert sha(safe(name))==h,'Protected file changed: '+name
assert json.loads(request('api/chatbot_health.php'))['ok']
before={}
for p in BASE.rglob('*'):
 name=p.relative_to(BASE).as_posix()
 if name.startswith('.deployment-backups/') or name.startswith('api/storage/'):continue
 if p.is_symlink():raise RuntimeError('Review symlink before deployment: '+name)
 if p.is_file():before[name]={'sha256':sha(p),'mode':p.stat().st_mode & 0o777}
save('before-files.json',before)
with tarfile.open(WORK/'pre-deploy.tar.gz','w:gz') as archive:
 for n in sorted(before):archive.add(safe(n),arcname=n,recursive=False)
with tarfile.open(WORK/'pre-deploy.tar.gz') as archive:
 for m in archive.getmembers():assert hashlib.sha256(archive.extractfile(m).read()).hexdigest()==before[m.name]['sha256']
assert all(sha(safe(n))==r['sha256'] for n,r in before.items()),'Production changed during backup'
save('backup.json',{'sha256':sha(WORK/'pre-deploy.tar.gz'),'files':len(before),'runtime_storage_excluded':True})
snapshot('kg-before.json')
stage=WORK/'stage';stage.mkdir()
with tarfile.open(WORK/'release.tar.gz') as archive:
 assert set(archive.getnames())==set(manifest)
 for m in archive.getmembers():
  assert m.isfile() and not m.name.startswith('/') and '..' not in Path(m.name).parts
  dest=stage/m.name;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(archive.extractfile(m).read());os.chmod(dest,0o644)
for p in stage.rglob('*'):
 if p.is_dir():os.chmod(p,0o755)
assert all(sha(stage/n)==h for n,h in manifest.items())
for name in plan['preserve']:subprocess.run(['cp','-a',str(safe(name)),str(stage/name)],check=True)
if (BASE/'api/storage').exists():subprocess.run(['cp','-a',str(BASE/'api/storage'),str(stage/'api/storage')],check=True)
for p in (stage/'api').rglob('*.php'):
 if '/storage/' not in str(p):subprocess.run(['/usr/bin/php5.6','-l',str(p)],check=True,stdout=subprocess.DEVNULL)
# Preserve previous API (including ACLs and caches) for immediate rollback.
try:
 for n in manifest:
  if n.startswith('api/') or n in ['index.html','.htaccess']:continue
  dest=safe(n);dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(stage/n,str(dest)+'.next');os.replace(str(dest)+'.next',dest)
 os.rename(BASE/'api',WORK/'previous-api');os.rename(stage/'api',BASE/'api')
 for name in ['.htaccess','index.html']:
  shutil.copy2(stage/name,str(BASE/name)+'.next');os.replace(str(BASE/name)+'.next',BASE/name)
 health=json.loads(request('api/chatbot_health.php'));assert health['ok']
 answer=json.loads(request('api/chatbot_response.php',{'question':'What Shodhana method is used to purify Copper?'}))
 assert answer['status']=='success' and answer['query_plan']['result_type']=='Shodhana_Method' and answer['citations']
 save('health.json',health);save('chat-smoke.json',answer)
 assert all(sha(safe(n))==h for n,h in manifest.items())
 assert all(sha(safe(n))==h for n,h in plan['preserve'].items())
 snapshot('kg-after.json')
 norm=lambda name:{r['@rid']:r for rows in json.loads((WORK/name).read_text())['tables'].values() for r in rows}
 assert norm('kg-before.json')==norm('kg-after.json')
 save('deployment.json',{'status':'activated','manifest_files':len(manifest),'protected_files_unchanged':True,'kg_unchanged':True,'backup_sha256':sha(WORK/'pre-deploy.tar.gz')})
 print('Activated verified release; rollback backup retained.')
except Exception:
 rollback();raise
