"""One-time, source-only restructuring. Does not open databases or provider APIs."""
from pathlib import Path
import csv, datetime, hashlib, json, re, shutil, zipfile

ROOT = Path(__file__).resolve().parents[2]
STAMP = datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
MANIFEST = ROOT / 'docs/architecture/applied-structure.json'
if MANIFEST.exists():
    raise SystemExit('Restructure manifest already exists; do not replay this migration.')

directories = '''app/Bootstrap app/Core/Database app/Core/Http app/Core/Routing app/Core/Exceptions
app/Shared/Auth app/Shared/Authorization app/Shared/Security app/Shared/Companies app/Shared/Branding app/Shared/Support
config routes resources/templates database/migrations/shared database/migrations/konveksi database/migrations/marketplace database/seeders
storage/private/konveksi storage/private/marketplace storage/cache storage/logs storage/sessions storage/temporary
bin/workers scripts/development scripts/deployment scripts/backup scripts/diagnostics
tests/Unit tests/Integration tests/Feature docs/architecture docs/integrations docs/deployment docs/recovery
public/assets/shared public/assets/konveksi public/assets/marketplace'''.split()
for module in ('Konveksi', 'Marketplace'):
    directories += [f'app/Modules/{module}/{part}' for part in ('Controllers','Services','Repositories','Models','Requests','Views','Jobs')]
directories += ['app/Modules/Marketplace/Integrations/'+p for p in ('Contracts','Shopee','TikTokShop','TikTokAds')]

moves = {}
def add(src, dest):
    if (ROOT/src).is_file():
        if (ROOT/dest).exists(): raise RuntimeError('Destination exists: '+dest)
        moves[src] = dest

add('app/bootstrap.php','app/Bootstrap/bootstrap.php')
add('app/Core/Database.php','app/Core/Database/Database.php')
for p in (ROOT/'app/Http').glob('*.php'): add(p.relative_to(ROOT).as_posix(),'app/Core/Http/'+p.name)
for name,folder in {'Workspace':'Authorization','SchemaManager':'Support','SnapshotImporter':'Support','CollectionStore':'Support','AuditService':'Support'}.items():
    add('app/'+name+'.php','app/Shared/'+folder+'/'+name+'.php')
add('app/Security/MarketplaceTokenVault.php','app/Shared/Security/MarketplaceTokenVault.php')
add('app/RpcService.php','app/Modules/Konveksi/Controllers/RpcService.php')
for p in (ROOT/'app/Services').glob('*.php'):
    if p.stem in ('BackupService','DocumentService'): target='app/Shared/Support/'+p.name
    elif p.stem=='MarketplaceBrandingService': target='app/Shared/Branding/'+p.name
    elif p.stem=='MarketplaceScopeService': target='app/Shared/Companies/'+p.name
    elif p.stem=='ShopeeOrderDataClient': target='app/Modules/Marketplace/Integrations/Shopee/'+p.name
    elif p.stem in ('MarketplaceAutoSync','MarketplaceBackgroundSync'):target='app/Modules/Marketplace/Jobs/'+p.name
    elif p.stem.startswith('Marketplace'):target='app/Modules/Marketplace/Services/'+p.name
    else:target='app/Modules/Konveksi/Services/'+p.name
    add(p.relative_to(ROOT).as_posix(),target)
for p in (ROOT/'app/views').iterdir():
    if not p.is_file():continue
    target='app/Modules/Konveksi/Views/' if p.suffix=='.html' else 'app/Modules/Marketplace/Views/'
    add(p.relative_to(ROOT).as_posix(),target+p.name)
add('public/marketplace.php','app/Modules/Marketplace/Controllers/marketplace.php')
add('public/workspace.php','app/Shared/Auth/workspace.php')
for p in (ROOT/'public/api').glob('*.php'):
    target='app/Modules/Marketplace/Controllers/Api/' if p.name.startswith('marketplace-') else 'app/Shared/Auth/Api/'
    if p.name=='rpc.php':target='app/Modules/Konveksi/Controllers/Api/'
    if p.name in ('health.php','diagnostics.php','auth-diagnostic.php'):target='app/Shared/Support/Api/'
    add(p.relative_to(ROOT).as_posix(),target+p.name)
