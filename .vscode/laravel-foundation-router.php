<?php

$publicPath = 'D:/xampp/htdocs/jago-laba/laravel-foundation/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$requestedFile = $publicPath.$uri;

if ($uri !== '/' && is_file($requestedFile)) {
    return false;
}

require $publicPath.'/index.php';
