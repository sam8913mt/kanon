/**
 * profiles.js — «کاربران / پروفایل‌ها» در پنل
 *  - فهرست با جست‌وجو/فیلتر/صفحه‌بندی (Editor: فقط اردوهای مجاز و Mask‌شده؛ سمت سرور اعمال می‌شود)
 *  - پروفایل کامل کاربر با PV (profile-view.js) و مدیریت همه‌ی بخش‌ها (Admin)
 */
VIEWS.profiles = async (page = 1) => {
  const ed = !isAdmin();
  const F = window.__pf || (window.__pf = { q: '', status: '', school: '', grade: '', neighborhood: '', resp: '' });
  const qs = () => 'profile/users.php?' + new URLSearchParams({ ...F, page }).toString();
  $('#c').innerHTML = `<form class="row" id="pff" style="flex-wrap:wrap;gap:8px">
    <input id="pq" placeholder="${ed ? 'نام' : 'نام، موبایل، کد ملی یا ایمیل'}" value="${esc(F.q)}">
    ${ed ? '' : `<select id="ps"><option value="">فعال و تعلیق‌شده</option><option value="active">فعال</option><option value="suspended">تعلیق‌شده</option><option value="archived">بایگانی‌شده</option><option value="all">همه</option></select>
    <select id="pr"><option value="">مسئولیت: همه</option><option value="kanoon">دارای مسئولیت کانون</option><option value="heyat">دارای مسئولیت هیئت</option><option value="any">دارای مسئولیت</option><option value="none">بدون مسئولیت</option></select>`}
    <input id="psc" placeholder="مدرسه" value="${esc(F.school)}"><input id="pg" placeholder="پایه" value="${esc(F.grade)}"><input id="pn" placeholder="محله" value="${esc(F.neighborhood)}">
    <button class="btn sm">جست‌وجو</button>
    ${ed ? '' : '<a class="btn alt sm" href="#profiles/top">⭐ افراد برتر</a><button class="btn alt sm" id="pnew" type="button">+ کاربر جدید</button><button class="btn alt sm" id="pbc" type="button">ارسال اطلاعیه به همه</button>'}</form>
    ${ed ? '<p class="hint">شما فقط ثبت‌نام‌کنندگان اردوهای مجاز خود را می‌بینید؛ اطلاعات حساس (کد ملی، موبایل) Mask شده یا پنهان است.</p>' : ''}
    <div id="pl"><p>در حال بارگذاری…</p></div>`;
  if (!ed) { $('#ps').value = F.status; $('#pr').value = F.resp; }
  $('#pff').onsubmit = e => {
    e.preventDefault();
    Object.assign(F, { q: $('#pq').value.trim(), school: $('#psc').value.trim(), grade: $('#pg').value.trim(), neighborhood: $('#pn').value.trim() });
    if (!ed) Object.assign(F, { status: $('#ps').value, resp: $('#pr').value });
    VIEWS.profiles(1);
  };
  if (!ed) {
    $('#pnew').onclick = () => PV.formModal('کاربر جدید', [{ n: 'first_name', l: 'نام', req: 1 }, { n: 'last_name', l: 'نام خانوادگی', req: 1 }, { n: 'mobile', l: 'شماره موبایل', ltr: 1, ph: '09123456789' }, { n: 'password', l: 'گذرواژه (حداقل ۸ کاراکتر)', t: 'password' }], {},
      async ({ data }, close, err) => { const r = await call('profile/manage.php', { section: 'member', op: 'create', data }); if (!r.success) return err(r.message); toast(r.message); close(); location.hash = '#profiles/' + r.data.id; });
    $('#pbc').onclick = () => PV.formModal('اطلاعیه برای همه‌ی کاربران فعال', [{ n: 'title', l: 'عنوان', req: 1 }, { n: 'body', l: 'متن', t: 'area', max: 500 }], {},
      async ({ data }, close, err) => { if (!confirm('اطلاعیه برای همه‌ی کاربران فعال ارسال شود؟')) return; const r = await call('profile/manage.php', { section: 'broadcast', op: 'send', data }); if (!r.success) return err(r.message); toast(r.message); close(); }, { ok: 'ارسال' });
  }
  const r = await call(qs());
  if (!r.success) { $('#pl').innerHTML = `<p class="err">${esc(r.message)}</p>`; return; }
  const d = r.data, fa = n => (+n).toLocaleString('fa-IR');
  const av = x => x.avatar_id ? `<img src="/api/profile/file.php?id=${x.avatar_id}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover">` : '<span class="pv-av" style="width:36px;height:36px;font-size:15px">' + esc((x.first_name || '؟').slice(0, 1)) + '</span>';
  const dt = s => esc(PV.pd(s));
  const cols = ed ? ['عکس', 'نام', 'نام خانوادگی', 'کد ملی', 'موبایل', 'مدرسه', 'پایه', 'رشته', 'محله', 'وضعیت', 'نقش', 'عضویت']
    : ['عکس', 'نام', 'نام خانوادگی', 'کد ملی', 'موبایل', 'ایمیل', 'مدرسه', 'پایه', 'رشته', 'محله', 'وضعیت', 'نقش', 'عضویت', 'آخرین ورود', 'آخرین فعالیت', 'مسئولیت'];
  $('#pl').innerHTML = `<p class="hint">${fa(d.total)} کاربر</p><div class="tw"><table><tr>${cols.map(c => `<th>${c}</th>`).join('')}</tr>${d.rows.map(x => `<tr class="click" data-id="${x.id}" tabindex="0" style="cursor:pointer">
    <td>${av(x)}</td><td>${esc(x.first_name)}</td><td>${esc(x.last_name)}</td><td dir="ltr">${esc(x.national_id || '')}</td><td dir="ltr">${esc(x.mobile)}</td>${ed ? '' : `<td dir="ltr">${esc(x.email || '')}</td>`}
    <td>${esc(x.school || '')}</td><td>${esc(x.grade || '')}</td><td>${esc(x.field_of_study || '')}</td><td>${esc(x.neighborhood || '')}</td><td>${PV.badge(x.status, x.status_label)}</td><td>${esc(x.role_title)}</td><td>${dt(x.created_at)}</td>
    ${ed ? '' : `<td>${x.last_login_at ? esc(PV.pdt(x.last_login_at)) : '—'}</td><td>${x.last_activity_at ? esc(PV.pdt(x.last_activity_at)) : '—'}</td><td style="white-space:nowrap"><button class="btn alt sm" type="button" data-rb="kanoon" data-id="${x.id}">+ کانون</button> <button class="btn alt sm" type="button" data-rb="heyat" data-id="${x.id}">+ هیئت</button></td>`}</tr>`).join('') || `<tr><td colspan="${cols.length}">کاربری یافت نشد.</td></tr>`}</table></div>
    ${d.pages > 1 ? `<div class="row" style="margin-top:10px"><button class="btn alt sm" id="pp"${d.page <= 1 ? ' disabled' : ''}>قبلی</button><span>صفحه ${fa(d.page)} از ${fa(d.pages)}</span><button class="btn alt sm" id="pn2"${d.page >= d.pages ? ' disabled' : ''}>بعدی</button></div>` : ''}`;
  $('#pl').querySelectorAll('[data-rb]').forEach(b => { b.onclick = e => { e.stopPropagation(); location.hash = '#profiles/' + b.dataset.id + '/responsibilities/' + b.dataset.rb; }; });
  $('#pl').querySelectorAll('tr.click').forEach(tr => { const go = () => location.hash = '#profiles/' + tr.dataset.id; tr.onclick = go; tr.onkeydown = e => { if (e.key === 'Enter') go(); }; });
  if ($('#pp')) { $('#pp').onclick = () => VIEWS.profiles(d.page - 1); $('#pn2').onclick = () => VIEWS.profiles(d.page + 1); }
};

