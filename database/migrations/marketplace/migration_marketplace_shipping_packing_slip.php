<?php
declare(strict_types=1);
require (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
$p=Database::pdo();
$column=$p->query("SHOW COLUMNS FROM marketplace_shipping_documents LIKE 'document_type'")->fetch();
if(!$column)$p->exec("ALTER TABLE marketplace_shipping_documents ADD COLUMN document_type VARCHAR(48) NOT NULL DEFAULT 'SHIPPING_LABEL' AFTER package_id");
echo "Shipping document type migration ready.\n";
