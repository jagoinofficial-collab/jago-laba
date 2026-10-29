<?php
final class BackupService {
    private PDO $pdo; private CollectionStore $store; private array $cfg;
    public function __construct(PDO $pdo, CollectionStore $store){$this->pdo=$pdo;$this->store=$store;$this->cfg=Database::config();if(class_exists('KonveksiDatabase')&&$pdo===KonveksiDatabase::pdo()){$this->cfg['db']=KonveksiDatabase::config();$this->cfg['backup']['local_directory']=rtrim($this->cfg['backup']['local_directory'],'/\\').'/konveksi';}}
    private function ensureDir(string $d): void { if(!is_dir($d) && !mkdir($d,0775,true) && !is_dir($d)) throw new RuntimeException('Gagal membuat folder backup: '.$d); }
    private function backupDir(string $mode): string {
        $root=$this->cfg['backup']['local_directory'];$dir=rtrim($root,'/\\').'/'.strtoupper($mode).'/'.date('Y').'/'.date('m');$this->ensureDir($dir);return $dir;
    }
    private function externalCopy(array $files,string $mode): array {
        $ext=trim((string)($this->cfg['backup']['external_directory']??'')); if($ext==='') return [];
        try{$dir=rtrim($ext,'/\\').'/'.strtoupper($mode).'/'.date('Y').'/'.date('m');$this->ensureDir($dir);$out=[];foreach($files as $f){if($f && is_file($f)){ $to=$dir.'/'.basename($f); if(@copy($f,$to))$out[]=$to; }}return $out;}catch(Throwable $e){return ['error'=>$e->getMessage()];}
    }
    public function create(string $mode='MANUAL'): array {
        $mode=strtoupper($mode);$id='BKP-'.$mode.'-'.date('Ymd-His');$dir=$this->backupDir($mode);$base='jago_laba_'.date('Ymd_His');$files=[];$totalRows=0;
        $snapshot=['metadata'=>['backup_id'=>$id,'created_at'=>date(DATE_ATOM),'mode'=>$mode,'database'=>$this->cfg['db']['name'],'app_version'=>$this->cfg['app']['version']],'tables'=>[]];
        $q=$this->pdo->query("SELECT sheet_name,api_key,table_name,imported_row_count FROM system_table_map ORDER BY id");
        while($m=$q->fetch()){ $rows=$this->store->get($m['api_key']);$snapshot['tables'][$m['sheet_name']]=$rows;$totalRows+=count($rows); }
        $snapshotJson=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $snapPath=$dir.'/'.$base.'.json'.(function_exists('gzencode')?'.gz':'');
        file_put_contents($snapPath,function_exists('gzencode')?gzencode($snapshotJson,9):$snapshotJson);$files[]=$snapPath;

        $sqlPath=null;$dump=$this->cfg['backup']['mysqldump']??'';
        if($dump && is_file($dump) && function_exists('exec')){
            $c=$this->cfg['db'];$sqlPath=$dir.'/'.$base.'.sql';
            $cmd='"'.$dump.'" --host='.escapeshellarg($c['host']).' --port='.(int)$c['port'].' --user='.escapeshellarg($c['user']).' '.($c['pass']!==''?'--password='.escapeshellarg($c['pass']).' ':'').'--single-transaction --default-character-set=utf8mb4 --routines --triggers '.escapeshellarg($c['name']).' > '.escapeshellarg($sqlPath).' 2>&1';
            $out=[];$code=0;@exec($cmd,$out,$code);if($code!==0 || !is_file($sqlPath) || filesize($sqlPath)<100){@unlink($sqlPath);$sqlPath=null;}else{$files[]=$sqlPath;}
        }
        $manifest=['backup_id'=>$id,'created_at'=>date(DATE_ATOM),'mode'=>$mode,'status'=>'SUCCESS','database'=>$this->cfg['db']['name'],'total_rows'=>$totalRows,'snapshot_file'=>basename($snapPath),'sql_file'=>$sqlPath?basename($sqlPath):null,'checksums'=>[]];
        foreach($files as $f)$manifest['checksums'][basename($f)]=hash_file('sha256',$f);
        $manifestPath=$dir.'/'.$base.'_manifest.json';file_put_contents($manifestPath,json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));$files[]=$manifestPath;
        $external=$this->externalCopy($files,$mode);$size=0;foreach($files as $f)if(is_file($f))$size+=filesize($f);$checksum=hash('sha256',implode('|',$manifest['checksums']));
        $st=$this->pdo->prepare("INSERT INTO backup_log(backup_id,mode,status,sql_file,snapshot_file,manifest_file,total_rows,size_bytes,checksum,message) VALUES(?,?,?,?,?,?,?,?,?,?)");$st->execute([$id,$mode,'Berhasil',$sqlPath,$snapPath,$manifestPath,$totalRows,$size,$checksum,$sqlPath?'Backup SQL + JSON selesai':'Backup JSON selesai; mysqldump tidak tersedia/berhasil.']);
        AuditService::log($this->pdo,'BACKUP_DATABASE','Backup',null,$id,null,$manifest,'SUCCESS');
        return ['status'=>'success','id'=>$id,'backup_id'=>$id,'mode'=>$mode,'waktu'=>date(DATE_ATOM),'status_text'=>'Berhasil','status_backup'=>'Berhasil','statusLabel'=>'Berhasil','sql_file'=>$sqlPath,'snapshot_file'=>$snapPath,'manifest_file'=>$manifestPath,'external_files'=>$external,'jumlah_row'=>$totalRows,'ukuran'=>$size,'url'=>'file:///'.$dir,'message'=>'Backup database lokal berhasil dibuat.'];
    }
    public function history(): array {
        $out=[];$q=$this->pdo->query("SELECT * FROM backup_log ORDER BY created_at DESC LIMIT 100");while($r=$q->fetch()){$out[]=['id'=>$r['backup_id'],'backup_id'=>$r['backup_id'],'waktu'=>date(DATE_ATOM,strtotime($r['created_at'])),'mode'=>$r['mode'],'kategori'=>$r['mode'],'ukuran'=>$r['size_bytes'],'status'=>$r['status'],'url'=>$r['snapshot_file']?:$r['sql_file'],'keterangan'=>$r['message']];}return $out;
    }
    public function scheduleSmart(): array {
        $file=$this->cfg['app']['storage_path'].'/pending_snapshot.json';@file_put_contents($file,json_encode(['requested_at'=>date(DATE_ATOM),'requested_by'=>$_SESSION['username']??'system']));return ['status'=>'scheduled','mode'=>'MYSQL_LOCAL','message'=>'Smart snapshot dijadwalkan. Data transaksi sudah tersimpan di MySQL.'];
    }
    public function processPending(): ?array {
        $file=$this->cfg['app']['storage_path'].'/pending_snapshot.json';if(!is_file($file))return null;$age=time()-filemtime($file);if($age<60)return null;@unlink($file);return $this->create('SMART');
    }
}
