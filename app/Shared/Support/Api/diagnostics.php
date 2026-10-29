<?php
require_once (dirname(__DIR__, 4) . '/app/Bootstrap/bootstrap.php');header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id']) || stripos((string)($_SESSION['role']??''),'OWNER')===false) { http_response_code(403); echo json_encode(['status'=>'error']); exit; }
$out=['status'=>'PASS','time'=>date(DATE_ATOM),'checks'=>[]];
try{
  $pdo=Database::pdo();$out['checks']['database']=['status'=>'PASS','name'=>$pdo->query('SELECT DATABASE()')->fetchColumn()];
  $rows=[];$q=$pdo->query('SELECT sheet_name,api_key,table_name,source_row_count,imported_row_count FROM system_table_map ORDER BY id');$failed=0;while($r=$q->fetch()){$actual=(int)$pdo->query('SELECT COUNT(*) FROM `'.$r['table_name'].'`')->fetchColumn();$ok=$actual===(int)$r['source_row_count'];if(!$ok)$failed++;$rows[]=['sheet'=>$r['sheet_name'],'table'=>$r['table_name'],'source'=>(int)$r['source_row_count'],'actual'=>$actual,'status'=>$ok?'PASS':'FAIL'];}$out['checks']['migration']=['status'=>$failed?'FAIL':'PASS','tables'=>count($rows),'failed'=>$failed,'rows'=>$rows];
  $cfg=Database::config();$bdir=$cfg['backup']['local_directory'];$out['checks']['backup']=['status'=>is_dir($bdir)&&is_writable($bdir)?'PASS':'WARNING','local_directory'=>$bdir,'external_directory'=>$cfg['backup']['external_directory'],'mysqldump'=>$cfg['backup']['mysqldump'],'mysqldump_exists'=>is_file($cfg['backup']['mysqldump'])];
  $out['checks']['extensions']=['pdo_mysql'=>extension_loaded('pdo_mysql'),'curl'=>extension_loaded('curl'),'zlib'=>extension_loaded('zlib'),'json'=>extension_loaded('json')];
  if($failed)$out['status']='FAIL';
}catch(Throwable $e){http_response_code(500);$out=['status'=>'FAIL','message'=>$e->getMessage()];}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
