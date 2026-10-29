<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Bootstrap/bootstrap.php';KonveksiModule::boot();
if(PHP_SAPI!=='cli'||!in_array('--execute-operational-reset',$argv,true))exit("CLI only. Explicit --execute-operational-reset required.\n");
$cfg=KonveksiDatabase::config();if(empty($cfg['isolated']))throw new RuntimeException('Database Konveksi terpisah wajib aktif.');
$active=$cfg['name'];$legacy=Database::config()['db']['name'];$schemas=array_unique([$active,$legacy]);
foreach($schemas as $schema)if(!preg_match('/^[a-zA-Z0-9_]+$/',$schema))throw new RuntimeException('Invalid schema.');
$operations=['pembelian','pembelian_item','potong','potong_item','jahit','jahit_item','keuangan','kasbon','distribusi','mutasi_stok','pembelian_vendor','riwayat_bayar_hutang','hutang_konveksi','riwayat_bayar_hutang_konveksi','penjualan','penjualan_item','penjualan_koreksi','quality_control','mutasi_stok_konveksi','hutang_supplier'];
$balanceFields=['rekening'=>['saldo_awal'],'produk'=>['stok'],'bahan'=>['stok','stok_awal'],'master_stok'=>['stok','stok_awal','stok_akhir','total_valuasi_stok','masuk_jahit','masuk_vendor','keluar_penjualan']];
$p=Database::serverPdo();$locks=[];$report=['time'=>date(DATE_ATOM),'scope'=>'konveksi_operational_only','schemas'=>[]];
try{
 foreach($schemas as $schema){$lock='jl-konveksi-write-'.sha1($schema);$q=$p->prepare('SELECT GET_LOCK(?,30)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Transaksi masih berjalan.');$locks[]=$lock;}
 $protected=[];foreach($p->query("SHOW TABLES FROM `$legacy`")->fetchAll(PDO::FETCH_COLUMN) as $t)if(str_starts_with($t,'marketplace_')||in_array($t,['system_users','auth_users_v2','workspace_user_access'],true))$protected[$t]=(int)$p->query("SELECT COUNT(*) FROM `$legacy`.`$t`")->fetchColumn();
 $p->beginTransaction();
 foreach($schemas as $schema){$p->exec("USE `$schema`");$s=new KonveksiCollectionStore($p);$maps=$p->query('SELECT api_key,table_name FROM system_table_map')->fetchAll(PDO::FETCH_KEY_PAIR);$counts=[];
  foreach($operations as $key){if(!isset($maps[$key]))continue;$rows=$s->get($key,true);$counts[$key]=count($rows);$s->put($key,[]);}
  foreach($balanceFields as $key=>$fields){if(!isset($maps[$key]))continue;$rows=$s->get($key,true);foreach($rows as &$r){foreach($fields as $f)if(array_key_exists($f,$r))$r[$f]=0;if($key==='master_stok')$r['status_stok']='Habis';}unset($r);$s->put($key,$rows);}
  $report['schemas'][$schema]=['removed_rows'=>$counts,'master_records_preserved'=>true];
 }
 $p->exec("USE `$active`");$epoch=bin2hex(random_bytes(16));$p->prepare("INSERT INTO system_settings(setting_key,setting_value) VALUES ('KONVEKSI_RESET_EPOCH',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$epoch]);
 $p->exec('DELETE FROM konveksi_request_receipts');$p->exec('DELETE FROM idempotency_keys');
 foreach($schemas as $schema){$p->exec("USE `$schema`");$s=new KonveksiCollectionStore($p);$maps=$p->query('SELECT api_key,table_name FROM system_table_map')->fetchAll(PDO::FETCH_KEY_PAIR);foreach($operations as $key)if(isset($maps[$key])&&count($s->get($key,true))!==0)throw new RuntimeException('Verifikasi pengosongan gagal.');}
 foreach($protected as $t=>$n)if((int)$p->query("SELECT COUNT(*) FROM `$legacy`.`$t`")->fetchColumn()!==$n)throw new RuntimeException('Data terlindungi berubah.');
 $p->commit();$report['verified']=true;$report['protected_tables_unchanged']=count($protected);$report['backup_created']=false;
 file_put_contents(dirname(__DIR__).'/storage/temporary/konveksi-reset-result.json',json_encode($report,JSON_PRETTY_PRINT));echo json_encode($report,JSON_PRETTY_PRINT)."\n";
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}finally{foreach($locks as $lock)$p->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
