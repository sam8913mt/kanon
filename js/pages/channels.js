/** channels.js — کانال‌ها و شبکه‌های اجتماعی */
document.addEventListener('DOMContentLoaded', async () => {
  byId('g').innerHTML = ((await api('/channels.php')).data || []).map(x => `<a class="card hov" href="${esc(safeUrl(x.url))}" target="_blank" rel="noopener"><div class="bd"><h3>${esc(x.title)}</h3><small dir="ltr">${esc(x.url)}</small></div></a>`).join('') || '<p class="muted">کانالی ثبت نشده است.</p>';
});
