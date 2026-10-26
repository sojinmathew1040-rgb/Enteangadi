const CACHE_NAME = 'enteangadi-cache-v6';

self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            console.log('[SW] Deleting stale cache:', cache);
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Allow client code to trigger cache purge on demand (e.g., after ad deletion or logo update)
self.addEventListener('message', event => {
  if (event.data && (event.data.action === 'clearCache' || event.data === 'CLEAR_CACHE')) {
    caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))));
    if (event.ports && event.ports[0]) {
      event.ports[0].postMessage({ success: true });
    }
  }
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  // 1. NEVER intercept or cache:
  // - API endpoints (/api/, api_)
  // - Admin portal (/admin/)
  // - User uploads (/uploads/ - product photos, logos, posters, profile avatars change and delete frequently)
  // - Capacitor/native bridge scripts
  if (
    url.pathname.includes('/api/') ||
    url.pathname.includes('api_') ||
    url.pathname.includes('/admin/') ||
    url.pathname.includes('/uploads/') ||
    url.pathname.includes('/capacitor.js')
  ) {
    return; // Pass directly to network
  }

  // 2. Core CSS/JS and external Fonts: Stale-While-Revalidate
  const isStaticCoreAsset = 
    url.pathname.includes('/assets/css/') || 
    url.pathname.includes('/assets/js/') || 
    event.request.destination === 'font' ||
    event.request.url.includes('cdnjs.cloudflare.com') ||
    event.request.url.includes('fonts.googleapis.com') ||
    event.request.url.includes('fonts.gstatic.com');

  if (isStaticCoreAsset) {
    event.respondWith(
      caches.open(CACHE_NAME).then(cache => {
        return cache.match(event.request).then(cachedResponse => {
          const fetchPromise = fetch(event.request).then(networkResponse => {
            if (networkResponse && networkResponse.status === 200) {
              cache.put(event.request, networkResponse.clone());
            }
            return networkResponse;
          }).catch(() => {});

          return cachedResponse || fetchPromise;
        });
      })
    );
    return;
  }

  // 3. Dynamic HTML Pages & Documents: Network-First
  // Always fetch fresh from network to reflect deleted ads, updated listings, and user actions
  if (event.request.destination === 'document' || url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
    event.respondWith(
      fetch(event.request)
        .then(networkResponse => {
          return networkResponse;
        })
        .catch(() => {
          // Only show offline notice when there is completely no network connection
          return caches.match(event.request).then(cached => {
            return cached || new Response(
              '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Offline - Enteangadi</title></head><body style="font-family:sans-serif;text-align:center;padding:50px 20px;"><h2 style="color:#059669;">Enteangadi</h2><p>Connection lost. Please check your internet connection.</p><button onclick="window.location.reload()" style="padding:10px 24px;border-radius:10px;background:#059669;color:#fff;border:none;cursor:pointer;font-weight:bold;">Retry</button></body></html>',
              { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
            );
          });
        })
    );
    return;
  }
});
