<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
$cols = Database::pdo()->query("DESCRIBE system_audit_logs")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);

