/** pay.js — /pay: صفحه‌ی پرداخت کارت‌به‌کارت پس از ثبت‌نام؛ کپی شماره کارت و بارگذاری امن رسید ← /api/payment.php */
document.addEventListener('DOMContentLoaded', async () => {
  const root = byId('pay'), E = esc;
  let id = qs('id'), t = qs('t') || '', slug = '';
  try { id = id || sessionStorage.getItem('reg_id'); t = t || sessionStorage.getItem('reg_track') || ''; slug = sessionStorage.getItem('reg_camp') || ''; } catch (e) {}
  const done = () => { location.href = slug ? U.regDone(slug) : U.camps(); };
  if (CFG.DEMO_MODE || !id) { root.innerHTML = `<p class="err">ثبت‌نامی برای پرداخت پیدا نشد. <a href="${U.camps()}">اردوها</a></p>`; return; }
  const q = '?id=' + encodeURIComponent(id) + (t ? '&t=' + encodeURIComponent(t) : '');
  const r = await api('/payment.php' + q);
  if (!r.success) { root.innerHTML = `<p class="err">${E(r.message || 'خطا')}${r.code === 404 ? ' (اگر ثبت‌نام مهمان بوده‌اید، با همان مرورگر یا کد پیگیری وارد شوید)' : ''}</p><a class="btn" href="${U.camps()}">بازگشت به اردوها</a>`; return; }
  const d = r.data, card = d.card, rc = d.receipt;
  const num = card.number ? card.number.replace(/(\d{4})(?=\d)/g, '$1 ') : '';
  const fa = s => (window.JD ? JD.toFa(s) : s);
  const draw = () => {
    const ok = rc && rc.status === 'approved';
    root.innerHTML = `<h1>پرداخت ثبت‌نام «${E(d.camp)}»</h1><p>کد پیگیری: <b dir="ltr">${E(d.code)}</b>${d.cost ? ' · هزینه: <b>' + E(d.cost) + '</b>' : ''}</p>
      ${num ? `<div class="pay-card"><div class="muted" style="color:#fff9">شماره کارت</div><div class="pn" id="pn">${fa(num)}</div>
        <div class="ph"><span>${card.holder ? 'به نام: <b>' + E(card.holder) + '</b>' : ''}</span><button type="button" class="btn sm" id="cp">کپی شماره کارت</button></div></div>
        <p class="muted">مبلغ را کارت‌به‌کارت واریز کنید، سپس تصویر رسید را بارگذاری کنید.</p>`
      : '<p class="err">اطلاعات کارت هنوز توسط مدیر ثبت نشده است؛ بعداً دوباره مراجعه کنید یا با کانون تماس بگیرید.</p>'}
      ${rc ? `<p>وضعیت رسید: <span class="pay-st ${E(rc.status)}">${E(rc.status_label)}</span>${rc.status === 'rejected' && rc.note ? '<br><span class="err">' + E(rc.note) + '</span>' : ''}</p>` : ''}
      ${ok ? '<p class="ok">✔ رسید شما تأیید شد.</p>' : `<form id="pf" class="f" novalidate><label for="rf">تصویر رسید (JPG، PNG یا WEBP — حداکثر ${fa(d.max_mb)} مگابایت)</label>
        <input id="rf" type="file" accept="image/jpeg,image/png,image/webp"><img id="rp" class="pay-prev" alt="پیش‌نمایش رسید" hidden>
        <p class="err" id="pe" role="alert"></p><button class="btn" id="ps">${rc ? 'جایگزینی رسید' : 'ثبت رسید'}</button></form>`}
      <p><a class="btn alt" href="#" id="nx">${ok || rc ? 'ادامه' : 'بعداً رسید را ثبت می‌کنم'}</a></p>`;
    byId('nx').onclick = e => { e.preventDefault(); done(); };
    const cp = byId('cp'); if (cp) cp.onclick = async () => { cp.textContent = (await copyText(card.number)) ? 'کپی شد ✔' : 'کپی نشد؛ دستی کپی کنید'; setTimeout(() => cp.textContent = 'کپی شماره کارت', 2500); };
    const f = byId('pf'); if (!f) return;
    let file = null;
    byId('rf').onchange = async ev => {
      const e = byId('pe'); e.textContent = ''; file = null; const p = byId('rp'); p.hidden = true;
      const f0 = ev.target.files[0]; if (!f0) return;
      if (!/^image\/(jpeg|png|webp)$/.test(f0.type)) { e.textContent = 'فقط تصویر JPG، PNG یا WEBP مجاز است'; ev.target.value = ''; return; }
      file = await compressImage(f0, { maxDim: 1600, quality: 0.8 });
      if (file.size > d.max_mb * 1048576) { e.textContent = 'حجم تصویر بیش از حد مجاز است'; file = null; ev.target.value = ''; return; }
      p.src = URL.createObjectURL(file); p.hidden = false;
    };
    f.onsubmit = async ev => {
      ev.preventDefault(); const e = byId('pe'); e.textContent = '';
      if (!file) { e.textContent = 'تصویر رسید را انتخاب کنید'; return; }
      const fd = new FormData(); fd.append('id', id); fd.append('t', t); fd.append('csrf', d.csrf); fd.append('receipt', file, file.name);
      byId('ps').disabled = true;
      const x = await api('/payment.php', { method: 'POST', body: fd });
      byId('ps').disabled = false;
      if (!x.success) { e.textContent = x.message; return; }
      done();
    };
  };
  draw();
});
