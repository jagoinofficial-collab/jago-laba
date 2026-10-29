<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');MarketplaceModule::boot();
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Jobs/MarketplaceAutoSync.php');
session_write_close();(new MarketplaceAutoSync())->tick();
