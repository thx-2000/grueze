// GRUEZE Service Worker – bewusst minimal.
// Gecacht werden ausschließlich statische Assets (CSS/JS/Schriften/Icon).
// Seiten, Formulare und alles unter Login laufen immer direkt übers Netz.
// Cache-Name bei Bedarf hochzählen, um alte Einträge zu verwerfen.
const CACHE = 'grueze-assets-v2';
const ASSET_PREFIXES = ['/assets/', '/app-icon.svg'];

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (!ASSET_PREFIXES.some((p) => url.pathname.startsWith(p))) return; // Seiten unangetastet.

    event.respondWith(
        caches.open(CACHE).then((cache) => cache.match(request).then((hit) => {
            const network = fetch(request)
                .then((response) => {
                    if (response && response.ok) cache.put(request, response.clone());
                    return response;
                })
                .catch(() => hit);
            return hit || network;
        }))
    );
});
