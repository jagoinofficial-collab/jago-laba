<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/Bootstrap/bootstrap.php';
MarketplaceModule::boot();

$service=(new ReflectionClass(MarketplaceSyncService::class))->newInstanceWithoutConstructor();
$eligible=new ReflectionMethod(MarketplaceSyncService::class,'eligibleOrder');
$fetch=new ReflectionMethod(MarketplaceSyncService::class,'fetchStep');
$anchor=(new DateTimeImmutable('2026-10-01 00:00:00',new DateTimeZone('Asia/Jakarta')))->getTimestamp();
$old=(new DateTimeImmutable('2026-09-12 00:00:00',new DateTimeZone('Asia/Jakarta')))->getTimestamp();
$end=(new DateTimeImmutable('2026-10-25 00:00:00',new DateTimeZone('Asia/Jakarta')))->getTimestamp();
$cp=['sync_v2'=>true,'mode'=>'incremental','history_start'=>$anchor,'window_start'=>$end-300,'window_end'=>$end];
if(!$eligible->invoke($service,'S',['id'=>'OLD','create_time'=>$old],$cp))throw new RuntimeException('An older updated order must be accepted.');
$initial=$cp;$initial['mode']='initial';
if($eligible->invoke($service,'S',['id'=>'OLD','create_time'=>$old],$initial))throw new RuntimeException('Initial history bound was ignored.');
$skipped=$fetch->invoke($service,null,'', ['external_shop_cipher'=>''],['job_type'=>'products'],['not_due'=>true]);
if(!$skipped['done']||$skipped['records']!==[])throw new RuntimeException('Routine sync reread a catalog before reconciliation was due.');
echo "PASS: old updated orders and bounded catalog reconciliation.\n";
