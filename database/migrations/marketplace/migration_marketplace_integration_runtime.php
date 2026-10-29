<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__,3) . '/app/bootstrap.php';
require_once dirname(__DIR__,3) . '/app/Modules/Marketplace/Services/MarketplaceIntegrationRuntime.php';
MarketplaceIntegrationRuntime::install(Database::pdo());
echo "Integration metadata tables ready; existing store and transaction data preserved.\n";
