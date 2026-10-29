<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$options=getopt('',['store:','company:','steps::','list','enqueue']);
if(isset($options['list'])){
    $rows=Database::pdo()->query("SELECT store_id,company_key,status FROM marketplace_oauth_connections WHERE platform='TikTok Shop' AND UPPER(external_store_name) LIKE 'SANDBOX%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows,JSON_UNESCAPED_SLASHES)."\n";exit;
}
if(empty($options['store'])||empty($options['company'])){fwrite(STDERR,"Usage: php scripts/marketplace-cron.php --company=COMPANY --store=STORE_ID --steps=20\n");exit(2);}
$_SESSION['company_key']=(string)$options['company'];$_SESSION['user_id']='MARKETPLACE-WORKER';$_SESSION['role']='OWNER';
try{
    $worker=new MarketplaceSyncService();$limit=max(1,min(100,(int)($options['steps']??20)));
    if(isset($options['enqueue']))$worker->enqueueAll((string)$options['store']);
    for($i=0;$i<$limit;$i++){$status=$worker->step((string)$options['store']);if(!array_filter($status['jobs'],static fn($job)=>in_array($job['status'],['queued','running'],true)))break;}
    foreach($status['jobs'] as $job)echo $job['job_type'].' '.$job['status'].' saved='.$job['success_count'].' error='.($job['last_error_code']??'-')."\n";
    exit(array_filter($status['jobs'],static fn($job)=>$job['status']==='failed')?1:0);
}catch(Throwable $e){fwrite(STDERR,"Worker gagal: periksa scope toko, token dan database.\n");exit(1);}
