<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php'); MarketplaceModule::boot();
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store, private');
try {
    Workspace::require(Workspace::MARKETPLACE);
    if (!preg_match('/OWNER|ADMIN/i',(string)($_SESSION['role']??''))) throw new DomainException('Edit harga hanya untuk administrator.');
    if ($_SERVER['REQUEST_METHOD']!=='POST') throw new DomainException('Gunakan POST.');
    Workspace::verifyCsrf();
    $input=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
    $store=(string)($input['store_id']??''); $product=(string)($input['product_id']??'');
    session_write_close(); $service=new MarketplacePriceService();
    $data=match($input['action']??'') {'prepare'=>$service->prepare($store,$product),'save'=>$service->save($store,$product,$input['payload']??[]),default=>throw new InvalidArgumentException('Aksi tidak valid.')};
    echo json_encode(['success'=>true,'data'=>$data],JSON_THROW_ON_ERROR);
} catch(Throwable $e) {
    http_response_code($e instanceof DomainException?403:400);
    echo json_encode(['success'=>false,'message'=>$e instanceof DomainException||$e instanceof InvalidArgumentException?$e->getMessage():'API marketplace belum dapat memproses perubahan.','error_code'=>$e instanceof MarketplaceIntegrationException?$e->errorCode:null]);
}
