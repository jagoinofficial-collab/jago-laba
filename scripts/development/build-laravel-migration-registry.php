<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$stage='D:/JAGO_LABA_STAGING/laravel-step1-4-20261101';
$old=json_decode(file_get_contents($root.'/docs/deployment/staging-migration-registry-20261030.json'),true,512,JSON_THROW_ON_ERROR);
$cfg=require $stage.'/private/market.php';
$pdo=new PDO('mysql:host=127.0.0.1;port=3320;charset=utf8mb4',$cfg['user'],$cfg['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$report=['registry_id'=>'JL-STEP1-4-REGISTRY-20261101','generated_at'=>date(DATE_ATOM),
    'scope'=>'Local diagnostic staging only; production and release baseline approval NOT granted',
    'targets'=>['main'=>'jl_s14_market','konveksi'=>'jl_s14_konveksi','schema_experiment'=>'jl_s14_schema_rehearsal_v2'],
    'read_baseline_required_migrations'=>[],
    'read_baseline_reason'=>'Existing Laravel read pilots query restored legacy tables; no DDL required.',
    'execution_guard'=>'Runtime script checks fixed localhost port 3320 and disposable schema; never executes default users migration.',
    'baseline_write_gate'=>'BLOCKED until orphan disposition, ledger reconciliation and staging acceptance',
    'entries'=>[]];
foreach($old['entries'] as $entry) {
    $entry['sha256']=hash_file('sha256',$root.'/'.$entry['source']);
    foreach(['precondition_sql','post_check_sql'] as $key) $entry[$key]=str_replace('jl_staging_restore_20261030','jl_s14_market',$entry[$key]);
    $entry['precondition_observed_at']=date(DATE_ATOM);
    $entry['status']=str_contains($entry['migration_id'],'create_users')?'blocked_collision_do_not_execute':'rehearsed_disposable_clone_only';
    $entry['required_for']=match(true) {
        str_contains($entry['migration_id'],'create_cache')=>['database cache/locks; Step 10 scheduler locks'],
        str_contains($entry['migration_id'],'create_jobs')=>['database queue; Step 10'],
        str_contains($entry['migration_id'],'scope_idempotency')=>['expense mutation; Step 7 and scoped Konveksi receipts after connection-specific review'],
        default=>[],
    };
    $entry['precondition_result']=match(true) {
        str_contains($entry['migration_id'],'create_users')=>'Legacy users exists; do not execute up/down. Sessions split into independent migration.',
        str_contains($entry['migration_id'],'create_cache')=>'cache and cache_locks absent in read baseline',
        str_contains($entry['migration_id'],'create_jobs')=>'jobs, job_batches and failed_jobs absent in read baseline',
        default=>'Read-baseline columns/index evidence attached separately; scoped DDL rehearsed only on diagnostic clone',
    };
    $report['entries'][]=$entry;
}
$file='laravel-foundation/database/migrations/2026_11_01_000004_create_laravel_sessions_table.php';
$report['entries'][]=['migration_id'=>basename($file,'.php'),'dependency_order'=>5,'owner'=>'platform-foundation',
    'source'=>$file,'sha256'=>hash_file('sha256',$root.'/'.$file),'status'=>'rehearsed_disposable_clone_only',
    'required_for'=>['database session; Step 5'],
    'precondition_sql'=>"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='jl_s14_market' AND TABLE_NAME='laravel_sessions'",
    'precondition_result'=>'laravel_sessions absent; legacy users migration explicitly excluded; configure SESSION_TABLE=laravel_sessions',
    'post_check_sql'=>"SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='jl_s14_schema_rehearsal_v2' AND TABLE_NAME='laravel_sessions' ORDER BY ORDINAL_POSITION",
    'rollback'=>'Stop login/writers first; preserve sessions if needed. Dropping laravel_sessions logs out its users. Existing users/sessions tables untouched. Rehearsed only on disposable clone.',
    'collision_policy'=>'Fail on existing laravel_sessions; review ownership and exact schema rather than silently adopting or dropping an existing table.'];
foreach(['jl_s14_market','jl_s14_konveksi'] as $schema) {
    $q=$pdo->prepare('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN (\'users\',\'sessions\',\'laravel_sessions\',\'cache\',\'cache_locks\',\'jobs\',\'job_batches\',\'failed_jobs\',\'idempotency_keys\') ORDER BY TABLE_NAME,ORDINAL_POSITION');
    $q->execute([$schema]); $report['observations'][$schema]['columns']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME='idempotency_keys' ORDER BY INDEX_NAME,SEQ_IN_INDEX");
    $q->execute([$schema]); $report['observations'][$schema]['idempotency_indexes']=$q->fetchAll(PDO::FETCH_ASSOC);
}
$report['legacy_migration_sources']=[];
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/database/migrations',FilesystemIterator::SKIP_DOTS)) as $f) {
    if(!in_array($f->getExtension(),['php','sql'],true)) continue;
    $report['legacy_migration_sources'][]=['source'=>str_replace('\\','/',substr($f->getPathname(),strlen($root)+1)),
        'sha256'=>hash_file('sha256',$f->getPathname()),'status'=>'NOT approved for replay; restored schema already contains legacy objects; derive changes per future feature task'];
}
$report['rehearsal']=json_decode(file_get_contents($stage.'/evidence/schema-rehearsal.json'),true,512,JSON_THROW_ON_ERROR);
file_put_contents($root.'/docs/deployment/staging-migration-registry-20261101.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo count($report['entries'])." Laravel registry entries; ".count($report['legacy_migration_sources'])." canonical legacy sources inventoried.\n";
