<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
$args=getopt('',['company:','store:','action:']);
if(empty($args['company'])||empty($args['store']))exit(2);
$_SESSION['company_key']=$args['company'];$_SESSION['role']='OWNER';$_SESSION['user_id']='CLI-BACKGROUND-CONTROL';session_write_close();
try{
    $background=new MarketplaceBackgroundSync();$state=($args['action']??'status')==='start'?$background->start($args['store']):$background->status($args['store']);
    echo json_encode(['worker'=>$state['worker'],'pending'=>$state['pending'],'failed'=>$state['failed'],'jobs'=>array_map(static fn($j)=>['type'=>$j['job_type'],'status'=>$j['status'],'saved'=>$j['success_count'],'error'=>$j['last_error_code']],$state['jobs'])],JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){fwrite(STDERR,'BACKGROUND_CONTROL_FAILED: '.$e->getMessage()."\n");exit(1);}
