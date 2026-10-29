<?php
require_once dirname(__DIR__,5).'/app/Bootstrap/bootstrap.php';KonveksiModule::boot();
require_once dirname(__DIR__,5).'/app/Core/Http/JsonResponse.php';require_once dirname(__DIR__,5).'/app/Core/Http/RpcRequest.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
$requestId=bin2hex(random_bytes(8));header('X-Request-Id: '.$requestId);
try{
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');throw new InvalidArgumentException('Gunakan POST.');}
 $request=RpcRequest::fromGlobals();if($request->method!=='prosesLoginBackend'){if(empty($_SESSION['user_id'])){http_response_code(401);throw new RuntimeException('Sesi login berakhir. Silakan login kembali.');}Workspace::verifyCsrf();}
 JsonResponse::rpcSuccess((new RpcService())->dispatch($request->method,$request->args));
}catch(Throwable $e){
 $status=$e instanceof DomainException?($e->getCode()===409?409:403):($e instanceof InvalidArgumentException?422:400);
 if(http_response_code()>=400)$status=http_response_code();
 $message=$e->getMessage();if($e instanceof PDOException){$status=500;$message='Penyimpanan belum berhasil. Hubungi administrator dengan ID permintaan '.$requestId;error_log('Konveksi database failure '.$requestId.' SQLSTATE '.$e->getCode());}
 http_response_code($status);JsonResponse::send(['ok'=>false,'error'=>['message'=>$message,'request_id'=>$requestId]]);
}
