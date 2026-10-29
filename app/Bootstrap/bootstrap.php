<?php
declare(strict_types=1);
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($autoload)) { require_once $autoload; }
$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Jakarta');
require_once (dirname(__DIR__, 2) . '/app/Core/Database/Database.php');
Database::setConfig($config);
require_once __DIR__ . '/SharedRuntime.php';
SharedRuntime::boot();
require_once (dirname(__DIR__, 2) . '/app/Modules/Konveksi/Module.php');
KonveksiModule::boot();
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Module.php');
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_name($config['security']['session_name'] ?? 'JAGO_LABA_LOCAL_SESSION');
    session_start();
}
