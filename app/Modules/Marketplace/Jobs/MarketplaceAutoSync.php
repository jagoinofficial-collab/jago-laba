<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 4) . '/app/Modules/Marketplace/Services/MarketplaceSyncService.php');
final class MarketplaceAutoSync
{
 public const MODES=['status'=>300,'new'=>60,'unpaid'=>60,'finance'=>300,'returns'=>900];
 public function __construct(private ?PDO $pdo=null,private $factory=null){$this->pdo??=Database::pdo();}
 public function states(string $store):array
 {
  $scope=new MarketplaceScopeService($this->pdo);$scope->assertStoreAccess($store);
  $q=$this->pdo->prepare('SELECT mode,enabled,next_run_at,last_success_at,last_error_code FROM marketplace_auto_sync WHERE store_id=? AND company_key=?');$q->execute([$store,$scope->companyKey()]);$out=[];foreach(self::MODES as $mode=>$interval)$out[$mode]=['mode'=>$mode,'enabled'=>false,'next_run_at'=>null,'last_success_at'=>null,'last_error_code'=>null];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$row['enabled']=(bool)$row['enabled'];$out[$row['mode']]=$row;}return $out;
 }
 public function available():bool{return true;}
 public function browserPulse(int $seconds=5):void
 {
  if(empty($_SESSION['user_id'])||!Workspace::can(Workspace::MARKETPLACE))throw new DomainException('Akses marketplace diperlukan.');
  $company=(new MarketplaceScopeService($this->pdo))->companyKey();
  $this->tick(max(1,min(5,$seconds)),$company);
 }
 public function set(string $store,string $mode,bool $enabled):array
 {
  if(!preg_match('/OWNER|ADMIN/i',(string)($_SESSION['role']??'')))throw new DomainException('Hanya administrator dapat mengubah auto sync.');
  if(!isset(self::MODES[$mode]))throw new InvalidArgumentException('Pengaturan tidak dikenal.');
  $scope=new MarketplaceScopeService($this->pdo);$scope->assertStoreAccess($store);$company=$scope->companyKey();
  $q=$this->pdo->prepare("SELECT c.id FROM marketplace_oauth_connections c JOIN marketplace_stores s ON s.id=c.store_id AND s.company_key=c.company_key WHERE c.store_id=? AND c.company_key=? AND c.status='verified' AND s.archived_at IS NULL AND c.platform IN ('TikTok Shop','Shopee')");$q->execute([$store,$company]);
  if($enabled&&!$q->fetchColumn())throw new DomainException('Otorisasi toko diperlukan sebelum mengaktifkan auto sync.');
  if($enabled&&!$this->available())throw new DomainException('Penjadwal belum berjalan. Periksa layanan auto sync di server.');
  $this->pdo->prepare('INSERT INTO marketplace_auto_sync(store_id,company_key,mode,enabled,next_run_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),next_run_at=NOW(),last_error_code=NULL,updated_at=NOW()')->execute([$store,$company,$mode,$enabled?1:0]);
  return $this->states($store);
 }
 /** Browser requests must supply their authenticated company scope. */
 public function tick(int $seconds=45,?string $company=null):void
 {
  if(PHP_SAPI!=='cli'&&$company===null)throw new DomainException('Lingkup perusahaan diperlukan.');
  $originalSession=$_SESSION??[];
  $lockName='jl-auto-sync-'.sha1((string)$this->pdo->query('SELECT DATABASE()')->fetchColumn());$lock=$this->pdo->prepare('SELECT GET_LOCK(?,0)');$lock->execute([$lockName]);if((int)$lock->fetchColumn()!==1)return;
  try{
   $this->pdo->exec('INSERT INTO marketplace_auto_sync_runtime(id,heartbeat_at) VALUES (1,NOW()) ON DUPLICATE KEY UPDATE heartbeat_at=NOW()');
   $deadline=time()+$seconds;
   $query=$this->pdo->prepare("SELECT a.*,c.platform,c.status AS connection_status FROM marketplace_auto_sync a JOIN marketplace_stores s ON s.id=a.store_id AND s.company_key=a.company_key JOIN marketplace_oauth_connections c ON c.store_id=a.store_id AND c.company_key=a.company_key WHERE a.enabled=1 AND s.archived_at IS NULL AND (a.next_run_at IS NULL OR a.next_run_at<=NOW())".($company!==null?' AND a.company_key=?':'')." ORDER BY a.next_run_at,a.mode");$query->execute($company!==null?[$company]:[]);$rows=$query->fetchAll(PDO::FETCH_ASSOC);
   foreach($rows as $row){if(time()>=$deadline)break;
    $_SESSION['company_key']=$row['company_key'];$_SESSION['user_id']='AUTO-SYNC-WORKER';$_SESSION['role']='OWNER';
    $store=$row['store_id'];$mode=$row['mode'];$success=false;$code=null;
    try{
     if($row['connection_status']!=='verified')throw new MarketplaceIntegrationException('Otorisasi ulang diperlukan.','REAUTHORIZE_REQUIRED');
     $sync=new MarketplaceSyncService($this->pdo,$this->factory,true);
     $active=$this->pdo->prepare("SELECT run_key FROM marketplace_sync_jobs WHERE store_id=? AND company_key=? AND run_key LIKE ? AND status IN ('queued','running') ORDER BY id LIMIT 1");$active->execute([$store,$row['company_key'],'auto-'.$mode.'-%']);$run=$active->fetchColumn();
     if(!$run){$window=MarketplaceSyncWindow::forStore($this->pdo,$store);$from=max($window['window_start'],($row['last_success_at']?strtotime($row['last_success_at'])-300:0));$filter=$mode==='status'?['update_time_ge'=>$from]:['create_time_ge'=>$mode==='unpaid'?$window['window_start']:$from];if($mode==='unpaid')$filter['order_status']='UNPAID';$states=$this->states($store);$run=$mode==='returns'?$sync->enqueueReturns($store):($mode==='finance'?$sync->enqueueFinance($store):$sync->enqueueOrders($store,$mode,$filter,$mode!=='unpaid'&&!$states['unpaid']['enabled']));}
     while(time()<$deadline){
      $on=$this->pdo->prepare('SELECT enabled FROM marketplace_auto_sync WHERE store_id=? AND company_key=? AND mode=?');$on->execute([$store,$row['company_key'],$mode]);if(!(bool)$on->fetchColumn()){ $this->pdo->prepare("UPDATE marketplace_sync_jobs SET status='failed',last_error_code='AUTO_DISABLED',finished_at=NOW() WHERE store_id=? AND run_key=? AND status='queued'")->execute([$store,$run]);break; }
      $sync->step($store,$run);$q=$this->pdo->prepare('SELECT status,last_error_code FROM marketplace_sync_jobs WHERE store_id=? AND run_key=?');$q->execute([$store,$run]);$job=$q->fetch(PDO::FETCH_ASSOC);
      if($job['status']==='succeeded'){$success=true;break;}if($job['status']==='failed'){$code=$job['last_error_code'];break;}usleep(100000);
     }
    }catch(Throwable $e){$code=$e instanceof MarketplaceIntegrationException?$e->errorCode:'AUTO_SYNC_FAILED';}
    $delay=$success?self::MODES[$mode]:($code?300:1);$code=$code&&preg_match('/^[A-Z0-9_.-]{1,100}$/',$code)?$code:($code?'AUTO_SYNC_FAILED':null);
    $this->pdo->prepare('UPDATE marketplace_auto_sync SET next_run_at=DATE_ADD(NOW(),INTERVAL ? SECOND),last_success_at=IF(?=1,NOW(),last_success_at),last_error_code=? WHERE store_id=? AND company_key=? AND mode=?')->execute([$delay,$success?1:0,$code,$store,$row['company_key'],$mode]);
   }
  }finally{$_SESSION=$originalSession;$release=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lockName]);}
 }
}
