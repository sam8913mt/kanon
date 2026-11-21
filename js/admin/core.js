/**
 * core.js — هسته‌ی پنل مدیریت: وضعیت، ارتباط با API، ابزارهای مشترک
 */
const A = { csrf: '', me: null, areas: [] };
const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const isAdmin = () => A.me && A.me.role === 'admin';
const fmtSize = n => n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.ceil((n || 0) / 1024) + ' KB';

/** انواع فایل قابل آپلود در Media Manager (اعتبارسنجی نهایی سمت سرور است) */
const MEDIA_ACCEPT = 'image/jpeg,image/png,image/webp,video/mp4,video/webm,.pdf,.docx,.xlsx,.pptx,.txt,.zip';
const MEDIA_ICON = { video: '🎬', document: '📄', file: '📦' };
/** پیش‌نمایش یک رسانه بر اساس نوع (تصویر = تصویر؛ ویدئو/سند/فایل = آیکن با نام قابل فهم) */
const mediaThumb = (m, px = 56) => m.type === 'image'
  ? `<img src="/uploads/${esc(m.path)}" width="${px}" height="${px}" style="object-fit:cover;border-radius:6px" loading="lazy" alt="${esc(m.title || 'تصویر')}">`
  : `<span role="img" aria-label="${esc(m.title || m.type)}" style="display:inline-flex;align-items:center;justify-content:center;width:${px}px;height:${px}px;font-size:${Math.round(px / 2)}px;background:var(--bg2,#eef);border-radius:6px">${MEDIA_ICON[m.type] || '📎'}</span>`;
/** آدرس قابل باز کردن یک رسانه (فایل آپلودی، آدرس مستقیم یا لینک ویدئوی خارجی) */
const mediaHref = m => m.path ? (/^https?:/.test(m.path) ? m.path : '/uploads/' + m.path) : (m.url || '#');

/** فراخوانی JSON (GET بدون body، POST با body) */
async function call(path, body) {
  const o = { credentials: 'same-origin' };
  if (body) {
    o.method = 'POST';
    o.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': A.csrf };
    o.body = JSON.stringify(body);
  }
  const r = await fetch('/api/' + path, o);
  if (r.status === 401) location.href = '/login';
  try { return await r.json(); } catch (e) { return { success: false, message: 'پاسخ نامعتبر از سرور' }; }
}

/** آپلود چندبخشی (multipart) */
async function upload(path, fd) {
  const r = await fetch('/api/' + path, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': A.csrf }, body: fd });
  if (r.status === 401) location.href = '/login';
  try { return await r.json(); } catch (e) { return { success: false, message: 'پاسخ نامعتبر؛ احتمالاً حجم فایل از محدودیت PHP بیشتر است' }; }
}

const list = (e, f = {}) => call('crud.php?e=' + e + Object.entries(f).map(([k, v]) => `&f_${k}=${encodeURIComponent(v)}`).join(''));
const save = (e, data, id = null) => call('crud.php', { e, id, data });
const reorder = (e, ids) => call('crud.php', { e, op: 'reorder', ids });

/** پیام کوتاه (toast) */
function toast(msg, ok = true) {
  const t = document.createElement('div');
  t.className = 'toast ' + (ok ? 'ok' : 'bad');
  t.textContent = msg;
  $('#toast').append(t);
  setTimeout(() => t.remove(), 4000);
}
/** نتیجه‌ی API را به toast تبدیل می‌کند و موفق بودن را برمی‌گرداند */
const report = r => { if (r && r.message) toast(r.message, !!r.success); return !!(r && r.success); };

/** نامک پیشنهادی (فقط ASCII): از عنوان لاتین یا پیشوند + عدد تصادفی */
function slugify(title, prefix) {
  const s = String(title || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  return s.length >= 3 ? s : prefix + '-' + Math.random().toString(36).slice(2, 6);
}

/**
 * مرتب‌سازی با Drag & Drop (برای tr یا div). onDone(ids) پس از رها کردن فراخوانی می‌شود.
 * grid=true برای چیدمان شبکه‌ای (تصاویر آلبوم)
 */
function makeSortable(box, sel, onDone, grid = false) {
  let drag = null;
  box.addEventListener('dragstart', e => {
    const el = e.target.closest(sel);
    if (!el) return;
    drag = el; el.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', el.dataset.id || '');
  });
  box.addEventListener('dragover', e => {
    if (!drag) return;
    e.preventDefault();
    const over = e.target.closest(sel);
    if (!over || over === drag) return;
    const r = over.getBoundingClientRect();
    const rtl = getComputedStyle(box).direction === 'rtl';
    const after = grid ? (rtl ? e.clientX < r.left + r.width / 2 : e.clientX > r.left + r.width / 2) : e.clientY > r.top + r.height / 2;
    over.parentNode.insertBefore(drag, after ? over.nextSibling : over);
  });
  box.addEventListener('dragend', () => {
    if (!drag) return;
    drag.classList.remove('dragging'); drag = null;
    onDone([...box.querySelectorAll(sel)].map(el => +el.dataset.id));
  });
}

/** جابه‌جایی یک آیتم در آرایه‌ی شناسه‌ها (دکمه‌های ↑↓ برای دسترسی با کیبورد) */
function moveId(ids, id, dir) {
  const i = ids.indexOf(id), j = i + dir;
  if (i < 0 || j < 0 || j >= ids.length) return ids;
  [ids[i], ids[j]] = [ids[j], ids[i]];
  return ids;
}

/** انتخابگر تصویر از کتابخانه‌ی رسانه (مودال) */
async function pickImage(input) {
  const imgs = (await call('media.php?type=image')).data || [];
  const o = document.createElement('div');
  o.className = 'modal';
  o.innerHTML = `<div class="card box" role="dialog" aria-modal="true" aria-label="انتخاب تصویر"><button class="btn alt sm" data-x>بستن</button>
    <div class="pgrid">${imgs.map(m => `<button type="button" class="tile" data-p="${esc(m.path)}"><img loading="lazy" src="/uploads/${esc(m.path)}" alt="${esc(m.title)}"></button>`).join('') || '<p>ابتدا از «مدیریت فایل‌ها» تصویر آپلود کنید.</p>'}</div></div>`;
  o.onclick = e => {
    const p = e.target.closest('[data-p]');
    if (p) { input.value = p.dataset.p; input.dispatchEvent(new Event('input')); o.remove(); }
    if (e.target === o || e.target.hasAttribute('data-x')) o.remove();
  };
  document.body.append(o);
}

/** تم و منوی موبایل */
document.documentElement.dataset.theme = localStorage.getItem('theme') || 'light';
$('#th').onclick = () => {
  const n = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
  document.documentElement.dataset.theme = n;
  localStorage.setItem('theme', n);
};
$('#bg').onclick = () => $('#sb').classList.toggle('open');
