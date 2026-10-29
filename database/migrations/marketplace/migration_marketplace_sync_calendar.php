<?php
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
$pdo=Database::pdo();
if(!$pdo->query("SHOW COLUMNS FROM marketplace_stores LIKE 'sync_history_start_at'")->fetch()){
 $pdo->exec('ALTER TABLE marketplace_stores ADD sync_history_start_at DATETIME NULL');
 $pdo->exec("UPDATE marketplace_stores s LEFT JOIN marketplace_oauth_connections c ON c.store_id=s.id AND c.company_key=s.company_key SET s.sync_history_start_at=DATE_FORMAT(COALESCE(c.authorized_at,NOW()),'%Y-%m-01 00:00:00') WHERE s.sync_history_start_at IS NULL");
 // Existing enabled stores must backfill the missing beginning of their integration month.
 $pdo->exec('UPDATE marketplace_auto_sync SET last_success_at=NULL,next_run_at=NOW() WHERE enabled=1');
}
$jobs=$pdo->query("SELECT j.id,j.run_key,j.checkpoint,s.sync_history_start_at FROM marketplace_sync_jobs j JOIN marketplace_stores s ON s.id=j.store_id AND s.company_key=j.company_key WHERE j.job_type='orders' AND j.status IN ('queued','running') AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(j.checkpoint,'$.policy')),'')<>'calendar_month'")->fetchAll(PDO::FETCH_ASSOC);
foreach($jobs as $job){
 $start=(new DateTimeImmutable($job['sync_history_start_at'],new DateTimeZone('Asia/Jakarta')))->getTimestamp();
 $cp=['policy'=>'calendar_month','window_start'=>$start,'window_end'=>time(),'filter'=>['create_time_ge'=>$start,'create_time_lt'=>time()+1]];
 $old=json_decode((string)$job['checkpoint'],true)?:[];
 if(isset($old['exclude_unpaid']))$cp['exclude_unpaid']=(bool)$old['exclude_unpaid'];
 if(str_starts_with($job['run_key'],'auto-unpaid-'))$cp['filter']['order_status']='UNPAID';
 // Reset old pagination and leases; the old worker cannot commit its in-flight response.
 $pdo->prepare("UPDATE marketplace_sync_jobs SET checkpoint=?,status='queued',lease_token=NULL,lease_until=NULL,last_error_code=NULL WHERE id=? AND status IN ('queued','running')")->execute([json_encode($cp,JSON_THROW_ON_ERROR),$job['id']]);
}
echo "Calendar-month sync window ready.\n";
