<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
require_once (dirname(__DIR__, 5) . '/app/Modules/Marketplace/Jobs/MarketplaceAutoSync.php');
header('Content-Type: application/json');header('Cache-Control: no-store, private');
try{
 Workspace::require(Workspace::MARKETPLACE);$service=new MarketplaceAutoSync();$store=(string)($_POST['store_id']??$_GET['store_id']??'');
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){Workspace::verifyCsrf();if(!in_array($_POST['enabled']??null,['0','1'],true))throw new InvalidArgumentException('Nilai aktif tidak valid.');$states=$service->set($store,(string)($_POST['mode']??''),$_POST['enabled']==='1');
 }
 else $states=$service->states($store);
 echo json_encode(['success'=>true,'states'=>$states,'scheduler_available'=>$service->available()],JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code($e instanceof DomainException?403:400);echo json_encode(['success'=>false,'message'=>$e instanceof DomainException||$e instanceof InvalidArgumentException?$e->getMessage():'Auto sync belum dapat diproses. Periksa layanan server.']);}
