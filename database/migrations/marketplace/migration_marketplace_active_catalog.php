<?php
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');$pdo=Database::pdo();
if(!$pdo->query("SHOW COLUMNS FROM marketplace_catalog_variants LIKE 'catalog_visible'")->fetch())$pdo->exec('ALTER TABLE marketplace_catalog_variants ADD catalog_visible TINYINT NOT NULL DEFAULT 1');
$pdo->exec("UPDATE marketplace_catalog_variants SET catalog_visible=0 WHERE UPPER(COALESCE(source_status,'')) NOT IN ('ACTIVATE','NORMAL','AKTIF','ACTIVE')");
echo "Active catalog visibility ready.\n";
