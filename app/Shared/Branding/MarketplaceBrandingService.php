<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 3) . '/app/Shared/Companies/MarketplaceScopeService.php');
final class MarketplaceBrandingService
{
 public function __construct(private PDO $pdo){}
 public function read(bool $withImage=false):array {
  $fields=$withImage?'*':'company_key,logo_mime,logo_size,logo_fit,logo_hash,updated_at';
  try{$q=$this->pdo->prepare("SELECT $fields FROM marketplace_branding WHERE company_key=?");$q->execute([(new MarketplaceScopeService($this->pdo))->companyKey()]);$row=$q->fetch(PDO::FETCH_ASSOC);}
  catch(PDOException $e){if(($e->errorInfo[1]??0)!==1146)throw $e;$row=false;}
  if($row){$row['logo_size']=self::logoSize((int)$row['logo_size']);return $row;}
  return ['logo_mime'=>null,'logo_size'=>32,'logo_fit'=>'contain','logo_hash'=>null,'updated_at'=>null];
 }
 public static function logoSize(int $size):int {
  return match($size){28=>28,32,44=>32,36,52=>36,default=>32};
 }
 public static function validateImage(string $bytes):string {
  if(strlen($bytes)>2097152||strlen($bytes)<16)throw new InvalidArgumentException('Pilih gambar PNG, JPG, atau WebP maksimal 2 MB.');
  $image=@getimagesizefromstring($bytes);$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
  if(!$image||!in_array($mime,['image/png','image/jpeg','image/webp'],true)||($image['mime']??'')!==$mime)throw new InvalidArgumentException('Format logo harus PNG, JPG, atau WebP yang valid.');
  if($image[0]>2048||$image[1]>2048||$image[0]<1||$image[1]<1)throw new InvalidArgumentException('Ukuran logo maksimal 2048 × 2048 piksel.');
  return $mime;
 }
 public function save(?string $bytes,int $size,string $fit,bool $reset=false):array {
  if(!preg_match('/OWNER|ADMIN/i',(string)($_SESSION['role']??'')))throw new DomainException('Logo hanya dapat diubah oleh Owner atau Admin.');
  if(!in_array($size,[28,32,36,44,52],true)||!in_array($fit,['contain','cover'],true))throw new InvalidArgumentException('Pilihan tampilan logo tidak valid.');
  $size=self::logoSize($size);$current=$this->read(true);$mime=$bytes!==null?self::validateImage($bytes):($current['logo_mime']??null);$image=$bytes??($current['logo_blob']??null);
  if($reset){$image=null;$mime=null;}$hash=$image!==null?hash('sha256',$image):null;
  $q=$this->pdo->prepare('INSERT INTO marketplace_branding(company_key,logo_blob,logo_mime,logo_size,logo_fit,logo_hash,updated_by,updated_at) VALUES(?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE logo_blob=VALUES(logo_blob),logo_mime=VALUES(logo_mime),logo_size=VALUES(logo_size),logo_fit=VALUES(logo_fit),logo_hash=VALUES(logo_hash),updated_by=VALUES(updated_by),updated_at=NOW()');
  $q->execute([(new MarketplaceScopeService($this->pdo))->companyKey(),$image,$mime,$size,$fit,$hash,(string)($_SESSION['user_id']??'')]);return $this->read();
 }
}
