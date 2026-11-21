/** contact.js — صفحه‌ی تماس: اطلاعات تماس + کانال‌ها و شبکه‌ها + توضیح (قابل مدیریت از پنل) */
document.addEventListener('DOMContentLoaded', async () => {
  const S = (await siteP()).data || {}, s = S.settings || {};
  const link = c => {
    const v = String(c.value || '');
    if (c.kind === 'email') return `<a href="mailto:${esc(v)}">${esc(v)}</a>`;
    if (c.kind === 'phone' || c.kind === 'mobile') return `<a href="tel:${esc(v.replace(/[^\d+]/g, ''))}" dir="ltr">${esc(v)}</a>`;
    return esc(v);
  };
  byId('desc').textContent = s.contact_description || '';
  byId('g').innerHTML = (await api('/contacts.php')).data.map(x => `<div class="card"><div class="bd"><b>${esc(x.label)}</b><p>${link(x)}</p></div></div>`).join('') || '<p class="muted">اطلاعات تماس هنوز ثبت نشده است.</p>';
  const ch = S.channels || [];
  if (ch.length) byId('sc').innerHTML = ch.map(x => `<a class="card hov" href="${esc(safeUrl(x.url))}" target="_blank" rel="noopener"><div class="bd"><h3>${esc(x.title)}</h3></div></a>`).join('');
  else byId('sec-sc').hidden = true;
});
