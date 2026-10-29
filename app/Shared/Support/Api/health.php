<?php
require_once (dirname(__DIR__, 4) . '/app/Bootstrap/bootstrap.php');header('Content-Type: application/json; charset=utf-8');
try{Database::pdo()->query('SELECT 1');echo json_encode(['status'=>'ok']);}catch(Throwable $e){http_response_code(503);echo json_encode(['status'=>'error']);}
