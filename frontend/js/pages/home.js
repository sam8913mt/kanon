/** home.js — صفحه‌ی اصلی */
document.addEventListener('DOMContentLoaded', async () => {
  ['ac', 'c1', 'c2', 'c3', 'al', 'nw'].forEach(i => skel(byId(i)));
  const S = (await siteP()).data || {}, s = S.settings || {};
  byId('t').textContent = s.hero_title || '';
  byId('a').textContent = s.hero_text || '';
  wordsIn(byId('t'));
  byId('s').innerHTML = (S.stats || []).map(x => `<div><b>${esc(x[1])}</b>${esc(x[0])}</div>`).join('');
  byId('s').querySelectorAll('b').forEach(countUp);

  // فعالیت‌ها (از تنظیمات؛ اگر خالی باشد بخش پنهان می‌شود)
  const act = S.activities || [];
  if (act.length) cardSwiper(byId('ac'), act.map(x => `<div class="card"><div class="bd"><h3>${esc(x.title)}</h3><p>${esc(x.text)}</p></div></div>`), { effect: 'coverflow' });
  else byId('sec-ac').hidden = true;

  const camps = (await api('/camps')).data || [];
  // شمارنده‌ی معکوس نزدیک‌ترین اردوی پیش‌رو
  const nx = camps.filter(c => c.start && ['open', 'soon'].includes(c.status) && new Date(c.start) > Date.now()).sort((x, y) => new Date(x.start) - new Date(y.start))[0];
  if (nx) {
    const cd = byId('cd'), T = new Date(nx.start).getTime(); cd.hidden = false;
    const tk = () => { let d = Math.max(0, T - Date.now()) / 1000; const v = [['روز', 86400], ['ساعت', 3600], ['دقیقه', 60], ['ثانیه', 1]].map(([l, k]) => { const n = Math.floor(d / k); d -= n * k; return `<div><b>${n}</b><small>${l}</small></div>`; });
      cd.innerHTML = `<span>⏳ تا ${esc(nx.title)}</span><div class="cd-n">${v.join('')}</div>`; };
    tk(); setInterval(tk, 1000);
  }
  const fill = (g, id) => cardSwiper(byId(id), camps.filter(c => campGroup(c) === g).map(campCard));
  fill('open', 'c1'); fill('upcoming', 'c2'); fill('done', 'c3');

  const albums = ((await api('/albums.php')).data || []).slice(0, 3);
  cardSwiper(byId('al'), albums.map(albumCard), { effect: 'coverflow' });

  const ab = (await api('/pages.php?slug=about')).data; // معرفی کانون (کوتاه‌شده از صفحه‌ی «درباره»؛ متن پیش‌فرض نصب نمایش داده نمی‌شود)
  const abt = ab && ab.content ? String(ab.content).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : '';
  if (abt && !abt.startsWith('متن معرفی کانون را از پنل')) { byId('ab').textContent = abt.length > 320 ? abt.slice(0, 320) + '…' : abt; byId('sec-ab').hidden = false; }

  const top = (await api('/top.php')).data || [];
  if (top.length) { byId('sec-top').hidden = false; topSwiper(byId('top'), top); }

  const f = (await api('/albums.php?featured=1')).data || { images: [] };
  if (f.images.length) imgSwiper(byId('fi'), f.images); else byId('sec-fi').hidden = true;

  // برای هر آلبوم یک بخش با اسلایدر تصاویر
  const hs = (await api('/albums.php?home=1')).data || [];
  byId('alb-secs').innerHTML = hs.map((a, i) => `<section><div class="wrap"><h2>${ic('album')} ${esc(a.title)}</h2><div id="as${i}"></div><p><a class="btn alt" href="${esc(U.album(a.slug))}">مشاهده‌ی آلبوم</a></p></div></section>`).join('');
  hs.forEach((a, i) => imgSwiper(byId('as' + i), a.images));
  reveal(byId('alb-secs').children);

  const tm = (await api('/testimonials.php')).data || [];
  const tmBox = byId('tm');
  if (tm.length) cardSwiper(tmBox, tm.map(t => `<div class="card tm"><div class="bd">${ic('quote', 'q')}<p>${esc(t.body)}</p><div class="stars" aria-label="امتیاز ${+t.rating} از ۵">${ic('star').repeat(Math.max(0, Math.min(5, +t.rating || 5)))}</div><b>${esc(t.name)}</b><small>${esc(t.role || '')}</small></div></div>`), { effect: 'coverflow', auto: 1 });
  else tmBox.innerHTML = '<p class="muted">هنوز نظری ثبت نشده؛ اولین نفر باشید!</p>';
  tmBox.insertAdjacentHTML('afterend', `<div class="tm-form-wrap"><button class="btn" id="tm-open" type="button" aria-expanded="false">${ic('chat')} ثبت نظر شما</button>
    <form id="tm-form" class="f tm-form" hidden novalidate>
      <input name="name" placeholder="نام شما" maxlength="60" required aria-label="نام">
      <input name="role" placeholder="عنوان (اختیاری؛ مثلاً شرکت‌کننده‌ی اردوی مشهد)" maxlength="100" aria-label="عنوان">
      <select name="rating" aria-label="امتیاز"><option value="5">★★★★★ عالی</option><option value="4">★★★★ خوب</option><option value="3">★★★ متوسط</option><option value="2">★★ ضعیف</option><option value="1">★ خیلی ضعیف</option></select>
      <textarea name="body" rows="4" placeholder="نظر شما (حداقل ۱۰ کاراکتر)" maxlength="500" required aria-label="متن نظر"></textarea>
      <input name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button class="btn" type="submit">ارسال نظر</button><p class="hint" id="tm-msg" role="status">نظر شما پس از تأیید مدیر نمایش داده می‌شود.</p>
    </form></div>`);
  byId('tm-open').onclick = e => { const f = byId('tm-form'); f.hidden = !f.hidden; e.currentTarget.setAttribute('aria-expanded', !f.hidden); };
  byId('tm-form').onsubmit = async e => {
    e.preventDefault(); const f = e.currentTarget, m = byId('tm-msg'), btn = f.querySelector('[type=submit]'); btn.disabled = true;
    const r = await api('/testimonials.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(f))) });
    btn.disabled = false; m.textContent = r.message || (r.success ? 'ثبت شد' : 'خطا در ارسال'); m.className = 'hint ' + (r.success ? 'okm' : 'badm');
    if (r.success) { f.reset(); confetti(); }
  };
  if ((f.videos || []).length) { byId('sec-fv').hidden = false; renderGallery(byId('fv'), f.videos); }

  cardSwiper(byId('nw'), ((await api('/news.php')).data || []).slice(0, 6).map(newsCard), { auto: 1 });
  cardSwiper(byId('ch'), (S.channels || []).map(x => `<a class="card hov" href="${esc(safeUrl(x.url))}" rel="noopener" target="_blank"><div class="bd"><h3>${esc(x.title)}</h3><small>باز کردن کانال ↗</small></div></a>`));
  cardSwiper(byId('ct'), (S.contacts || []).map(c => `<div class="card"><div class="bd"><b>${esc(c.label)}</b><p>${esc(c.value)}</p></div></div>`));
});
