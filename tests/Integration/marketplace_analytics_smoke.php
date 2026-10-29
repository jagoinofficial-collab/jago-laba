<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceAnalyticsService.php');
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceAnalyticsCsv.php');
function analyticsCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE marketplace_stores(id TEXT PRIMARY KEY,company_key TEXT,store_name TEXT,platform TEXT,authorization_status TEXT,archived_at TEXT)');
$pdo->exec("INSERT INTO marketplace_stores VALUES ('S','A','Shopee <script>bad</script>','Shopee','pending',NULL),('T','A','TikTok','TikTok Shop','pending',NULL),('X','B','Other','Shopee','pending',NULL)");
$pdo->exec('CREATE TABLE marketplace_oauth_connections(store_id TEXT,company_key TEXT,status TEXT,verified_at TEXT,last_successful_sync_at TEXT)');
$pdo->exec('CREATE TABLE marketplace_orders(id TEXT,store_id TEXT,order_date TEXT,order_status TEXT,internal_state TEXT,gross_sales REAL,platform_admin_fee REAL,service_fee REAL,payment_fee REAL,shipping_adjustment REAL,other_deductions REAL,affiliate_commission REAL,live_commission REAL,seller_discount REAL,net_settlement_amount REAL,total_cogs_hpp REAL,adjustment_amount REAL,escrow_status TEXT,transaction_detail_json TEXT,source_kind TEXT,completion_date TEXT)');
$insert=$pdo->prepare('INSERT INTO marketplace_orders(id,store_id,order_date,order_status,internal_state,gross_sales,platform_admin_fee,net_settlement_amount,total_cogs_hpp,adjustment_amount,escrow_status,transaction_detail_json,source_kind,completion_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$insert->execute(['ONE','S','2026-10-01 10:00:00','Selesai','active',1000,200,850,300,50,'Sudah Dicairkan',json_encode(['released_at'=>'2026-10-02 12:00:00','is_estimated'=>false]),'api','2026-10-01 15:00:00']);
$insert->execute(['TWO','T','2026-10-02 10:00:00','Selesai','active',500,100,400,100,0,'Tertahan',null,'import','2026-10-02 15:00:00']);
$insert->execute(['CANCEL','S','2026-10-02 10:00:00','Dibatalkan','active',999,0,999,0,0,'Tertahan',null,'api',null]);
$insert->execute(['OLD','S','2026-09-28 10:00:00','Selesai','active',200,20,180,50,0,'Sudah Dicairkan',null,'api',null]);
$insert->execute(['OTHER','X','2026-10-01 10:00:00','Selesai','active',999999,0,999999,0,0,'Sudah Dicairkan',null,'api',null]);
$insert->execute(['ARCHIVED','S','2026-10-01 10:00:00','Selesai','archived',99999,0,99999,0,0,'Sudah Dicairkan',null,'api',null]);
$pdo->exec('CREATE TABLE marketplace_expenses(store_id TEXT,expense_date TEXT,category TEXT,amount REAL,affects_profit INT,is_cashflow_only INT)');
$pdo->exec("INSERT INTO marketplace_expenses VALUES ('S','2026-10-01','Biaya Iklan/Advertising Marketplace',100,1,0),('S','2026-09-28','Operasional',20,1,0),('S','2026-10-01','Topup',9999,0,1),('X','2026-10-01','Iklan',999999,1,0)");
$_SESSION=['company_key'=>'A'];$service=new MarketplaceAnalyticsService($pdo);$filters=['date_start'=>'2026-10-01','date_end'=>'2026-10-03'];$report=$service->report($filters);
$all=$report['current']['all'];$settled=$report['current']['settled'];
analyticsCheck($all['revenue']===1500.0&&$all['net_income']===1250.0&&$all['cogs']===400.0,'source totals or scope incorrect');
analyticsCheck($all['gross_profit']===850.0&&$all['net_profit']===null&&$all['known_operating_subtotal']===750.0,'adjustment double counted');
analyticsCheck($settled['revenue']===1000.0&&$settled['known_operating_subtotal']===450.0&&$all['pending_revenue']===500.0,'completed order incorrectly counted as settled');
analyticsCheck($all['cancelled']===1&&$all['orders']===2,'cancelled order included in financial metrics');
analyticsCheck($all['ads']===100.0&&$all['roas']===15.0,'cashflow or advertising counted twice');
analyticsCheck($report['previous']['all']['known_operating_subtotal']===110.0,'previous period mismatch');
analyticsCheck(array_sum(array_map(static fn($day)=>$day['all']['known_operating_subtotal']??0,$report['current']['daily']))===$all['known_operating_subtotal'],'daily totals disagree');
analyticsCheck(array_sum(array_column(array_column($report['current']['by_store'],'all'),'known_operating_subtotal'))===$all['known_operating_subtotal'],'store totals disagree');
$released=$service->report($filters+['date_basis'=>'released']);
analyticsCheck($released['current']['all']['revenue']===1000.0&&$released['current']['daily']['2026-10-02']['all']['revenue']===1000.0,'release date used order completion date');
analyticsCheck($service->report($filters+['analytics_stores'=>['T']])['current']['all']['revenue']===500.0,'multi-store filtering failed');
try{$service->report($filters+['analytics_stores'=>['X']]);throw new RuntimeException('foreign store permitted');}catch(DomainException $expected){}
try{$service->report(['date_start'=>'2026-02-30','date_end'=>'2026-03-01']);throw new RuntimeException('invalid date permitted');}catch(InvalidArgumentException $expected){}
try{$service->report(['date_start'=>'2026-01-01','date_end'=>'2026-10-01']);throw new RuntimeException('oversized date range permitted');}catch(InvalidArgumentException $expected){}
for($i=0;$i<55;$i++)$insert->execute(['EXTRA-'.$i,'T','2026-10-03 10:00:00','Terkirim','active',1,0,1,0,0,'Tertahan',null,'api',null]);
analyticsCheck($service->report($filters)['current']['all']['revenue']===1555.0,'analytics incorrectly limited to UI page size');
function formatRp($value):string{return 'Rp '.number_format((float)$value,0,',','.');}
$analyticsReport=$report;$globalFilters=['store_id'=>'','platform'=>'','analytics_stores'=>[],'include_disconnected'=>false];$query=static fn(array $changes=[]):string=>http_build_query($changes);
ob_start();require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Views/marketplace_global_analytics.php');$html=ob_get_clean();
analyticsCheck(str_contains($html,'KPI Utama')&&str_contains($html,'Data Penjualan Harian Global')&&str_contains($html,'Rincian Biaya Global'),'sections missing');
analyticsCheck(str_contains($html,'&lt;script&gt;bad&lt;/script&gt;')&&!str_contains($html,'<script>bad</script>'),'unsafe store name rendered');
$exportReport=$report;$exportReport['current']['by_store'][0]['store']['store_name']='=HYPERLINK("https://example.test")';
$out=fopen('php://temp','w+');MarketplaceAnalyticsCsv::write($out,$exportReport);rewind($out);$csv=stream_get_contents($out);fclose($out);
analyticsCheck(str_starts_with($csv,"\xEF\xBB\xBF")&&str_contains($csv,"'=HYPERLINK")&&str_contains($csv,'1500')&&str_contains($csv,'2026-10-03'),'CSV values, encoding or formula escaping failed');
$_SESSION=['company_key'=>'EMPTY'];$empty=$service->report($filters);analyticsCheck($empty['current']['all']['roas']===null&&$empty['current']['all']['revenue']===0.0,'empty data fabricated');
$_SESSION=['company_key'=>'A'];$insert->execute(['MISSING','T','2026-10-02 10:00:00','Terkirim','active',200,0,0,100,0,'Tertahan',json_encode(['net_income'=>null,'total_cogs'=>100,'total_fees'=>null]),'api',null]);
$incomplete=$service->report($filters);analyticsCheck($incomplete['current']['all']['net_profit']===null&&$incomplete['current']['all']['net_income']===null&&$incomplete['current']['all']['fees']===null,'missing finance reported as zero/negative final profit');
analyticsCheck($incomplete['current']['all']['missing_income']===1,'missing income not counted');
analyticsCheck($incomplete['current']['settled']['known_operating_subtotal']===450.0,'missing pending income changed confirmed paid result');
$analyticsReport=$incomplete;ob_start();require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Views/marketplace_global_analytics.php');$pendingHtml=ob_get_clean();analyticsCheck(str_contains($pendingHtml,'Menunggu data keuangan/HPP'),'pending finance label absent');
echo "PASS: settled/pending separation, adjustments, expenses, complete/unknown results, all-row aggregation, daily/store reconciliation, date basis, comparison, scope, validation, empty state and safe view render\n";

// Per-store view, safe XLSX, source revenue and final evidence regressions.
require_once dirname(__DIR__,2).'/app/Modules/Marketplace/Services/MarketplaceAnalyticsXlsx.php';
$analyticsReport=$service->report($filters+['store_id'=>'S']);
ob_start();require dirname(__DIR__,2).'/app/Modules/Marketplace/Views/marketplace_store_analytics.php';$storeHtml=ob_get_clean();
analyticsCheck(str_contains($storeHtml,'Analitik &amp; Laporan')&&str_contains($storeHtml,'Keuangan Final')&&str_contains($storeHtml,'Ekspor XLSX'),'store analytics view missing');
analyticsCheck(!str_contains($storeHtml,'<script>bad</script>'),'store view XSS');
$book=MarketplaceAnalyticsXlsx::bytes($analyticsReport);
$path=sys_get_temp_dir().'/jl-analytics-'.bin2hex(random_bytes(6)).'.xlsx';file_put_contents($path,$book);
try{$zip=new PharData($path,0,null,Phar::ZIP);analyticsCheck(isset($zip['xl/worksheets/sheet5.xml']),'XLSX sheets missing');foreach(['xl/workbook.xml','xl/worksheets/sheet1.xml','xl/worksheets/sheet2.xml'] as $entry)analyticsCheck(simplexml_load_string($zip[$entry]->getContent())!==false,'invalid XLSX XML');}finally{unset($zip);unlink($path);}
$detail=['gross_amount'=>225000,'finance_revenue'=>128340,'total_fees'=>120998,'net_income'=>104002,'total_cogs'=>59000,'components_signed'=>true,'components'=>['seller_voucher'=>96660,'platform_commission'=>24338],'is_estimated'=>false,'source_status'=>'AWAITING_SHIPMENT','items'=>[['name'=>'Tas','product_id'=>'P','variant_id'=>'V','sku'=>'SKU','quantity'=>1,'returned_quantity'=>0]]];
$insert->execute(['SOURCE','S','2026-10-03','AWAITING_SHIPMENT','active',225000,0,104002,59000,0,'Tertahan',json_encode($detail),'api',null]);
$r=$service->report($filters+['store_id'=>'S']);
analyticsCheck($r['current']['settled']['revenue']===129340.0,'seller discount counted twice');
analyticsCheck($r['current']['settled']['gross_profit']===45552.0,'final finance excluded until payout or incorrect HPP');
analyticsCheck($r['current']['products'][0]['quantity']===1.0,'product units missing');
$detail['source_status']='IN_CANCEL';$detail['is_estimated']=true;
$insert->execute(['REQUEST','S','2026-10-03','IN_CANCEL','active',225000,0,104002,59000,0,'Tertahan',json_encode($detail),'api',null]);
$r=$service->report($filters+['store_id'=>'S']);analyticsCheck($r['current']['all']['cancel_requested']===1&&$r['current']['all']['cancelled']===1,'cancel request treated as cancellation');
if(in_array('--preview',$argv??[],true)){file_put_contents(dirname(__DIR__,2).'/storage/temporary/analytics-preview.html','<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>'.$storeHtml.'</body></html>');}
echo "PASS: per-store UI, real source revenue, final evidence, cancellation requests, product facts and XLSX structure\n";

