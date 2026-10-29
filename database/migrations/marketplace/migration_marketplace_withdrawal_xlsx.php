<?php
require (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');$pdo=Database::pdo();
foreach(['source_sheet'=>'VARCHAR(100)','source_timezone'=>'VARCHAR(30)','source_date_precision'=>'VARCHAR(12)','completed_date_precision'=>'VARCHAR(12)','source_signed_amount'=>'DECIMAL(18,2)'] as $name=>$type){$q=$pdo->query("SHOW COLUMNS FROM marketplace_withdrawals LIKE '$name'");if(!$q->fetch())$pdo->exec("ALTER TABLE marketplace_withdrawals ADD `$name` $type NULL");}echo "XLSX source metadata ready.\n";