for p in (ROOT/'scripts').glob('*'):
    if not p.is_file():continue
    if p.name in ('marketplace-background-worker.php','marketplace-auto-sync.php','marketplace-cron.php'):target='bin/workers/'
    elif 'backup' in p.name.lower():target='scripts/backup/'
    elif p.name.startswith(('install-','install_','remove_')):target='scripts/deployment/'
    elif p.name.startswith(('check','diagnose','verify','preview')):target='scripts/diagnostics/'
    else:target='scripts/development/'
    add(p.relative_to(ROOT).as_posix(),target+p.name)
for p in (ROOT/'database').glob('*'):
    if not p.is_file():continue
    n=p.name.lower()
    if n.startswith('migration'):
        folder='marketplace' if 'marketplace' in n or 'marketing' in n or 'expenses_and_api' in n or 'settings_and_stores' in n else 'shared'
        target='database/migrations/'+folder+'/'
    elif n.startswith('seed'):target='database/seeders/'
    elif p.suffix=='.php':target='scripts/diagnostics/'
    else:target='database/legacy/'
    add(p.relative_to(ROOT).as_posix(),target+p.name)
for p in (ROOT/'tests').glob('*'):
    if not p.is_file():continue
    folder='Feature' if p.name.startswith(('render','browser')) or 'page_readcheck' in p.name else 'Integration'
    add(p.relative_to(ROOT).as_posix(),'tests/'+folder+'/'+p.name)
for p in (ROOT/'public/assets').rglob('*'):
    if not p.is_file():continue
    rel=p.relative_to(ROOT/'public/assets').as_posix()
    scope='konveksi' if p.name=='jl-api.js' else 'shared' if p.name.startswith('date-range') else 'marketplace'
    add(p.relative_to(ROOT).as_posix(),'public/assets/'+scope+'/'+rel)

# Backup all source paths that this script can rewrite, plus configuration and docs.
source_dirs=('app','public','config','database','scripts','tests','routes','bin','resources')
before={}
for d in source_dirs:
    if not (ROOT/d).exists():continue
    for p in (ROOT/d).rglob('*'):
        if p.is_file() and not p.relative_to(ROOT).as_posix().startswith('public/storage/'):
            before[p.relative_to(ROOT).as_posix()]=p.read_bytes()
backup=Path('D:/JAGO_LABA_BACKUP/restructure')/('source-'+STAMP+'.zip')
backup.parent.mkdir(parents=True,exist_ok=True)
with zipfile.ZipFile(backup,'x',zipfile.ZIP_DEFLATED) as z:
    for path,data in before.items():z.writestr(path,data)
    for name in ('.env.example','.gitignore'):
        if (ROOT/name).exists():z.write(ROOT/name,name)

for d in directories:
    p=ROOT/d;p.mkdir(parents=True,exist_ok=True)
    if not any(p.iterdir()):(p/'.gitkeep').write_text('',encoding='utf-8')

php_path=re.compile(r"(?P<base>dirname\(\s*__DIR__\s*(?:,\s*(?P<n>\d+))?\s*\)|__DIR__)(?P<tail>\s*\.\s*(?P<quote>['\"])(?P<suffix>[^'\"\r\n]*)(?P=quote))?")
def relocate_php(text,old,new):
    olddir=ROOT/Path(old).parent
    depth=len(Path(new).parent.parts)
    def replace(m):
        base=olddir
        if m.group('base').startswith('dirname'):
            for _ in range(int(m.group('n') or 1)):base=base.parent
        if m.group('suffix') is not None:
            suffix=m.group('suffix')
            if '\\' in suffix or '$' in suffix:return m.group(0)
            base=Path(str(base)+suffix)
        try:path=base.resolve().relative_to(ROOT).as_posix()
        except ValueError:return m.group(0)
        if path=='.':path=''
        target=moves.get(path,path)
        # Dynamic Konveksi HTML view directory is owned by this module.
        if target=='app/views' and old=='public/index.php':target='app/Modules/Konveksi/Views'
        expr=f'dirname(__DIR__, {depth})' if depth else '__DIR__'
        return '('+expr+(" . '/"+target+"'" if target else '')+')'
    return php_path.sub(replace,text)