/** پروفایل کامل یک کاربر (#profiles/ID) */
VIEWS.__profile = async (id, tab, body) => {
  const admin = isAdmin();
  $('#c').innerHTML = `<p><a class="btn alt sm" href="#profiles">← بازگشت به فهرست</a></p><div id="pvr"></div>`;
  const manage = (section, op, p) => {
    const { id: rid = 0, data = {}, file } = p;
    if (file) {
      const fd = new FormData();
      fd.append('member_id', id); fd.append('section', section); fd.append('op', op); fd.append('id', rid); fd.append('data', JSON.stringify(data)); fd.append('file', file);
      return upload('profile/manage.php', fd);
    }
    return call('profile/manage.php', { member_id: id, section, op, id: rid, data });
  };
  const act = (section, op, p) => {
    const { id: rid, data = {}, file } = p;
    switch (section) {
      case 'personal': return manage('member', 'update', { data });
      case 'avatar': return manage('member', op === 'remove' ? 'remove_avatar' : 'avatar', { file });
      case 'member': return manage('member', op, { data });
      case 'camps': return op === 'attendance'
        ? call('registrations.php', { op: 'attendance', id: rid, attendance: data.attendance })
        : call('registrations.php', { op: 'note', id: rid, note: data.note, visible: data.visible });
      case 'security': return manage('member', 'revoke_sessions', {});
      default: return manage(section, op, { id: rid, data, file });
    }
  };
  PV.mount($('#pvr'), {
    mode: admin ? 'admin' : 'editor', tab: tab || '', openAdd: admin && tab === 'responsibilities' && ['kanoon', 'heyat'].includes(body) ? body : '',
    get: (section, params) => call(`profile/user.php?id=${id}&section=${section}${params ? '&' + new URLSearchParams(params) : ''}`),
    act, fileUrl: fid => '/api/profile/file.php?id=' + fid, upl: p => '/uploads/' + p, campUrl: slug => '/camp/' + encodeURIComponent(slug),
  });
};

