// Service worker de Hospital Nido (módulo investigación y prototipo NativePHP: extensión móvil como PWA instalable).
// Guarda en caché solo la estructura de la aplicación (HTML, JS, CSS e íconos). Los datos clínicos de la API
// NUNCA se guardan en el dispositivo: siempre se piden al servidor.
const CACHE = 'nido-shell-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(['/nido.svg', '/icon-192.png', '/icon-512.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))));
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) {
        return;
    }
    // Recursos versionados de /build: primero caché. Páginas: primero red, y la caché solo si no hay conexión.
    if (url.pathname.startsWith('/build/') || url.pathname.endsWith('.png') || url.pathname.endsWith('.svg')) {
        event.respondWith(caches.match(event.request).then((hit) => hit || fetch(event.request).then((res) => {
            const copy = res.clone();
            caches.open(CACHE).then((cache) => cache.put(event.request, copy));
            return res;
        })));
        return;
    }
    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).then((res) => {
            const copy = res.clone();
            caches.open(CACHE).then((cache) => cache.put('/', copy));
            return res;
        }).catch(() => caches.match('/')));
    }
});
