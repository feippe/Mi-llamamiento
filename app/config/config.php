<?php

$config = [
    'app' => [
        'name' => 'Mi Llamamiento',
        'env' => 'development',
        'cookie_secure' => false,
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'mi_llamamiento',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];

$local = __DIR__ . '/local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
