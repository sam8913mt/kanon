/** testimonials.js — تأیید/حذف/مخفی‌کردن نظرهای شرکت‌کنندگان */
VIEWS.testimonials = async () => {
  const r = await call('testimonials_admin.php');
  if (!r.success) { $('#c').innerHTML = `<p class="err">${esc(r.message)}</p>`; return; }
  const L = r.data || [];
  const stars = n => '★'.repeat(+n || 0) + '☆'.repeat(5 - (+n || 0));
  $('#c').innerHTML = (L.length ? '' : '<p class="hint">هنوز نظری ثبت نشده است.</p>') + L.map(t => `<div class="card tcard" data-id="${t.id}"><div class="bd">
    <div class="row" style="justify-content:space-between;gap:8px"><b>${esc(t.name)}</b><span class="hint">${esc(t.role || '')}</span></div>
    <div style="color:#e0a82b" aria-label="امتیاز ${+t.rating} از ۵">${stars(t.rating)}</div>
    <p style="white-space:pre-wrap">${esc(t.body)}</p>
    <div class="row" style="gap:8px;align-items:center;flex-wrap:wrap">
      <span class="hint">${+t.approved ? 'تأییدشده' : 'در انتظار تأیید'} · ${+t.enabled ? 'نمایش داده می‌شود' : 'مخفی'}</span>
      ${+t.approved ? '<button class="btn sm tred" data-op="delete" type="button">حذف نظر</button>' : '<button class="btn sm tgreen" data-op="approve" type="button">تأیید</button>'}
      ${+t.enabled ? '<button class="btn sm tred" data-op="hide" type="button">مخفی کردن</button>' : '<button class="btn sm tgreen" data-op="show" type="button">نمایش دادن</button>'}
    </div></div></div>`).join('');
  $('#c').onclick = async e => {
    const b = e.target.closest('[data-op]'); if (!b) return;
    if (b.dataset.op === 'delete' && !confirm('این نظر برای همیشه حذف شود؟')) return;
    if (report(await call('testimonials_admin.php', { id: +b.closest('[data-id]').dataset.id, op: b.dataset.op }))) VIEWS.testimonials();
  };
};
