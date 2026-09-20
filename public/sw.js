/*
 * Service worker GESTAVI Terrain.
 *
 * - Assets (build Vite, icônes, manifest) : cache d'abord, mise à jour en arrière-plan.
 * - Pages « terrain » : réseau d'abord, repli sur la dernière version en cache hors-ligne.
 * - API terrain (GET flocks / vaccinations) : réseau d'abord, repli cache (l'application
 *   garde aussi une copie dans IndexedDB).
 * - POST (synchronisation) : jamais mis en cache ; la file d'attente vit dans IndexedDB.
 *
 * La liste des assets à pré-cacher vient de /offline/assets.json (manifest Vite).
 */
const VERSION = 'gestavi-terrain-v1';
const ASSET_CACHE = `${VERSION}-assets`;
const PAGE_CACHE = `${VERSION}-pages`;
const API_CACHE = `${VERSION}-api`;

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(ASSET_CACHE);

        try {
            const response = await fetch('/offline/assets.json', { cache: 'no-store' });
            const { assets } = await response.json();
            await cache.addAll([...assets, '/manifest.webmanifest', '/icons/icon-192.svg', '/icons/icon-512.svg']);
        } catch (error) {
            // Hors-ligne à l'installation : les assets seront mis en cache à la volée
        }

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest') {
        event.respondWith(cacheFirst(request, ASSET_CACHE));

        return;
    }

    if (/^\/farm\/[^/]+\/terrain\/api\//.test(url.pathname)) {
        event.respondWith(networkFirst(request, API_CACHE));

        return;
    }

    if (/^\/farm\/[^/]+\/terrain\/?$/.test(url.pathname)) {
        event.respondWith(networkFirst(request, PAGE_CACHE));
    }
});

async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    if (cached) {
        // Rafraîchissement silencieux
        fetch(request).then((response) => response.ok && cache.put(request, response.clone())).catch(() => {});

        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        cache.put(request, response.clone());
    }

    return response;
}

async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);

    try {
        const response = await fetch(request);

        if (response.ok) {
            cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        const cached = await cache.match(request);

        if (cached) {
            return cached;
        }

        return new Response('<!DOCTYPE html><html lang="fr"><body style="font-family:sans-serif;padding:2rem;text-align:center"><h1>Hors-ligne</h1><p>Cette page n\'a pas encore été chargée en ligne sur cet appareil.</p></body></html>', {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
}
