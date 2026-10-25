/* Only this public offline notice is cached. All app data and writes stay online-only. */
const CACHE_PREFIX = 'dtc-ems-offline-';
const CACHE_NAME = CACHE_PREFIX + 'v2';
const OFFLINE_URL = new URL('offline.html', self.registration.scope).href;

self.addEventListener('install', function (event) {
    event.waitUntil((async function () {
        const response = await fetch(OFFLINE_URL, { cache: 'reload', credentials: 'omit', redirect: 'error' });
        if (!response.ok || !(response.headers.get('content-type') || '').includes('text/html')) {
            throw new Error('Offline notice unavailable');
        }
        const cache = await caches.open(CACHE_NAME);
        await cache.put(OFFLINE_URL, response);
    })());
});

self.addEventListener('activate', function (event) {
    event.waitUntil((async function () {
        const names = await caches.keys();
        await Promise.all(names.filter(name => name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME).map(name => caches.delete(name)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', function (event) {
    const request = event.request;
    if (request.method !== 'GET' || request.mode !== 'navigate' || new URL(request.url).origin !== self.location.origin) return;
    event.respondWith((async function () {
        try {
            // Never save portal pages, proofs, receipts, API results, or authentication responses.
            return await fetch(request, { cache: 'no-store' });
        } catch (error) {
            const cache = await caches.open(CACHE_NAME);
            return await cache.match(OFFLINE_URL) || new Response('You are offline. Reconnect and try again.', {
                status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' }
            });
        }
    })());
});
