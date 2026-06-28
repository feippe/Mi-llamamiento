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
    // SMTP para la recuperación de contraseña (iCloud Mail).
    // La contraseña debe ser una "contraseña específica para app" generada
    // en appleid.apple.com. El 'from' debe ser tu dirección de iCloud (o un
    // alias verificado en iCloud); no puede ser un dominio arbitrario.
    'mail' => [
        'host'       => 'smtp.mail.me.com',
        'port'       => 587,
        'encryption' => 'tls',
        'username'   => 'tu-correo@icloud.com',
        'password'   => 'xxxx-xxxx-xxxx-xxxx', // contraseña específica para app
        'from'       => 'tu-correo@icloud.com',
        'from_name'  => 'Mi Llamamiento',
    ],
];
