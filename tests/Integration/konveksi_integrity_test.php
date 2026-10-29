<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/Bootstrap/bootstrap.php';KonveksiModule::boot();
function kcheck(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
$source=KonveksiDatabase::config()['name'];$name='jl_konveksi_test_'.bin2hex(random_bytes(5));$admin=Database::serverPdo();$admin->exec("CREATE DATABASE `$name`");$cfg=Database::config()['db'];$p=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',$cfg['host'],$cfg['port'],$name),$cfg['user'],$cfg['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
try{
 foreach($admin->query("SHOW TABLES FROM `$source`")->fetchAll(PDO::FETCH_COLUMN) as $table){if(!preg_match('/^[a-z0-9_]+$/',$table))throw new RuntimeException('Invalid test table');$p->exec("CREATE TABLE `$table` LIKE `$source`.`$table`");} $p->exec("INSERT INTO system_table_map SELECT * FROM `$source`.system_table_map");
 $_SESSION=['user_id'=>'KONVEKSI-TEST','role'=>'OWNER','workspace'=>'konveksi','company_key'=>'LOCAL-COMPANY'];
 $s=new KonveksiCollectionStore($p);$g=new KonveksiOperationGate($p,$s,$name);$inv=new InventoryService($p,$s);$docs=new DocumentService($p);$purchase=new PurchaseService($p,$s,$inv,$docs);$sales=new SalesService($p,$s);$hpp=new HppService($p,$s);
 $s->put('bahan',[['id'=>'B','id_bahan'=>'B','nama'=>'Kain','stok'=>0]]);$s->put('produk',[['id'=>'P','kode'=>'SKU','stok'=>10,'id_stok_item'=>'STK']]);$s->put('master_stok',[['id_produk'=>'P','kode_sku'=>'SKU','stok_akhir'=>10,'id_stok_item'=>'STK']]);
 $buy=['id'=>'BUY','id_supplier'=>'SUP','no_faktur_supplier'=>'INV1','items'=>[['id_bahan'=>'B','qty'=>2,'harga_satuan'=>100,'subtotal'=>200]],'total_tagihan'=>200,'nominal_bayar'=>50,'id_rekening'=>'BANK','status_pembayaran'=>'DP'];
 $one=$g->run('savePurchaseInvoiceServerRpc',[$buy],fn()=>$purchase->saveInvoice($buy),'test-purchase-00001');$two=$g->run('savePurchaseInvoiceServerRpc',[$buy],fn()=>$purchase->saveInvoice($buy),'test-purchase-00001');
 kcheck($one==$two&&$s->get('bahan')[0]['stok']==2,'request retry duplicated stock');kcheck(count($s->get('keuangan'))===1&&count($s->get('hutangsupplier'))===1,'purchase accounting duplicated');
 $g->run('savePurchaseInvoiceServerRpc',[$buy],fn()=>$purchase->saveInvoice($buy),'test-purchase-00002');kcheck($s->get('bahan')[0]['stok']==2&&count($s->get('pembelian'))===1,'document retry with another key duplicated');
 $changed=$buy;$changed['nominal_bayar']=60;try{$g->run('savePurchaseInvoiceServerRpc',[$changed],fn()=>$purchase->saveInvoice($changed),'test-purchase-00001');throw new RuntimeException('hash conflict allowed');}catch(DomainException){}
 $old=$s->versions();$g->run('syncTab',['profil','[]'],fn()=>$s->put('profil',[['nama'=>'Baru']]),'test-versions-00001',$old);
 try{$g->run('syncTab',['profil','[]'],fn()=>$s->put('profil',[]),'test-versions-00002',$old);throw new RuntimeException('stale write allowed');}catch(DomainException $e){kcheck($e->getCode()===409,'wrong conflict code');}
 try{$s->get('marketplace_sync_queue');throw new RuntimeException('marketplace accessible');}catch(DomainException){}
 $before=$s->get('bahan');try{$g->run('failure',[],function()use($s){$s->put('bahan',[['id'=>'changed','stok'=>99]]);throw new RuntimeException('injected failure');},'test-rollback-00001');}catch(RuntimeException){}kcheck($s->get('bahan')===$before,'partial operation committed');
 $sale=['id_penjualan'=>'SALE','items'=>[['id_produk'=>'P','kode_sku'=>'SKU','qty'=>6],['id_produk'=>'P','kode_sku'=>'SKU','qty'=>6]],'nominal_dibayar'=>0];
 try{$g->run('postPenjualanProduksiServer',[$sale],fn()=>$sales->post($sale),'test-oversell-00001');throw new RuntimeException('duplicate SKU oversold');}catch(RuntimeException $e){kcheck(str_contains($e->getMessage(),'Stok tidak mencukupi'),'unexpected oversell failure');}kcheck($s->get('master_stok')[0]['stok_akhir']==10,'failed sale changed stock');
 $sale['items'][0]['qty']=3;$sale['items'][1]['qty']=2;$g->run('postPenjualanProduksiServer',[$sale],fn()=>$sales->post($sale),'test-sale-00000001');kcheck($s->get('master_stok')[0]['stok_akhir']==5,'multi-line stock wrong');
 $g->run('voidPenjualanProduksiServer',[$sale],fn()=>$sales->void($sale),'test-void-00000001');$g->run('voidPenjualanProduksiServer',[$sale],fn()=>$sales->void($sale),'test-void-00000002');kcheck($s->get('master_stok')[0]['stok_akhir']==10,'void retry duplicated stock');
 $products=$s->get('produk');$products[0]['hpp_produk']=150;$s->put('produk',$products);
 $money=['id_penjualan'=>'MONEY','items'=>[['id_produk'=>'P','kode_sku'=>'SKU','qty'=>1,'harga_jual'=>100,'hpp_satuan'=>1]],'total_tagihan'=>999999,'total_hpp'=>1,'laba_kotor'=>999999,'nominal_dibayar'=>120,'id_rekening'=>'BANK'];
 $calculated=$g->run('postPenjualanProduksiServer',[$money],fn()=>$sales->post($money),'test-money-0000001')['data'];
 kcheck($calculated['total_tagihan']==100&&$calculated['total_hpp']==150&&$calculated['laba_kotor']==-50,'server financial calculation trusted client totals');
 kcheck($calculated['nominal_dibayar']==100&&$calculated['kembalian']==20,'cash receipt includes change');
 $_SESSION['company_key']='FOREIGN';try{$g->run('test',[],fn()=>['status'=>'success'],'test-foreign-00001');throw new RuntimeException('foreign company allowed');}catch(DomainException){}$_SESSION['company_key']='LOCAL-COMPANY';
 echo "PASS: purchase retry, key conflicts, document dedup, stale writes, cross-domain access, rollback, multi-line SKU, void idempotence and company isolation\n";
}finally{$p=null;$admin->exec("DROP DATABASE `$name`");}


