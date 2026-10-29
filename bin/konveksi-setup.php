<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Bootstrap/bootstrap.php';KonveksiModule::boot();
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);$activeFile=$root.'/storage/private/konveksi/database.php';
if(is_file($activeFile)){echo json_encode(['status'=>'already_configured','database'=>KonveksiDatabase::config()['name']]).PHP_EOL;exit;}
if(!in_array('--prepare',$argv,true)){echo "Usage: php bin/konveksi-setup.php --prepare [--activate]\n";exit(2);}
$source=Database::config()['db'];$sourceName=$source['name'];$target='jago_laba_konveksi';
if(!preg_match('/^[a-zA-Z0-9_]+$/',$sourceName)||$target===$sourceName)throw new RuntimeException('Nama database sumber/tujuan tidak valid.');
$p=Database::pdo();$lock='jl-konveksi-write-'.sha1($sourceName);$q=$p->prepare('SELECT GET_LOCK(?,30)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Tunggu transaksi berjalan sebelum migrasi.');
try{
 $server=Database::serverPdo();$exists=$server->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=?');$exists->execute([$target]);if((int)$exists->fetchColumn()>0)throw new RuntimeException('Database tujuan sudah berisi tabel. Audit staging diperlukan sebelum melanjutkan; data tidak ditimpa.');
 $server->exec("CREATE DATABASE IF NOT EXISTS `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
 $cfg=$source;$cfg['name']=$target;$admin=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',$cfg['host'],$cfg['port'],$target),$cfg['user'],$cfg['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 foreach(['system_table_map','system_audit_logs','document_files','backup_log','idempotency_keys','system_settings'] as $table)$admin->exec("CREATE TABLE `$table` LIKE `$sourceName`.`$table`");
 $maps=$p->query('SELECT * FROM system_table_map')->fetchAll();$allowed=array_flip(KonveksiCollectionStore::COLLECTIONS);$report=[];
 foreach($maps as $map){if(!isset($allowed[$map['api_key']]))continue;$table=$map['table_name'];if(!preg_match('/^jl_data_[a-z0-9_]+$/',$table))throw new RuntimeException('Pemetaan tabel tidak aman.');$admin->exec("CREATE TABLE `$table` LIKE `$sourceName`.`$table`");}
 foreach(KonveksiCollectionStore::COLLECTIONS as $key){$table=SnapshotImporter::tableName($key);$admin->exec("CREATE TABLE IF NOT EXISTS `$table` (_row_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,_record_key VARCHAR(190),_row_order INT NOT NULL DEFAULT 0,_data_json LONGTEXT NOT NULL,_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(_record_key),INDEX(_row_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}
 KonveksiOperationGate::install($admin);
 $admin->beginTransaction();$p->beginTransaction();
 foreach($maps as $map){if(!isset($allowed[$map['api_key']]))continue;$table=$map['table_name'];$admin->exec("INSERT INTO `$table` SELECT * FROM `$sourceName`.`$table`");$columns=array_keys($map);$admin->prepare('INSERT INTO system_table_map (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')')->execute(array_values($map));
  $a=$p->query("SELECT _data_json FROM `$table` ORDER BY _row_order,_row_id")->fetchAll(PDO::FETCH_COLUMN);$b=$admin->query("SELECT _data_json FROM `$table` ORDER BY _row_order,_row_id")->fetchAll(PDO::FETCH_COLUMN);if($a!==$b)throw new RuntimeException('Rekonsiliasi tabel gagal.');$report[]=['collection'=>$map['api_key'],'rows'=>count($a),'exact_match'=>true];
 }
 foreach(KonveksiCollectionStore::COLLECTIONS as $key){$table=SnapshotImporter::tableName($key);$admin->prepare('INSERT IGNORE INTO system_table_map(sheet_name,api_key,table_name,headers_json,source_row_count,imported_row_count,checksum) VALUES (?,?,?, ?,0,0,?)')->execute([$key,$key,$table,'[]',hash('sha256','[]')]);}
 // Preserve only Konveksi document/history/settings tables, never marketplace tokens or jobs.
 foreach(['document_files','backup_log','idempotency_keys'] as $table)$admin->exec("INSERT INTO `$table` SELECT * FROM `$sourceName`.`$table`");
 $admin->exec("INSERT INTO system_settings SELECT * FROM `$sourceName`.system_settings WHERE setting_key IN ('GEMINI_API_KEY','GEMINI_MODEL')");
 $p->commit();$admin->commit();
 $username='jl_konv_'.bin2hex(random_bytes(4));$password=bin2hex(random_bytes(32));$u=$server->quote($username);$secret=$server->quote($password);
 $server->exec("CREATE USER $u@'127.0.0.1' IDENTIFIED BY $secret");$server->exec("CREATE USER $u@'localhost' IDENTIFIED BY $secret");
 foreach(['127.0.0.1','localhost'] as $host)$server->exec("GRANT SELECT,INSERT,UPDATE,DELETE ON `$target`.* TO $u@".$server->quote($host));
 $cfg['user']=$username;$cfg['pass']=$password;$cfg['isolated']=true;$cfg['company_key']='LOCAL-COMPANY';$cfg['activated_at']=date(DATE_ATOM);
 // Verify the app user can read target and cannot read a marketplace table in the old database.
 $test=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',$cfg['host'],$cfg['port'],$target),$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$test->query('SELECT COUNT(*) FROM system_table_map')->fetchColumn();$denied=false;try{$test->query("SELECT id FROM `$sourceName`.marketplace_stores LIMIT 1");}catch(PDOException){$denied=true;}if(!$denied)throw new RuntimeException('Isolasi kredensial database gagal.');
 $dir=dirname($activeFile);if(!is_dir($dir))mkdir($dir,0700,true);
 if(in_array('--activate',$argv,true)){$tmp=$activeFile.'.tmp';file_put_contents($tmp,"<?php\nreturn ".var_export($cfg,true).";\n",LOCK_EX);if(!rename($tmp,$activeFile))throw new RuntimeException('Aktivasi konfigurasi gagal.');}
 file_put_contents($root.'/storage/temporary/konveksi-migration-result.json',json_encode(['status'=>'verified','activated'=>in_array('--activate',$argv,true),'source'=>$sourceName,'target'=>$target,'credential_isolation'=>true,'collections'=>$report],JSON_PRETTY_PRINT));
 echo json_encode(['status'=>'verified','activated'=>in_array('--activate',$argv,true),'collections'=>count($report),'rows'=>array_sum(array_column($report,'rows')),'credential_isolation'=>true]).PHP_EOL;
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();if(isset($admin)&&$admin->inTransaction())$admin->rollBack();throw $e;}finally{$p->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
