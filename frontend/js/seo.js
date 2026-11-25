/**
 * seo.js — همه‌ی تنظیمات و منطق سئوی سایت در یک فایل جداست
 * (نام برند فارسی/انگلیسی، دامنه، کلیدواژه‌ها، متاتگ‌ها، OpenGraph، Schema و Canonical)
 * سئوی هر اردو/آلبوم/خبر/صفحه از backend/api/seo.php می‌آید و از پنل «SEO» قابل ویرایش است.
 * این فایل باید بعد از api.js و قبل از components.js بارگذاری شود.
 */
const SEO_CONFIG = {
  name_fa: 'کانون نخبه‌پروری رویش',
  name_en: 'royesh',
  domain: 'https://nokhbeparvari.ir',
  locale: 'fa_IR',
  keywords: 'کانون نخبه‌پروری رویش, کانون رویش, نخبه‌پروری, اردوی علمی, اردوی دانش‌آموزی, royesh',
};

function setMeta(name, value, attr = 'name') {
  if (!value) return;
  let e = document.querySelector(`meta[${attr}="${name}"]`);
  if (!e) { e = document.createElement('meta'); e.setAttribute(attr, name); document.head.append(e); }
  e.content = value;
}

function setSEO(o = {}) {
  if (o.title) document.title = o.title;
  setMeta('description', o.description);
  setMeta('keywords', o.keywords);
  setMeta('og:title', o.og_title || o.title, 'property');
  setMeta('og:description', o.og_description || o.description, 'property');
  setMeta('og:image', o.og_image, 'property');
  setMeta('og:type', o.og_type || 'website', 'property');
  setMeta('og:site_name', o.site_name, 'property');
  setMeta('og:locale', o.locale, 'property');
  if (o.canonical) {
    let l = document.querySelector('link[rel=canonical]');
    if (!l) { l = document.createElement('link'); l.rel = 'canonical'; document.head.append(l); }
    l.href = o.canonical;
  }
  [].concat(o.schema || []).forEach(sc => {
    const x = document.createElement('script');
    x.type = 'application/ld+json';
    x.textContent = typeof sc === 'string' ? sc : JSON.stringify(sc);
    document.head.append(x);
  });
}

const absUrl = p => !p ? undefined : new URL(/^https?:/.test(p) ? p : upl(p), location.href).href;

/** SEO اختصاصی یک صفحه/اردو/آلبوم/خبر از API (در صورت نبود، مقادیر پیش‌فرض) */
async function seoFor(entity, slug, fallback) {
  const r = await api(`/seo.php?entity=${entity}&slug=${encodeURIComponent(slug)}`);
  const d = r && r.success && r.data ? r.data : {};
  if (d.og_image) d.og_image = absUrl(d.og_image);
  setSEO({ ...fallback, ...Object.fromEntries(Object.entries(d).filter(([, v]) => v)) });
}

/** سئوی سراسری سایت (برند، Organization و WebSite)؛ از layout() در components.js صدا زده می‌شود */
function siteSEO({ S, s, logo, name }) {
  const gs = S.global_seo || {};
  const en = SEO_CONFIG.name_en;
  const base = CFG.DEMO_MODE ? location.origin : SEO_CONFIG.domain;
  const isHome = !!document.querySelector('.hero');
  const o = {
    keywords: SEO_CONFIG.keywords,
    og_image: absUrl(gs.og_image || s.og_image || (CFG.DEMO_MODE ? '' : ASSET + 'logo.png')),
    site_name: name,
    locale: SEO_CONFIG.locale,
    schema: [
      { '@context': 'https://schema.org', '@type': 'Organization', name, alternateName: en, url: base, logo: new URL(logo, location.href).href, sameAs: (S.channels || []).map(c => c.url) },
      { '@context': 'https://schema.org', '@type': 'WebSite', name, alternateName: en, url: base, inLanguage: 'fa' },
    ],
  };
  // عنوان و توضیح: فقط صفحه‌ی اصلی از تنظیمات سراسری می‌گیرد؛ بقیه‌ی صفحه‌ها عنوان خودشان را نگه می‌دارند
  if (isHome) {
    o.title = gs.seo_title || s.site_title || `${name} | ${en}`;
    o.description = gs.meta_description || s.site_description;
  } else if (!document.querySelector('meta[name=description]')) {
    o.description = gs.meta_description || s.site_description;
  }
  if (!CFG.DEMO_MODE && !document.querySelector('meta[name=robots][content*=noindex]')) {
    o.canonical = base + location.pathname.replace(/index\.html$/, '');
  }
  setSEO(o);
}
