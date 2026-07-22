const CACHE_NAME = 'kodeweb-lite-pwa-cache-v3';

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
    const url = new URL(e.request.url);

    if (e.request.method === 'POST' && url.pathname.includes('share_target')) {
        e.respondWith((async () => {
            try {
                const formData = await e.request.formData();
                const files = formData.getAll('shared_file');
                const text = formData.get('text') || '';
                const sharedUrl = formData.get('url') || '';
                
                const cache = await caches.open('kodeweb-shared-cache');
                
                let fileCount = 0;
                if (files && files.length > 0) {
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        if (file instanceof File && file.name) {
                            await cache.put(
                                new Request('/shared-file-' + i),
                                new Response(file, { headers: { 'Content-Type': file.type, 'Content-Disposition': `attachment; filename="${file.name}"` } })
                            );
                            fileCount++;
                        }
                    }
                }
                
                let redirectUrl = new URL('./', e.request.url);
                redirectUrl.searchParams.set('shared_files', fileCount);
                if (text || sharedUrl) {
                    redirectUrl.searchParams.set('shared_text', text);
                    redirectUrl.searchParams.set('shared_url', sharedUrl);
                }
                
                return Response.redirect(redirectUrl.href, 303);
            } catch (err) {
                const errUrl = new URL('./', e.request.url);
                errUrl.searchParams.set('share_error', '1');
                return Response.redirect(errUrl.href, 303);
            }
        })());
        return;
    }

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
