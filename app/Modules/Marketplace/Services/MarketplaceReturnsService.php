<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/Shared/Companies/MarketplaceScopeService.php';

/** Read model over the original API cases. Never changes stock, HPP or payouts. */
final class MarketplaceReturnsService
{
 public function __construct(private PDO $pdo){}
 public static function existsSql(string $order='o',string $type='',string $dateField='',?int $start=null,?int $end=null):string {
  $kind=match($type){'goods'=>" AND JSON_UNQUOTE(JSON_EXTRACT(rc.payload_json,'$.return_type'))='RETURN_AND_REFUND'",'refund'=>" AND JSON_UNQUOTE(JSON_EXTRACT(rc.payload_json,'$.return_type'))='REFUND'",'replacement'=>" AND JSON_UNQUOTE(JSON_EXTRACT(rc.payload_json,'$.return_type')) IN ('REPLACEMENT','EXCHANGE')",default=>''};
  if(in_array($dateField,['create_time','update_time'],true)&&$start!==null&&$end!==null)$kind.=" AND CAST(JSON_UNQUOTE(JSON_EXTRACT(rc.payload_json,'$.".$dateField."')) AS UNSIGNED)>=".$start." AND CAST(JSON_UNQUOTE(JSON_EXTRACT(rc.payload_json,'$.".$dateField."')) AS UNSIGNED)<".$end;
  return "EXISTS(SELECT 1 FROM marketplace_sync_records rc WHERE rc.store_id=$order.store_id AND rc.resource_type='return' AND JSON_UNQUOTE(JSON_EXTRACT(rc.payload_json,'$.order_id'))=$order.id$kind)";
 }
 public static function normalize(array $r,string $platform):array {
  $status=(string)($r['return_status']??$r['status']??'');$type=(string)($r['return_type']??'');
  $labels=$platform==='TikTok Shop'?[
   'RETURN_OR_REFUND_REQUEST_PENDING'=>'Diajukan','AWAITING_BUYER_SHIP'=>'Menunggu barang dikirim pembeli','BUYER_SHIPPED_ITEM'=>'Barang dalam perjalanan kembali','REJECT_RECEIVE_PACKAGE'=>'Penerimaan ditolak','REFUND_OR_RETURN_REQUEST_REJECT'=>'Ditolak','RETURN_OR_REFUND_REQUEST_SUCCESS'=>'Refund sedang diproses','RETURN_OR_REFUND_REQUEST_COMPLETE'=>'Refund selesai','RETURN_OR_REFUND_REQUEST_CANCEL'=>'Dibatalkan','REPLACEMENT_REQUEST_PENDING'=>'Penggantian diajukan','REPLACEMENT_REQUEST_REJECT'=>'Ditolak','REPLACEMENT_REQUEST_CANCEL'=>'Dibatalkan','REPLACEMENT_REQUEST_COMPLETE'=>'Penggantian disetujui','EXCHANGE_REQUEST_PENDING'=>'Penukaran diajukan','EXCHANGE_REQUEST_CANCEL'=>'Dibatalkan','EXCHANGE_REQUEST_COMPLETE'=>'Penukaran disetujui','AWAITING_BUYER_RESPONSE'=>'Menunggu respons pembeli']:[];
  $complete=$platform==='TikTok Shop'&&$status==='RETURN_OR_REFUND_REQUEST_COMPLETE';
  $amount=$r['refund_amount']??null;if(is_array($amount))$amount=$amount['amount']??null;
  return ['id'=>(string)($r['return_id']??$r['id']??''),'order_id'=>(string)($r['order_id']??''),'type'=>$type,'type_label'=>match($type){'REFUND'=>'Dana Saja','RETURN_AND_REFUND'=>'Barang & Dana','REPLACEMENT'=>'Penggantian','EXCHANGE'=>'Penukaran',default=>'Jenis belum dipetakan'},'source_status'=>$status,'status'=>$labels[$status]??'Status belum dikenali','refund_status'=>$complete?'Refund selesai':($status==='RETURN_OR_REFUND_REQUEST_SUCCESS'?'Refund sedang diproses':'Belum dikonfirmasi'),'refund_amount'=>is_numeric($amount)?(string)$amount:null,'refund_complete'=>$complete,'currency'=>$r['currency']??($r['refund_amount']['currency']??null),'reason'=>(string)($r['return_reason_text']??$r['return_reason']??''),'tracking'=>(string)($r['return_tracking_number']??''),'courier'=>(string)($r['return_provider_name']??''),'arbitration'=>(string)($r['arbitration_status']??''),'created'=>$r['create_time']??null,'updated'=>$r['update_time']??null,'items'=>$r['return_line_items']??$r['return_items']??[]];
 }
 public function forOrders(string $store,array $ids,string $platform):array {
  (new MarketplaceScopeService($this->pdo))->assertStoreAccess($store);if(!$ids)return [];
  $ids=array_values(array_unique(array_map('strval',$ids)));if(count($ids)>5001)throw new InvalidArgumentException('Terlalu banyak pesanan.');
  $q=$this->pdo->prepare("SELECT payload_json,fetched_at FROM marketplace_sync_records WHERE store_id=? AND resource_type='return' AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.order_id')) IN (".implode(',',array_fill(0,count($ids),'?')).")");$q->execute(array_merge([$store],$ids));$out=[];
  while($r=$q->fetch(PDO::FETCH_ASSOC)){$case=self::normalize(json_decode($r['payload_json'],true)?:[],$platform);$case['synced_at']=$r['fetched_at'];$out[$case['order_id']][]=$case;}return $out;
 }
 public function coverage(string $store):array {
  (new MarketplaceScopeService($this->pdo))->assertStoreAccess($store);
  $q=$this->pdo->prepare("SELECT status,last_error_code,finished_at FROM marketplace_sync_jobs WHERE store_id=? AND job_type='returns' ORDER BY id DESC LIMIT 1");$q->execute([$store]);$last=$q->fetch(PDO::FETCH_ASSOC);
  $q=$this->pdo->prepare("SELECT MAX(finished_at) FROM marketplace_sync_jobs WHERE store_id=? AND job_type='returns' AND status='succeeded'");$q->execute([$store]);$success=$q->fetchColumn();
  return ['available'=>(bool)$success,'last_success'=>$success?:null,'status'=>$last['status']??'unavailable','error'=>$last['last_error_code']??null];
 }
}
