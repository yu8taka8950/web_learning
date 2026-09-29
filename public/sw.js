const CACHE_NAME = 'web-learning-static-v1';
const CACHE_PREFIX = 'web-learning-';
const SCOPE_URL = new URL(self.registration.scope);
const OFFLINE_URL = new URL('offline.html', SCOPE_URL).toString();
const PRECACHE_PATHS = Object.freeze([
    "offline.html",
    "images/pwa/icon-192.png",
    "images/pwa/icon-512.png",
    "images/pwa/icon-maskable-192.png",
    "images/pwa/icon-maskable-512.png",
    "images/pwa/apple-touch-icon.png"
]);
const precacheUrls = PRECACHE_PATHS.map((path) => new URL(path, SCOPE_URL).toString());
const PRECACHE_URLS = new Set(precacheUrls);

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(precacheUrls)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => Promise.all(
            cacheNames
                .filter((cacheName) => cacheName.startsWith(CACHE_PREFIX) && cacheName !== CACHE_NAME)
                .map((cacheName) => caches.delete(cacheName)),
        )),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));

        return;
    }

    if (!PRECACHE_URLS.has(request.url)) {
        return;
    }

    event.respondWith(caches.match(request).then((cachedResponse) => cachedResponse || fetch(request)));
});
