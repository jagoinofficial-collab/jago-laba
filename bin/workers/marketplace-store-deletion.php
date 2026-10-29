<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__,2).'/app/Bootstrap/bootstrap.php'; MarketplaceModule::boot();
$args=getopt('',['store:','install']);$pdo=Database::pdo();
if(isset($args['install'])){MarketplaceStoreDeletion::install($pdo);echo "Deletion schema and write barriers installed.\n";exit;}
$id=(string)($args['store']??'');if($id==='')exit(2);
session_write_close();$service=new MarketplaceStoreDeletion($pdo);$deadline=time()+3600;
try{while(time()<$deadline){if($service->step($id))exit(0);usleep(250000);}exit(3);}catch(Throwable){fwrite(STDERR,"Deletion paused; retry required.\n");exit(1);}
