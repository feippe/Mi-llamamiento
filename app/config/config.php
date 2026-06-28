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
    // VAPID keys for Web Push (RFC 8291/8292).
    // In production override via app/config/local.php
    'vapid' => [
        'public_key'     => 'BLTKLXjiGaTPRp7Tmqnpk8kd58YToGz3CIMJh6TXSgzoPaK6lInNhb-oeGpRlTB6s516egq4NLAAvDfg2aCputk',
        'private_key_pem' => "-----BEGIN PRIVATE KEY-----\nMIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgUZ0pH7YHxALkOSgm\nrhgfjf2aZ5e4Hz/GcA7DUJOPE0uhRANCAAS0yi144hmkz0ae05qp6ZPJHefGE6Bs\n9wiDCYek10oM6D2iupSJzYW/qHhqUZUwerOdenoKuDSwALw34NmgqbrZ\n-----END PRIVATE KEY-----\n",
        'subject'        => 'mailto:admin@millamamiento.com',
    ],
    // SMTP para correos transaccionales (recuperación de contraseña).
    // Configura las credenciales reales en app/config/local.php
    'mail' => [
        'host'       => '',
        'port'       => 587,
        'encryption' => 'tls', // 'tls' (STARTTLS/587), 'ssl' (465) o '' (sin cifrado)
        'username'   => '',
        'password'   => '',
        'from'       => 'noreply@millamamiento.feippe.com',
        'from_name'  => 'Mi Llamamiento',
        'timeout'    => 15,
    ],
];

$local = __DIR__ . '/local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
