<?php
require dirname(__DIR__,2).'/app/Bootstrap/bootstrap.php';KonveksiModule::boot();
try{
 Workspace::require(Workspace::KONVEKSI);KonveksiDatabase::assertCompany();
 $id=(string)($_GET['id']??'');if(!preg_match('/^DOC-[a-f0-9]{32}$/',$id))throw new InvalidArgumentException('Dokumen tidak valid.');
 $q=KonveksiDatabase::pdo()->prepare('SELECT * FROM document_files WHERE file_id=?');$q->execute([$id]);$doc=$q->fetch();if(!$doc)throw new DomainException('Dokumen tidak ditemukan.');
 $root=realpath(Database::config()['storage']['private']['konveksi']);$path=realpath(Database::config()['storage']['root'].'/'.$doc['relative_path']);if(!$root||!$path||!str_starts_with(strtolower(str_replace('\\','/',$path)),strtolower(str_replace('\\','/',$root)).'/')||!is_file($path))throw new DomainException('Dokumen tidak tersedia.');
 header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Content-Type: '.$doc['mime_type']);header('Content-Disposition: inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_',$doc['file_name']).'"');header('Content-Length: '.filesize($path));readfile($path);
}catch(Throwable $e){if(http_response_code()<400)http_response_code($e instanceof InvalidArgumentException?422:404);header('Content-Type: text/plain; charset=utf-8');echo 'Dokumen tidak dapat diakses. Periksa sesi dan izin ruang kerja.';}
