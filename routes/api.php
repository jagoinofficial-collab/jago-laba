<?php
declare(strict_types=1);
// Built from the compatibility entry points; not a public arbitrary-file dispatcher.
$routes = [];
foreach (glob(dirname(__DIR__) . '/public/api/*.php') ?: [] as $file) {
    $routes[basename($file, '.php')] = '/api/' . basename($file);
}
return $routes;
