/** heyats.js — فهرست هیئت‌ها با شمارشگر معکوس */
document.addEventListener('DOMContentLoaded', async () => {
  skel(byId('g'));
  const rows = (await api('/heyats.php')).data || [];
  const now = Date.now(), up = [], past = [];
  // بدون ساعت: تا پایان همان روز «پیش‌رو» حساب می‌شود
  rows.forEach(h => { const end = h.date ? new Date(h.date + 'T' + (h.time ? h.time : '23:59') + ':00+03:30').getTime() : 0; (end > now ? up : past).push(h); });
  up.sort((a, b) => new Date(a.at) - new Date(b.at));

  const card = (h, live) => `<article class="card hy"><div class="cv"${coverStyle(h.image)}></div><div class="bd">
    ${h.occasion ? `<span class="tag">${esc(h.occasion)}</span>` : ''}<h3>${esc(h.title)}</h3>
    ${h.caption ? `<p>${esc(h.caption)}</p>` : ''}
    <small>${esc(fmtDate(h.date))}${h.time ? ' — ساعت ' + esc(h.time) : ''}</small>
    ${live ? `<div class="cd-t" data-at="${esc(h.at)}"></div>` : ''}</div></article>`;

  byId('g').className = 'grid';
  byId('g').innerHTML = up.map(h => card(h, true)).join('') || '<p class="muted">هیئت پیش‌رویی ثبت نشده است.</p>';
  if (past.length) { byId('ph').hidden = false; byId('g2').className = 'grid'; byId('g2').innerHTML = past.map(h => card(h, false)).join(''); }
  reveal(document.querySelectorAll('#g > *, #g2 > *'));

  const tick = () => document.querySelectorAll('.cd-t').forEach(el => {
    let d = Math.floor((new Date(el.dataset.at).getTime() - Date.now()) / 1000);
    if (d <= 0) { el.innerHTML = '<div class="cd-now">زمان برگزاری فرا رسیده است</div>'; return; }
    el.innerHTML = '<div class="cd-n">' + [['روز', 86400], ['ساعت', 3600], ['دقیقه', 60], ['ثانیه', 1]].map(([l, k]) => { const n = Math.floor(d / k); d -= n * k; return `<div><b>${n}</b><small>${l}</small></div>`; }).join('') + '</div>';
  });
  tick(); if (up.length) setInterval(tick, 1000);
});
