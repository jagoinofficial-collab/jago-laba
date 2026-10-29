<?php
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
Database::pdo()->exec("INSERT IGNORE INTO marketplace_auto_sync(store_id,company_key,mode,enabled,next_run_at) SELECT s.id,s.company_key,'finance',1,NOW() FROM marketplace_stores s JOIN marketplace_oauth_connections c ON c.store_id=s.id AND c.company_key=s.company_key WHERE s.archived_at IS NULL AND c.platform='TikTok Shop' AND c.status='verified'");
echo "Automatic finance sync ready.\n";
