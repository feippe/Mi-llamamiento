const CACHE_NAME = 'mi-llamamiento-v1';
const APP_SHELL = [
  './',
  './index.html',
  './manifest.json',
  './assets/css/app.css',
  './assets/js/app.js',
  './assets/js/db.js',
  './assets/js/store.js',
  './assets/js/api.js',
  './assets/icons/icon.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(fetch(request).catch(() => new Response(JSON.stringify({ error: { code: 'OFFLINE', message: 'Sin conexión' } }), { status: 503, headers: { 'Content-Type': 'application/json' } })));
    return;
  }
  event.respondWith(
    caches.match(request).then(cached => cached || fetch(request).then(response => {
      const copy = response.clone();
      caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
      return response;
    }).catch(() => caches.match('./index.html')))
  );
});

self.addEventListener('sync', event => {
  if (event.tag === 'sync-queue') {
    event.waitUntil(self.clients.matchAll().then(clients => clients.forEach(client => client.postMessage({ type: 'SYNC_REQUESTED' }))));
  }
});

self.addEventListener('push', event => {
  const data = event.data ? event.data.json() : { title: 'Mi Llamamiento', body: 'Tienes un aviso nuevo.' };
  event.waitUntil(self.registration.showNotification(data.title, { body: data.body, icon: './assets/icons/icon.svg', data: data.data || {} }));
});
