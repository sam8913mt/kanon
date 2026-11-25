/** pwa.js — ثبت Service Worker و دکمه‌ی «نصب برنامه» (اندروید/کروم) + راهنمای iOS */
(() => {
  const demo = !!(window.CFG && CFG.DEMO_MODE);          // نسخه‌ی GitHub Pages: مسیرهای نسبی
  const swUrl = demo ? 'sw.js' : '/sw.js', scope = demo ? './' : '/';
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    addEventListener('load', () => navigator.serviceWorker.register(swUrl, { scope }).catch(() => {}));
  }

  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  if (standalone) return;
  const KEY = 'pwa-hide-until';
  const hidden = () => { try { return Date.now() < +localStorage.getItem(KEY); } catch (_) { return false; } };
  const hideFor = days => { try { localStorage.setItem(KEY, String(Date.now() + days * 864e5)); } catch (_) {} };

  function bar(text, actionText, onAction) {
    if (document.getElementById('pwa-bar') || hidden()) return;
    const b = document.createElement('div'); b.id = 'pwa-bar'; b.setAttribute('role', 'dialog');
    b.style.cssText = 'position:fixed;inset-inline:12px;bottom:12px;z-index:500;display:flex;align-items:center;gap:10px;max-width:460px;margin-inline:auto;padding:10px 14px;border-radius:16px;background:#243B64;color:#fff;box-shadow:0 10px 30px rgba(0,0,0,.35);font:14px/1.7 inherit';
    const t = document.createElement('span'); t.textContent = text; t.style.cssText = 'flex:1';
    const x = document.createElement('button'); x.type = 'button'; x.textContent = '✕'; x.setAttribute('aria-label', 'بستن');
    x.style.cssText = 'background:none;border:0;color:#fff;font-size:16px;cursor:pointer;opacity:.8';
    x.onclick = () => { hideFor(14); b.remove(); };
    b.append(t);
    if (actionText) {
      const a = document.createElement('button'); a.type = 'button'; a.textContent = actionText;
      a.style.cssText = 'border:0;border-radius:999px;padding:8px 18px;background:#fff;color:#243B64;font:inherit;font-weight:700;cursor:pointer';
      a.onclick = onAction; b.append(a);
    }
    b.append(x); document.body.appendChild(b);
  }

  let ev = null;
  addEventListener('beforeinstallprompt', e => {
    e.preventDefault(); ev = e;
    bar('رویش را به‌صورت برنامه نصب کنید', 'نصب', async () => {
      document.getElementById('pwa-bar')?.remove();
      ev.prompt(); try { await ev.userChoice; } catch (_) {} ev = null;
    });
  });
  addEventListener('appinstalled', () => document.getElementById('pwa-bar')?.remove());

  // iOS (Safari) رویداد نصب ندارد؛ فقط راهنما
  if (/iphone|ipad|ipod/i.test(navigator.userAgent) && /safari/i.test(navigator.userAgent) && !/crios|fxios/i.test(navigator.userAgent)) {
    addEventListener('load', () => setTimeout(() => bar('برای نصب: دکمه‌ی اشتراک‌گذاری (Share) ← «Add to Home Screen»', '', null), 2500));
  }
})();
