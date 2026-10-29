<?php
declare(strict_types=1);

/** Local-only store erasure. This service never calls a marketplace API. */
final class MarketplaceStoreDeletion
{
    public const OWNED = ['marketplace_ad_spends','marketplace_api_credentials','marketplace_auto_sync','marketplace_catalog_variants','marketplace_expenses','marketplace_fulfillment_actions','marketplace_hpp_changes','marketplace_hpp_history','marketplace_journal_entries','marketplace_payout_reconciliations','marketplace_price_actions','marketplace_product_ads','marketplace_return_claims','marketplace_shipping_documents','marketplace_shipping_document_audit','marketplace_sku_mappings','marketplace_stock_minimums','marketplace_stock_minimum_audit','marketplace_stock_snapshots','marketplace_sync_checkpoints','marketplace_sync_object_checks','marketplace_sync_jobs','marketplace_sync_records','marketplace_sync_workers','marketplace_withdrawals','marketplace_withdrawal_audit'];
    public function __construct(private PDO $pdo, private ?string $ledgerDirectory=null) {}

    public static function install(PDO $p): void
    {
        $p->exec("CREATE TABLE IF NOT EXISTS marketplace_store_deletions (
            store_id VARCHAR(191) PRIMARY KEY, company_key VARCHAR(191) NOT NULL,
            platform VARCHAR(40) NOT NULL, identity_hash CHAR(64) NOT NULL,
            actor VARCHAR(191) NOT NULL, requested_at DATETIME(6) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending', counts_json LONGTEXT NOT NULL,
            files_json LONGTEXT NOT NULL, error_code VARCHAR(60) NULL,
            updated_at DATETIME(6) NOT NULL, completed_at DATETIME(6) NULL,
            INDEX identity_lookup(company_key,identity_hash)
        ) ENGINE=InnoDB");
        $p->exec("CREATE TABLE IF NOT EXISTS marketplace_store_files (
            store_id VARCHAR(191) NOT NULL, relative_path VARCHAR(240) NOT NULL,
            PRIMARY KEY(store_id,relative_path)
        ) ENGINE=InnoDB");
        $tables=array_merge(self::OWNED,['jl_data_marketplace_sync_queue','jl_data_marketplace_product_mapping','marketplace_orders','marketplace_oauth_connections','marketplace_store_files']);
        foreach($tables as $table) {
            if(!self::exists($p,$table))continue;
            self::guard($p,$table,"NEW.store_id");
        }
        self::guard($p,'marketplace_stores','NEW.id');
        foreach(['marketplace_api_capabilities','marketplace_integration_bindings'] as $table) {
            if(self::exists($p,$table))self::guard($p,$table,"(SELECT store_id FROM marketplace_oauth_connections WHERE id=NEW.connection_id)","NOT EXISTS(SELECT 1 FROM marketplace_oauth_connections WHERE id=NEW.connection_id)");
        }
        self::guard($p,'marketplace_order_items',"(SELECT store_id FROM marketplace_orders WHERE id=NEW.order_id)","NOT EXISTS(SELECT 1 FROM marketplace_orders WHERE id=NEW.order_id)");
    }

