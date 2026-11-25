/** payments.js — رسیدهای پرداخت: دسته‌های «رزرو شده / تأیید شده / قدیمی» + تنظیم شماره کارت (فقط Admin) */
const PAY_CATS = [['reserved', 'رزرو شده'], ['approved', 'تأیید شده'], ['old', 'قدیمی']];
VIEWS.payments = async (cat = 'reserved') => {
  const r = await call('payments_admin.php?cat=' + cat);
  if (!r.success) { $('#c').innerHTML = `<p class="err">${esc(r.message)}</p>`; return; }
  const { counts, items, old_days } = r.data;
  let card = { number: '', holder: '' };
  if (isAdmin()) card = (await call('payments_admin.php?card=1')).data || card;
  const fa = n => (+n).toLocaleString('fa-IR');
  $('#c').innerHTML = `${isAdmin() ? `<form class="f wide" id="cf"><fieldset class="fs"><legend>کارت پرداخت (نمایش به ثبت‌نام‌کنندگان در صفحه‌ی پرداخت)</legend>
      <label for="cn">شماره کارت (۱۶ رقم)</label><input id="cn" dir="ltr" inputmode="numeric" maxlength="23" value="${esc(card.number)}" placeholder="6037 0000 0000 0000">
      <label for="ch">نام صاحب کارت</label><input id="ch" maxlength="100" value="${esc(card.holder)}"><button class="btn">ذخیره‌ی اطلاعات کارت</button></fieldset></form>` : ''}
    <div class="row" role="tablist">${PAY_CATS.map(([k, l]) => `<button class="btn ${k === cat ? '' : 'alt'}" data-cat="${k}" role="tab" aria-selected="${k === cat}">${l} (${fa(counts[k])})</button>`).join('')}</div>
    <p class="hint">«قدیمی»: رسیدهای بایگانی‌شده یا قدیمی‌تر از ${fa(old_days)} روز.</p>
    <div class="tw"><table><tr><th>رسید</th><th>ثبت‌نام</th><th>نام</th><th>اردو</th><th>کد ملی</th><th>تاریخ ثبت</th><th>وضعیت</th><th></th></tr>${items.map(x => `<tr>
      <td><a href="/api/reg_file.php?id=${x.reg_id}&r=1" target="_blank" rel="noopener"><img src="/api/reg_file.php?id=${x.reg_id}&r=1" width="48" height="48" style="object-fit:cover;border-radius:8px" alt="رسید" loading="lazy"></a></td>
      <td dir="ltr">${esc(x.code)}</td><td>${esc(x.name || '—')}</td><td>${esc(x.camp)}</td><td dir="ltr">${esc(x.national_id || '')}</td><td>${esc(jd(x.created_at, true))}</td>
      <td>${esc(x.status_label)}${x.note ? `<div class="hint">${esc(x.note)}</div>` : ''}${x.approved_at ? `<div class="hint">${esc(jd(x.approved_at, true))}</div>` : ''}</td>
      <td>${x.status !== 'approved' ? `<button class="btn sm" data-op="approve" data-id="${x.id}">تأیید</button> ` : ''}${x.status !== 'rejected' ? `<button class="btn alt sm" data-op="reject" data-id="${x.id}">رد</button> ` : ''}${x.archived ? '' : `<button class="btn alt sm" data-op="archive" data-id="${x.id}">بایگانی</button>`}${cat === 'old' ? `<button class="btn alt sm" data-op="restore" data-id="${x.id}">بازگردانی</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="8">رسیدی در این دسته نیست.</td></tr>'}</table></div>`;
  const cf = $('#cf');
  if (cf) cf.onsubmit = async e => { e.preventDefault(); report(await call('payments_admin.php', { op: 'card', number: $('#cn').value, holder: $('#ch').value })); };
  $('#c').onclick = async e => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.cat) return VIEWS.payments(b.dataset.cat);
    const op = b.dataset.op; if (!op) return;
    let note = '';
    if (op === 'reject') { note = prompt('دلیل رد رسید (اختیاری؛ به کاربر نمایش داده می‌شود):'); if (note === null) return; }
    if (report(await call('payments_admin.php', { op, id: +b.dataset.id, note }))) VIEWS.payments(cat);
  };
};
