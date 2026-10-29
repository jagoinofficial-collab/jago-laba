<?php
require_once (dirname(__DIR__, 4) . '/app/Bootstrap/bootstrap.php');
if (empty($_SESSION['user_id']) || stripos((string)($_SESSION['role']??''),'OWNER')===false) { http_response_code(403); echo json_encode(['status'=>'error']); exit; }
header('Content-Type: application/json; charset=utf-8');
try{
  $username=trim((string)($_GET['username']??'owner'))?:'owner';
  $test=null;
  echo json_encode(['status'=>'ok','diagnostic'=>SchemaManager::authDiagnostic(Database::pdo(),$username,$test)],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){http_response_code(500);echo json_encode(['status'=>'error','message'=>$e->getMessage()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
