/**
 * api.js — ارتباط با PHP API (یا داده‌ی Demo)، توابع کمکی و آدرس‌سازی
 */
const ST = { open: 'ثبت‌نام فعال', soon: 'به‌زودی', full: 'تکمیل ظرفیت', done: 'برگزار شده', cancelled: 'لغو شده' };

/** Escape برای درج امن در HTML */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
/** آدرس‌های خطرناک (javascript: و ...) را خنثی می‌کند */
const safeUrl = u => /^\s*(javascript|data|vbscript):/i.test(String(u || '')) ? '#' : String(u || '');
const qs = k => new URLSearchParams(location.search).get(k);
const byId = id => document.getElementById(id);

// مسیرها: در Production مطلق (کار با آدرس‌های تو در تو)، در Demo نسبی (اجرا از فایل)
const UP = CFG.DEMO_MODE ? 'uploads/' : '/uploads/';
const ASSET = CFG.DEMO_MODE ? 'assets/' : '/assets/';
/** مسیر فایل آپلودشده → آدرس قابل نمایش (آدرس کامل و data: دست‌نخورده می‌ماند) */
const upl = p => !p ? '' : (/^(https?:|data:|\/)/.test(p) ? p : UP + p);

/** آدرس‌های تمیز در Production (نیازمند .htaccess)؛ در Demo همان فایل‌های .html */
const U = {
  home: () => CFG.DEMO_MODE ? 'index.html' : '/',
  camps: () => CFG.DEMO_MODE ? 'camps.html' : '/camps',
  signup: () => CFG.DEMO_MODE ? 'signup.html' : '/signup',
  profile: () => CFG.DEMO_MODE ? 'profile.html' : '/profile',
  completeProfile: () => '/complete-profile',
  dashboard: () => CFG.DEMO_MODE ? 'dashboard.html' : '/dashboard',
  camp: s => CFG.DEMO_MODE ? 'camp.html?slug=' + encodeURIComponent(s) : '/camp/' + encodeURIComponent(s),
  reg: s => CFG.DEMO_MODE ? 'register.html?camp=' + encodeURIComponent(s) : '/camp/' + encodeURIComponent(s) + '/register',
  regDone: s => CFG.DEMO_MODE ? 'register-success.html' : '/camp/' + encodeURIComponent(s) + '/register/success',
  album: s => CFG.DEMO_MODE ? 'album.html?slug=' + encodeURIComponent(s) : '/album/' + encodeURIComponent(s),
  news: s => CFG.DEMO_MODE ? 'article.html?slug=' + encodeURIComponent(s) : '/news/' + encodeURIComponent(s),
  page: s => CFG.DEMO_MODE ? 'page.html?slug=' + encodeURIComponent(s) : '/page/' + encodeURIComponent(s),
  /** لینک منوی ذخیره‌شده در دیتابیس (مثل /camps یا camps.html) */
  nav(l) {
    l = String(l || '');
    if (/^(https?:|\/|#|mailto:|tel:)/i.test(l)) return safeUrl(l);
    return CFG.DEMO_MODE ? safeUrl(l) : '/' + l.replace(/\.html$/, '').replace(/^index$/, '');
  },
};

/** تاریخ میلادی ISO → شمسی خوانا (مقدار غیر ISO بدون تغییر نمایش داده می‌شود) */
function fmtDate(s) {
  if (!s) return '';
  if (!/^\d{4}-\d{2}-\d{2}/.test(s)) return s;
  try { return new Date(s.slice(0, 10) + 'T12:00:00').toLocaleDateString('fa-IR-u-ca-persian', { year: 'numeric', month: 'long', day: 'numeric' }); }
  catch (e) { return s; }
}

/** فراخوانی API. در Demo از window.DEMO پاسخ می‌دهد. */
async function api(path, opt) {
  if (CFG.DEMO_MODE) return demo(path, opt);
  path = path.replace(/^([^?]+?)(\?|$)/, (m, a, b) => (a.endsWith('.php') ? a : a + '.php') + b);
  try {
    const r = await fetch(CFG.API + path, { credentials: 'same-origin', ...opt });
    return await r.json();
  } catch (e) {
    return { success: false, message: 'ارتباط با سرور برقرار نشد' };
  }
}

/** پاسخ‌های Demo (فقط وقتی DEMO_MODE=true) */
function demo(path, opt) {
  const D = window.DEMO || {};
  const [file, query = ''] = path.replace(/^\//, '').split('?');
  const q = new URLSearchParams(query);
  const ok = data => ({ success: true, data });
  const no = message => ({ success: false, message: message || 'یافت نشد' });
  const one = (list, slug) => (list || []).find(x => x.slug === slug);
  switch (file.replace(/\.php$/, '')) {
    case 'site': return ok(D.site);
    case 'camps': return ok(D.camps);
    case 'camp': { const c = one(D.camps, q.get('slug')); return c ? ok(c) : no('اردو پیدا نشد'); }
    case 'forms': { const c = one(D.camps, q.get('camp')); return c ? ok({ title: c.form_title, intro: c.form_intro, photo_required: c.photo_required, fields: c.fields }) : no(); }
    case 'albums':
      if (q.has('featured')) return ok(D.featured);
      if (q.has('home')) return ok(D.albums.map(a => ({ slug: a.slug, title: a.title, images: a.media })));
      if (q.get('slug')) { const a = one(D.albums, q.get('slug')); return a ? ok(a) : no('آلبوم پیدا نشد'); }
      return ok(D.albums.map(({ media, ...a }) => a));
    case 'news':
      if (q.get('slug')) { const n = one(D.news, q.get('slug')); return n ? ok(n) : no('خبر پیدا نشد'); }
      return ok(D.news);
    case 'top': return ok(D.top);
    case 'pages': { const p = one(D.pages, q.get('slug')); return p ? ok(p) : no('صفحه پیدا نشد'); }
    case 'contacts': case 'contact': return ok(D.contacts);
    case 'channels': return ok(D.channels);
    case 'member': return opt && opt.method === 'POST' ? { success: false, message: 'در حالت Demo پروفایل غیرفعال است' } : ok({ csrf: 'demo', member: null });
    case 'register':
      if (!opt || opt.method !== 'POST') return ok({ csrf: 'demo' });
      return { success: true, message: 'ثبت‌نام شما با موفقیت انجام شد.', data: { id: 'DEMO-' + Date.now().toString().slice(-6) } };
    default: return no();
  }
}
