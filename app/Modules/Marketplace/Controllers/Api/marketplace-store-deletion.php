<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require dirname(__DIR__,5).'/app/Bootstrap/bootstrap.php'; MarketplaceModule::boot();
try{
    Workspace::require(Workspace::MARKETPLACE);
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new DomainException('Gunakan POST.');
    Workspace::verifyCsrf();
    $service=new MarketplaceStoreDeletion(Database::pdo());
    if(!$service->ready()){
        MarketplaceStoreDeletion::install(Database::pdo());
    }
    if(!$service->ready())throw new DomainException('Fitur menunggu migrasi penghapusan toko oleh administrator server.');
    $id=trim((string)($_POST['store_id']??''));$action=(string)($_POST['action']??'');
    if($id===''&&$action!=='list')throw new DomainException('Pilih toko.');
    if($action==='status'&&$id!==''){
        try{$service->step($id);}catch(Throwable){}
    }
    $data=match($action){
        'preview'=>$service->preview($id),
        'status'=>$service->status($id),
        'request'=>$service->request($id,(string)($_POST['confirmation']??'')),
        'resume'=>$service->status($id)??throw new DomainException('Proses penghapusan tidak ditemukan.'),
        'list'=>$service->recentStatuses(),
        default=>throw new DomainException('Tindakan tidak tersedia.')
    };
    if(in_array($action,['request','resume'],true)&&$data['status']!=='completed'){
        // Execute an immediate batch to speed up small store cleanups
        try{if($service->step($id)){$data=$service->status($id)??$data;}}catch(Throwable){}
    }
    if(in_array($action,['request','resume'],true)&&$data['status']!=='completed'){
        session_write_close();
        if(PHP_OS_FAMILY!=='Windows'||!function_exists('proc_open'))throw new DomainException('Permintaan tersimpan. Worker CLI perlu dijalankan oleh administrator server.');
        $root=dirname(__DIR__,5);$php=getenv('JL_PHP_CLI')?:(defined('PHP_BINARY')&&is_file(PHP_BINARY)?PHP_BINARY:'D:/xampp/php/php.exe');
        $quote=static fn($s)=>"'".str_replace("'","''",$s)."'";
        // Use a fixed worker path and a validated internal identifier instead of evaluating PHP input.
        if(!preg_match('/^[A-Za-z0-9_-]{1,191}$/D',$id))throw new DomainException('Identitas internal toko tidak didukung launcher.');
        $args=implode(' ',array_map(static fn($s)=>'"'.$s.'"',[$root.'/bin/workers/marketplace-store-deletion.php','--store='.$id]));
        $command='$p=Start-Process -FilePath '.$quote($php).' -ArgumentList '.$quote($args).' -WorkingDirectory '.$quote($root).' -WindowStyle Hidden -PassThru; if(!$p){exit 1}';
        $proc=proc_open(['powershell.exe','-NoProfile','-NonInteractive','-WindowStyle','Hidden','-EncodedCommand',base64_encode(mb_convert_encoding($command,'UTF-16LE','UTF-8'))],[0=>['file','NUL','r'],1=>['file','NUL','w'],2=>['file','NUL','w']],$pipes,$root);
        if(!is_resource($proc)||proc_close($proc)!==0)throw new DomainException('Permintaan tersimpan, tetapi worker belum dimulai. Klik coba lagi.');
    }
    echo json_encode(['success'=>true,'data'=>$data],JSON_THROW_ON_ERROR);
}catch(DomainException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>$e instanceof DomainException?$e->getMessage():'Penghapusan belum selesai. Data lain tetap terlindungi; coba lagi atau periksa migrasi dan penyimpanan server.']);}
