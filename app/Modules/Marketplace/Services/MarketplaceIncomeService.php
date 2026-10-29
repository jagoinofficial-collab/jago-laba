<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 4) . '/app/Shared/Companies/MarketplaceScopeService.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceOrderPresentation.php');
final class MarketplaceIncomeService
{
 public function __construct(private PDO $pdo){}
 public function overview(array $filters=[]):array
 {
  $scope=new MarketplaceScopeService($this->pdo);$args=[$scope->companyKey()];$where=["s.company_key=?","s.archived_at IS NULL","o.source_kind='api'","(o.internal_state IS NULL OR o.internal_state='active')"];
  $store=trim((string)($filters['store_id']??''));if($store!==''){$scope->assertStoreAccess($store);$where[]='o.store_id=?';$args[]=$store;}
  $platform=(string)($filters['platform']??'');if(in_array($platform,['TikTok Shop','Shopee'],true)){$where[]='s.platform=?';$args[]=$platform;}
  foreach(['date_start'=>'>=','date_end'=>'<'] as $key=>$op){$v=(string)($filters[$key]??'');if($v==='')continue;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Tanggal filter tidak valid.');$where[]='o.order_date'.$op.'?';$args[]=$key==='date_end'?$d->modify('+1 day')->format('Y-m-d'):$v;}
  $search=trim((string)($filters['search']??''));if($search!==''){$where[]='(o.id LIKE ? OR o.payout_batch_id LIKE ?)';$args[]='%'.$search.'%';$args[]='%'.$search.'%';}
  $q=$this->pdo->prepare('SELECT o.*,s.store_name,s.platform,c.last_successful_sync_at FROM marketplace_orders o JOIN marketplace_stores s ON s.id=o.store_id LEFT JOIN marketplace_oauth_connections c ON c.store_id=s.id AND c.company_key=s.company_key WHERE '.implode(' AND ',$where).' ORDER BY o.order_date DESC,o.id DESC');$q->execute($args);
  $page=max(1,(int)($filters['page']??1));$status=(string)($filters['status']??'');$summary=['estimated'=>null,'final'=>null,'unknown'=>0,'count'=>0];$rows=[];$total=0;$stores=[];
  while($o=$q->fetch(PDO::FETCH_ASSOC)){
   $p=MarketplaceOrderPresentation::build($o);if(in_array($p['source_status'],['UNPAID','CANCELLED','Dibatalkan'],true))continue;
   $snapshot=json_decode((string)$o['transaction_detail_json'],true)?:[];$known=$p['net_income']!==null;$estimated=$known&&($snapshot['is_estimated']??true)!==false;$state=!$known?'unknown':($estimated?'estimated':'final');
   if($status!==''&&$status!==$state)continue;
   $summary['count']++;if(!$known)$summary['unknown']++;else{$k=$estimated?'estimated':'final';$summary[$k]=($summary[$k]??0)+$p['net_income'];}
   $stores[$o['store_id']]=['name'=>$o['store_name'],'platform'=>$o['platform'],'last_sync'=>$o['last_successful_sync_at']];
   if($total>=($page-1)*50&&$total<$page*50)$rows[]=['order'=>$o,'facts'=>$p,'state'=>$state];$total++;
  }
  $q=$this->pdo->prepare('SELECT id,store_name,platform FROM marketplace_stores WHERE company_key=? AND archived_at IS NULL ORDER BY store_name');$q->execute([$scope->companyKey()]);
  return ['summary'=>$summary,'rows'=>$rows,'stores'=>$stores,'store_options'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/50))];
 }
}
