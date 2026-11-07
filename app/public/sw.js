// App shell offline (NFR-02). Pages: network first, cached copy when offline. Static assets: cache first.
// API calls are never cached; visits waiting to upload live in IndexedDB (src/lib/local.ts).
const VERSION = 'ms-v2';
const SHELL = ['/home', '/find', '/new-shop', '/capture', '/processing', '/review', '/visits', '/login', '/owner/shop', '/manifest.webmanifest', '/icon.svg'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(VERSION).then(c => c.addAll(SHELL).catch(() => {})).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== VERSION).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const req = e.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== self.location.origin) return;
  // React Server Component payloads (client-side navigation): network only. When offline the fetch fails and
  // Next falls back to a full page load, which the cached page below serves. Never answer them with HTML.
  if (req.headers.get('RSC') || url.searchParams.has('_rsc')) return;
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req)
        .then(res => { const copy = res.clone(); caches.open(VERSION).then(c => c.put(url.pathname, copy)); return res; })
        .catch(async () => (await caches.match(url.pathname, { ignoreVary: true })) || (await caches.match('/home', { ignoreVary: true })) || Response.error()),
    );
    return;
  }
  if (url.pathname.startsWith('/_next/static/') || /\.(svg|png|webmanifest|css|js)$/.test(url.pathname)) {
    e.respondWith(caches.match(req).then(hit => hit || fetch(req).then(res => { const copy = res.clone(); caches.open(VERSION).then(c => c.put(req, copy)); return res; })));
  }
});
