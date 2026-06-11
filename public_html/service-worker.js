const CACHE = 'mi-llamamiento-mvp-v24';
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
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE).map(key => caches.delete(key)))));
  self.clients.claim();
});

self.addEventListener('message', event => {
  if (event.data === 'getVersion') {
    event.source.postMessage({ type: 'version', version: CACHE });
  }
});

self.addEventListener('push', event => {
  let data = { title: 'Mi Llamamiento', body: '' };
  if (event.data) {
    try { data = event.data.json(); } catch (_) {}
  }
  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: '/assets/icons/icon-192.png',
      badge: '/assets/icons/icon-192.png',
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const client of list) {
        if (client.url.includes(self.location.origin) && 'focus' in client) return client.focus();
      }
      if (clients.openWindow) return clients.openWindow('/');
    })
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/api/')) {
    return;
  }
  event.respondWith(
    caches.match(event.request).then(cached => cached || fetch(event.request).catch(() => caches.match('/app.html')))
  );
});
