/**
 * components.js — اجزای مشترک: Header/Footer، کارت‌ها، Gallery و Lightbox (سئو در js/seo.js)
 */

/* ───────────── Header / Footer ───────────── */
const siteP = () => window._sp ??= api('/site.php');
const DEFAULT_MENU = [
  { title: 'اردوها', link: 'camps.html' }, { title: 'آلبوم‌ها', link: 'albums.html' }, { title: 'اخبار', link: 'news.html' },
  { title: 'درباره کانون', link: 'about.html' }, { title: 'کانال‌ها', link: 'channels.html' }, { title: 'تماس', link: 'contact.html' },
];

async function layout() {
  const S = (await siteP()).data || {}, s = S.settings || {};
  const name = s.site_name || 'کانون نخبه‌پروری رویش';
  const logo = s.logo ? upl(s.logo) : ASSET + 'logo.png';
  const menu = S.menu && S.menu.length ? S.menu : DEFAULT_MENU;

  // رنگ‌های قابل تنظیم از پنل (فقط مقدار hex معتبر)
  const hex = v => /^#[0-9a-fA-F]{3,8}$/.test(v || '') ? v : '';
  if (hex(s.color_navy) || hex(s.color_plum)) {
    document.head.insertAdjacentHTML('beforeend', `<style>:root:not([data-theme=dark]){${hex(s.color_navy) ? '--navy:' + s.color_navy + ';' : ''}${hex(s.color_plum) ? '--plum:' + s.color_plum : ''}}</style>`);
  }
  if (s.favicon) document.querySelector('link[rel=icon]')?.setAttribute('href', upl(s.favicon));

  siteSEO({ S, s, logo, name }); // منطق سئو در js/seo.js

  const nav = menu.map(m => `<a href="${esc(U.nav(m.link))}">${esc(m.title)}</a>`).join('');
  document.getElementById('hdr').outerHTML =
    `${CFG.DEMO_MODE ? '<div class="demo">حالت نمایشی (Demo) — داده‌ها واقعی نیستند</div>' : ''}
     <a class="skip" href="#main">رفتن به محتوای اصلی</a>
     <header><div class="wrap">
       <a class="brand" href="${U.home()}"><img src="${esc(logo)}" alt="لوگوی ${esc(name)}" width="40" height="46"><span>${esc(name)}</span></a>
       <button id="burger" aria-label="باز و بسته کردن منو" aria-expanded="false" aria-controls="nav">☰</button>
       <nav id="nav" aria-label="منوی اصلی">${nav}</nav>
       ${/^\/(index(\.html)?)?$/.test(location.pathname) ? '' : `<a class="btn alt sw" id="home-btn" href="${U.home()}" title="بازگشت به صفحه اصلی">🏠<span class="lbl"> صفحه اصلی</span></a>`}
       <a class="btn cta" id="reg-cta" href="${U.signup()}">ثبت‌نام</a>
       <button class="btn alt icon" id="th" aria-label="تغییر تم روشن/تاریک">◐</button>
     </div></header>`;

  const flinks = S.footer_links && S.footer_links.length ? S.footer_links : menu;
  const list = (arr, fn) => arr.map(fn).join('') || '';
  document.getElementById('ftr').outerHTML =
    `<footer><div class="wrap fg">
       <div><img src="${esc(logo)}" alt="" height="52"><p>${esc(s.footer_text || '')}</p></div>
       <div><h4>پیوندها</h4>${list(flinks, m => `<a href="${esc(U.nav(m.link))}">${esc(m.title)}</a>`)}${list(S.pages || [], x => `<a href="${esc(U.page(x.slug))}">${esc(x.title)}</a>`)}</div>
       <div><h4>تماس</h4>${list(S.contacts || [], c => `<span>${esc(c.label)}: ${esc(c.value)}</span>`)}</div>
       <div><h4>کانال‌ها و شبکه‌ها</h4>${list(S.channels || [], c => `<a href="${esc(safeUrl(c.url))}" target="_blank" rel="noopener">${esc(c.title)}</a>`)}</div>
     </div><div class="wrap copy">${esc(s.copyright || '© ' + name)} <small>v${esc(CFG.VERSION)}</small></div></footer>`;

  // پیوند «aria-current» برای صفحه‌ی جاری
  document.querySelectorAll('#nav a').forEach(a => {
    if (new URL(a.href, location.href).pathname === location.pathname) a.setAttribute('aria-current', 'page');
  });
  byId('burger').onclick = e => {
    const o = byId('nav').classList.toggle('open');
    e.currentTarget.setAttribute('aria-expanded', o);
  };
  byId('th').onclick = toggleTheme;
  cleanStaticLinks();
  chrome();
  if (!CFG.DEMO_MODE) {
    const [mr, sr] = await Promise.all([api('/member.php'), api('/me.php')]);
    const mm = mr.data?.member;
    if (mm) { const b = byId('reg-cta'); b.href = U.dashboard(); b.textContent = 'داشبورد من'; }
    if (sr.success && sr.data && sr.data.user) { // ادمین/ویرایشگر واردشده: دکمه‌ی رفتن به پنل
      byId('reg-cta').insertAdjacentHTML('beforebegin', '<a class="btn alt sw" id="panel-btn" href="/admin/" title="رفتن به پنل مدیریت">⚙️<span class="lbl"> پنل مدیریت</span></a>');
    }
  }
}

