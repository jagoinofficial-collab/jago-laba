<?php
require_once (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
header('Content-Type: application/json');header('Cache-Control: no-store, private');
try{
 Workspace::require(Workspace::MARKETPLACE);Workspace::verifyCsrf();
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new DomainException('Gunakan POST.');
 if(!preg_match('/OWNER|ADMIN/i',(string)($_SESSION['role']??'')))throw new DomainException('Memerlukan administrator.');
 $id=(string)($_POST['order_id']??'');$store=(string)($_POST['store_id']??'');
 $scope=new MarketplaceScopeService(Database::pdo());$scope->assertStoreAccess($store);
 $q=Database::pdo()->prepare('SELECT transaction_detail_json FROM marketplace_orders WHERE id=? AND store_id=?');$q->execute([$id,$store]);$raw=$q->fetchColumn();if($raw===false)throw new DomainException('Pesanan tidak ditemukan.');$snapshot=json_decode((string)$raw,true)?:[];
 session_write_close();$fresh=!empty($snapshot['finance_updated_at'])&&strtotime($snapshot['finance_updated_at'])>time()-30;
 $synced=$fresh||(new MarketplaceSyncService(null,null,true))->refreshOrderFinance($store,$id);
 $detail=(new MarketplaceService())->getOrderDetail($id,$store);
 echo json_encode(['success'=>true,'synced'=>$synced,'presentation'=>$detail['presentation'],'message'=>$synced?'Data Finance API diperbarui.':'Marketplace belum menyediakan data keuangan pesanan ini.']);
}catch(Throwable $e){http_response_code($e instanceof DomainException?403:400);$code=$e instanceof MarketplaceIntegrationException?$e->errorCode:null;echo json_encode(['success'=>false,'message'=>$e instanceof DomainException?$e->getMessage():'Finance API belum dapat diperbarui. Periksa izin seller.finance.info atau coba lagi setelah batas API mereda.','error_code'=>$code]);}
