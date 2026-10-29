<?php
require_once (dirname(__DIR__, 4) . '/app/Bootstrap/bootstrap.php');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['status'=>'error']); exit; }
try { Workspace::verifyCsrf(); } catch (Throwable $e) { http_response_code(403); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit; }
$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE)session_destroy();echo json_encode(['status'=>'success']);
