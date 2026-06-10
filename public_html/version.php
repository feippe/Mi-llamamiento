<?php
$files = [
    __DIR__ . '/app.html',
    __DIR__ . '/assets/css/app.css',
    __DIR__ . '/assets/js/app.js',
];
$v = 0;
foreach ($files as $f) { if (file_exists($f)) $v = max($v, filemtime($f)); }
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
echo $v;
