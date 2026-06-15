const CACHE_NAME = 'enteangadi-cache-v2';

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  // Do not intercept native capacitor.js calls, local-api updates, or admin portal
  if (url.pathname.includes('/capacitor.js') || url.pathname.includes('/admin/')) return;

  // Determine if it is a static asset (CSS, JS, Fonts, Images)
  const isStaticAsset = 
    url.pathname.includes('/assets/css/') || 
    url.pathname.includes('/assets/js/') || 
    url.pathname.includes('/uploads/logo/') ||
    event.request.destination === 'font' ||
    event.request.destination === 'image' ||
    event.request.url.includes('cdnjs.cloudflare.com') ||
    event.request.url.includes('fonts.googleapis.com') ||
    event.request.url.includes('fonts.gstatic.com');

  if (isStaticAsset) {
    // Cache-First (with background revalidation)
    event.respondWith(
      caches.open(CACHE_NAME).then(cache => {
        return cache.match(event.request).then(cachedResponse => {
          const fetchPromise = fetch(event.request).then(networkResponse => {
            if (networkResponse.status === 200) {
              cache.put(event.request, networkResponse.clone());
            }
            return networkResponse;
          }).catch(() => {});

          return cachedResponse || fetchPromise;
        });
      })
    );
  } else {
    // Network-First with Cache Fallback (for dynamic pages and local API calls)
    event.respondWith(
      fetch(event.request)
        .then(networkResponse => {
          // If it is a page document, cache it for offline fallback
          if (networkResponse.status === 200 && event.request.destination === 'document') {
            const responseClone = networkResponse.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, responseClone);
            });
          }
          return networkResponse;
        })
        .catch(() => {
          return caches.match(event.request).then(cachedResponse => {
            return cachedResponse || new Response('Offline: Connection lost.', {
              status: 503,
              statusText: 'Service Unavailable',
              headers: { 'Content-Type': 'text/plain' }
            });
          });
        })
    );
  }
});
