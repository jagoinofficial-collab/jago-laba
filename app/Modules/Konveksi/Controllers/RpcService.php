<?php
final class RpcService {
    private PDO $pdo; private CollectionStore $store; private BackupService $backup; private DocumentService $docs; private InventoryService $inventory; private HppService $hpp; private PurchaseService $purchase; private SalesService $sales; private HutangKonveksiService $hutang; private GeminiService $gemini;
    public function __construct(){
        $this->pdo=KonveksiDatabase::pdo();$this->store=new KonveksiCollectionStore($this->pdo);$this->docs=new DocumentService($this->pdo);$this->backup=new BackupService($this->pdo,$this->store);$this->inventory=new InventoryService($this->pdo,$this->store);$this->hpp=new HppService($this->pdo,$this->store);$this->purchase=new PurchaseService($this->pdo,$this->store,$this->inventory,$this->docs);$this->sales=new SalesService($this->pdo,$this->store);$this->hutang=new HutangKonveksiService($this->pdo,$this->store);$this->gemini=new GeminiService($this->pdo);
    }
    private function requireLogin():void{if(empty($_SESSION['user_id'])){http_response_code(401);throw new RuntimeException('Sesi login berakhir. Silakan login kembali.');}}
    public function dispatch(string $method,array $args){
        if($method==='prosesLoginBackend')return $this->login((string)($args[0]??''),(string)($args[1]??''));
        $this->requireLogin();
        Workspace::require(Workspace::KONVEKSI);
        KonveksiDatabase::assertCompany();
        if(!in_array($method,['loadSemuaData','getRiwayatBackupJagoLaba','getGeminiApiKeyStatusServerRpc'],true)){
            if(!preg_match('/OWNER|ADMIN/i',(string)($_SESSION['role']??'')))throw new DomainException('Perubahan Konveksi memerlukan izin administrator pada alur kompatibilitas ini.');
            if(in_array($method,['saveDocumentFileServer','extractPurchaseInvoiceServerRpc','retryUploadPurchaseDocumentServerRpc','backupSekarangJagoLaba','buatBackupManualSekarang','jadwalkanSnapshotRinganJagoLaba'],true))return $this->dispatchAction($method,$args);
            return (new KonveksiOperationGate($this->pdo,$this->store))->run($method,$args,fn()=>$this->dispatchAction($method,$args));
        }
        return $this->dispatchAction($method,$args);
    }
    private function dispatchAction(string $method,array $args){
        return match($method){
            'loadSemuaData'=>$this->store->allKnown(),
            'syncTab'=>$this->syncTab((string)($args[0]??''),$args[1]??'[]'),
            'syncMultiTabs'=>$this->syncMulti($args[0]??'{}'),
            'jadwalkanSnapshotRinganJagoLaba'=>$this->backup->scheduleSmart(),
            'backupSekarangJagoLaba','buatBackupManualSekarang'=>$this->backup->create('MANUAL'),
            'getRiwayatBackupJagoLaba'=>$this->backup->history(),
            'saveDocumentFileServer'=>$this->docs->saveBase64((string)($args[0]??''),(string)($args[1]??('document_'.time().'.pdf')),(string)($args[2]??'General'),(string)($args[3]??''),(bool)($args[4]??false),'application/pdf'),
            'saveMaterialMasterServerRpc'=>$this->purchase->saveMaterial($this->toArray($args[0]??[])),
            'savePurchaseInvoiceServerRpc'=>$this->purchase->saveInvoice($this->toArray($args[0]??[])),
            'retryUploadPurchaseDocumentServerRpc'=>$this->purchase->retryDocument($this->toArray($args[0]??[])),
            'extractPurchaseInvoiceServerRpc'=>$this->gemini->extractInvoice($this->toArray($args[0]??[])),
            'getGeminiApiKeyStatusServerRpc'=>$this->gemini->status(),
            'saveGeminiApiKeyServerRpc'=>$this->gemini->saveKey((string)($args[0]??'')),
            'syncBahanHppFromPurchasesServerRpc'=>$this->hpp->syncFromPurchases(),
            'activateHppProductionServerRpc'=>$this->hpp->activate($this->toArray($args[0]??[])),
            'saveDraftPenjualanProduksiServer'=>$this->sales->saveDraft($this->toArray($args[0]??[])),
            'postPenjualanProduksiServer'=>$this->sales->post($this->toArray($args[0]??[])),
            'correctPenjualanProduksiServer'=>$this->sales->correct($this->toArray($args[0]??[])),
            'voidPenjualanProduksiServer'=>$this->sales->void($this->toArray($args[0]??[])),
            'retryBackupPenjualanProduksiServer'=>$this->sales->retryBackup($this->toArray($args[0]??[])),
            'createHutangKonveksiManual'=>$this->hutang->createManual($this->toArray($args[0]??[])),
            'bayarHutangKonveksi'=>$this->hutang->pay($this->toArray($args[0]??[])),
            'auditInventoryConsistencyServer'=>$this->inventory->audit(),
            'migrateInventoryIdStokItemServer'=>$this->inventory->migrateIds(),
            'saveDocumentTemplate'=>$this->saveTemplate($args[0]??'{}',$this->toArray($args[1]??[])),
            default=>throw new RuntimeException('RPC belum tersedia pada JAGO LABA LOCAL: '.$method)
        };
    }
    private function toArray($v):array{if(is_array($v))return $v;if(is_object($v))return (array)$v;if(is_string($v)){ $x=json_decode($v,true);if(is_array($x))return $x;}return [];}
    private function login(string $username,string $password):array{
        $username=trim($username);
        if($username===''||$password==='')return ['status'=>'error','code'=>'AUTH_REQUIRED','message'=>'Username dan password wajib diisi.'];

        // R4 canonical local authentication. Always prefer auth_users_v2 so old
        // system_users schemas/triggers cannot break login.
        try {
            $st=Database::pdo()->prepare("SELECT * FROM auth_users_v2 WHERE LOWER(TRIM(username))=LOWER(TRIM(?)) AND LOWER(TRIM(status)) IN ('aktif','active') ORDER BY id ASC");
            $st->execute([$username]);
            foreach($st->fetchAll(PDO::FETCH_ASSOC) as $candidate){
                $hash=(string)($candidate['password_hash']??'');
                if($hash!=='' && password_verify($password,$hash)){
                    session_regenerate_id(true);
                    $_SESSION['user_id']='AUTHV2-'.($candidate['id']??'0');
                    $_SESSION['username']=$candidate['username'];
                    $_SESSION['role']=$candidate['role_id']??'ROLE-OWNER_SUPER_ADMIN';
                    $_SESSION['name']=$candidate['name']??$candidate['username'];
                    unset($_SESSION['workspace']);
                    Workspace::csrf();
                    try{Database::pdo()->prepare('UPDATE auth_users_v2 SET last_login_at=NOW() WHERE id=?')->execute([$candidate['id']]);}catch(Throwable $ignore){}
                    $role=stripos((string)($candidate['role_id']??''),'OWNER')!==false?'owner':'user';
                    return ['status'=>'success','role'=>$role,'token'=>session_id(),'nama'=>$candidate['name']??$candidate['username'],'name'=>$candidate['name']??$candidate['username'],'username'=>$candidate['username'],'auth_source'=>'auth_users_v2'];
                }
            }
        } catch (Throwable $ignoreCanonical) {}

        // Legacy fallback for pre-R4 installations/users.
        try {
            $st=Database::pdo()->prepare("SELECT * FROM system_users WHERE LOWER(TRIM(username))=LOWER(TRIM(?)) AND LOWER(TRIM(status)) IN ('aktif','active') ORDER BY id ASC");
            $st->execute([$username]);
            foreach($st->fetchAll(PDO::FETCH_ASSOC) as $candidate){
                $hash=(string)($candidate['password_hash']??'');
                if($hash!=='' && password_verify($password,$hash)){
                    session_regenerate_id(true);
                    $_SESSION['user_id']=$candidate['id'];$_SESSION['username']=$candidate['username'];$_SESSION['role']=$candidate['role_id']??'ROLE-USER';
                    $_SESSION['name']=$candidate['name']??$candidate['nama']??$candidate['username'];
                    unset($_SESSION['workspace']);
                    Workspace::csrf();
                    $role=stripos((string)($candidate['role_id']??''),'OWNER')!==false?'owner':'user';
                    return ['status'=>'success','role'=>$role,'token'=>session_id(),'nama'=>$candidate['name']??$candidate['nama']??$candidate['username'],'name'=>$candidate['name']??$candidate['nama']??$candidate['username'],'username'=>$candidate['username'],'auth_source'=>'system_users'];
                }
            }
        } catch (Throwable $ignoreLegacy) {}

        return ['status'=>'error','code'=>'AUTH_INVALID_CREDENTIALS','message'=>'Username atau password tidak sesuai.'];
    }