/** لینک‌های ایستای HTML (مثل camps.html) در Production به آدرس تمیز تبدیل می‌شوند */
function cleanStaticLinks() {
  if (CFG.DEMO_MODE) return;
  document.querySelectorAll('a[href$=".html"]').forEach(a => {
    const h = a.getAttribute('href');
    if (!/^https?:|^\//.test(h)) a.setAttribute('href', '/' + h.replace(/\.html$/, '').replace(/^index$/, ''));
  });
}

/* ───────────── کارت‌ها ───────────── */
const STATUS_CLASS = { open: 'open', soon: 'soon', full: 'full', done: 'done', cancelled: 'cancelled' };
const tag = st => `<span class="tag ${STATUS_CLASS[st] || ''}">${esc(ST[st] || '')}</span>`;

/** گروه نمایش اردو در صفحه‌ی اصلی: open | upcoming | done | cancelled */
function campGroup(c) {
  if (c.status === 'cancelled' || c.camp_status === 'cancelled') return 'cancelled';
  if (c.status === 'done' || c.camp_status === 'done') return 'done';
  return c.status === 'open' ? 'open' : 'upcoming';
}

const coverStyle = p => p ? ` style="background-image:url('${esc(upl(p))}')"` : '';

function campCard(c) {
  const left = c.capacity > 0 && c.registered != null ? Math.max(0, c.capacity - c.registered) : null;
  return `<a class="card hov" href="${esc(U.camp(c.slug))}">
    <div class="cv"${coverStyle(c.cover)}></div>
    <div class="bd">${tag(c.status)}<h3>${esc(c.title)}</h3>
    <small>${esc(c.location || '')}${c.start ? ' • ' + esc(fmtDate(c.start)) : ''}${c.capacity ? ' • ظرفیت ' + esc(c.capacity) : ''}${left !== null && c.status === 'open' ? ' • ' + left + ' جای خالی' : ''}</small>
    ${c.capacity > 0 && c.registered != null ? `<div class="prog" role="img" aria-label="${Math.round(100 * c.registered / c.capacity)}٪ ظرفیت پر شده"><i style="--w:${Math.min(100, Math.round(100 * c.registered / c.capacity))}%"></i></div>` : ''}</div></a>`;
}

const albumCard = a => `<a class="card hov" href="${esc(U.album(a.slug))}"><div class="cv"${coverStyle(a.cover)}></div><div class="bd"><h3>${esc(a.title)}</h3><small>${esc(fmtDate(a.date))}</small></div></a>`;
const newsCard = n => `<a class="card hov" href="${esc(U.news(n.slug))}">${n.cover ? `<div class="cv"${coverStyle(n.cover)}></div>` : ''}<div class="bd"><h3>${esc(n.title)}</h3><p>${esc(n.summary || '')}</p><small>${esc(fmtDate(n.date))}</small></div></a>`;

/* ───────────── Gallery + Lightbox ───────────── */
const Lightbox = (() => {
  let items = [], i = 0, root = null, opener = null, z = { s: 1, x: 0, y: 0 }, drag = null, suppress = false;
  const fa = n => new Intl.NumberFormat('fa-IR').format(n);

  function stageHtml(m) {
    if (m.type === 'video') { // ویدئو: Embed امن (آپارات/یوتیوب/ویمیو) یا فایل مستقیم با پخش‌کننده‌ی مرورگر
      if (m.embed) return `<iframe class="lb-vid" src="${esc(m.embed)}" title="${esc(m.title || 'ویدئو')}" allowfullscreen referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-presentation"></iframe>`;
      return `<video class="lb-vid" controls preload="metadata" src="${esc(upl(m.path || ''))}" aria-label="${esc(m.title || 'ویدئو')}"></video>`;
    }
    const src = upl(m.path || m.src || '');
    return `<img class="lb-img" src="${esc(src)}" alt="${esc(m.title || '')}" draggable="false">`;
  }
  function applyZoom() {
    const img = root.querySelector('.lb-img');
    if (img) img.style.transform = `translate(${z.x}px,${z.y}px) scale(${z.s})`;
    root.classList.toggle('zoomed', z.s > 1);
  }
  function show(k) {
    i = (k + items.length) % items.length;
    z = { s: 1, x: 0, y: 0 };
    root.querySelector('.lb-stage').innerHTML = stageHtml(items[i]);
    root.querySelector('.lb-cap').textContent = `${items[i].title || ''}  ${fa(i + 1)} / ${fa(items.length)}`;
    const isImg = items[i].type !== 'video';
    root.querySelectorAll('[data-z]').forEach(b => b.hidden = !isImg);
    root.querySelectorAll('[data-nav]').forEach(b => b.hidden = items.length < 2);
  }
  function zoom(f, reset) {
    z.s = reset ? 1 : Math.min(5, Math.max(1, +(z.s * f).toFixed(2)));
    if (z.s === 1) { z.x = z.y = 0; }
    applyZoom();
  }
  function toggleFs() {
    if (document.fullscreenElement) document.exitFullscreen?.();
    else root.requestFullscreen?.();
  }
  function close() {
    if (!root) return;
    if (document.fullscreenElement) document.exitFullscreen?.();
    root.remove(); root = null;
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKey);
    opener?.focus?.();
  }
  function onKey(e) {
    if (!root) return;
    const k = e.key;
    if (k === 'Escape') close();
    else if (k === 'ArrowLeft') show(i + 1);   // جهت RTL: چپ = بعدی
    else if (k === 'ArrowRight') show(i - 1);
    else if (k === '+' || k === '=') zoom(1.4);
    else if (k === '-') zoom(1 / 1.4);
    else if (k === '0') zoom(1, true);
    else if (k === 'f' || k === 'F') toggleFs();
    else if (k === 'Tab') { // محبوس‌کردن فوکوس داخل دیالوگ
      const f = [...root.querySelectorAll('button:not([hidden]),a[href]')];
      if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  }
  function open(list, start = 0, from = null) {
    if (!list.length) return;
    items = list; opener = from || document.activeElement;
    root = document.createElement('div');
    root.className = 'lb'; root.setAttribute('role', 'dialog'); root.setAttribute('aria-modal', 'true'); root.setAttribute('aria-label', 'نمایشگر تصاویر و ویدئوها');
    root.innerHTML = `<div class="lb-top">
        <button data-z data-act="in" aria-label="بزرگ‌نمایی">＋</button><button data-z data-act="out" aria-label="کوچک‌نمایی">－</button><button data-z data-act="reset" aria-label="اندازه‌ی اصلی">۱:۱</button>
        <button data-act="fs" aria-label="تمام‌صفحه">⛶</button><button data-act="close" aria-label="بستن" class="lb-x">✕</button></div>
      <button class="lb-n lb-prev" data-nav data-act="prev" aria-label="قبلی">‹</button>
      <div class="lb-stage"></div>
      <button class="lb-n lb-next" data-nav data-act="next" aria-label="بعدی">›</button>
      <div class="lb-cap" aria-live="polite"></div>`;
    document.body.append(root);
    document.body.style.overflow = 'hidden';
    root.addEventListener('click', e => {
      const a = e.target.closest('[data-act]')?.dataset.act;
      if (a === 'close') close();
      else if (a === 'prev') show(i - 1);
      else if (a === 'next') show(i + 1);
      else if (a === 'in') zoom(1.4);
      else if (a === 'out') zoom(1 / 1.4);
      else if (a === 'reset') zoom(1, true);
      else if (a === 'fs') toggleFs();
      else if (e.target.classList.contains('lb-img')) { if (suppress) suppress = false; else zoom(z.s > 1 ? 1 : 2.5, z.s > 1); } // کلیک = زوم
      else if (e.target === root || e.target.classList.contains('lb-stage')) close();
    });
    root.addEventListener('wheel', e => { if (e.target.classList.contains('lb-img')) { e.preventDefault(); zoom(e.deltaY < 0 ? 1.2 : 1 / 1.2); } }, { passive: false });
    // کشیدن برای جابه‌جایی (هنگام زوم) و Swipe برای تصویر بعدی/قبلی
    root.addEventListener('pointerdown', e => { if (e.target.classList.contains('lb-img')) drag = { x: e.clientX, y: e.clientY, ox: z.x, oy: z.y, moved: false }; });
    root.addEventListener('pointermove', e => {
      if (!drag || z.s === 1) return;
      z.x = drag.ox + e.clientX - drag.x; z.y = drag.oy + e.clientY - drag.y; drag.moved = true; applyZoom();
    });
    root.addEventListener('pointerup', e => {
      if (drag && z.s === 1) { const dx = e.clientX - drag.x; if (Math.abs(dx) > 70) show(i + (dx < 0 ? 1 : -1)); }
      suppress = !!(drag && drag.moved);
      drag = null;
    });
    document.addEventListener('keydown', onKey);
    show(start);
    root.querySelector('[data-act=close]').focus();
  }
  return { open, close };
})();

/** رسانه‌ها را در یک گرید ریسپانسیو با بارگذاری تنبل نمایش می‌دهد؛ کلیک = Lightbox */
function renderGallery(el, media, limit) {
  const M = (media || []).slice(0, limit || media.length);
  el.classList.add('gal');
  el.innerHTML = M.map((m, k) => {
    if (m.type === 'video') return `<button type="button" class="gal-item gal-vid" data-k="${k}" aria-label="${esc('پخش ویدئو: ' + (m.title || ''))}"><span class="gal-play" aria-hidden="true">▶</span><small>${esc(m.title || 'ویدئو')}</small></button>`;
    const src = upl(m.path || m.src || '');
    return `<button type="button" class="gal-item" data-k="${k}" aria-label="${esc(m.title || 'مشاهده‌ی تصویر')}"><img loading="lazy" decoding="async" width="400" height="300" alt="${esc(m.title || '')}" src="${esc(src)}"></button>`;
  }).join('') || '<p>موردی ثبت نشده است.</p>';
  el.onclick = e => {
    const b = e.target.closest('[data-k]');
    if (b) Lightbox.open(M, +b.dataset.k, b);
  };
}

/** فهرست دانلود اسناد/فایل‌های یک آلبوم (لینک مستقیم؛ سرور با Content-Disposition دانلود می‌کند) */
function renderFiles(el, files) {
  if (!files || !files.length) { el.hidden = true; return; }
  const ext = p => (String(p).split('.').pop() || '').toUpperCase();
  const sz = n => n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.ceil((n || 0) / 1024) + ' KB';
  el.hidden = false;
  el.innerHTML = '<h2 class="h2">📎 فایل‌ها و اسناد</h2><ul class="files">' + files.map(f =>
    `<li><a href="${esc(upl(f.path))}" download rel="noopener">${esc(f.title || ext(f.path) + ' فایل')}</a> <small>${esc(ext(f.path))}${f.size ? ' · ' + sz(f.size) : ''}</small></li>`).join('') + '</ul>';
}

/* ───────────── Swiper (فایل‌های محلی: js/vendor/swiper-bundle.min.js و css/vendor/swiper-bundle.min.css) ───────────── */
const RM = () => matchMedia('(prefers-reduced-motion:reduce)').matches;
/** ساخت اسلایدر داخل el از آرایه‌ی HTML اسلایدها؛ بدون Swiper به اسکرول افقی ساده تبدیل می‌شود */
function mkSwiper(el, slides, opt, cls = '') {
  el.className = 'swiper sw ' + cls;
  el.setAttribute('dir', 'rtl');
  el.innerHTML = `<div class="swiper-wrapper">${slides.map(h => `<div class="swiper-slide">${h}</div>`).join('')}</div><div class="swiper-pagination"></div>`;
  if (!window.Swiper) { el.classList.add('sw-fb'); return null; }
  return new Swiper(el, { keyboard: true, grabCursor: true, pagination: { el: el.querySelector('.swiper-pagination'), clickable: true }, ...opt });
}

/** اسلایدر تصاویر (آلبوم/منتخب)؛ کلیک = Lightbox */
function imgSwiper(el, media) {
  const M = media || [];
  const sw = mkSwiper(el, M.map((m, k) => `<button type="button" class="gal-item" data-k="${k}" aria-label="${esc(m.title || 'مشاهده‌ی تصویر')}"><img loading="lazy" decoding="async" width="400" height="300" alt="${esc(m.title || '')}" src="${esc(upl(m.path || m.src || ''))}"></button>`), {
    slidesPerView: 1.15, spaceBetween: 12, rewind: true,
    autoplay: RM() || M.length < 2 ? false : { delay: 3500, disableOnInteraction: false },
    breakpoints: { 640: { slidesPerView: 2.2 }, 1000: { slidesPerView: 3.2 } },
  });
  el.onclick = e => { const b = e.target.closest('[data-k]'); if (b) Lightbox.open(M, +b.dataset.k, b); };
  return sw;
}

/** اسلایدر ورق‌زن «افراد برتر»: عکس، نام، مسئولیت (هیئت/کانون) و مرتبه؛ مقدار خالی = جای خالی */
function topSwiper(el, people) {
  const blank = '<span class="blank" role="img" aria-label="ثبت نشده"></span>';
  const row = (l, v) => `<div><dt>${l}</dt><dd>${v ? esc(v) : blank}</dd></div>`;
  const hey = v => v ? row('مسئولیت در هیئت', v) : '';
  return mkSwiper(el, (people || []).map(p => `<article class="top-card"><div class="top-av">${p.avatar ? `<img loading="lazy" decoding="async" alt="${esc(p.name)}" src="${esc(p.avatar)}">` : `<span aria-hidden="true">${esc((p.name || '؟').slice(0, 1))}</span>`}</div>
    <h3>${esc(p.name)}</h3>${p.heyat || p.kanoon ? `<dl>${hey(p.heyat)}${row('مرتبه', p.kanoon)}</dl>` : ''}</article>`), {
    effect: 'cards', cardsEffect: { slideShadows: false, perSlideOffset: 8, perSlideRotate: 2 },
    autoplay: RM() || (people || []).length < 2 ? false : { delay: 4000, disableOnInteraction: true },
  });
}

/** اسلایدر کارتی برای فهرست‌ها (فعالیت‌ها، اردوها، آلبوم‌ها، اخبار، کانال‌ها…) */
function cardSwiper(el, items, o = {}) {
  if (!items.length) { el.className = ''; el.innerHTML = '<p class="muted">موردی ثبت نشده است.</p>'; return null; }
  const fx = o.effect === 'coverflow' && items.length >= 4 ? { effect: 'coverflow', centeredSlides: true, coverflowEffect: { rotate: 14, stretch: 0, depth: 70, modifier: 1, slideShadows: false } } : {};
  return mkSwiper(el, items, { slidesPerView: 'auto', spaceBetween: 16, watchOverflow: true,
    autoplay: o.auto && !RM() && items.length > 3 ? { delay: 4500, disableOnInteraction: true } : false, ...fx }, 'sw-c');
}
/** شمارش تدریجی عددها */
function countUp(el) {
  const n = parseInt(String(el.textContent).replace(/[^\d]/g, ''), 10);
  if (!n || RM()) return;
  const t0 = performance.now(), d = 1400;
  (function f(t) { const k = Math.min(1, (t - t0) / d); el.textContent = Math.round(n * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(f); })(t0);
}

/* ───────────── آیکن SVG، اسکلتون، نوار پایین، دکمه‌ی شناور ───────────── */
const ic = (n, cls = '') => `<svg class="ic ${cls}" aria-hidden="true"><use href="${ASSET}icons.svg#${n}"/></svg>`;
const iconify = root => (root || document).querySelectorAll('[data-ic]').forEach(e => { e.outerHTML = ic(e.dataset.ic); });
const skel = (el, n = 3) => { el.className = 'skel-row'; el.innerHTML = '<div class="skel"></div>'.repeat(n); };
function chrome() {
  if (/signup|login|register|complete-profile/.test(location.pathname)) return;
  const here = location.pathname.replace(/\/$/, '') || '/';
  const it = (n, l, h, c = '') => `<a class="${c}" href="${esc(h)}"${new URL(h, location.href).pathname.replace(/\/$/, '') === here ? ' aria-current="page"' : ''}>${ic(n)}<span>${l}</span></a>`;
  document.body.insertAdjacentHTML('beforeend', `<div class="bnav" role="navigation" aria-label="منوی سریع">${it('home', 'خانه', U.home())}${it('camp', 'اردوها', U.nav('/camps'))}${it('plus', 'ثبت‌نام', U.signup(), 'go')}${it('album', 'آلبوم‌ها', U.nav('/albums'))}${it('news', 'اخبار', U.nav('/news'))}</div>
    <a class="btn cta fab" href="${esc(U.signup())}">${ic('sparkle')} ثبت‌نام</a>`);
  const fab = document.querySelector('.fab');
  const t = () => fab.classList.toggle('show', scrollY > 500);
  addEventListener('scroll', t, { passive: true }); t();
}
