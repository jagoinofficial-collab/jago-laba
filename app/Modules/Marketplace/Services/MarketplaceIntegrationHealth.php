<?php
declare(strict_types=1);
require_once __DIR__ . '/MarketplaceIntegrationRuntime.php';

/** Public status contains evidence and machine codes, never credentials or source payloads. */
final class MarketplaceIntegrationHealth
{
    public function __construct(private PDO $pdo) {}
    public function forStore(string $storeId):array
    {
        $scope=new MarketplaceScopeService($this->pdo);$store=$scope->assertStoreAccess($storeId);
        $q=$this->pdo->prepare('SELECT id,platform,status,authorized_at,token_expires_at,last_successful_sync_at FROM marketplace_oauth_connections WHERE store_id=? AND company_key=?');$q->execute([$storeId,$scope->companyKey()]);$connection=$q->fetch(PDO::FETCH_ASSOC);
        $resources=[];$runtime=new MarketplaceIntegrationRuntime($this->pdo);
        $evidence=$connection?$runtime->evidence((int)$connection['id']):[];
        $binding=$connection?$runtime->binding((int)$connection['id']):null;
        $q=$this->pdo->prepare('SELECT j.* FROM marketplace_sync_jobs j JOIN (SELECT job_type,MAX(id) id FROM marketplace_sync_jobs WHERE store_id=? AND company_key=? GROUP BY job_type) latest ON latest.id=j.id');$q->execute([$storeId,$scope->companyKey()]);$jobs=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$this->pdo->prepare('SELECT resource_type,MAX(fetched_at) verified_at FROM marketplace_sync_records WHERE store_id=? GROUP BY resource_type');$q->execute([$storeId]);$sourceTimes=$q->fetchAll(PDO::FETCH_KEY_PAIR);
        $q=$this->pdo->prepare("SELECT COUNT(DISTINCT external_product_id) products,COUNT(*) variants,SUM(price IS NOT NULL) prices,SUM(stock_qty IS NOT NULL) stock FROM marketplace_catalog_variants WHERE store_id=? AND catalog_visible=1 AND source_status IN ('ACTIVATE','NORMAL','ACTIVE','AKTIF')");$q->execute([$storeId]);$counts=$q->fetch(PDO::FETCH_ASSOC);
        $sourceMap=['products'=>'product','prices'=>'product','stock'=>'product','orders'=>'order','finance'=>'transaction','returns'=>'return'];
        $connectionStatus=$connection['status']??'missing';
        $contextChanged=false;
        if($binding){
            try{$runtime->assertContext($connection,(new MarketplacePartnerSettings($this->pdo))->configFor($store['platform']));}
            catch(MarketplaceIntegrationException){$contextChanged=true;}
        }
        foreach(MarketplaceIntegrationPolicy::resources($store['platform']) as $key=>$definition){
            $state=$definition['supported']?'unverified':'unsupported';$verified=null;$attempt=null;$error=null;$latest=null;
            if($definition['supported']){
                $proof=$evidence[$key]??null;
                if($proof){$state=$proof['state'];$verified=$proof['last_verified_at'];$attempt=$proof['last_attempt_at'];$error=$proof['last_error_code'];}
                else{
                    $date=$sourceTimes[$sourceMap[$key]??'']??null;
                    // Old data does not prove newly granted tokens still have access.
                    if($date && $connection && $date >= $connection['authorized_at'] && (!in_array($key,['prices','stock'],true)||(int)($counts[$key]??0)>0)){$state='available';$verified=$date;}
                }
                foreach($jobs as $job)if(in_array($job['job_type'],$definition['job_types'],true)&&(!$latest||(int)$job['id']>(int)$latest['id']))$latest=$job;
                if($latest && $latest['status']==='failed' && (!$connection||$latest['requested_at'] >= $connection['authorized_at'])){
                    $error=$latest['last_error_code'];$state=match(MarketplaceIntegrationPolicy::classify((string)$error)){'authorization'=>'reauthorize','permission'=>'permission','unsupported'=>'unsupported',default=>'temporary'};
                }
                if($connectionStatus!=='verified'||$contextChanged)$state='reauthorize';
            }
            $labels=['available'=>'Tersedia','unverified'=>'Belum diverifikasi','permission'=>'Memerlukan izin','unsupported'=>'Tidak didukung konektor','temporary'=>'Gagal sementara','reauthorize'=>'Perlu otorisasi ulang'];
            $checkpoint=$latest?json_decode((string)($latest['checkpoint']??''),true):[];
            $sync=$latest['status']??'not_started';$retryAt=$checkpoint['_retry']['next_at']??null;
            if($sync==='queued'&&$retryAt&&$retryAt>time())$sync='retry_wait';
            $syncLabels=['not_started'=>'Belum dijalankan','queued'=>'Dalam antrean','running'=>'Sedang diproses','retry_wait'=>'Menunggu percobaan ulang','succeeded'=>'Proses selesai','failed'=>'Proses gagal'];
            $resources[]=['key'=>$key,'label'=>$definition['label'],'state'=>$state,'state_label'=>$labels[$state]??'Belum diverifikasi',
                'last_verified_at'=>$verified,'last_attempt_at'=>$attempt,'error_code'=>$error,'sync_status'=>$sync,'sync_label'=>$syncLabels[$sync]??'Belum diketahui',
                'processed'=>$latest?(int)$latest['success_count']:0,'retry_at'=>$retryAt,'retry_attempt'=>(int)($checkpoint['_retry']['attempt']??0),
                'retry_job_id'=>$latest&&$latest['status']==='failed'?(int)$latest['id']:null];
        }
        // A successful shipping document is independent from successful order reads.
        if($store['platform']==='TikTok Shop' && $this->pdo->query("SHOW TABLES LIKE 'marketplace_shipping_document_audit'")->fetchColumn()){
            $q=$this->pdo->prepare("SELECT event_type,error_code,created_at FROM marketplace_shipping_document_audit WHERE store_id=? AND company_key=? AND event_type IN ('prepared','cache_reused','failed') ORDER BY id DESC LIMIT 1");$q->execute([$storeId,$scope->companyKey()]);$document=$q->fetch(PDO::FETCH_ASSOC);
            if($document && $connection && $document['created_at'] >= $connection['authorized_at'])foreach($resources as &$r)if($r['key']==='shipping' && $r['state']!=='reauthorize'){
                $r['state']=$document['event_type']==='failed'?(MarketplaceIntegrationPolicy::classify((string)$document['error_code'])==='permission'?'permission':'temporary'):'available';
                $r['state_label']=match($r['state']){'available'=>'Tersedia','permission'=>'Memerlukan izin',default=>'Gagal sementara'};$r['error_code']=$document['error_code'];$r['last_verified_at']=$r['state']==='available'?$document['created_at']:null;
            }unset($r);
        }
        $environment=$binding['environment']??'unspecified';
        return ['store_id'=>$storeId,'platform'=>$store['platform'],'store_name'=>$store['store_name'],
            'connection_status'=>$contextChanged?'reauthorize':$connectionStatus,'connection_label'=>($contextChanged||$connectionStatus==='reauthorize')?'Perlu Otorisasi Ulang':($connectionStatus==='verified'?'Terhubung':($connectionStatus==='disconnected'?'Terputus':'Belum terhubung')),
            'environment'=>$environment,'environment_label'=>match($environment){'live'=>'Live','sandbox'=>'Sandbox','development'=>'Development Shop',default=>'Lingkungan belum diverifikasi'},
            'token_expired'=>$connection&&!empty($connection['token_expires_at'])&&strtotime($connection['token_expires_at'])<=time(),
            'last_successful_sync_at'=>$connection['last_successful_sync_at']??null,'counts'=>array_map('intval',$counts),
            'resources'=>$resources,'webhook_active'=>false,'auto_driver'=>'browser-pulse','manual_driver'=>'server-worker',
            'metadata_ready'=>$runtime->ready(),'can_manage'=>preg_match('/OWNER|ADMIN/i',(string)($_SESSION['role']??''))===1];
    }
}
