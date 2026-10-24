"""Build a subdirectory release candidate locally; never connect to production."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tarfile

ROOT = Path(__file__).resolve().parents[1]
PRESERVE = {'webservice/api/config.php', 'webservice/api/core/reference_catalog_v1.json'}

def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()

def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, required=True, help='New local output directory')
    parser.add_argument('--base', default='/APP_BASE_PATH/')
    args = parser.parse_args()
    if not re.fullmatch(r'/(?:[A-Za-z0-9_-]+/)+', args.base):
        parser.error('Use an absolute subdirectory base with a trailing slash')
    out = args.output.resolve()
    if os.name == 'nt' and out.drive.upper() != 'D:':
        parser.error('All release artifacts must be on D:')
    if out.exists():
        parser.error('Output must be new; existing releases are never overwritten')
    out.mkdir(parents=True)
    env = os.environ.copy()
    for key in ('TEMP', 'TMP', 'TMPDIR', 'npm_config_cache', 'NODE_COMPILE_CACHE', 'XDG_CACHE_HOME'):
        directory = out/'runtime'/key.lower()
        directory.mkdir(parents=True)
        env[key] = str(directory)
    env.update(VITE_API_BASE=args.base.rstrip('/'), VITE_CHAT_ENABLED='true')
    stage = out/'stage'
    npm = 'npm.cmd' if os.name == 'nt' else 'npm'
    for name, command in [('lint', [npm, 'run', 'lint']), ('build', [npm, 'run', 'build', '--', '--base='+args.base, '--outDir='+str(stage)]), ('unit', [npm, 'test'])]:
        with (out/(name+'.log')).open('w', encoding='utf8') as log:
            result = subprocess.run(command, cwd=ROOT/'webservice/frontend', env=env, stdout=log, stderr=subprocess.STDOUT)
        (out/(name+'-exit.json')).write_text(json.dumps({'command': command, 'exit_code': result.returncode}), encoding='utf8')
        print(name, result.returncode, flush=True)
        if result.returncode:
            raise SystemExit(result.returncode)
    (stage/'api').mkdir()
    for path in (ROOT/'webservice/api').glob('*.php'):
        if path.name != 'test_db.php':
            shutil.copy2(path, stage/'api'/path.name)
    for folder in ('core', 'vendor'):
        shutil.copytree(ROOT/'webservice/api'/folder, stage/'api'/folder)
    # Staging uses local config/catalog for local verification only. Neither is shipped.
    (stage/'.htaccess').write_text('Options -Indexes\nDirectoryIndex index.html\n<FilesMatch "^(\\.|.*\\.(?:bak|zip|gz|env))">\n Require all denied\n</FilesMatch>\nRewriteEngine On\nRewriteBase '+args.base+'\nRewriteRule ^(?:\\.deployment-backups|neo4j-export\\.json)(?:/|$) - [F,L]\nRewriteRule ^api(?:/|$) - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . '+args.base+'index.html [L]\n', encoding='utf8')
    (stage/'api/.htaccess').write_text('Options -Indexes\nRewriteEngine On\nRewriteRule ^(?:core|vendor|tests|storage)(?:/|$) - [F,L]\n<FilesMatch "^(?:config|deployment_environment|db|graph_service)\\.php$">\n Require all denied\n</FilesMatch>\n', encoding='utf8')
    files = {p.relative_to(stage).as_posix():p for p in stage.rglob('*') if p.is_file()}
    manifest = {name:digest(p) for name,p in files.items() if name not in PRESERVE}
    (out/'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf8')
    (out/'deployment-contract.json').write_text(json.dumps({'base':args.base, 'api_base':args.base.rstrip('/'), 'required_preserved_files':sorted(PRESERVE), 'stage_only_local_files':{n:digest(files[n]) for n in PRESERVE}, 'instructions':'Preserve target config and validate/reconcile target-bound reference catalog before activation. Archive is not standalone without these files. Do not restore KG during code rollback.'}, indent=2), encoding='utf8')
    with tarfile.open(out/'release.tar.gz', 'w:gz') as archive:
        for name in sorted(manifest):
            archive.add(files[name], arcname=name, recursive=False)
    with tarfile.open(out/'release.tar.gz', 'r:gz') as archive:
        assert set(archive.getnames()) == set(manifest)
        for member in archive.getmembers():
            assert hashlib.sha256(archive.extractfile(member).read()).hexdigest() == manifest[member.name]
    (out/'release.sha256').write_text(digest(out/'release.tar.gz')+'  release.tar.gz\n')
    print('Verified archive:', len(manifest), 'files;', out/'release.tar.gz', flush=True)

if __name__ == '__main__':
    main()
