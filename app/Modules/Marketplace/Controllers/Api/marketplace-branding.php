<?php
declare(strict_types=1);
require (dirname(__DIR__, 5) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();require_once (dirname(__DIR__, 5) . '/app/Shared/Branding/MarketplaceBrandingService.php');
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
try{
 Workspace::require(Workspace::MARKETPLACE);$service=new MarketplaceBrandingService(Database::pdo());
 if(($_SERVER['REQUEST_METHOD']??'')==='GET'&&($_GET['image']??'')==='1'){
  $brand=$service->read(true);if(empty($brand['logo_blob'])){http_response_code(404);exit;}
  if(!in_array($brand['logo_mime'],['image/png','image/jpeg','image/webp'],true))throw new DomainException('Logo tidak valid.');
  header('Content-Type: '.$brand['logo_mime']);header('Content-Disposition: inline; filename="logo-marketplace"');header("Content-Security-Policy: default-src 'none'; sandbox");echo $brand['logo_blob'];exit;
 }
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new DomainException('Gunakan formulir pengaturan logo.');
 Workspace::verifyCsrf();$bytes=null;
 if(isset($_FILES['logo'])&&$_FILES['logo']['error']!==UPLOAD_ERR_NO_FILE){$file=$_FILES['logo'];if($file['error']!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name'])||$file['size']>2097152)throw new InvalidArgumentException('Unggahan logo gagal. Pilih gambar maksimal 2 MB.');$bytes=file_get_contents($file['tmp_name']);}
 $brand=$service->save($bytes,(int)($_POST['logo_size']??32),(string)($_POST['logo_fit']??'contain'),($_POST['reset_logo']??'')==='1');
 header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>true,'message'=>'Identitas dan logo tersimpan.','branding'=>$brand]);
}catch(Throwable $e){header('Content-Type: application/json; charset=utf-8');http_response_code($e instanceof InvalidArgumentException?422:403);echo json_encode(['success'=>false,'message'=>$e instanceof DomainException||$e instanceof InvalidArgumentException?$e->getMessage():'Logo belum dapat disimpan. Coba kembali.']);}
