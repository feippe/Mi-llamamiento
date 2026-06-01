<?php

return [
    'app_name' => 'Mi Llamamiento',
    'env' => getenv('APP_ENV') ?: 'development',
    'base_url' => getenv('APP_BASE_URL') ?: '',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_DATABASE') ?: 'mi_llamamiento',
        'user' => getenv('DB_USERNAME') ?: 'root',
        'pass' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'auth' => [
        'google_client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
        'allow_dev_login' => (getenv('APP_ENV') ?: 'development') !== 'production',
    ],
    'push' => [
        'vapid_public_key' => getenv('VAPID_PUBLIC_KEY') ?: '',
        'vapid_private_key' => getenv('VAPID_PRIVATE_KEY') ?: '',
    ],
];
