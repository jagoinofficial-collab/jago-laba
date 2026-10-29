<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
require_once (dirname(__DIR__, 5) . '/app/Modules/Marketplace/Jobs/MarketplaceAutoSync.php');
header('Content-Type: application/json');header('Cache-Control: no-store, private');
try{
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);header('Allow: POST');throw new DomainException('Gunakan POST.');}
 if(empty($_SESSION['user_id'])){http_response_code(401);throw new DomainException('Silakan login terlebih dahulu.');}
 if(!Workspace::can(Workspace::MARKETPLACE)){http_response_code(403);throw new DomainException('Akses marketplace diperlukan.');}
 Workspace::verifyCsrf();
 // Release the browser session before API work; the service restores its in-memory identity.
 session_write_close();
 (new MarketplaceAutoSync())->browserPulse();
 echo json_encode(['success'=>true,'mode'=>'browser'],JSON_THROW_ON_ERROR);
}catch(Throwable $e){if(http_response_code()<400)http_response_code($e instanceof DomainException?403:500);echo json_encode(['success'=>false,'message'=>'AutoSync belum dapat berjalan. Muat ulang halaman atau periksa koneksi toko.']);}