/** افراد برتر (#profiles/top): انتخاب، مرتبه و ترتیب نمایش در اسلایدر صفحه‌ی اصلی */
VIEWS.__top = async (q = '') => {
  if (!isAdmin()) { $('#c').innerHTML = '<p class="err">به این بخش دسترسی ندارید.</p>'; return; }
  const r = await call('profile/top.php?q=' + encodeURIComponent(q));
  if (!r.success) { $('#c').innerHTML = `<p class="err">${esc(r.message)}</p>`; return; }
  const { top, candidates } = r.data;
  const av = x => x.avatar_id ? `<img src="/api/profile/file.php?id=${x.avatar_id}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover">` : '<span class="pv-av" style="width:36px;height:36px;font-size:15px">' + esc((x.name || '؟').slice(0, 1)) + '</span>';
  const info = x => `<div style="flex:1;min-width:0"><b>${esc(x.name)}</b><div class="hint">هیئت: ${esc(x.heyat) || '—'} · مرتبه: ${esc(x.kanoon) || '—'}</div></div>`;
  $('#c').innerHTML = `<p><a class="btn alt sm" href="#profiles">← بازگشت به فهرست</a></p>
    <h3>افراد برتر نمایش‌داده‌شده در صفحه‌ی اصلی</h3>
    <div id="tl">${top.map((x, i) => `<div class="row" style="gap:8px;margin:6px 0;align-items:center" data-id="${x.id}">${av(x)}${info(x)}
      <button class="btn alt sm" data-mv="${x.id}:-1" type="button" aria-label="بالا">↑</button><button class="btn alt sm" data-mv="${x.id}:1" type="button" aria-label="پایین">↓</button><button class="btn alt sm dng" data-rm="${x.id}" type="button">حذف</button></div>`).join('') || '<p class="hint">هنوز کسی انتخاب نشده است.</p>'}</div>
    <h3>افزودن فرد برتر</h3>
    <form class="row" id="tq" style="gap:8px"><input id="tqi" placeholder="جست‌وجوی نام" value="${esc(q)}"><button class="btn sm">جست‌وجو</button></form>
    <div id="tc">${candidates.map(x => `<div class="row" style="gap:8px;margin:6px 0;align-items:center">${av(x)}${info(x)}<button class="btn sm" data-add="${x.id}" type="button">+ افزودن</button></div>`).join('') || '<p class="hint">موردی یافت نشد.</p>'}</div>`;
  const again = () => VIEWS.__top($('#tqi') ? $('#tqi').value.trim() : q);
  $('#tq').onsubmit = e => { e.preventDefault(); again(); };
  $('#c').onclick = async e => {
    const b = e.target.closest('button'); if (!b) return;
    const d = b.dataset;
    const m = (op, id, data) => call('profile/manage.php', { member_id: +id, section: 'member', op, data });
    if (d.add) { if (report(await m('set_top', d.add, { is_top: 1 }))) again(); }
    else if (d.rm) { if (report(await m('set_top', d.rm, { is_top: 0 }))) again(); }
    else if (d.mv) { const [id, dir] = d.mv.split(':'); if (report(await call('profile/top.php', { op: 'reorder', ids: moveId(top.map(x => x.id), +id, +dir) }))) again(); }
  };
};
