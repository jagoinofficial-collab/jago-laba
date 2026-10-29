<?php
declare(strict_types=1);
final class KonveksiOperationGate
{
 public function __construct(private PDO $pdo,private KonveksiCollectionStore $store,private ?string $expectedDatabase=null){}
 public static function install(PDO $p):void{$p->exec("CREATE TABLE IF NOT EXISTS konveksi_request_receipts (request_key VARCHAR(190) PRIMARY KEY,company_key VARCHAR(120) NOT NULL,request_hash CHAR(64) NOT NULL,response_json LONGTEXT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}
 public function run(string $method,array $args,callable $operation,?string $key=null,?array $versions=null):array{
  KonveksiDatabase::assertCompany();
  $db=(string)$this->pdo->query('SELECT DATABASE()')->fetchColumn();$lock='jl-konveksi-write-'.sha1($db);$q=$this->pdo->prepare('SELECT GET_LOCK(?,10)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Transaksi lain sedang diproses. Coba kembali.');
  try{
   if($db!==($this->expectedDatabase??KonveksiDatabase::config()['name']))throw new DomainException('Database Konveksi baru diaktifkan. Muat ulang halaman sebelum menyimpan.');
   $epoch=$this->store->resetEpoch();if($epoch!=='0'&&!hash_equals($epoch,(string)($_SERVER['HTTP_X_JL_RESET_EPOCH']??'')))throw new DomainException('Data operasional telah dikosongkan. Muat ulang halaman sebelum menyimpan.',409);
   $company=(string)(KonveksiDatabase::config()['company_key']??'LOCAL-COMPANY');$key??=(string)($_SERVER['HTTP_X_IDEMPOTENCY_KEY']??'');
   if(!preg_match('/^[A-Za-z0-9_.:-]{16,120}$/',$key))throw new InvalidArgumentException('Identitas permintaan belum tersedia. Muat ulang halaman.');
   $requestKey=hash('sha256',$company.'|'.(string)($_SESSION['user_id']??'').'|'.$key);$hash=hash('sha256',json_encode([$method,$args],JSON_THROW_ON_ERROR));
   $this->pdo->beginTransaction();$find=$this->pdo->prepare('SELECT request_hash,response_json FROM konveksi_request_receipts WHERE request_key=?');$find->execute([$requestKey]);$old=$find->fetch();
   if($old){if(!hash_equals($old['request_hash'],$hash))throw new DomainException('Identitas permintaan sudah dipakai untuk isi berbeda.');$this->pdo->commit();return json_decode($old['response_json'],true,512,JSON_THROW_ON_ERROR);}
   if(in_array($method,['syncTab','syncMultiTabs'],true)){
    $versions??=json_decode((string)($_SERVER['HTTP_X_JL_COLLECTION_VERSIONS']??'{}'),true,512,JSON_THROW_ON_ERROR);$current=$this->store->versions();
    $names=$method==='syncTab'?[(string)($args[0]??'')]:array_keys(is_array($args[0]??null)?$args[0]:(json_decode((string)($args[0]??'{}'),true)??[]));
    foreach($names as $name){$name=KonveksiCollectionStore::key($name);if(!isset($versions[$name])||!hash_equals($current[$name],(string)$versions[$name]))throw new DomainException('Data '.$name.' telah berubah atau belum dimuat. Muat ulang data sebelum menyimpan.',409);}
   }
   $result=$operation();$result['__meta']=['versions'=>$this->store->versions(),'domain'=>'konveksi'];
   $this->pdo->prepare('INSERT INTO konveksi_request_receipts(request_key,company_key,request_hash,response_json) VALUES (?,?,?,?)')->execute([$requestKey,$company,$hash,json_encode($result,JSON_THROW_ON_ERROR)]);$this->pdo->commit();return $result;
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}finally{$r=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$r->execute([$lock]);}
 }
}
