<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';MarketplaceModule::boot();
require_once dirname(__DIR__,2).'/app/Modules/Marketplace/Services/MarketplaceIntegrationHealth.php';
function integrationCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
final class RuntimeOAuthFixture implements MarketplaceOAuthClient
{
    public int $generation=1;
    public function __construct(private ?PDO $pdo=null){}
    public function platform():string{return 'Shopee';}
    public function configurationMissing():array{return [];}
    public function authorizationContext():array{return ['fingerprint'=>hash('sha256','fixture-context-'.$this->generation),'environment'=>'live'];}
    public function authorizationUrl(string $state,string $redirectUri):string{return 'https://example.test/?state='.$state;}
    public function exchangeCode(string $code,array $callback):array{return ['access_token'=>'FIXTURE_ACCESS','refresh_token'=>'FIXTURE_REFRESH','token_expires_at'=>date('Y-m-d H:i:s',time()+3600),'refresh_token_expires_at'=>date('Y-m-d H:i:s',time()+7200),'granted_scopes'=>[],'shops'=>[['id'=>'100','name'=>'Fixture Shopee']]];}
    public function refreshToken(string $refreshToken,?string $externalStoreId=null,?string $shopCipher=null):array{
        if($this->pdo){$this->pdo->exec('UPDATE test_refresh_counter SET calls=calls+1');usleep(800000);}
        return $this->exchangeCode('',[]);
    }
}
$oauthConfig=['base_url'=>'https://example.test','callback_url'=>'https://example.test/callback','state_ttl_seconds'=>600];
if(in_array('--refresh-child',$argv,true)){
    $db=(string)getenv('JL_INTEGRATION_TEST_DB');integrationCheck((bool)preg_match('/^jl_integration_runtime_test_[a-f0-9]+$/',$db),'Invalid child fixture DB.');
    $cfg=Database::config();$cfg['db']['name']=$db;Database::setConfig($cfg);Database::reset();$p=Database::pdo();
    $_SESSION=['company_key'=>'INTEGRATION-A','user_id'=>'TEST','role'=>'OWNER','workspace'=>'marketplace'];
    $client=new RuntimeOAuthFixture($p);$service=new MarketplaceOAuthService($p,['Shopee'=>$client],$oauthConfig);$service->refreshConnection((string)getenv('JL_INTEGRATION_TEST_STORE'),false);echo "REFRESH_OK\n";exit;
}
$original=Database::config();$source=$original['db']['name'];integrationCheck((bool)preg_match('/^[a-zA-Z0-9_]+$/',$source),'Invalid source schema.');
$name='jl_integration_runtime_test_'.bin2hex(random_bytes(6));$server=Database::serverPdo();$server->exec("CREATE DATABASE `$name`");$cfg=$original;$cfg['db']['name']=$name;Database::setConfig($cfg);Database::reset();
$keyBefore=getenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY');putenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY='.base64_encode(random_bytes(32)));
try{
    $p=Database::pdo();foreach(['marketplace_stores','marketplace_oauth_connections','marketplace_oauth_states','marketplace_oauth_pending','marketplace_sync_jobs','marketplace_partner_settings','marketplace_sync_records','marketplace_catalog_variants','marketplace_orders','marketplace_order_items','marketplace_hpp_history','marketplace_sync_workers'] as $table)$p->exec("CREATE TABLE `$table` LIKE `$source`.`$table`");
    MarketplaceIncrementalSync::install($p);
    MarketplaceIntegrationRuntime::install($p);
    $_SESSION=['company_key'=>'INTEGRATION-A','user_id'=>'TEST','role'=>'OWNER','workspace'=>'marketplace'];
    $client=new RuntimeOAuthFixture();$oauth=new MarketplaceOAuthService($p,['Shopee'=>$client],$oauthConfig);
    $state=$oauth->startAuthorization('Shopee');parse_str(parse_url($state['authorization_url'],PHP_URL_QUERY),$params);$client->generation++;
    $blocked=false;try{$oauth->handleCallback(['state'=>$params['state'],'code'=>'FIXTURE']);}catch(MarketplaceIntegrationException $e){$blocked=$e->errorCode==='INTEGRATION_CONTEXT_CHANGED';}integrationCheck($blocked,'Changed OAuth environment/app context accepted.');
    $state=$oauth->startAuthorization('Shopee');parse_str(parse_url($state['authorization_url'],PHP_URL_QUERY),$params);$connected=$oauth->handleCallback(['state'=>$params['state'],'code'=>'FIXTURE']);$store=$connected['store_id'];
    $report=(new MarketplaceIntegrationHealth($p))->forStore($store);$caps=array_column($report['resources'],null,'key');integrationCheck($caps['products']['state']==='unverified'&&$caps['finance']['state']==='unsupported','OAuth alone claimed catalog/finance permission.');
    integrationCheck(!str_contains(json_encode($report),'FIXTURE_ACCESS')&&!str_contains(json_encode($report),'FIXTURE_REFRESH'),'Status leaked credentials.');
    $p->exec('CREATE TABLE test_refresh_counter(calls INT NOT NULL)');$p->exec('INSERT INTO test_refresh_counter VALUES (0)');
    $p->prepare('UPDATE marketplace_oauth_connections SET token_expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE store_id=?')->execute([$store]);
    putenv('JL_INTEGRATION_TEST_DB='.$name);putenv('JL_INTEGRATION_TEST_STORE='.$store);$processes=[];
    for($i=0;$i<2;$i++){$proc=proc_open([PHP_BINARY,__FILE__,'--refresh-child'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);integrationCheck(is_resource($proc),'Cannot spawn refresh fixture.');fclose($pipes[0]);$processes[]=[$proc,$pipes];}
    foreach($processes as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);integrationCheck($exit===0&&str_contains($out,'REFRESH_OK'),'Concurrent refresh failed: '.$err.$out);}
    integrationCheck((int)$p->query('SELECT calls FROM test_refresh_counter')->fetchColumn()===1,'Concurrent refresh called provider twice.');
    $p->exec("INSERT INTO marketplace_stores(id,company_key,platform,store_name,store_identifier,authorization_status) VALUES('T','INTEGRATION-A','TikTok Shop','Fixture TikTok','200','verified'),('X','OTHER','TikTok Shop','Other','300','verified')");
    $p->prepare("INSERT INTO marketplace_oauth_connections(company_key,store_id,platform,external_store_id,external_shop_cipher,external_store_name,access_token_encrypted,authorized_at,verified_at,status) VALUES('INTEGRATION-A','T','TikTok Shop','200','cipher','Fixture TikTok',?,NOW(),NOW(),'verified')")->execute([MarketplaceTokenVault::encrypt('FIXTURE_ACCESS')]);
    $fake=new class{public int $calls=0;public string $error='HTTP_503';public function call($method,$path,$query,$token,$body=null):array{if(str_contains($path,'/products/')){$this->calls++;throw new MarketplaceIntegrationException('Fixture transient',$this->error,40);}if(str_contains($path,'/orders/search'))return ['total_count'=>0,'next_page_token'=>''];if(str_contains($path,'/returns/search'))return ['return_orders'=>[],'next_page_token'=>''];throw new RuntimeException('Unexpected fixture endpoint');}};
    $sync=new MarketplaceSyncService($p,fn($config)=>$fake,false,false,true);$sync->enqueueAll('T');$sync->step('T');
    $job=$p->query("SELECT * FROM marketplace_sync_jobs WHERE store_id='T' AND job_type='products'")->fetch(PDO::FETCH_ASSOC);$cp=json_decode($job['checkpoint'],true);integrationCheck($job['status']==='queued'&&$cp['_retry']['next_at']>=time()+35,'Retry-After not respected.');
    $sync->step('T');integrationCheck($p->query("SELECT status FROM marketplace_sync_jobs WHERE store_id='T' AND job_type='orders'")->fetchColumn()==='succeeded','Delayed product retry blocked independent order job.');
    integrationCheck($fake->calls===1,'Transient request retried before due time.');
    $report=(new MarketplaceIntegrationHealth($p))->forStore('T');$caps=array_column($report['resources'],null,'key');integrationCheck($caps['products']['sync_status']==='retry_wait'&&$caps['orders']['state']==='available'&&$caps['finance']['state']==='unverified','Per-resource evidence/retry status wrong.');
    $blocked=false;try{(new MarketplaceIntegrationHealth($p))->forStore('X');}catch(DomainException){$blocked=true;}integrationCheck($blocked,'Health endpoint scope leaked other company.');
    for($i=0;$i<3;$i++){$job=$p->query("SELECT id,checkpoint FROM marketplace_sync_jobs WHERE store_id='T' AND job_type='products'")->fetch(PDO::FETCH_ASSOC);$cp=json_decode($job['checkpoint'],true);$cp['_retry']['next_at']=0;$p->prepare('UPDATE marketplace_sync_jobs SET checkpoint=? WHERE id=?')->execute([json_encode($cp),$job['id']]);$sync->step('T');}
    integrationCheck($p->query("SELECT status FROM marketplace_sync_jobs WHERE store_id='T' AND job_type='products'")->fetchColumn()==='failed','Retries not bounded.');
    integrationCheck(MarketplaceIntegrationPolicy::retry('HTTP_401',0)===null&&MarketplaceIntegrationPolicy::retry('TIKTOK_API_105005',0)===null,'Authorization/permission auto retried.');
    $runtime=new MarketplaceIntegrationRuntime($p);$runtime->cooldown('fixture-scope',50);integrationCheck($runtime->remainingCooldown('fixture-scope')>=48,'Platform cooldown not stored.');
    $manual=$sync->retryFailed('T',(int)$job['id']);integrationCheck(isset($manual['run_key'])&&!$manual['catalog'],'Failed process retry did not retain original run.');
    $fake->error='TIKTOK_API_105005';$sync->step('T');$caps=array_column((new MarketplaceIntegrationHealth($p))->forStore('T')['resources'],null,'key');integrationCheck($caps['products']['state']==='permission','Permission error not isolated to product access.');
    echo "PASS: OAuth configuration binding, no false capabilities, real concurrent refresh lock, tenant/secret isolation, independent resource jobs, Retry-After/backoff, bounded retries and explicit resume.\n";
}finally{
    Database::reset();Database::setConfig($original);$server->exec("DROP DATABASE `$name`");
    if($keyBefore===false)putenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY');else putenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY='.$keyBefore);
    putenv('JL_INTEGRATION_TEST_DB');putenv('JL_INTEGRATION_TEST_STORE');
}
