<?php
require dirname(__DIR__,3).'/app/Bootstrap/bootstrap.php';MarketplaceModule::boot();
require_once dirname(__DIR__,3).'/app/Modules/Marketplace/Services/MarketplaceIncrementalSync.php';
if(PHP_SAPI!=='cli')exit(1);
MarketplaceIncrementalSync::install(Database::pdo());echo "Incremental sync checkpoint and object refresh schema ready.\n";
