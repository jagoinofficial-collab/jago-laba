<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
try{
    Workspace::require(Workspace::MARKETPLACE);
    if(!preg_match('/OWNER|ADMIN/i',(string)($_SESSION['role']??'')))throw new DomainException('Sinkronisasi hanya untuk administrator.');
    $action=(string)($_POST['action']??$_GET['action']??'status');$id=trim((string)($_POST['store_id']??$_GET['store_id']??''));
    if(in_array($action,['start','step','background','catalog'],true)){
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new DomainException('Gunakan POST untuk menjalankan sinkronisasi.');
        Workspace::verifyCsrf();
    }
    session_write_close();
    $sync=new MarketplaceSyncService(null,null,false,false,true);
    if($action==='preview'){ $scope=new MarketplaceScopeService(Database::pdo()); $q=Database::pdo()->prepare("SELECT s.id,s.store_name,s.platform,s.sync_history_start_at,c.external_store_name,c.last_successful_sync_at FROM marketplace_stores s JOIN marketplace_oauth_connections c ON c.store_id=s.id AND c.company_key=s.company_key WHERE s.company_key=? AND s.archived_at IS NULL AND c.status='verified' AND s.platform IN ('TikTok Shop','Shopee')"); $q->execute([$scope->companyKey()]); $stores=$q->fetchAll(PDO::FETCH_ASSOC); if($id!==''){ $scope->assertStoreAccess($id); $stores=array_values(array_filter($stores,static fn($s)=>$s['id']===$id)); } foreach($stores as &$s){$c=Database::pdo()->prepare('SELECT COUNT(*) FROM marketplace_orders WHERE store_id=?');$c->execute([$s['id']]);$s['order_count']=(int)$c->fetchColumn();$s['window']=MarketplaceSyncWindow::checkpoint();if(!empty($s['sync_history_start_at']))$s['window']['window_start']=(new DateTimeImmutable($s['sync_history_start_at'],new DateTimeZone('Asia/Jakarta')))->getTimestamp();unset($s['sync_history_start_at']);}unset($s); echo json_encode(['success'=>true,'data'=>['stores'=>$stores]]);exit; }
    $data=match($action){'start'=>$sync->enqueueAll($id),'step'=>$sync->step($id),'catalog'=>(new MarketplaceBackgroundSync())->start($id,true),'catalog_status'=>(new MarketplaceBackgroundSync())->status($id,true),'background'=>(new MarketplaceBackgroundSync())->start($id),'status'=>(new MarketplaceBackgroundSync())->status($id),default=>throw new InvalidArgumentException('Aksi tidak dikenal.')};
    echo json_encode(['success'=>true,'data'=>$data],JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code($e instanceof DomainException?403:400);echo json_encode(['success'=>false,'message'=>$e instanceof DomainException||$e instanceof InvalidArgumentException?$e->getMessage():'Sinkronisasi belum dapat diproses. Periksa koneksi, schema worker, dan konfigurasi API.']);}