    private function syncTab(string $tab,$payload):array{$rows=is_string($payload)?json_decode($payload,true):$payload;if(!is_array($rows))throw new RuntimeException('Payload syncTab tidak valid.');$res=$this->store->put($tab,$rows);AuditService::log($this->pdo,'SYNC_TAB','Database',null,$tab,null,['rows'=>count($rows)]);return $res;}
    private function syncMulti($payload):array{$map=is_string($payload)?json_decode($payload,true):$payload;if(!is_array($map))throw new RuntimeException('Payload syncMultiTabs tidak valid.');$res=$this->store->putMany($map);AuditService::log($this->pdo,'SYNC_MULTI_TABS','Database',null,null,null,['tabs'=>array_keys($map)]);return $res;}
    private function saveTemplate($payload,array $actor):array{$p=$this->toArray($payload);if(!$p)throw new RuntimeException('Template dokumen tidak valid.');$rows=$this->store->get('template_dokumen');$id=(string)($p['id']??$p['id_template']??'TPL-DEFAULT');$found=false;foreach($rows as $i=>$r)if((string)($r['id']??$r['id_template']??'')===$id){$rows[$i]=array_merge($r,$p,['updated_at'=>date(DATE_ATOM),'updated_by'=>$actor['username']??($_SESSION['username']??'system')]);$found=true;break;}if(!$found)$rows[]=array_merge($p,['id'=>$id,'created_at'=>date(DATE_ATOM),'updated_at'=>date(DATE_ATOM)]);$this->store->put('template_dokumen',$rows);return ['status'=>'success','message'=>'Template dokumen tersimpan.','data'=>$p];}
}
