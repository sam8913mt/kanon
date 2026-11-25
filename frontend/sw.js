/* sw.js — Service Worker کانون نخبه‌پروری رویش (PWA)
 * - فایل‌های ایستا (css/js/فونت/تصویر سایت): ابتدا کش، همزمان به‌روزرسانی در پس‌زمینه
 * - صفحه‌ها: ابتدا شبکه؛ بدون اینترنت نسخه‌ی کش‌شده یا صفحه‌ی آفلاین
 * - API، پنل ادمین و آپلودها هرگز کش نمی‌شوند (داده‌ی زنده/شخصی) */
const VERSION = 'royesh-v3.5.4';
const STATIC = VERSION + '-static';
const PAGES = VERSION + '-pages';
const SCOPE = self.registration.scope;
const OFFLINE = new URL('offline', SCOPE).href;
const PRECACHE = [OFFLINE, new URL('assets/logo.png', SCOPE).href, new URL('assets/icons/icon-192.png', SCOPE).href];

const clean = async r => new Response(await r.blob(), { status: r.status, statusText: r.statusText, headers: r.headers }); // حذف پرچم redirected

self.addEventListener('install', e => {
  e.waitUntil((async () => {
    const c = await caches.open(STATIC);
    await Promise.all(PRECACHE.map(async u => { try { const r = await fetch(u, { cache: 'reload' }); if (r.ok) await c.put(u, await clean(r)); } catch (_) {} }));
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', e => {
  e.waitUntil((async () => {
    for (const k of await caches.keys()) if (!k.startsWith(VERSION)) await caches.delete(k);
    await self.clients.claim();
  })());
});

const isStatic = u => /\/(css|js|fonts|assets)\//.test(u.pathname) || /\.(css|js|woff2?|png|jpe?g|webp|svg|ico)$/.test(u.pathname);
const never = u => /^\/(api|admin|backend|uploads)(\/|$)/.test(u.pathname.replace(new URL(SCOPE).pathname.replace(/\/$/, ''), '') || '/');

self.addEventListener('fetch', e => {
  const req = e.request, u = new URL(req.url);
  if (req.method !== 'GET' || u.origin !== location.origin || never(u)) return;

  if (req.mode === 'navigate') {
    e.respondWith((async () => {
      try {
        const r = await fetch(req);
        if (r.ok && !r.redirected) { const c = await caches.open(PAGES); c.put(req, r.clone()); }
        return r;
      } catch (_) {
        return (await caches.match(req)) || (await caches.match(OFFLINE)) || new Response('آفلاین', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
      }
    })());
    return;
  }

  if (isStatic(u)) {
    e.respondWith((async () => {
      const c = await caches.open(STATIC);
      const hit = await c.match(req);
      const net = fetch(req).then(r => { if (r.ok) c.put(req, r.clone()); return r; }).catch(() => null);
      return hit || (await net) || Response.error();
    })());
  }
});