def rewrite(text,old,new):
    if Path(old).suffix=='.php':text=relocate_php(text,old,new)
    if old!=new and Path(old).suffix=='.ps1':
        # Resolve the former script directory without embedding a machine path.
        root_expr='$PSScriptRoot'
        for _ in Path(new).parent.parts:root_expr='(Split-Path -Parent '+root_expr+')'
        former_dir="(Join-Path "+root_expr+" '"+Path(old).parent.as_posix()+"')"
        former_file="(Join-Path "+root_expr+" '"+old+"')"
        text=text.replace('$MyInvocation.MyCommand.Path','__FORMER_SCRIPT_FILE__')
        text=text.replace('$PSScriptRoot',former_dir).replace('__FORMER_SCRIPT_FILE__',former_file)
    if old!=new and Path(old).suffix=='.cjs':
        text=text.replace('__dirname',"require('node:path').resolve(__dirname, '../..', 'tests')")
    # Full root-relative filesystem literals and browser asset URLs.
    for src,dst in sorted(moves.items(),key=lambda pair:-len(pair[0])):
        # Public URLs must remain stable; static __DIR__ references were resolved above.
        if not src.startswith('public/'):
            text=text.replace('/'+src,"/"+dst)
        if src.startswith('public/assets/'):
            text=text.replace(src[len('public/'):],dst[len('public/'):])
    if Path(old).suffix=='.php' and old!=new:
        # Preserve script identity checks using the compatibility entry point.
        depth=len(Path(new).parent.parts)
        text=text.replace('__FILE__',"(dirname(__DIR__, "+str(depth)+") . '/"+old+"')")
    return text

text_ext={'.php','.html','.js','.cjs','.css','.sql','.ps1','.bat','.json','.py'}
written=[]
for old,data in before.items():
    if old==Path(__file__).relative_to(ROOT).as_posix():continue
    new=moves.get(old,old)
    p=ROOT/new;p.parent.mkdir(parents=True,exist_ok=True)
    if Path(old).suffix in text_ext:
        try:data=rewrite(data.decode('utf-8'),old,new).encode('utf-8')
        except UnicodeDecodeError:pass
    p.write_bytes(data);written.append(new)

for old,new in moves.items():
    p=ROOT/old
    if p.suffix=='.php':
        depth=len(Path(old).parent.parts)
        keyword='require' if '/views/' in old or old.startswith('public/') else 'require_once'
        p.write_text("<?php\n// Compatibility entry point; implementation lives in its functional module.\n"+keyword+" dirname(__DIR__, "+str(depth)+") . '/"+new+"';\n",encoding='utf-8')
    elif old.startswith('public/assets/'):
        # Static alias retained for previously cached pages and existing downloads.
        p.write_bytes((ROOT/new).read_bytes())
    elif p.suffix in ('.ps1','.bat','.cjs'):
        # External scheduled-task and developer invocations keep their old paths.
        if p.suffix=='.ps1':
            p.write_text("# Compatibility launcher.\n& (Join-Path $PSScriptRoot '"+str(Path('..')/Path(new)).replace('\\','/')+"') @args\n",encoding='utf-8')
        elif p.suffix=='.cjs':p.write_text("require('./"+str(Path(new).relative_to('tests')).replace('\\','/')+"');\n",encoding='utf-8')
        else:p.write_bytes(before[old])
    else:
        p.unlink() # Original is in the verified source archive; canonical file now owns it.

manifest={'created_at':STAMP,'root':str(ROOT),'backup':str(backup),'database_changed':False,
 'moves':[{'source':s,'target':t,'original_sha256':hashlib.sha256(before[s]).hexdigest()} for s,t in moves.items()],
 'compatibility':'PHP entry-point wrappers; legacy static asset aliases and operation scripts retained',
 'notes':['Runtime storage and database contents were not moved.','No migration or seed executed.']}
MANIFEST.write_text(json.dumps(manifest,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
print(json.dumps({'moved':len(moves),'backup':str(backup),'manifest':str(MANIFEST)}))
