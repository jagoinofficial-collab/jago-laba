<?php
declare(strict_types=1);
require (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php'); MarketplaceModule::boot();
require_once (dirname(__DIR__, 5) . '/app/Modules/Marketplace/Services/MarketplaceShippingDocumentService.php');
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
try {
 Workspace::require(Workspace::MARKETPLACE);
 $service=new MarketplaceShippingDocumentService(Database::pdo());
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  Workspace::verifyCsrf();$store=(string)($_POST['store_id']??'');$order=(string)($_POST['order_id']??'');
  if(strlen($store)>120||!preg_match('/^[a-zA-Z0-9_-]{1,120}$/',$order))throw new InvalidArgumentException('Identitas pesanan tidak valid.');
  session_write_close();$documents=$service->prepare($store,$order);
  foreach($documents as &$document)if($document['success']){$document['download_url']='api/marketplace-shipping-document.php?id='.$document['document_id'];$document['preview_url']=$document['download_url'].'&preview=1';}unset($document);
  header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>true,'documents'=>$documents],JSON_THROW_ON_ERROR);exit;
 }
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')throw new InvalidArgumentException('Metode tidak didukung.');
 $preview=($_GET['preview']??'')==='1';session_write_close();$document=$service->read((string)($_GET['id']??''),$preview);
 header('Content-Type: application/pdf');header('Content-Disposition: '.($preview?'inline':'attachment').'; filename="'.$document['filename'].'"');header('Content-Length: '.strlen($document['pdf']));echo $document['pdf'];
}catch(Throwable $e){
 if(http_response_code()<400)http_response_code($e instanceof DomainException?403:400);
 header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>$e instanceof DomainException||$e instanceof InvalidArgumentException?$e->getMessage():'Dokumen belum dapat diambil. Periksa sesi login, koneksi toko, dan izin Fulfillment.']);
}
