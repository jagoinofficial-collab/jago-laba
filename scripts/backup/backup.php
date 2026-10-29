<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
try{$pdo=Database::pdo();$store=new CollectionStore($pdo);$svc=new BackupService($pdo,$store);$svc->processPending();$r=$svc->create($argv[1]??'AUTO');echo json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(0);}catch(Throwable $e){fwrite(STDERR,'BACKUP ERROR: '.$e->getMessage().PHP_EOL);exit(1);}
