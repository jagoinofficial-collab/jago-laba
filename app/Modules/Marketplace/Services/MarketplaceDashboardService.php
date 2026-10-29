<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 4) . '/app/Shared/Companies/MarketplaceScopeService.php');
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceOrderPresentation.php');
final class MarketplaceDashboardService
{
 public function __construct(private PDO $pdo){}
 public static function period(array $filters=[]):array {
  $today=new DateTimeImmutable('today',new DateTimeZone('Asia/Jakarta'));
  $preset=(string)($filters['period']??'month');
  [$start,$end]=match($preset){'today'=>[$today,$today],'yesterday'=>[$today->modify('-1 day'),$today->modify('-1 day')],'week'=>[$today->modify('-6 days'),$today],'last_month'=>[$today->modify('first day of last month'),$today->modify('last day of last month')],default=>[$today->modify('first day of this month'),$today]};
  if($preset==='custom'){
   foreach(['date_start','date_end'] as $k){$v=(string)($filters[$k]??'');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Tanggal tidak valid.');if($k==='date_start')$start=$d;else $end=$d;}
  }
  if($start>$end||$start->diff($end)->days>365)throw new InvalidArgumentException('Pilih rentang berurutan maksimal 366 hari.');
  return ['start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d'),'preset'=>$preset,'exclusive_end'=>$end->modify('+1 day')->format('Y-m-d')];
 }
 public function report(string $storeId,array $filters=[]):array {
  $owns=!$this->pdo->inTransaction();if($owns)$this->pdo->beginTransaction();
  try{$result=$this->readReport($storeId,$filters);if($owns)$this->pdo->commit();return $result;}
  catch(Throwable $e){if($owns&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 private function readReport(string $storeId,array $filters):array {
  (new MarketplaceScopeService($this->pdo))->assertStoreAccess($storeId);$period=self::period($filters);
  $q=$this->pdo->prepare("SELECT id,source_kind,order_date,order_status,gross_sales,transaction_detail_json FROM marketplace_orders WHERE store_id=? AND source_kind='api' AND (internal_state IS NULL OR internal_state='active') AND order_date>=? AND order_date<? ORDER BY order_date,id");$q->execute([$storeId,$period['start'],$period['exclusive_end']]);
  $e=$this->pdo->prepare('SELECT id,expense_date,category,description,amount FROM marketplace_expenses WHERE store_id=? AND expense_date>=? AND expense_date<? AND affects_profit=1 AND is_cashflow_only=0 ORDER BY expense_date,id');$e->execute([$storeId,$period['start'],$period['exclusive_end']]);
  $result=self::aggregate((function()use($q){while($row=$q->fetch(PDO::FETCH_ASSOC))yield $row;})(),$e->fetchAll(PDO::FETCH_ASSOC));
  return $result+['period'=>$period,'generated_at'=>date('Y-m-d H:i:s')];
 }
 private static function bucket():array{return ['count'=>0,'sales_known'=>0.0,'net_known'=>0.0,'hpp_known'=>0.0,'fees_known'=>0.0,'missing_sales'=>0,'missing_net'=>0,'missing_hpp'=>0,'unreconciled'=>0,'profit_known'=>0.0,'profit_count'=>0];}
 public static function aggregate(iterable $orders,array $expenses):array {
  $groups=['final'=>self::bucket(),'estimated'=>self::bucket(),'unknown'=>self::bucket()];$daily=[];$attention=['cancelled'=>0,'unpaid'=>0,'returns'=>0,'loss'=>0,'missing_finance'=>0,'missing_hpp'=>0,'unreconciled'=>0,'cancel_finance'=>0];$rows=[];
  foreach($orders as $o){
   if(($o['source_kind']??'')!=='api')continue;
   $p=MarketplaceOrderPresentation::build($o);$snap=json_decode((string)($o['transaction_detail_json']??''),true)?:[];
   if($p['status']==='Batal'){$attention['cancelled']++;if(($p['net_income']??0)!=0)$attention['cancel_finance']++;continue;}
   if($p['status']==='Belum Dibayar'){$attention['unpaid']++;continue;}
   $known=array_key_exists('net_income',$snap)&&is_numeric($snap['net_income']);
   $state=!$known?'unknown':(($snap['is_estimated']??true)===false?'final':'estimated');
   // Finance revenue is after seller discount. Derive the net deduction once from that same basis.
   $sales=is_numeric($p['finance_revenue'])?(float)$p['finance_revenue']:(isset($snap['gross_amount'])&&is_numeric($snap['gross_amount'])?(float)$snap['gross_amount']:null);
   $net=$known?$p['net_income']:null;$hpp=array_key_exists('total_cogs',$snap)&&is_numeric($snap['total_cogs'])?$p['total_cogs']:null;
   if(!empty($snap['has_return'])){$attention['returns']++;foreach($p['items'] as $item)if($item['returned_quantity']===null)$hpp=null;}
   $fees=$sales!==null&&$net!==null?$sales-$net:null;
   $unreconciled=$known&&isset($snap['gross_amount'],$snap['total_fees'])&&is_numeric($snap['gross_amount'])&&is_numeric($snap['total_fees'])&&abs((float)$snap['gross_amount']-(float)$snap['total_fees']-$net)>1;
   $g=&$groups[$state];$g['count']++;
   foreach(['sales'=>$sales,'net'=>$net,'hpp'=>$hpp] as $key=>$v){if($v===null)$g['missing_'.$key]++;else $g[$key.'_known']+=$v;}
   if($fees!==null)$g['fees_known']+=$fees;
   if($unreconciled){$g['unreconciled']++;$attention['unreconciled']++;}
   if($net===null)$attention['missing_finance']++;if($hpp===null)$attention['missing_hpp']++;
   $profit=$net!==null&&$hpp!==null&&!$unreconciled?$net-$hpp:null;
   if($profit!==null){$g['profit_known']+=$profit;$g['profit_count']++;if($profit<0)$attention['loss']++;}
   $date=substr((string)$o['order_date'],0,10);
   if(!isset($daily[$date]))$daily[$date]=['date'=>$date,'sales'=>0.0,'net'=>0.0,'profit'=>0.0,'orders'=>0,'complete'=>true];
   $d=&$daily[$date];$d['orders']++;$d['sales']+=$sales??0;$d['net']+=$net??0;$d['profit']+=$profit??0;if($sales===null||$profit===null)$d['complete']=false;unset($d,$g);
   $rows[]=['id'=>$o['id'],'state'=>$state,'sales'=>$sales,'net'=>$net,'hpp'=>$hpp,'profit'=>$profit];
  }
  foreach($groups as &$g){$g['sales']=$g['count']&& !$g['missing_sales']?$g['sales_known']:null;$g['net']=$g['count']&&!$g['missing_net']?$g['net_known']:null;$g['hpp']=$g['count']&&!$g['missing_hpp']?$g['hpp_known']:null;$g['fees']=$g['sales']!==null&&$g['net']!==null?$g['sales']-$g['net']:null;$g['profit']=$g['count']&&$g['profit_count']===$g['count']?$g['profit_known']:null;}unset($g);
  $costs=['ads'=>null,'operational'=>null,'count'=>0];$expenseRows=[];
  foreach($expenses as $e){if(!is_numeric($e['amount']??null))continue;$type=preg_match('/iklan|advertising|ads/i',(string)$e['category'])?'ads':'operational';$costs[$type]=($costs[$type]??0)+(float)$e['amount'];$costs['count']++;$expenseRows[]=$e;}
  // No verified Ads connector/expense-coverage declaration exists. Never certify absent costs as zero.
  $combined=self::bucket();foreach($groups as $g)foreach(array_keys($combined) as $k)$combined[$k]+=$g[$k];
  $subtotal=$combined['profit_count']>0?$combined['profit_known']-($costs['ads']??0)-($costs['operational']??0):null;
  return ['groups'=>$groups,'attention'=>$attention,'daily'=>array_values($daily),'costs'=>$costs,'rows'=>$rows,'expenses'=>$expenseRows,'known_subtotal'=>$subtotal,'subtotal_orders'=>$combined['profit_count'],'eligible_orders'=>$combined['count'],'net_profit'=>null,'cost_coverage_complete'=>false];
 }
}
