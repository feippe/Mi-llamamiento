<?php
$files = [
    __DIR__ . '/app.html',
    __DIR__ . '/assets/css/app.css',
    __DIR__ . '/assets/js/app.js',
];
$v = 0;
foreach ($files as $f) { if (file_exists($f)) $v = max($v, filemtime($f)); }

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
?>
const CACHE = 'mi-llamamiento-<?= $v ?>';
const SHELL = [
  '/',
  '/app.html',
  '/manifest.json',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/icons/logo.svg',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/api/') || url.pathname.endsWith('.php')) return;
  event.respondWith(
    caches.match(event.request)
      .then(cached => cached || fetch(event.request).catch(() => caches.match('/app.html')))
  );
});
