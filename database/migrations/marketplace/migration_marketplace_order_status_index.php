<?php
require (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');$p=Database::pdo();
$q=$p->query("SHOW COLUMNS FROM marketplace_orders LIKE 'source_order_status'");if(!$q->fetch())$p->exec("ALTER TABLE marketplace_orders ADD COLUMN source_order_status VARCHAR(64) GENERATED ALWAYS AS (COALESCE(NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(transaction_detail_json),transaction_detail_json,'{}'),'$.source_status')),'null'),''),order_status)) STORED");
$q=$p->query("SHOW INDEX FROM marketplace_orders WHERE Key_name='idx_store_order_operational'");if(!$q->fetch())$p->exec('ALTER TABLE marketplace_orders ADD INDEX idx_store_order_operational(store_id,source_kind,order_date,source_order_status)');echo "Marketplace order status index ready.\n";
