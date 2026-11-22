/** app.js — راه‌اندازی مشترک همه‌ی صفحات: Header/Footer، SEO پایه، تم */
document.addEventListener('DOMContentLoaded', layout);

/** ورود نرم بخش‌ها هنگام اسکرول (روی موبایل و دسکتاپ یکسان؛ با reduced-motion خاموش) */
const _io = 'IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion:reduce)').matches
  ? new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); _io.unobserve(e.target); } }), { threshold: .08 }) : null;
function reveal(els) { [...els].forEach(x => { if (_io) { x.classList.add('rv'); _io.observe(x); } }); }
document.addEventListener('DOMContentLoaded', () => reveal(document.querySelectorAll('main > section, main > .band')));

/* ───── افکت‌های زنده (مشترک موبایل و دسکتاپ) ───── */
function wordsIn(el) { if (!el || RM()) return; el.innerHTML = el.textContent.split(/\s+/).filter(Boolean).map((w, i) => `<span class="w" style="--d:${i * 90}ms">${esc(w)}</span>`).join(' '); }
document.addEventListener('DOMContentLoaded', () => {
  if (RM()) return;
  // نوار پیشرفت اسکرول + پارالاکس هیرو
  const bar = document.createElement('div'); bar.id = 'prog'; bar.setAttribute('aria-hidden', 'true'); document.body.prepend(bar);
  const hero = document.querySelector('.hero'); let tick = false;
  const upd = () => { tick = false; const h = document.documentElement, y = h.scrollTop || document.body.scrollTop, m = h.scrollHeight - h.clientHeight; bar.style.transform = `scaleX(${m > 0 ? y / m : 0})`; if (hero && y < 900) hero.style.setProperty('--py', (y * .18) + 'px'); };
  addEventListener('scroll', () => { if (!tick) { tick = true; requestAnimationFrame(upd); } }, { passive: true }); upd();
  // حباب‌ها و ستاره‌های شناور در هیرو
  if (hero) for (let i = 0; i < 16; i++) { const b = document.createElement('i'); b.className = 'bub' + (i % 3 === 0 ? ' st' : ''); b.setAttribute('aria-hidden', 'true');
    b.style.cssText = `inset-inline-start:${Math.random() * 100}%;--s:${8 + Math.random() * 26}px;--t:${7 + Math.random() * 9}s;animation-delay:${-Math.random() * 12}s`; hero.appendChild(b); }
  // موج لمس/کلیک
  document.addEventListener('pointerdown', e => { const t = e.target.closest('.btn,.card,.gal-item'); if (!t) return; const r = t.getBoundingClientRect(), d = Math.max(r.width, r.height) * 1.6, s = document.createElement('span');
    s.className = 'rip'; s.style.cssText = `width:${d}px;height:${d}px;left:${e.clientX - r.left - d / 2}px;top:${e.clientY - r.top - d / 2}px`; t.appendChild(s); setTimeout(() => s.remove(), 700); }, { passive: true });
});

/* لودینگ ورود، آیکن‌ها و توقف انیمیشن‌های خارج از دید (سبک‌تر برای موبایل) */
(() => {
  if (!document.body) return;
  document.body.insertAdjacentHTML('afterbegin', `<div id="ld" aria-hidden="true"><i></i><img src="${ASSET}logo.png" alt="" width="64" height="74"></div>`);
  const off = () => { const l = document.getElementById('ld'); if (!l) return; l.classList.add('done'); setTimeout(() => l.remove(), 700); };
  if (document.readyState === 'complete') off(); else addEventListener('load', off); setTimeout(off, 4000);
  document.addEventListener('DOMContentLoaded', () => {
    iconify();
    if ('IntersectionObserver' in window) { const o = new IntersectionObserver(es => es.forEach(e => e.target.classList.toggle('off', !e.isIntersecting)), { rootMargin: '120px' }); document.querySelectorAll('main>section,.hero').forEach(x => o.observe(x)); }
  });
})();
