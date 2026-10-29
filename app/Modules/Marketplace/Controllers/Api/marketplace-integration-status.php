<?php
declare(strict_types=1);
require dirname(__DIR__,5) . '/app/bootstrap.php';MarketplaceModule::boot();
require_once __DIR__ . '/../../Services/MarketplaceIntegrationHealth.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
try{
    Workspace::require(Workspace::MARKETPLACE);
    $id=trim((string)($_GET['store_id']??$_POST['store_id']??''));
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        Workspace::verifyCsrf();
        if(!preg_match('/OWNER|ADMIN/i',(string)($_SESSION['role']??'')))throw new DomainException('Administrator diperlukan.');
        if(($_POST['action']??'')!=='retry')throw new InvalidArgumentException('Aksi tidak dikenal.');
        $worker=(new MarketplaceBackgroundSync())->status($id)['worker']??null;
        if($worker&&in_array($worker['status'],['starting','running'],true))throw new DomainException('Sinkronisasi toko masih berjalan. Coba ulang sumber yang gagal setelah proses selesai.');
        $sync=new MarketplaceSyncService(null,null,false,false,true);
        $retry=$sync->retryFailed($id,(int)($_POST['job_id']??0));
        session_write_close();(new MarketplaceBackgroundSync())->start($id,$retry['catalog'],$retry['run_key']);
    }elseif(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')throw new InvalidArgumentException('Metode tidak didukung.');
    echo json_encode(['success'=>true,'data'=>(new MarketplaceIntegrationHealth(Database::pdo()))->forStore($id)],JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code($e instanceof DomainException?403:400);echo json_encode(['success'=>false,'message'=>$e instanceof DomainException||$e instanceof InvalidArgumentException?$e->getMessage():'Status integrasi belum dapat dimuat. Coba kembali.']);}