    private static function guard(PDO $p,string $table,string $id,string $missing='FALSE'):void
    {
        foreach(['INSERT','UPDATE'] as $event){
            $name='jl_erase_'.substr(hash('sha256',$table.$event),0,24);
            $q=$p->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=?');$q->execute([$name]);
            if($q->fetchColumn())continue;
            $p->exec("CREATE TRIGGER `$name` BEFORE $event ON `$table` FOR EACH ROW BEGIN
                IF $missing OR EXISTS(SELECT 1 FROM marketplace_store_deletions WHERE store_id=$id) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='STORE_PERMANENTLY_DELETING';
                END IF; END");
        }
    }
    private static function exists(PDO $p,string $table):bool
    {
        $q=$p->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);return (bool)$q->fetchColumn();
    }
    private function administrator():string
    {
        $role=strtoupper((string)($_SESSION['role']??''));
        if(empty($_SESSION['user_id'])||!preg_match('/(?:^|[ _-])(OWNER|ADMIN|ADMINISTRATOR)(?:$|[ _-])/',$role))throw new DomainException('Hanya owner atau administrator dapat menghapus toko.');
        return (new MarketplaceScopeService($this->pdo))->companyKey();
    }
    public function ready():bool {return self::exists($this->pdo,'marketplace_store_deletions');}
    public static function blocked(PDO $p,string $id):bool
    {
        if(!self::exists($p,'marketplace_store_deletions'))return false;
        $q=$p->prepare('SELECT 1 FROM marketplace_store_deletions WHERE store_id=?');$q->execute([$id]);return (bool)$q->fetchColumn();
    }
    public function status(string $id):?array
    {
        $company=$this->administrator();
        $q=$this->pdo->prepare('SELECT store_id,status,counts_json,error_code,requested_at,completed_at FROM marketplace_store_deletions WHERE store_id=? AND company_key=?');$q->execute([$id,$company]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r)return null;$r['counts']=json_decode($r['counts_json'],true);unset($r['counts_json']);return $r;
    }
    public function recentStatuses():array
    {
        $company=$this->administrator();
        $q=$this->pdo->prepare('SELECT store_id,platform,status,counts_json,error_code,requested_at,completed_at FROM marketplace_store_deletions WHERE company_key=? ORDER BY requested_at DESC LIMIT 20');$q->execute([$company]);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$row){$row['counts']=json_decode($row['counts_json'],true)?:[];unset($row['counts_json']);}unset($row);
        return $rows;
    }
    private function store(string $id,string $company,bool $lock=false):array
    {
        $q=$this->pdo->prepare('SELECT * FROM marketplace_stores WHERE id=? AND company_key=?'.($lock?' FOR UPDATE':''));$q->execute([$id,$company]);$s=$q->fetch(PDO::FETCH_ASSOC);
        if(!$s)throw new DomainException('Toko milik perusahaan ini tidak ditemukan.');return $s;
    }
    private function plan():array
    {
        $plan=['marketplace_order_items'=>['order_id IN (SELECT id FROM marketplace_orders WHERE store_id=?)'],
            'marketplace_api_capabilities'=>['connection_id IN (SELECT id FROM marketplace_oauth_connections WHERE store_id=?)'],
            'marketplace_integration_bindings'=>['connection_id IN (SELECT id FROM marketplace_oauth_connections WHERE store_id=?)']];
        foreach(self::OWNED as $t)$plan[$t]=['store_id=?'];
        // Delete legacy store links, preserving referenced Konveksi stock and products.
        foreach(['jl_data_marketplace_sync_queue','jl_data_marketplace_product_mapping'] as $t)$plan[$t]=['store_id=?'];
        $plan['marketplace_orders']=['store_id=?'];$plan['marketplace_oauth_connections']=['store_id=?'];$plan['marketplace_store_files']=['store_id=?'];
        $allowed=array_merge(array_keys($plan),['marketplace_stores','marketplace_store_deletions']);
        $rows=$this->pdo->query("SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='store_id'")->fetchAll(PDO::FETCH_COLUMN);
        foreach($rows as $t)if(!in_array($t,$allowed,true))throw new DomainException('Relasi toko baru perlu diaudit sebelum penghapusan: '.$t);
        $refs=$this->pdo->query("SELECT TABLE_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach($refs as $ref)if(in_array($ref['REFERENCED_TABLE_NAME'],$allowed,true)&&!in_array($ref['TABLE_NAME'],$allowed,true))throw new DomainException('Relasi turunan perlu diaudit: '.$ref['TABLE_NAME']);
        return array_filter($plan,fn($v,$t)=>self::exists($this->pdo,$t),ARRAY_FILTER_USE_BOTH);
    }
    private function files(string $id):array
    {
        $q=$this->pdo->prepare('SELECT relative_path FROM marketplace_store_files WHERE store_id=?');$q->execute([$id]);$files=$q->fetchAll(PDO::FETCH_COLUMN);
        if(self::exists($this->pdo,'marketplace_sync_workers')){
            $q=$this->pdo->prepare('SELECT launch_key FROM marketplace_sync_workers WHERE store_id=?');$q->execute([$id]);
            foreach($q->fetchAll(PDO::FETCH_COLUMN) as $key)if(preg_match('/^(catalog-)?[a-f0-9]{32}$/',$key))foreach(['out.log','err.log','launch.log','launch.err.log'] as $suffix)$files[]='sync-worker/'.$key.'.'.$suffix;
        }
        return array_values(array_unique($files));
    }
    public function preview(string $id):array
    {
        $company=$this->administrator();$s=$this->store($id,$company);$counts=[];
        foreach($this->plan() as $t=>[$where]){$q=$this->pdo->prepare("SELECT COUNT(*) FROM `$t` WHERE $where");$q->execute([$id]);$counts[$t]=(int)$q->fetchColumn();}
        $counts['marketplace_stores']=1;
        $files=$this->files($id);$existing=0;
        foreach($files as $file){
            try{
                $path=$this->safePath($file);
                if(is_file($path))$existing++;
            }catch(Throwable){}
        }
        $connection=$this->pdo->prepare('SELECT id,external_store_id FROM marketplace_oauth_connections WHERE store_id=? AND company_key=? ORDER BY id DESC LIMIT 1');$connection->execute([$id,$company]);$c=$connection->fetch(PDO::FETCH_ASSOC)?:[];
        $environment='Lingkungan belum terverifikasi';
        if($c&&self::exists($this->pdo,'marketplace_integration_bindings')){$q=$this->pdo->prepare('SELECT environment FROM marketplace_integration_bindings WHERE connection_id=?');$q->execute([$c['id']]);$environment=match((string)$q->fetchColumn()){'live'=>'Live','sandbox'=>'Sandbox','development'=>'Development',default=>$environment};}
        if($environment==='Lingkungan belum terverifikasi'){
            if(str_contains(strtoupper((string)($s['store_name']??'')),'SANDBOX')||str_contains(strtoupper((string)($c['external_store_name']??'')),'SANDBOX')){
                $environment='Sandbox';
            }else{
                $environment='Live';
            }
        }
        return ['store_id'=>$id,'name'=>$s['store_name'],'platform'=>$s['platform'],'external_id'=>$c['external_store_id']??$s['store_identifier'],'environment'=>$environment,'counts'=>$counts,'file_count'=>$existing,'file_types'=>['Dokumen PDF dan daftar pengemasan di database','Berkas terdaftar dan log worker'], 'job'=>$this->status($id)];
    }
    private function identity(array $s):string{return hash('sha256',$s['platform'].':'.$s['store_identifier']);}
    public function request(string $id,string $name):array
    {
        $company=$this->administrator();if($old=$this->status($id))return $old;$s=$this->store($id,$company);
        if(!hash_equals((string)$s['store_name'],trim($name)))throw new DomainException('Nama konfirmasi harus sama dengan nama toko.');
        $this->plan();$this->assertGuards();
        $this->pdo->beginTransaction();
        try{
            $s=$this->store($id,$company,true);
            if($old=$this->status($id)){$this->pdo->commit();return $old;}
            if(!hash_equals((string)$s['store_name'],trim($name)))throw new DomainException('Nama toko berubah. Muat ulang pratinjau.');
            $this->pdo->prepare("UPDATE marketplace_stores SET archived_at=COALESCE(archived_at,NOW()),archived_by=?,status='Terputus',authorization_status='disconnected',api_access_token=NULL,updated_at=NOW() WHERE id=? AND company_key=?")
                ->execute([(string)$_SESSION['user_id'],$id,$company]);
            $this->pdo->prepare('UPDATE marketplace_auto_sync SET enabled=0 WHERE store_id=?')->execute([$id]);
            $this->pdo->prepare("UPDATE marketplace_sync_jobs SET status='cancelled',finished_at=NOW(),last_error_code='STORE_DELETING',lease_token=NULL,lease_until=NULL WHERE store_id=?")->execute([$id]);
            $this->pdo->prepare("UPDATE marketplace_oauth_connections SET status='disconnected',access_token_encrypted=NULL,refresh_token_encrypted=NULL WHERE store_id=?")->execute([$id]);
            $files=$this->files($id);$hash=$this->identity($s);
            // Durable deletion intent is not a data backup. Required before committing irreversible cleanup.
            $time=$this->pdo->query('SELECT NOW(6)')->fetchColumn();
            $this->writeLedger(['store_id'=>$id,'company_key'=>$company,'platform'=>$s['platform'],'identity_hash'=>$hash,'requested_at'=>$time,'actor'=>(string)$_SESSION['user_id']]);
            $this->pdo->prepare("INSERT INTO marketplace_store_deletions(store_id,company_key,platform,identity_hash,actor,requested_at,status,counts_json,files_json,updated_at) VALUES (?,?,?,?,?,?,'pending','{}',?,NOW(6))")
                ->execute([$id,$company,$s['platform'],$hash,(string)$_SESSION['user_id'],$time,json_encode($files,JSON_THROW_ON_ERROR)]);
            $this->pdo->commit();return $this->status($id);
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function assertGuards():void
    {
        foreach(array_merge(array_keys($this->plan()),['marketplace_stores']) as $t)foreach(['INSERT','UPDATE'] as $event){
            $q=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=?');$q->execute(['jl_erase_'.substr(hash('sha256',$t.$event),0,24)]);
            if(!$q->fetchColumn()){
                self::install($this->pdo);
                break 2;
            }
        }
        foreach(array_merge(array_keys($this->plan()),['marketplace_stores']) as $t)foreach(['INSERT','UPDATE'] as $event){
            $q=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=?');$q->execute(['jl_erase_'.substr(hash('sha256',$t.$event),0,24)]);
            if(!$q->fetchColumn())throw new DomainException('Pengaman penghapusan belum terpasang. Jalankan migrasi penghapusan toko.');
        }
    }
    /** One bounded batch, safe to repeat after a crash; CLI worker also uses this entry point. */
    public function step(string $id):bool
    {
        $lock='jl-delete-'.hash('sha1',$id);$q=$this->pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if(!$q->fetchColumn())return false;
        $held=[];
        try{
            $q=$this->pdo->prepare('SELECT * FROM marketplace_store_deletions WHERE store_id=?');$q->execute([$id]);$job=$q->fetch(PDO::FETCH_ASSOC);
            if(!$job||$job['status']==='completed')return true;
            $db=$this->pdo->query('SELECT DATABASE()')->fetchColumn();
            foreach(['jl-sync-'.substr(hash('sha256',$job['company_key'].':'.$id),0,50),'jl-read-store-'.sha1($db.':'.$job['company_key'].':'.$id)] as $workerLock){
                $q=$this->pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute([$workerLock]);if(!$q->fetchColumn())return false;$held[]=$workerLock;
            }
            $this->assertGuards();$counts=json_decode($job['counts_json'],true)?:[];
            foreach($this->plan() as $table=>[$where]){
                $this->pdo->beginTransaction();
                $q=$this->pdo->prepare("DELETE FROM `$table` WHERE $where LIMIT 500");$q->execute([$id]);$n=$q->rowCount();
                $counts[$table]=($counts[$table]??0)+$n;
                $this->pdo->prepare("UPDATE marketplace_store_deletions SET status='running',counts_json=?,error_code=NULL,updated_at=NOW(6) WHERE store_id=?")->execute([json_encode($counts,JSON_THROW_ON_ERROR),$id]);$this->pdo->commit();
                if($n)return false;
            }
            $this->cleanPending($job);
            foreach(json_decode($job['files_json'],true)?:[] as $relative){$path=$this->safePath($relative);if(is_file($path)&&!unlink($path))throw new RuntimeException('FILE_CLEANUP_FAILED');if(file_exists($path))throw new RuntimeException('FILE_CLEANUP_FAILED');}
            // Parent is kept until all child rows and files have been verified absent.
            $this->pdo->beginTransaction();
            foreach($this->plan() as $t=>[$where]){$q=$this->pdo->prepare("SELECT COUNT(*) FROM `$t` WHERE $where");$q->execute([$id]);if($q->fetchColumn())throw new RuntimeException('VERIFICATION_FAILED');}
            $q=$this->pdo->prepare('DELETE FROM marketplace_stores WHERE id=? AND company_key=? AND archived_at IS NOT NULL');$q->execute([$id,$job['company_key']]);$counts['marketplace_stores']=($counts['marketplace_stores']??0)+$q->rowCount();
            $this->pdo->prepare("UPDATE marketplace_store_deletions SET status='completed',counts_json=?,files_json='[]',error_code=NULL,completed_at=NOW(6),updated_at=NOW(6) WHERE store_id=?")->execute([json_encode($counts),$id]);$this->pdo->commit();return true;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();$this->pdo->prepare("UPDATE marketplace_store_deletions SET status='failed',error_code='CLEANUP_RETRY_REQUIRED',updated_at=NOW(6) WHERE store_id=?")->execute([$id]);throw $e;
        }finally{foreach(array_reverse($held) as $workerLock)$this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$workerLock]);$this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
    }
    private function cleanPending(array $job):void
    {
        $q=$this->pdo->prepare('SELECT id,shops_json FROM marketplace_oauth_pending WHERE company_key=? AND platform=? AND created_at<=?');$q->execute([$job['company_key'],$job['platform'],$job['requested_at']]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
            $payload=json_decode($row['shops_json'],true)?:[];$shops=$payload['shops']??[];
            $filtered=array_values(array_filter($shops,fn($s)=>hash('sha256',$job['platform'].':'.($s['id']??''))!==$job['identity_hash']));
            if(count($filtered)===count($shops))continue;
            if(!$filtered)$this->pdo->prepare('DELETE FROM marketplace_oauth_pending WHERE id=?')->execute([$row['id']]);
            else{$payload['shops']=$filtered;$this->pdo->prepare('UPDATE marketplace_oauth_pending SET shops_json=? WHERE id=?')->execute([json_encode($payload),$row['id']]);}
        }
    }
    private function safePath(string $relative):string
    {
        if(!preg_match('~^(sync-worker|private/marketplace|cache/marketplace|temporary/marketplace)/[a-zA-Z0-9_./-]+$~D',$relative)||str_contains($relative,'..'))throw new RuntimeException('UNSAFE_FILE_PATH');
        $root=realpath(dirname(__DIR__,4).'/storage');if(!$root)throw new RuntimeException('STORAGE_UNAVAILABLE');
        $path=$root.'/'.$relative;$parent=dirname($path);
        while(!file_exists($parent)&&$parent!==dirname($parent))$parent=dirname($parent);
        $resolved=realpath($parent);
        if(!$resolved||!str_starts_with(strtolower(str_replace('\\','/',$resolved)).'/',strtolower(str_replace('\\','/',$root)).'/')||is_link($path))throw new RuntimeException('UNSAFE_FILE_PATH');
        for($p=dirname($path);strlen($p)>strlen($root);$p=dirname($p))if(is_link($p))throw new RuntimeException('UNSAFE_FILE_PATH');
        return $path;
    }
    private function writeLedger(array $record):void
    {
        $directory=$this->ledgerDirectory??(getenv('JL_DELETION_LEDGER_DIR')?:'D:/JAGO_LABA_RECOVERY/deletions');
        if(!is_dir($directory)&&!@mkdir($directory,0700,true)&&!is_dir($directory)){
            $directory=dirname(__DIR__,4).'/storage/temporary/deletions';
            if(!is_dir($directory)&&!@mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('DELETION_LEDGER_UNAVAILABLE');
        }
        $path=$directory.'/'.hash('sha256',$record['company_key'].':'.$record['store_id']).'.json';
        if(file_put_contents($path,json_encode($record,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('DELETION_LEDGER_UNAVAILABLE');
    }
    public static function guardAuthorization(PDO $p,array $context,string $platform,string $externalId):bool
    {
        if(!self::exists($p,'marketplace_store_deletions'))return false;
        $q=$p->prepare('SELECT status,requested_at FROM marketplace_store_deletions WHERE company_key=? AND identity_hash=? ORDER BY requested_at DESC LIMIT 1 FOR UPDATE');$q->execute([$context['company_key'],hash('sha256',$platform.':'.$externalId)]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)return false;
        if($row['status']!=='completed'||empty($context['authorization_started'])||strcmp($context['authorization_started'],$row['requested_at'])<=0)throw new DomainException('Toko sedang atau sudah dihapus. Mulai otorisasi baru setelah penghapusan selesai.');
        return true;
    }
}
