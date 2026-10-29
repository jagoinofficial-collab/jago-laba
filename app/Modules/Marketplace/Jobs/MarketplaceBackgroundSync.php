<?php
declare(strict_types=1);
final class MarketplaceBackgroundSync
{
    private PDO $pdo;
    public function __construct(?PDO $pdo=null){$this->pdo=$pdo?:Database::pdo();}
    public function start(string $storeId,bool $catalog=false,?string $runKey=null):array
    {
        $sync=new MarketplaceSyncService($this->pdo,null,$catalog,$catalog,true);if($runKey===null){if($catalog)$sync->enqueueCatalog($storeId);else $sync->enqueueAll($storeId);}else{$sync->status($storeId);$job=$this->pdo->prepare("SELECT id FROM marketplace_sync_jobs WHERE store_id=? AND company_key=? AND run_key=? AND status='queued' LIMIT 1");$job->execute([$storeId,(new MarketplaceScopeService($this->pdo))->companyKey(),$runKey]);if(!$job->fetchColumn())throw new DomainException('Antrean percobaan ulang tidak ditemukan.');}
        $company=(new MarketplaceScopeService($this->pdo))->companyKey();
        $this->pdo->beginTransaction();
        try{
            $storeLock=$this->pdo->prepare('SELECT id FROM marketplace_stores WHERE id=? AND company_key=? AND archived_at IS NULL FOR UPDATE');$storeLock->execute([$storeId,$company]);
            if(!$storeLock->fetchColumn()||MarketplaceStoreDeletion::blocked($this->pdo,$storeId))throw new DomainException('Toko tidak tersedia untuk sinkronisasi.');
            $this->pdo->prepare("INSERT IGNORE INTO marketplace_sync_workers(store_id,company_key,launch_key,status,heartbeat_at) VALUES (?,?,'','idle',NOW())")->execute([$storeId,$company]);
            $stmt=$this->pdo->prepare('SELECT * FROM marketplace_sync_workers WHERE store_id=? AND company_key=? FOR UPDATE');$stmt->execute([$storeId,$company]);$worker=$stmt->fetch(PDO::FETCH_ASSOC);
            if(in_array($worker['status'],['starting','running'],true)&&strtotime($worker['heartbeat_at'])>time()-90&&(!$catalog||str_starts_with($worker['launch_key'],'catalog-'))){if($runKey!==null)throw new DomainException('Worker toko masih berjalan. Antrean percobaan ulang tersimpan; lanjutkan setelah proses ini selesai.');$this->pdo->commit();return $this->status($storeId,$catalog);}
            if(PHP_OS_FAMILY!=='Windows'||!function_exists('proc_open'))throw new RuntimeException('Background launcher tidak tersedia.');
            $php=getenv('JL_PHP_CLI')?:'D:/xampp/php/php.exe';
            if(!is_file($php))throw new RuntimeException('PHP CLI tidak tersedia.');
            $root=(dirname(__DIR__, 4));$script=$root.'/bin/workers/marketplace-background-worker.php';$key=($catalog?'catalog-':'').bin2hex(random_bytes(16));
            $logs=$root.'/storage/sync-worker';if(!is_dir($logs)&&!mkdir($logs,0700,true)&&!is_dir($logs))throw new RuntimeException('Folder log tidak tersedia.');
            if((new MarketplaceStoreDeletion($this->pdo))->ready())foreach(['out.log','err.log','launch.log','launch.err.log'] as $suffix)$this->pdo->prepare('INSERT IGNORE INTO marketplace_store_files(store_id,relative_path) VALUES (?,?)')->execute([$storeId,'sync-worker/'.$key.'.'.$suffix]);
            $psQuote=static fn(string $s):string=>"'".str_replace("'","''",$s)."'";
            $arguments=['-d','session.save_path='.$root.'/storage',$script,'--company='.$company,'--store='.$storeId,'--launch='.$key];
            if($catalog)$arguments[]='--catalog';
            if($runKey!==null)$arguments[]='--run='.$runKey;
            $command='$p = Start-Process -FilePath '.$psQuote($php).' -ArgumentList @('.implode(',',array_map($psQuote,$arguments)).') -WorkingDirectory '.$psQuote($root).' -WindowStyle Hidden -RedirectStandardOutput '.$psQuote($logs.'/'.$key.'.out.log').' -RedirectStandardError '.$psQuote($logs.'/'.$key.'.err.log').' -PassThru; Write-Output $p.Id';
            // Encode PowerShell code, including all quoted arguments; no shell interpolation of user values.
            $encoded=base64_encode(mb_convert_encoding($command,'UTF-16LE','UTF-8'));
            $this->pdo->prepare("UPDATE marketplace_sync_workers SET launch_key=?,status='starting',pid=NULL,heartbeat_at=NOW(),started_at=NOW(),finished_at=NULL,error_code=NULL WHERE store_id=? AND company_key=?")->execute([$key,$storeId,$company]);
            $launchOutput=$logs.'/'.$key.'.launch.log';
            $process=proc_open(['powershell.exe','-NoProfile','-NonInteractive','-WindowStyle','Hidden','-EncodedCommand',$encoded],[0=>['file','NUL','r'],1=>['file',$launchOutput,'w'],2=>['file',$logs.'/'.$key.'.launch.err.log','w']],$pipes,$root);
            if(!is_resource($process))throw new RuntimeException('Tidak dapat menjalankan worker.');
            // Files avoid waiting for EOF on a pipe inherited by the detached child.
            $exit=proc_close($process);$output=trim((string)file_get_contents($launchOutput));
            if($exit!==0||!ctype_digit($output))throw new RuntimeException('Worker gagal dimulai.');
            $this->pdo->prepare('UPDATE marketplace_sync_workers SET pid=? WHERE store_id=? AND company_key=? AND launch_key=?')->execute([(int)$output,$storeId,$company,$key]);
            $this->pdo->commit();return $this->status($storeId,$catalog);
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function status(string $id,bool $catalog=false):array
    {
        $state=(new MarketplaceSyncService($this->pdo,null,$catalog,$catalog,true))->status($id);$company=(new MarketplaceScopeService($this->pdo))->companyKey();
        $stmt=$this->pdo->prepare('SELECT status,heartbeat_at,started_at,finished_at,error_code FROM marketplace_sync_workers WHERE store_id=? AND company_key=?');$stmt->execute([$id,$company]);$state['worker']=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
        if($state['worker']&&in_array($state['worker']['status'],['starting','running'],true)&&strtotime($state['worker']['heartbeat_at'])<time()-90)$state['worker']['status']='stalled';
        $pending=count(array_filter($state['jobs'],static fn($j)=>in_array($j['status'],['queued','running'],true)));
        $failed=count(array_filter($state['jobs'],static fn($j)=>$j['status']==='failed'));
        $state['pending']=$pending;$state['failed']=$failed;
        $state['estimate_seconds']=null; // Total work is unknown; do not invent an ETA.
        $state['can_close']=$state['worker']&&in_array($state['worker']['status'],['starting','running'],true);
        return $state;
    }
}
