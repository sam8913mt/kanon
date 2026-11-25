/** registrations.js — ثبت‌نام‌ها: جستجو، فیلتر، مشاهده‌ی جزئیات (با عکس)، ویرایش، تغییر وضعیت، حذف، Export */
const REG = LBL.reg_status;
const regOptions = cur => Object.entries(REG).map(([k, v]) => `<option value="${k}" ${k === cur ? 'selected' : ''}>${v}</option>`).join('');

VIEWS.registrations = async () => {
  const camps = (await list('camps')).data || [];
  $('#c').innerHTML = `<div class="row">
      <select id="fc" aria-label="اردو"><option value="">همه اردوها</option>${camps.map(c => `<option value="${esc(c.slug)}">${esc(c.title)}</option>`).join('')}</select>
      <select id="fs" aria-label="وضعیت"><option value="">همه وضعیت‌ها</option>${regOptions('')}</select>
      <input id="fq" placeholder="کد ملی" style="max-width:150px" aria-label="کد ملی">
      <select id="fl" aria-label="سؤال"><option value="">فیلتر بر اساس سؤال</option></select>
      <input id="fv" placeholder="مقدار پاسخ" style="max-width:170px" aria-label="مقدار پاسخ">
      <button class="btn" id="go">فیلتر</button></div>
    <div class="row"><a class="btn alt" id="ex1">Export CSV</a><a class="btn alt" id="ex2">Export Excel</a><a class="btn alt" id="ex3" target="_blank">چاپ / PDF</a>
      <select id="bs" aria-label="تغییر گروهی"><option value="">تغییر گروهی وضعیت…</option>${regOptions('')}</select><button class="btn alt" id="ba">اعمال روی انتخاب‌شده‌ها</button></div>
    <div class="tw" id="rt"></div>`;
  const qstr = () => `registrations.php?camp=${encodeURIComponent($('#fc').value)}&status=${$('#fs').value}&q=${encodeURIComponent($('#fq').value)}&af=${encodeURIComponent($('#fl').value)}&av=${encodeURIComponent($('#fv').value)}`;
  let rows = [];
  const load = async () => {
    $('#ex1').href = '/api/' + qstr() + '&export=csv'; $('#ex2').href = '/api/' + qstr() + '&export=xlsx'; $('#ex3').href = '/api/' + qstr() + '&export=print';
    rows = (await call(qstr())).data || [];
    $('#rt').innerHTML = `<table><tr><th><input type="checkbox" id="sa" aria-label="انتخاب همه"></th><th>عکس</th><th>شناسه</th><th>اردو</th><th>کد ملی</th><th>تاریخ</th><th>وضعیت</th><th></th></tr>${rows.map(x => `<tr>
      <td><input type="checkbox" class="rc" value="${x.id}" aria-label="انتخاب"></td>
      <td>${x.has_photo ? `<img src="/api/reg_file.php?id=${x.id}" width="48" height="48" style="object-fit:cover;border-radius:8px" alt="عکس دانش‌آموز" loading="lazy">` : '—'}</td>
      <td>${x.id}</td><td>${esc(x.camp)}</td><td dir="ltr">${esc(x.national_id)}</td><td>${esc(jd(x.created_at, true))}</td>
      <td><select data-id="${x.id}" aria-label="وضعیت">${regOptions(x.status)}</select></td>
      <td><button class="btn alt sm" data-view="${x.id}">مشاهده</button></td></tr>`).join('') || '<tr><td colspan="8">ثبت‌نامی یافت نشد.</td></tr>'}</table>`;
    $('#sa').onchange = e => document.querySelectorAll('.rc').forEach(i => i.checked = e.target.checked);
  };
  const labels = async () => {
    const l = (await call('registrations.php?labels=1&camp=' + encodeURIComponent($('#fc').value))).data || [];
    $('#fl').innerHTML = '<option value="">فیلتر بر اساس سؤال</option>' + l.map(x => `<option>${esc(x)}</option>`).join('');
  };
  $('#go').onclick = load; $('#fc').onchange = labels;
  $('#ba').onclick = async () => {
    const ids = [...document.querySelectorAll('.rc:checked')].map(i => +i.value), st = $('#bs').value;
    if (!ids.length || !st) return toast('ثبت‌نام‌ها و وضعیت را انتخاب کنید', false);
    if (!confirm(ids.length + ' ثبت‌نام تغییر کند؟')) return;
    report(await call('registrations.php', { ids, status: st })); load();
  };
  $('#rt').onchange = async e => { if (e.target.dataset.id) report(await call('registrations.php', { id: e.target.dataset.id, status: e.target.value })); };
  $('#rt').onclick = e => { const v = e.target.dataset.view; if (v) regDetail(rows.find(r => r.id == v), load); };
  labels(); load();
};

