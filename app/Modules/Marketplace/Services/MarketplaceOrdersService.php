<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 4) . '/app/Shared/Companies/MarketplaceScopeService.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceOrderPresentation.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceDashboardService.php');
require_once __DIR__.'/MarketplaceReturnsService.php';
final class MarketplaceOrdersService
{
 public const TABS=['all'=>'Semua','new'=>'Pesanan Baru','pickup'=>'Menunggu Pickup','shipped'=>'Dikirim','cancelled'=>'Dibatalkan','returns'=>'Pengembalian Barang'];
 public function __construct(private PDO $pdo){}
 public static function group(string $status,string $platform):string {
  if(in_array($status,['CANCELLED','Dibatalkan','Batal'],true))return 'cancelled';
  if(in_array($status,['IN_TRANSIT','SHIPPED','TO_CONFIRM_RECEIVE','PARTIALLY_SHIPPING','Terkirim'],true))return 'shipped';
  if($status==='AWAITING_COLLECTION'||($platform==='Shopee'&&$status==='PROCESSED'))return 'pickup';
  if(in_array($status,['AWAITING_SHIPMENT','READY_TO_SHIP'],true)||($platform==='TikTok Shop'&&$status==='PROCESSED'))return 'new';
  return 'all';
 }
 private static function json(string $field):string{return "NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(o.transaction_detail_json,'$.$field')),'null'),'')";}
 private static function source():string{return 'o.source_order_status';}
 private static function groupSql(string $platform):string {
  $source=self::source();$pickup=$platform==='Shopee'?"'AWAITING_COLLECTION','PROCESSED'":"'AWAITING_COLLECTION'";$new=$platform==='TikTok Shop'?"'AWAITING_SHIPMENT','READY_TO_SHIP','PROCESSED'":"'AWAITING_SHIPMENT','READY_TO_SHIP'";
  return "CASE WHEN $source IN ('CANCELLED','Dibatalkan','Batal') THEN 'cancelled' WHEN $source IN ('IN_TRANSIT','SHIPPED','TO_CONFIRM_RECEIVE','PARTIALLY_SHIPPING','Terkirim') THEN 'shipped' WHEN $source IN ($pickup) THEN 'pickup' WHEN $source IN ($new) THEN 'new' ELSE 'all' END";
 }
 public function listing(string $storeId,array $filters=[],bool $export=false):array {
  $owns=!$this->pdo->inTransaction();if($owns)$this->pdo->beginTransaction();
  try{$result=$this->readListing($storeId,$filters,$export);if($owns)$this->pdo->commit();return $result;}
  catch(Throwable $e){if($owns&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 private function readListing(string $storeId,array $filters,bool $export):array {
  $store=(new MarketplaceScopeService($this->pdo))->assertStoreAccess($storeId);$period=MarketplaceDashboardService::period($filters);if(($filters['order_tab']??'')==='complaint')$filters['order_tab']='returns';$tab=array_key_exists((string)($filters['order_tab']??''),self::TABS)?$filters['order_tab']:'all';
  $search=trim((string)($filters['search']??''));if(strlen($search)>200)throw new InvalidArgumentException('Pencarian maksimal 200 karakter.');$by=(string)($filters['search_by']??'order');
  $where=["o.store_id=?","o.source_kind='api'","(o.internal_state IS NULL OR o.internal_state='active')","o.order_date>=?","o.order_date<?"];$args=[$storeId,$period['start'],$period['exclusive_end']];
  $tracking="COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.tracking_number')),''),".self::json('tracking_number').",'')";
  $recipient="COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.recipient_address.name')),'')";
  $courier="COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.shipping_provider')),JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.shipping_carrier')),".self::json('shipping_provider').",'')";
  if($search!==''){$field=match($by){'sku'=>"JSON_EXTRACT(o.transaction_detail_json,'$.items[*].sku')",'product'=>"JSON_EXTRACT(o.transaction_detail_json,'$.items[*].name')",'tracking'=>$tracking,'recipient'=>$recipient,default=>'o.id'};$where[]="$field LIKE ?";$args[]='%'.$search.'%';}
  $courierFilter=trim((string)($filters['courier']??''));if($courierFilter!==''){$where[]="$courier LIKE ?";$args[]='%'.substr($courierFilter,0,100).'%';}
  $payment=(string)($filters['payment']??'');if($payment==='cod')$where[]="JSON_EXTRACT(o.transaction_detail_json,'$.is_cod')=true";elseif($payment==='non_cod')$where[]="JSON_EXTRACT(o.transaction_detail_json,'$.is_cod')=false";
  $extra=(string)($filters['condition']??'');$source=self::source();$whereExtra=match($extra){'unpaid'=>"$source='UNPAID'",'cancel_request'=>"(".self::json('is_buyer_request_cancel')."='true' OR $source IN ('IN_CANCEL','CANCEL_REQUESTED')) AND $source NOT IN ('CANCELLED','Dibatalkan')",'partial'=>"$source='PARTIALLY_SHIPPING'",'completed'=>"$source IN ('COMPLETED','Selesai')",'return'=>MarketplaceReturnsService::existsSql('o','goods'),'refund'=>MarketplaceReturnsService::existsSql('o','refund'),default=>''};if($whereExtra!=='')$where[]=$whereExtra;
  $dateBasis=in_array($filters['return_date']??'', ['created','updated','order'],true)?$filters['return_date']:'created';
  if($tab==='returns'&&$dateBasis!=='order'){
   $where=array_values(array_filter($where,static fn($v)=>$v!=='o.order_date>=?'&&$v!=='o.order_date<?'));array_splice($args,1,2);
   $field=$dateBasis==='updated'?'update_time':'create_time';
   $where[]=MarketplaceReturnsService::existsSql('o',(string)($filters['return_type']??''),$field,strtotime($period['start']),strtotime($period['exclusive_end']));
  }
  $base=" FROM marketplace_orders o LEFT JOIN marketplace_sync_records r ON r.store_id=o.store_id AND r.resource_type='order' AND r.external_id=o.id WHERE ".implode(' AND ',$where);$group=self::groupSql($store['platform']);$complaint=MarketplaceReturnsService::existsSql('o',(string)($filters['return_type']??''));
  $q=$this->pdo->prepare("SELECT COUNT(*) AS all_count,SUM(($group)='new') AS new_count,SUM(($group)='pickup') AS pickup_count,SUM(($group)='shipped') AS shipped_count,SUM(($group)='cancelled') AS cancelled_count,SUM($complaint) AS returns_count".$base);$q->execute($args);$counts=$q->fetch(PDO::FETCH_ASSOC);$out=[];foreach(array_keys(self::TABS) as $key)$out[$key]=(int)($counts[$key.'_count']??0);
  $tabWhere=$tab==='all'?'':($tab==='returns'?" AND $complaint":" AND ($group)=?");$params=$args;if($tab!=='all'&&$tab!=='returns')$params[]=$tab;
  $total=$out[$tab];$per=in_array((int)($filters['per_page']??50),[25,50,100],true)?(int)($filters['per_page']??50):50;$pages=max(1,(int)ceil($total/$per));$page=min($pages,max(1,(int)($filters['page']??1)));$limit=$export?' LIMIT 5001':' LIMIT '.$per.' OFFSET '.(($page-1)*$per);
  $q=$this->pdo->prepare('SELECT o.id,o.order_date,o.order_status,o.transaction_detail_json,o.gross_sales,r.payload_json,r.source_updated_at'.$base.$tabWhere.' ORDER BY o.order_date DESC,o.id DESC'.$limit);$rows=[];if(empty($filters['summary_only'])){$q->execute($params);while($o=$q->fetch(PDO::FETCH_ASSOC))$rows[]=self::present($o,$store['platform']);}
  $returnsService=new MarketplaceReturnsService($this->pdo);$cases=$returnsService->forOrders($storeId,array_column($rows,'id'),$store['platform']);foreach($rows as &$row){$row['return_cases']=$cases[$row['id']]??[];}unset($row);
  if($export&&count($rows)>5000)throw new InvalidArgumentException('Ekspor maksimal 5.000 pesanan. Persempit periode atau filter.');
  return ['store'=>$store,'period'=>$period,'counts'=>$out,'tab'=>$tab,'rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$per,'return_date'=>$dateBasis,'return_coverage'=>$returnsService->coverage($storeId),'complaint_coverage'=>false,'label_supported'=>$store['platform']==='TikTok Shop'];
 }
 public static function present(array $o,string $platform):array {
  $snap=json_decode((string)($o['transaction_detail_json']??''),true)?:[];$raw=json_decode((string)($o['payload_json']??''),true)?:[];$source=trim((string)($snap['source_status']??''));if($source==='')$source=(string)($o['order_status']??'');$p=MarketplaceOrderPresentation::build($o);$items=$p['items'];$qty=null;if($items){$qty=0;foreach($items as $item)$qty+=$item['quantity'];}
  $packages=[];foreach(($raw['packages']??$raw['package_list']??[]) as $package)if(is_array($package))$packages[]=['id'=>(string)($package['id']??$package['package_id']??$package['package_number']??''),'tracking'=>(string)($package['tracking_number']??''),'courier'=>(string)($package['shipping_provider_name']??$package['shipping_carrier']??''),'status'=>(string)($package['status']??$package['package_status']??'')];
  if(!$packages){$byPackage=[];foreach(($raw['line_items']??[]) as $line){$pid=(string)($line['package_id']??'');if($pid!==''&&!isset($byPackage[$pid]))$byPackage[$pid]=['id'=>$pid,'tracking'=>(string)($line['tracking_number']??''),'courier'=>(string)($line['shipping_provider_name']??''),'status'=>(string)($line['package_status']??'')];}$packages=array_values($byPackage);}
  $group=self::group($source,$platform);$displayStatus=match($group){'new'=>'Pesanan Baru','pickup'=>'Menunggu Pickup','shipped'=>$source==='PARTIALLY_SHIPPING'?'Dikirim Sebagian':'Dikirim','cancelled'=>'Dibatalkan',default=>array_key_exists($source,MarketplaceOrderPresentation::STATUS_LABELS)||in_array($source,['Selesai','Belum Dibayar','Menunggu Konfirmasi'],true)?$p['status']:'Belum Dipetakan'};
  $recipient=$raw['recipient_address']??[];if(!is_array($recipient))$recipient=[];$amount=$raw['payment']['total_amount']??$raw['total_amount']??$snap['order_paid_amount']??null;
  $timeline=[];foreach(['create_time'=>'Dibuat','paid_time'=>'Dibayar','collection_time'=>'Diserahkan ke kurir','delivery_time'=>'Diterima','completion_time'=>'Selesai'] as $field=>$label){$value=$raw[$field]??null;if(is_numeric($value)&&(int)$value>0)$timeline[]=['label'=>$label,'time'=>date('Y-m-d H:i:s',(int)$value)];}
  return ['id'=>(string)$o['id'],'date'=>$o['order_date'],'source_status'=>$source,'status'=>$displayStatus,'group'=>$group,'cancel_requested'=>!in_array($source,['CANCELLED','Dibatalkan'],true)&&(($snap['is_buyer_request_cancel']??false)===true||in_array($source,['IN_CANCEL','CANCEL_REQUESTED'],true)),'complaint_active'=>($snap['complaint_active']??null)===true,'has_return'=>($snap['has_return']??null)===true,'has_refund'=>($snap['has_refund']??null)===true,'items'=>$items,'quantity'=>$qty,'amount'=>is_numeric($amount)?(float)$amount:null,'payment_method'=>$p['payment_method'],'is_cod'=>$p['is_cod'],'recipient'=>(string)($recipient['name']??''),'address'=>(string)($recipient['full_address']??''),'phone'=>(string)($recipient['phone_number']??$recipient['phone']??''),'courier'=>(string)($raw['shipping_provider']??$raw['shipping_carrier']??$snap['shipping_provider']??''),'tracking'=>(string)($raw['tracking_number']??$p['tracking_number']),'packages'=>$packages,'timeline'=>$timeline,'updated'=>$snap['source_updated_at']??$o['source_updated_at']??null,'cancel_reason'=>(string)($snap['cancel_reason']??$raw['cancel_reason']??'')];
 }
}
