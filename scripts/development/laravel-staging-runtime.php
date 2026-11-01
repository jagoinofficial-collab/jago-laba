<?php
declare(strict_types=1);

// Fixed local diagnostic target; never uses the application's .env/DB credentials.
$stage = 'D:/JAGO_LABA_STAGING/laravel-step1-4-20261101';
$root = dirname(__DIR__,2);
$mode = PHP_SAPI === 'cli-server' ? 'http' : ($argv[1] ?? 'probe');
if (!in_array($mode,['http','probe','schema'],true)) throw new RuntimeException('Unsupported staging operation');
if ($mode==='http') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH);
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET' || !in_array($path,['/','/foundation','/up','/api/health.php'],true)) {
        http_response_code(403); header('Content-Type: application/json'); echo '{"error":"STAGING_READ_ONLY_ALLOWLIST"}'; return;
    }
}
foreach (['framework/cache/data','framework/sessions','framework/views','logs','app/private','bootstrap'] as $dir) {
    if(!is_dir($stage.'/runtime/'.$dir)) mkdir($stage.'/runtime/'.$dir,0700,true);
}
if(!is_file($stage.'/private/app.key')) file_put_contents($stage.'/private/app.key','base64:'.base64_encode(random_bytes(32)));
if(!is_file($stage.'/private/.env')) file_put_contents($stage.'/private/.env',"APP_ENV=staging\nAPP_DEBUG=false\n");
$env = ['APP_ENV'=>'staging','APP_DEBUG'=>'false','APP_KEY'=>trim(file_get_contents($stage.'/private/app.key')),
    'APP_CONFIG_CACHE'=>$stage.'/runtime/bootstrap/config.php','APP_SERVICES_CACHE'=>$stage.'/runtime/bootstrap/services.php',
    'APP_PACKAGES_CACHE'=>$stage.'/runtime/bootstrap/packages.php','APP_ROUTES_CACHE'=>$stage.'/runtime/bootstrap/routes.php',
    'JL_KONVEKSI_DATABASE_CONFIG'=>$stage.'/private/konveksi.php','JL_KONVEKSI_DATABASE_ISOLATED'=>'true',
    'JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY_FILE'=>$stage.'/private/marketplace.key',
    'JL_MARKETPLACE_OAUTH_MODE'=>'sandbox','JL_ALLOWED_HOSTS'=>'127.0.0.1,localhost',
    'JL_LEGACY_SESSION_PATH'=>$stage.'/runtime/framework/sessions','SESSION_DRIVER'=>'file','CACHE_STORE'=>'file','LOG_CHANNEL'=>'null'];
foreach($env as $k=>$v) {putenv($k.'='.$v); $_ENV[$k]=$v; $_SERVER[$k]=$v;}
require $root.'/laravel-foundation/vendor/autoload.php';
$app = require $root.'/laravel-foundation/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix('D:/');
$app->useEnvironmentPath($stage.'/private');
$app->useStoragePath($stage.'/runtime');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cfg=require $stage.'/private/'.($mode==='schema'?'admin':'market').'.php';
$schema=$mode==='schema'?'jl_s14_schema_rehearsal_v2':'jl_s14_market';
config(['app.url'=>'http://127.0.0.1:8820','app.debug'=>false,'database.default'=>'mysql',
    'database.connections.mysql.host'=>'127.0.0.1','database.connections.mysql.port'=>3320,
    'database.connections.mysql.database'=>$schema,'database.connections.mysql.username'=>$cfg['user'],
    'database.connections.mysql.password'=>$cfg['pass'],'database.connections.mysql.url'=>null,
    'session.driver'=>'file','session.table'=>'laravel_sessions','cache.default'=>'file','queue.default'=>'sync','logging.default'=>'null',
    'jago.konveksi_database_config_path'=>$stage.'/private/konveksi.php','jago.konveksi_database_isolated'=>true,
    'jago.oauth.mode'=>'sandbox','jago.company_key'=>'LOCAL-COMPANY']);
