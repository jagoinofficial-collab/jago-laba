<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();
$accs = $pdo->query("SELECT * FROM accounts")->fetchAll(PDO::FETCH_ASSOC);
print_r($accs);

