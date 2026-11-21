/** home.js — صفحه‌ی اصلی */
document.addEventListener('DOMContentLoaded', async () => {
  const S = (await siteP()).data || {}, s = S.settings || {};
  byId('t').textContent = s.hero_title || '';
  byId('a').textContent = s.hero_text || '';
  byId('s').innerHTML = (S.stats || []).map(x => `<div><b>${esc(x[1])}</b>${esc(x[0])}</div>`).join('');

  // فعالیت‌ها (از تنظیمات؛ اگر خالی باشد بخش پنهان می‌شود)
  const act = S.activities || [];
  if (act.length) byId('ac').innerHTML = act.map(x => `<div class="card"><div class="bd"><h3>${esc(x.title)}</h3><p>${esc(x.text)}</p></div></div>`).join('');
  else byId('sec-ac').hidden = true;

  const empty = '<p class="muted">موردی ثبت نشده است.</p>';
  const camps = (await api('/camps')).data || [];
  const fill = (g, id) => { byId(id).innerHTML = camps.filter(c => campGroup(c) === g).map(campCard).join('') || empty; };
  fill('open', 'c1'); fill('upcoming', 'c2'); fill('done', 'c3');

  const albums = ((await api('/albums.php')).data || []).slice(0, 3);
  byId('al').innerHTML = albums.map(albumCard).join('') || empty;

  const ab = (await api('/pages.php?slug=about')).data; // معرفی کانون (کوتاه‌شده از صفحه‌ی «درباره»؛ متن پیش‌فرض نصب نمایش داده نمی‌شود)
  const abt = ab && ab.content ? String(ab.content).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : '';
  if (abt && !abt.startsWith('متن معرفی کانون را از پنل')) { byId('ab').textContent = abt.length > 320 ? abt.slice(0, 320) + '…' : abt; byId('sec-ab').hidden = false; }

  const top = (await api('/top.php')).data || [];
  if (top.length) { byId('sec-top').hidden = false; topSwiper(byId('top'), top); }

  const f = (await api('/albums.php?featured=1')).data || { images: [] };
  if (f.images.length) imgSwiper(byId('fi'), f.images); else byId('sec-fi').hidden = true;

  // برای هر آلبوم یک بخش با اسلایدر تصاویر
  const hs = (await api('/albums.php?home=1')).data || [];
  byId('alb-secs').innerHTML = hs.map((a, i) => `<section><div class="wrap"><h2>📷 ${esc(a.title)}</h2><div id="as${i}"></div><p><a class="btn alt" href="${esc(U.album(a.slug))}">مشاهده‌ی آلبوم</a></p></div></section>`).join('');
  hs.forEach((a, i) => imgSwiper(byId('as' + i), a.images));
  reveal(byId('alb-secs').children);
  if ((f.videos || []).length) { byId('sec-fv').hidden = false; renderGallery(byId('fv'), f.videos); }

  byId('nw').innerHTML = ((await api('/news.php')).data || []).slice(0, 3).map(newsCard).join('') || empty;
  byId('ch').innerHTML = (S.channels || []).map(x => `<a class="card hov" href="${esc(safeUrl(x.url))}" rel="noopener" target="_blank"><div class="bd"><h3>${esc(x.title)}</h3><small>باز کردن کانال ↗</small></div></a>`).join('') || empty;
  byId('ct').innerHTML = (S.contacts || []).map(c => `<div class="card"><div class="bd"><b>${esc(c.label)}</b><p>${esc(c.value)}</p></div></div>`).join('') || empty;
});
