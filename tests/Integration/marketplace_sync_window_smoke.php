<?php
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceSyncWindow.php');
$tz=new DateTimeZone('Asia/Jakarta');
foreach(['2026-10-25 10:30:00'=>'2026-10-01 00:00:00','2026-11-01 00:00:00'=>'2026-11-01 00:00:00','2027-01-12 14:00:00'=>'2027-01-01 00:00:00','2028-02-29 12:00:00'=>'2028-02-01 00:00:00'] as $date=>$expected){
 $now=(new DateTimeImmutable($date,$tz))->getTimestamp();$window=MarketplaceSyncWindow::checkpoint($now);
 if($window['window_start']!==(new DateTimeImmutable($expected,$tz))->getTimestamp()||$window['filter']['create_time_lt']!==$now+1)throw new RuntimeException('Incorrect Jakarta integration-month boundary');
}
$original=MarketplaceSyncWindow::checkpoint((new DateTimeImmutable('2026-10-25 10:00:00',$tz))->getTimestamp());
$next=MarketplaceSyncWindow::checkpoint((new DateTimeImmutable('2026-11-02 10:00:00',$tz))->getTimestamp());$next['window_start']=$original['window_start'];
$normalized=MarketplaceSyncWindow::normalize(['filter'=>['update_time_ge'=>$original['window_end']-300]],$next);
if($normalized['window_start']!==$original['window_start']||$normalized['filter']['create_time_ge']!==$original['window_start']||$normalized['window_end']!==$next['window_end'])throw new RuntimeException('Month rollover lost persisted integration anchor');
echo "PASS: Jakarta calendar month, 25 October example, new year, leap year and fixed anchor across months.\n";
