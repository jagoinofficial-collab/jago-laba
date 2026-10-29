<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$args=getopt('',['company:','store:','launch:','catalog','run:']);
if(empty($args['company'])||empty($args['store'])||empty($args['launch']))exit(2);
$_SESSION['company_key']=$args['company'];$_SESSION['user_id']='BACKGROUND-WORKER';$_SESSION['role']='OWNER';
session_write_close();$pdo=Database::pdo();$lock='jl-sync-'.substr(hash('sha256',$args['company'].':'.$args['store']),0,50);
$stmt=$pdo->prepare('SELECT GET_LOCK(?,'.(array_key_exists('catalog',$args)?30:0).')');$stmt->execute([$lock]);if((int)$stmt->fetchColumn()!==1)exit(0);
$update=$pdo->prepare('UPDATE marketplace_sync_workers SET status=?,heartbeat_at=NOW(),finished_at=IF(?=1,NOW(),NULL),error_code=? WHERE store_id=? AND company_key=? AND launch_key=?');
try{
    $runKey=isset($args['run'])?(string)$args['run']:null;$automation=array_key_exists('catalog',$args)||($runKey!==null&&str_starts_with($runKey,'auto-'));$sync=new MarketplaceSyncService($pdo,null,$automation,array_key_exists('catalog',$args),true);$deadline=time()+3600;
    while(time()<$deadline){
        if(MarketplaceStoreDeletion::blocked($pdo,$args['store']))exit(0);
        $update->execute(['running',0,null,$args['store'],$args['company'],$args['launch']]);if($update->rowCount()===0){
            $check=$pdo->prepare('SELECT launch_key FROM marketplace_sync_workers WHERE store_id=? AND company_key=?');$check->execute([$args['store'],$args['company']]);if($check->fetchColumn()!==$args['launch'])break;
        }
        $state=$sync->step($args['store'],$runKey);
        $pdo->beginTransaction();$check=$pdo->prepare('SELECT launch_key FROM marketplace_sync_workers WHERE store_id=? AND company_key=? FOR UPDATE');$check->execute([$args['store'],$args['company']]);
        if($check->fetchColumn()!==$args['launch']){$pdo->rollBack();break;}
        $pending=$pdo->prepare("SELECT COUNT(*) FROM marketplace_sync_jobs WHERE store_id=? AND company_key=? AND ".($runKey!==null?"run_key=? AND ":(array_key_exists('catalog',$args)?"run_key LIKE 'auto-catalog-%' AND ":"run_key NOT LIKE 'auto-%' AND "))."status IN ('queued','running')");$pending->execute($runKey!==null?[$args['store'],$args['company'],$runKey]:[$args['store'],$args['company']]);
        if((int)$pending->fetchColumn()===0){$failed=(bool)array_filter($state['jobs'],static fn($j)=>$j['status']==='failed');$update->execute([$failed?'failed':'completed',1,$failed?'SYNC_PARTIAL_FAILURE':null,$args['store'],$args['company'],$args['launch']]);$pdo->commit();exit($failed?1:0);}
        $pdo->commit();usleep(1000000);
    }
    $update->execute(['stalled',1,'WORKER_TIME_LIMIT',$args['store'],$args['company'],$args['launch']]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if(MarketplaceStoreDeletion::blocked($pdo,$args['store']))exit(0);$update->execute(['failed',1,'WORKER_FAILED',$args['store'],$args['company'],$args['launch']]);exit(1);}
finally{$pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
