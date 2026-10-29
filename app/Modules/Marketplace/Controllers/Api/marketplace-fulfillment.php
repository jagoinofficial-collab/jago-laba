<?php
declare(strict_types=1);
require (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
require_once (dirname(__DIR__, 5) . '/app/Modules/Marketplace/Services/MarketplaceFulfillmentService.php');
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
try{
 Workspace::require(Workspace::MARKETPLACE);if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new InvalidArgumentException('Gunakan POST.');Workspace::verifyCsrf();
 $store=(string)($_POST['store_id']??'');$order=(string)($_POST['order_id']??'');$package=(string)($_POST['package_id']??'');$action=(string)($_POST['action']??'options');
 if(strlen($store)>120||!preg_match('/^[a-zA-Z0-9_-]{1,120}$/',$order)||($package!==''&&!preg_match('/^[a-zA-Z0-9_-]{1,120}$/',$package)))throw new InvalidArgumentException('Identitas pesanan atau paket tidak valid.');
 if(!in_array($action,['options','confirm'],true))throw new InvalidArgumentException('Aksi tidak valid.');
 if($action==='confirm'&&($_POST['confirmed']??'')!=='1')throw new DomainException('Konfirmasi pengaturan pengiriman diperlukan.');
 session_write_close();ignore_user_abort(true);set_time_limit(120);$service=new MarketplaceFulfillmentService(Database::pdo());
 if($action==='options')$data=['packages'=>$service->options($store,$order,$package!==''?$package:null)];
 else {$slot=isset($_POST['slot_start'])&&$_POST['slot_start']!==''?filter_var($_POST['slot_start'],FILTER_VALIDATE_INT):null;if($slot===false)throw new InvalidArgumentException('Jadwal tidak valid.');$data=$service->confirm($store,$order,$package,(string)($_POST['offer_token']??''),(string)($_POST['method']??''),$slot);}
 echo json_encode(['success'=>true,'data'=>$data],JSON_THROW_ON_ERROR);
}catch(Throwable $e){if(http_response_code()<400)http_response_code($e instanceof DomainException?403:400);echo json_encode(['success'=>false,'message'=>MarketplaceFulfillmentService::safeError($e)]);}
