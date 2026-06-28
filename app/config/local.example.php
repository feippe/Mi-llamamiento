<?php

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'mi_llamamiento',
        'user' => 'root',
        'pass' => 'tu_password_mysql',
    ],
    'app' => [
        'env' => 'development',
        'cookie_secure' => false,
        // Opcional: URL pública usada en los enlaces de los emails.
        // Si se omite, se deriva del host de la petición.
        'url' => 'https://millamamiento.feippe.com',
    ],
    // SMTP para la recuperación de contraseña.
    'mail' => [
        'host'       => 'smtp.tu-proveedor.com',
        'port'       => 587,
        'encryption' => 'tls', // 'tls' (587), 'ssl' (465) o '' (sin cifrado)
        'username'   => 'tu-usuario-smtp',
        'password'   => 'tu-password-smtp',
        'from'       => 'noreply@millamamiento.feippe.com',
        'from_name'  => 'Mi Llamamiento',
    ],
];
