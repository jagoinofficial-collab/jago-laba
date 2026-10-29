<?php
final class DocumentService {
 private PDO $pdo;private array $cfg;
 public function __construct(PDO $pdo){$this->pdo=$pdo;$this->cfg=Database::config();}
 public function saveBase64(string $base64,string $fileName,string $type='General',string $referenceId='',bool $share=false,string $mime='application/pdf'):array{
  if(strlen($base64)>15000000)throw new InvalidArgumentException('Dokumen maksimal 10 MB.');
  $type=preg_replace('/[^A-Za-z0-9_-]+/','_',trim($type)?:'General');$safe=preg_replace('/[^A-Za-z0-9._-]+/','_',basename($fileName?:'document.pdf'));
  $raw=preg_replace('#^data:[^;]+;base64,#','',$base64);$bin=base64_decode($raw,true);if($bin===false||$bin===''||strlen($bin)>10485760)throw new InvalidArgumentException('Data dokumen tidak valid atau melebihi 10 MB.');
  $detected=(new finfo(FILEINFO_MIME_TYPE))->buffer($bin);$allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($allowed[$detected]))throw new InvalidArgumentException('Dokumen harus PDF, JPG, PNG, atau WebP yang valid.');
  $fid='DOC-'.bin2hex(random_bytes(16));$rel='private/konveksi/documents/'.$type.'/'.date('Y/m').'/'.$fid.'.'.$allowed[$detected];$path=$this->cfg['storage']['root'].'/'.$rel;$dir=dirname($path);if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Penyimpanan dokumen tidak dapat disiapkan.');
  if(file_put_contents($path,$bin,LOCK_EX)!==strlen($bin))throw new RuntimeException('Dokumen belum tersimpan lengkap.');
  try{$this->pdo->prepare('INSERT INTO document_files(file_id,document_type,reference_id,file_name,relative_path,mime_type,size_bytes) VALUES(?,?,?,?,?,?,?)')->execute([$fid,$type,$referenceId,$safe,$rel,$detected,strlen($bin)]);}catch(Throwable $e){if(is_file($path))unlink($path);throw $e;}
  $url='api/konveksi-document.php?id='.rawurlencode($fid);return ['status'=>'success','file_id'=>$fid,'file_name'=>$safe,'url'=>$url,'preview_url'=>$url,'drive_url'=>$url,'folder_name'=>$type,'sharing_status'=>'PRIVATE_AUTHENTICATED','document_type'=>$type,'reference_id'=>$referenceId,'created_at'=>date(DATE_ATOM),'message'=>'Dokumen tersimpan privat di Jago Laba.'];
 }
}
