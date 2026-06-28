<?php
/**
 * Diagnóstico temporal de envío de correo (SMTP iCloud).
 * Uso:  /maildiag.php?k=diag-7f3a9c2e&to=TU-EMAIL
 * BORRAR este archivo cuando termine el diagnóstico.
 */

require_once __DIR__ . '/../app/core/helpers.php';
require_once base_path('app/services/MailService.php');

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

const DIAG_KEY = 'diag-7f3a9c2e';

if (($_GET['k'] ?? '') !== DIAG_KEY) {
    http_response_code(403);
    echo "Acceso denegado.\n";
    exit;
}

$config = require base_path('app/config/config.php');
$m = $config['mail'] ?? [];

echo "=== Config mail (sin password) ===\n";
echo 'host       : ' . ($m['host'] ?? '(vacío)') . "\n";
echo 'port       : ' . ($m['port'] ?? '(vacío)') . "\n";
echo 'encryption : ' . ($m['encryption'] ?? '(vacío)') . "\n";
echo 'username   : ' . ($m['username'] ?? '(vacío)') . "\n";
echo 'from       : ' . ($m['from'] ?? '(vacío)') . "\n";
echo 'password   : ' . (empty($m['password']) ? '(VACÍO)' : strlen((string) $m['password']) . ' caracteres') . "\n";
echo "\n";

$to = $_GET['to'] ?? '';
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "Agrega &to=TU-EMAIL para probar el envío.\n";
    exit;
}

$mail = new MailService();
echo '=== Envío de prueba a ' . $to . " ===\n";
echo 'Configurado: ' . ($mail->isConfigured() ? 'sí' : 'NO') . "\n";

$ok = $mail->send(
    $to,
    'Prueba',
    'Prueba de correo — Mi Llamamiento',
    '<p>Esto es una prueba de envío desde Mi Llamamiento.</p>',
    'Esto es una prueba de envío desde Mi Llamamiento.'
);

echo 'Resultado: ' . ($ok ? 'ENVIADO OK ✅' : 'FALLÓ ❌') . "\n";
echo 'Detalle  : ' . ($mail->lastError() ?? '(sin error)') . "\n";