foreach(['shopee','tiktok_shop'] as $platform) foreach(['partner_id','partner_key','app_key','app_secret','service_id'] as $key) config(["jago.integration.$platform.$key"=>'']);
foreach(['shopee','tiktok_shop'] as $platform) foreach(['auth_url','api_base_url'] as $key) config(["jago.integration.$platform.$key"=>'http://127.0.0.1:9/disabled']);
Illuminate\Support\Facades\DB::purge('mysql');
if($mode==='http') { $app->handleRequest(Illuminate\Http\Request::capture()); return; }
$db=Illuminate\Support\Facades\DB::connection();
if((int)$db->selectOne('SELECT @@port AS p')->p!==3320 || $db->getDatabaseName()!==$schema) throw new RuntimeException('Staging target guard failed');
if($mode==='probe') {
    $data=$app->make(App\Pilots\Konveksi\KonveksiPilotReadService::class)->overview('LOCAL-COMPANY');
    require_once $root.'/app/Shared/Security/MarketplaceTokenVault.php';
    $cryptoProbe='staging-copy-probe-'.bin2hex(random_bytes(16));
    $keyRoundTrip=hash_equals($cryptoProbe,MarketplaceTokenVault::decrypt(MarketplaceTokenVault::encrypt($cryptoProbe)));
    $f=fopen($stage.'/private/konveksi-stock-identity-owner-decisions.csv','w');
    fputcsv($f,['product_id','sku','verified_stock_id','authoritative_source','approved_by','approved_at'],',','"','');
    foreach($data['collections']['produk'] as $product) if(empty($product['id_stok_item'])) {
        fputcsv($f,[(string)($product['id_produk']??$product['id']??''),(string)($product['kode_sku']??$product['sku_varian']??$product['kode']??''),'','','',''],',','"','');
    }
    fclose($f);
    $e=['market_select'=>(int)$db->selectOne('SELECT 1 AS n')->n,'konveksi_collections'=>count($data['collections'])-1,
        'konveksi_health'=>$data['collections']['__meta']['health'],'company_bound'=>$data['collections']['__meta']['company_bound'],
        'outbound_endpoints_disabled'=>true,'http_routes'=>'GET / /foundation /up /api/health.php only',
        'key_copy_round_trip'=>$keyRoundTrip,'scheduler_started'=>false,'worker_started'=>false,'provider_calls'=>0];
    file_put_contents($stage.'/evidence/laravel-probe.json',json_encode($e,JSON_PRETTY_PRINT)."\n");
    echo json_encode($e,JSON_PRETTY_PRINT)."\n"; exit;
}
// Independent schema experiment only. Does not promote the orphaned restore baseline.
$fingerprint=function() use($db,$schema):array {
    $out=[];
    foreach($db->select('SHOW TABLES') as $t) {
        $table=array_values((array)$t)[0]; if($table==='migrations') continue;
        $hashes=[];
        foreach($db->select("SELECT * FROM `$table`") as $r) $hashes[]=hash('sha256',json_encode((array)$r,JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR));
        sort($hashes); $out[$table]=['count'=>count($hashes),'digest'=>hash('sha256',implode('',$hashes))];
    }
    ksort($out); return $out;
};
$before=$fingerprint();
$paths=[
 'database/migrations/0001_01_01_000001_create_cache_table.php',
 'database/migrations/0001_01_01_000002_create_jobs_table.php',
 'database/migrations/2026_10_30_000003_scope_idempotency_keys.php',
 'database/migrations/2026_11_01_000004_create_laravel_sessions_table.php',
];
$e=['target'=>$schema,'kind'=>'independent disposable schema rehearsal; NOT release approval','operations'=>[]];
foreach($paths as $path) {
    $exit=Illuminate\Support\Facades\Artisan::call('migrate',['--path'=>[$path],'--force'=>true]);
    $e['operations'][]=['path'=>$path,'sha256'=>hash_file('sha256',$root.'/laravel-foundation/'.$path),'exit'=>$exit];
    if($exit!==0) throw new RuntimeException('Migration rehearsal failed');
}
$after=$fingerprint();
$preserved=true;
foreach($before as $table=>$digest) if(($after[$table]??null)!==$digest) $preserved=false;
$e['all_original_table_data_preserved']=$preserved;
$e['added_tables']=array_values(array_diff(array_keys($after),array_keys($before)));
$e['idempotency_scope_columns']=$db->select("SHOW COLUMNS FROM idempotency_keys WHERE Field IN ('company_key','module')");
$e['idempotency_indexes']=$db->select('SHOW INDEX FROM idempotency_keys');
$e['users_table_untouched']=($before['users']??null)===($after['users']??null);
$e['rerun_exit']=Illuminate\Support\Facades\Artisan::call('migrate',['--path'=>$paths,'--force'=>true]);
// All rows inserted below are diagnostic, in the isolated clone only.
$db->table('laravel_sessions')->insert(['id'=>'s14-probe','payload'=>'probe','last_activity'=>time()]);
$db->table('laravel_sessions')->where('id','s14-probe')->delete();
$e['session_round_trip']=true;
$e['rollback_exit']=Illuminate\Support\Facades\Artisan::call('migrate:rollback',['--path'=>$paths,'--step'=>4,'--force'=>true]);
$restored=$fingerprint();
$e['rollback_data_matches_before']=$restored===$before;
$e['migration_repository_rows_after_rollback']=$db->table('migrations')->count();
$e['production_commands_executed']=false;
file_put_contents($stage.'/evidence/schema-rehearsal.json',json_encode($e,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
if(!$preserved || !$e['rollback_data_matches_before'] || $e['rollback_exit']!==0) throw new RuntimeException('Schema/data rehearsal post-check failed');
echo "Four explicit migrations applied, rerun and rolled back on diagnostic clone. Original table data preserved.\n";
