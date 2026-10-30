const ASSET_CACHE = 'enteangadi-assets-v11';
const PAGE_CACHE = 'enteangadi-pages-v11';
const VALID_CACHES = [ASSET_CACHE, PAGE_CACHE];

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(names.map((n) => (!VALID_CACHES.includes(n) ? caches.delete(n) : null))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  const action = event.data && (event.data.action || event.data);
  if (action === 'clearCache' || action === 'CLEAR_CACHE') {
    event.waitUntil(
      caches.keys().then((keys) => Promise.all(keys.map((k) => caches.delete(k)))).then(() => {
        if (event.ports && event.ports[0]) event.ports[0].postMessage({ success: true });
      })
    );
  } else if (action === 'clearPageCache') {
    event.waitUntil(caches.delete(PAGE_CACHE));
  }
});

function buildCleanPageResponse(res) {
  return res.clone().arrayBuffer().then((buf) => {
    const headers = new Headers(res.headers);
    headers.set('X-SW-Cached-At', String(Date.now()));
    return new Response(buf, {
      status: res.status,
      statusText: res.statusText,
      headers
    });
  });
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Any state-changing POST/PUT/DELETE invalidates preloaded HTML pages immediately
  if (req.method !== 'GET') {
    if (url.origin === self.location.origin) {
      event.waitUntil(caches.delete(PAGE_CACHE).catch(() => {}));
    }
    return;
  }

  // Never intercept APIs, Admin, Capacitor bridge, or dynamic auth/state URLs
  if (
    url.pathname.includes('/api/') ||
    url.pathname.includes('api_') ||
    url.pathname.includes('/admin/') ||
    url.pathname.includes('/capacitor.js') ||
    url.pathname.endsWith('chat.php')
  ) {
    return;
  }

  if (
    url.pathname.endsWith('logout.php') ||
    url.searchParams.has('logged_out') ||
    url.searchParams.has('lang') ||
    url.searchParams.has('lang_changed') ||
    url.searchParams.has('loc_set')
  ) {
    event.waitUntil(caches.delete(PAGE_CACHE).catch(() => {}));
    return;
  }

  // 1. Static Assets + Optimized Logos, Category Icons & Versioned Product Thumbnails: Stale-While-Revalidate
  const isCacheableUpload =
    url.pathname.includes('/uploads/logo/') ||
    url.pathname.includes('/uploads/categories/') ||
    (url.pathname.includes('/uploads/products/') && url.searchParams.has('v'));

  const isStaticAsset =
    isCacheableUpload ||
    url.pathname.includes('/assets/css/') ||
    url.pathname.includes('/assets/js/') ||
    req.destination === 'font' ||
    req.url.includes('cdnjs.cloudflare.com') ||
    req.url.includes('fonts.googleapis.com') ||
    req.url.includes('fonts.gstatic.com');

  if (isStaticAsset) {
    event.respondWith(
      caches.open(ASSET_CACHE).then((cache) =>
        cache.match(req).then((cached) => {
          const networkFetch = fetch(req)
            .then((netRes) => {
              if (netRes && netRes.status === 200) {
                cache.put(req, netRes.clone());
              }
              return netRes;
            })
            .catch(() => cached);
          return cached || networkFetch;
        })
      )
    );
    return;
  }

  // Never serve cached feed/dashboard pages so Guest & User modes always list 100% live ads
  const isLiveFeedOrDash =
    url.pathname === '/' ||
    url.pathname.endsWith('/index.php') ||
    url.pathname.endsWith('my_ads.php') ||
    url.pathname.endsWith('inbox.php');

  // 2. Background Preload Requests (X-Enteangadi-Preload: 1) -> Populate PAGE_CACHE for detail/static pages
  const isPreload = req.headers.get('X-Enteangadi-Preload') === '1';
  if (isPreload && url.origin === self.location.origin) {
    if (isLiveFeedOrDash) {
      return;
    }
    event.respondWith(
      caches.open(PAGE_CACHE).then(async (cache) => {
        const existing = await cache.match(url.href);
        if (existing) {
          const cachedAt = Number(existing.headers.get('X-SW-Cached-At') || 0);
          if (Date.now() - cachedAt < 25000) return existing;
        }
        const netRes = await fetch(req);
        if (netRes && netRes.status === 200 && (netRes.headers.get('content-type') || '').includes('text/html')) {
          const targetUrl = netRes.url || url.href;
          const clean = await buildCleanPageResponse(netRes);
          await cache.put(targetUrl, clean.clone());
          if (targetUrl !== url.href) {
            await cache.put(url.href, clean.clone());
          }
          return clean;
        }
        return netRes;
      })
    );
    return;
  }

  // 3. HTML Page Navigations -> Network-First for live feeds, Instant Hit for preloaded detail pages
  const isHtmlNav =
    req.mode === 'navigate' ||
    req.destination === 'document' ||
    (url.origin === self.location.origin && (url.pathname.endsWith('.php') || url.pathname.endsWith('/')) && req.headers.get('X-Requested-With') !== 'XMLHttpRequest');

  if (isHtmlNav && url.origin === self.location.origin) {
    event.respondWith(
      caches.open(PAGE_CACHE).then(async (cache) => {
        const cached = await cache.match(url.href);

        const refreshInBg = fetch(req)
          .then(async (netRes) => {
            if (netRes && netRes.status === 200 && !netRes.redirected && (netRes.headers.get('content-type') || '').includes('text/html')) {
              const clean = await buildCleanPageResponse(netRes);
              cache.put(url.href, clean).catch(() => {});
            }
            return netRes;
          })
          .catch(() => null);

        if (!isLiveFeedOrDash && cached) {
          const age = Date.now() - Number(cached.headers.get('X-SW-Cached-At') || 0);
          if (age < 30000) {
            event.waitUntil(refreshInBg);
            return cached;
          }
        }

        const liveRes = await refreshInBg;
        if (liveRes) return liveRes;
        if (cached) return cached;

        return new Response(
          '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline - Enteangadi</title></head><body style="font-family:sans-serif;text-align:center;padding:60px 20px;"><h2 style="color:#2E7D32;">Enteangadi</h2><p style="color:#64748b;">Connection lost. Please check your internet connection.</p><button onclick="window.location.reload()" style="padding:10px 24px;border-radius:12px;background:#2E7D32;color:#fff;border:none;cursor:pointer;font-weight:700;">Retry</button></body></html>',
          { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
      })
    );
  }
});
