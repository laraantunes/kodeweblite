const CACHE_NAME = 'kodeweb-lite-pwa-cache-v1';

self.addEventListener('install', (e) => {
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keyList) => {
            return Promise.all(keyList.map((key) => {
                if (key !== CACHE_NAME) {
                    return caches.delete(key);
                }
            }));
        })
    );
    return self.clients.claim();
});

self.addEventListener('fetch', (e) => {
    if (e.request.method !== 'GET') {
        return;
    }
    
    const url = new URL(e.request.url);
    
    // Ignore API calls and query strings related to actions to avoid cache mismatches on dynamic actions
    if (url.pathname.includes('/api/') || url.search.includes('action=')) {
        return;
    }

    e.respondWith(
        fetch(e.request)
            .then((response) => {
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(e.request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                return caches.match(e.request);
            })
    );
});