/** مشاهده/ویرایش یک ثبت‌نام: عکس کنار اطلاعات */
function regDetail(x, back) {
  const draw = () => {
    $('#c').innerHTML = `<div class="card detail"><div class="bd"><div class="row"><button class="btn alt sm" id="bk">← بازگشت</button><b>ثبت‌نام #${x.id} — ${esc(x.camp)}</b></div>
      <div class="rd">${x.has_photo ? `<a href="/api/reg_file.php?id=${x.id}" target="_blank" rel="noopener"><img class="rphoto" src="/api/reg_file.php?id=${x.id}" alt="عکس دانش‌آموز"></a>` : '<div class="rphoto none">بدون عکس</div>'}
      <div><table><tr><th>کد ملی</th><td dir="ltr">${esc(x.national_id)}</td></tr><tr><th>تاریخ ثبت</th><td>${esc(jd(x.created_at, true))}</td></tr>
        <tr><th>نوع ثبت‌نام</th><td>${x.member_id ? 'عضو (دارای حساب)' : 'مهمان (بدون حساب)'}${x.tracking_code ? ` — کد پیگیری: <b dir="ltr">${esc(x.tracking_code)}</b>` : ''}</td></tr>
        <tr><th>وضعیت</th><td><select id="st">${regOptions(x.status)}</select></td></tr>
        <tr><th>حضور</th><td><select id="at">${[['unknown', 'نامشخص'], ['attended', 'شرکت کرده'], ['absent', 'غایب']].map(([k, l]) => `<option value="${k}"${k === x.attendance ? ' selected' : ''}>${l}</option>`).join('')}</select></td></tr>
        <tr><th>توضیحات مدیر</th><td><textarea id="nt" rows="2" maxlength="2000">${esc(x.review_note || '')}</textarea><label class="op"><input type="checkbox" id="nv"${+x.note_visible ? ' checked' : ''}> نمایش به کاربر</label> <button class="btn alt sm" id="ns" type="button">ذخیره‌ی توضیحات</button></td></tr>
        ${x.member_id && A.areas.includes('profiles') ? `<tr><th>پروفایل</th><td><a href="#profiles/${+x.member_id}">مشاهده‌ی پروفایل کاربر</a></td></tr>` : ''}
        ${Object.entries(x.answers).map(([k, v]) => `<tr><th>${esc(k)}</th><td>${x.files && x.files[k] ? `<a href="/api/reg_file.php?id=${x.id}&a=${+x.files[k]}" target="_blank" rel="noopener">مشاهده‌ی فایل</a>` : esc(v)}</td></tr>`).join('')}</table>
        <div class="row"><button class="btn" id="ed">ویرایش پاسخ‌ها</button>${isAdmin() ? '<button class="btn alt" id="dl">حذف ثبت‌نام</button>' : ''}</div></div></div></div></div>`;
    $('#bk').onclick = () => VIEWS.registrations();
    $('#st').onchange = async e => { if (report(await call('registrations.php', { id: x.id, status: e.target.value }))) x.status = e.target.value; };
    $('#at').onchange = async e => { if (report(await call('registrations.php', { op: 'attendance', id: x.id, attendance: e.target.value }))) x.attendance = e.target.value; };
    $('#ns').onclick = async () => { if (report(await call('registrations.php', { op: 'note', id: x.id, note: $('#nt').value, visible: $('#nv').checked ? 1 : 0 }))) { x.review_note = $('#nt').value; x.note_visible = $('#nv').checked ? 1 : 0; } };
    $('#ed').onclick = edit;
    const dl = $('#dl');
    if (dl) dl.onclick = async () => { if (confirm('این ثبت‌نام و فایل‌های آن برای همیشه حذف شود؟') && report(await call('registrations.php', { op: 'delete', id: x.id }))) VIEWS.registrations(); };
  };
  const edit = () => {
    $('#c').innerHTML = `<form class="f wide" id="er"><h3>ویرایش ثبت‌نام #${x.id}</h3>${Object.entries(x.answers).filter(([k]) => !(x.files && x.files[k])).map(([k, v]) => `<label>${esc(k)}<input data-k="${esc(k)}" value="${esc(v)}"></label>`).join('')}<p class="err" id="ee" role="alert"></p><button class="btn">ذخیره</button> <button type="button" class="btn alt" id="cn">انصراف</button></form>`;
    $('#cn').onclick = draw;
    $('#er').onsubmit = async e => {
      e.preventDefault();
      const a = {};
      $('#er').querySelectorAll('[data-k]').forEach(i => a[i.dataset.k] = i.value);
      const r = await call('registrations.php', { id: x.id, answers: a });
      if (r.success) { Object.assign(x.answers, a); toast(r.message); draw(); } else $('#ee').textContent = r.message;
    };
  };
  draw();
}
