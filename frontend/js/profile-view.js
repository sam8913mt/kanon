/**
 * profile-view.js — نمایش‌دهنده‌ی مشترک پروفایل (صفحه‌ی /profile، و پروفایل کاربر در پنل Admin/Editor)
 * PV.mount(root, opts) — opts:
 *   mode: 'self' | 'admin' | 'editor'
 *   get(section, params) → Promise<{success,data,message}>      (خواندن؛ همه‌ی کنترل‌های دسترسی در PHP است)
 *   act(section, op, {id, data, file}) → Promise<{success,message}> (نوشتن؛ آداپتور هر حالت را به API درست می‌برد)
 *   fileUrl(id) آدرس فایل خصوصی | upl(path) آدرس فایل ثبت‌نام | campUrl(slug) | tab (تب آغازین)
 * این فایل فقط UI است؛ هیچ تصمیم امنیتی‌ای در آن گرفته نمی‌شود.
 */
(function () {
  const E = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const pd = s => {
    if (!s) return '';
    if (!/^\d{4}-\d{2}-\d{2}/.test(s)) return s;
    if (window.JD) return JD.fmt(s);
    try { return new Date(s.slice(0, 10) + 'T12:00:00').toLocaleDateString('fa-IR-u-ca-persian', { year: 'numeric', month: '2-digit', day: '2-digit' }); } catch (e) { return s; }
  };
  const pdt = s => {
    if (!s) return '';
    if (window.JD && /^\d{4}-\d{2}-\d{2}/.test(s)) return JD.fmt(s, true);
    try { return pd(s) + ' ' + new Date(String(s).replace(' ', 'T')).toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' }); } catch (e) { return pd(s); }
  };
  const fsize = n => n == null ? '' : n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
  const BADGE = { approved: 'ok', completed: 'ok', done: 'ok', active: 'ok', attended: 'ok', rejected: 'bad', cancelled: 'bad', failed: 'bad', suspended: 'bad', archived: 'bad', absent: 'bad',
    pending: 'warn', reviewed: 'info', new: 'warn', in_progress: 'info', planned: 'info', inactive: 'warn', ended: '' };
  const badge = (st, label) => `<span class="pv-b ${BADGE[st] || ''}">${E(label)}</span>`;
  const empty = t => `<div class="pv-empty">${E(t)}</div>`;

  function toast(msg, bad) {
    const t = document.createElement('div');
    t.className = 'pv-toast' + (bad ? ' bad' : ''); t.textContent = msg; t.setAttribute('role', 'status');
    document.body.appendChild(t); setTimeout(() => t.remove(), 3200);
  }

  /** مودال عمومی. fields: [{n,l,t,o,req,ph,hint,dis,dep,om,half}] */
  function modal(title, bodyHtml, onSubmit, opt = {}) {
    const ov = document.createElement('div'); ov.className = 'pv-ov';
    ov.innerHTML = `<form class="pv-modal" novalidate role="dialog" aria-modal="true" aria-label="${E(title)}"><h2>${E(title)}</h2>${bodyHtml}<p class="pv-err" role="alert"></p>
      <div class="ft">${opt.noSubmit ? '' : `<button class="btn" type="submit">${E(opt.ok || 'ذخیره')}</button>`}<button class="btn alt" type="button" data-x>${opt.noSubmit ? 'بستن' : 'انصراف'}</button></div></form>`;
    document.body.appendChild(ov);
    const f = ov.querySelector('form'), er = ov.querySelector('.pv-err');
    const close = () => ov.remove();
    ov.querySelector('[data-x]').onclick = close;
    ov.addEventListener('mousedown', e => { if (e.target === ov) close(); });
    f.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    f.onsubmit = async e => {
      e.preventDefault();
      if (!onSubmit) return;
      const btn = f.querySelector('button[type=submit]'); er.textContent = '';
      if (btn) btn.disabled = true;
      const res = await onSubmit(collect(f), close, m => { er.textContent = m; });
      if (btn) btn.disabled = false;
      return res;
    };
    const first = f.querySelector('input:not([disabled]),select,textarea'); if (first) first.focus();
    return { ov, f, close };
  }

  function collect(f) {
    const data = {}; let file = null;
    f.querySelectorAll('[name]').forEach(el => {
      if (el.type === 'file') { file = el.files[0] || file; }
      else if (el.type === 'checkbox') data[el.name] = el.checked ? 1 : 0;
      else if (!el.disabled) data[el.name] = el.value;
    });
    return { data, file };
  }

  function fieldsHtml(fields, v = {}) {
    return fields.map(x => {
      const val = v[x.n] ?? '';
      const id = 'pvf_' + x.n;
      const dis = x.dis ? ' disabled' : '';
      let ctl;
      if (x.t === 'area') ctl = `<textarea id="${id}" name="${x.n}" rows="3" maxlength="${x.max || 2000}"${dis}>${E(val)}</textarea>`;
      else if (x.t === 'sel') {
        const opts = x.dep ? (x.om[v[x.dep]] || {}) : x.o;
        ctl = `<select id="${id}" name="${x.n}"${dis} ${x.dep ? `data-dep="${x.dep}"` : ''}>${x.blank ? '<option value="">—</option>' : ''}${Object.entries(opts).map(([k, l]) => `<option value="${E(k)}"${String(k) === String(val) ? ' selected' : ''}>${E(l)}</option>`).join('')}</select>`;
      } else if (x.t === 'check') ctl = `<input id="${id}" name="${x.n}" type="checkbox"${val ? ' checked' : ''}${dis}>`;
      else if (x.t === 'file') ctl = `<input id="${id}" name="${x.n}" type="file" accept="${x.accept || 'image/jpeg,image/png,image/webp,application/pdf'}">`;
      else ctl = `<input id="${id}" name="${x.n}" type="${x.t || 'text'}" value="${E(val)}" maxlength="${x.max || 200}"${x.ltr ? ' dir="ltr"' : ''}${x.jd ? ' data-jdate="text"' : ''}${x.ph ? ` placeholder="${E(x.ph)}"` : ''}${dis} autocomplete="off">`;
      return `<div class="${x.half ? 'half' : ''}"><label for="${id}">${E(x.l)}${x.req ? ' *' : ''}</label>${ctl}${x.hint ? `<div class="pv-warn">${E(x.hint)}</div>` : ''}</div>`;
    }).join('');
  }

  /** وابستگی انتخاب‌ها (مثلاً نوع مسئولیت بر اساس کانون/هیئت) */
  function bindDeps(f, fields) {
    fields.filter(x => x.dep).forEach(x => {
      const src = f.querySelector(`[name=${x.dep}]`), dst = f.querySelector(`[name=${x.n}]`);
      const fill = () => { const cur = dst.value; dst.innerHTML = '<option value="">— سایر / عنوان دلخواه —</option>' + Object.entries(x.om[src.value] || {}).map(([k, l]) => `<option value="${E(k)}">${E(l)}</option>`).join(''); dst.value = cur; };
      src.addEventListener('change', fill);
    });
  }

  function formModal(title, fields, values, submit, opt) {
    const m = modal(title, `<div class="row2">${fieldsHtml(fields, values)}</div>`, submit, opt);
    bindDeps(m.f, fields);
    return m;
  }

  /* ───────────────────────── mount ───────────────────────── */
  function mount(root, o) {
    const S = { main: null, tab: o.tab || 'personal', cache: {} };
    const mode = o.mode, self = mode === 'self', editor = mode === 'editor';
    let admin = mode === 'admin', visitor = false; // admin = دسترسی کامل؛ visitor = مدیر در پروفایل دیگران (فقط مسئولیت و مرتبه)
    const TABS = [['personal', 'اطلاعات شخصی'], ['education', 'تحصیلی'], ['camps', 'اردوها و ثبت‌نام‌ها'], ['activities', 'فعالیت‌ها'], ['responsibilities', 'مسئولیت‌ها'],
      ['courses', 'دوره‌های گذرانده‌شده'], ['books', 'کتاب‌ها'], ['interests', 'علایق'], ['achievements', 'دستاوردها'], ['documents', 'مدارک'], ['images', 'تصاویر'], ['notifications', 'اعلان‌ها'], ['timeline', 'Timeline'],
      ['history', 'سوابق فعالیت'], ['security', 'امنیت حساب']];
    const tabsFor = () => TABS.filter(t => editor ? ['personal', 'education', 'camps'].includes(t[0]) : true);
    const SEC = { camps: 'registrations' };
    const sec = k => SEC[k] || k;

    async function load(section, params) {
      if (!params && S.cache[section]) return S.cache[section];
      const r = await o.get(section, params);
      if (!r.success) throw new Error(r.message || 'خطا در دریافت اطلاعات');
      if (!params) S.cache[section] = r.data;
      return r.data;
    }
    async function act(section, op, p, okMsg) {
      const r = await o.act(section, op, p || {});
      if (!r.success) { toast(r.message || 'خطا', true); return r; }
      toast(okMsg || r.message || 'انجام شد');
      S.cache = {};
      return r;
    }
    async function reload() {
      S.cache = {};
      const r = await o.get('profile');
      if (r.success) { S.main = r.data; S.cache.profile = r.data; }
      draw();
    }

    async function init() {
      root.innerHTML = '<p class="muted">در حال بارگذاری…</p>';
      const r = await o.get('profile');
      if (!r.success) { root.innerHTML = `<div class="pv-empty">${E(r.message || 'خطا در دریافت پروفایل')}</div>`; return; }
      S.main = r.data; S.cache.profile = r.data;
      if (mode === 'admin') { admin = !!r.data.viewer.can_manage; visitor = !admin; }
      if (!tabsFor().some(t => t[0] === S.tab)) S.tab = 'personal';
      draw();
      if (o.openAdd && (admin || visitor)) openForm('responsibilities', 0, { body: o.openAdd });
      if (self) load('notifications').then(n => { S.unread = n.unread; badge2(); }).catch(() => {});
    }
    function badge2() { const b = root.querySelector('[data-tab=notifications]'); if (b && S.unread) b.innerHTML = `اعلان‌ها<span class="n">${S.unread}</span>`; }

    /* ── سربرگ + آمار + تب‌ها ── */
    function draw() {
      const h = S.main.header, st = S.main.stats;
      const av = h.avatar_id ? `<img src="${E(o.fileUrl(h.avatar_id))}" alt="">` : E((h.first_name || '؟').slice(0, 1));
      const resp = (h.responsibilities || []).map(r => `<span class="pv-chip ${r.body === 'kanoon' ? 'k' : 'h'}">${E(r.title)} — ${E(r.body_label)}</span>`).join('');
      const SL = { stats: [['camps', 'اردوها'], ['attended', 'اردوهای شرکت‌کرده'], ['registrations', 'ثبت‌نام‌ها'], ['activities', 'فعالیت‌ها'], ['responsibilities', 'مسئولیت‌ها'], ['courses', 'دوره‌ها'], ['books', 'کتاب‌ها'], ['achievements', 'دستاوردها']] };
      root.innerHTML = `<div class="pv${visitor ? ' pv-visit-mode' : ''}">
        ${visitor ? '<div class="pv-visit" role="note"><span aria-hidden="true">👁</span><div><b>حالت بازدید</b><small>اطلاعات این پروفایل فقط قابل مشاهده است. تنها «گذرواژه»، «مسئولیت‌ها»، «دوره‌ها»، «کتاب‌ها»، «مرتبه» و «حساب پنل» را می‌توانید تغییر دهید.</small></div></div>' : ''}
        <div class="pv-head"><div class="pv-av">${av}</div>
          <div class="pv-hi"><h1>${E(h.full_name)}</h1>
            <div class="pv-chips"><span class="pv-chip">${E(h.role_title)}</span>${badge(h.status, h.status_label)}${h.member_rank ? `<span class="pv-chip">مرتبه: ${E(h.member_rank)}</span>` : ''}${resp}</div>
            <div class="pv-meta"><span>شناسه: ${E(h.id)}</span>${h.username ? `<span>نام کاربری: <bdi>${E(h.username)}</bdi></span>` : ''}${h.mobile ? `<span>موبایل: <bdi>${E(h.mobile)}</bdi></span>` : ''}${h.email ? `<span>ایمیل: <bdi>${E(h.email)}</bdi></span>` : ''}
              <span>عضویت: ${E(pd(h.joined_at))}</span>${h.last_login_at ? `<span>آخرین ورود: ${E(pdt(h.last_login_at))}</span>` : ''}${h.last_activity_at ? `<span>آخرین فعالیت: ${E(pdt(h.last_activity_at))}</span>` : ''}</div></div>
          <div class="pv-acts">${headActs()}</div></div>
        ${st ? `<div class="pv-stats">${SL.stats.map(([k, l]) => `<div class="pv-stat"><b>${(+st[k]).toLocaleString('fa-IR')}</b><span>${l}</span></div>`).join('')}</div>` : '<div style="height:16px"></div>'}
        <div class="pv-tabs" role="tablist">${tabsFor().map(([k, l]) => `<button class="pv-tab" role="tab" data-tab="${k}" aria-selected="${k === S.tab}" type="button">${l}</button>`).join('')}</div>
        <div class="pv-panel" id="pvp" role="tabpanel"></div></div>`;
      root.querySelectorAll('.pv-tab').forEach(b => b.onclick = () => { S.tab = b.dataset.tab; if (self) history.replaceState(null, '', '#' + S.tab); draw(); });
      root.querySelectorAll('[data-h]').forEach(b => b.onclick = () => headAct(b.dataset.h));
      const cur = root.querySelector('.pv-tab[aria-selected=true]'); if (cur) cur.scrollIntoView({ block: 'nearest', inline: 'center' });
      badge2(); panel();
    }

    function headActs() {
      const h = S.main.header, b = (a, l, c = 'alt') => `<button class="btn ${c} sm" data-h="${a}" type="button">${l}</button>`;
      if (self) return `<a class="btn alt sm" href="${E(o.summaryUrl || '/dashboard')}">خلاصه‌ی وضعیت</a>` + b('avatar', 'تغییر عکس') + (h.avatar_id ? b('rmavatar', 'حذف عکس') : '') + b('edit', 'ویرایش اطلاعات', '');
      if (visitor) return b('rank', 'ثبت/تغییر مرتبه', '') + b('staff', 'حساب پنل');
      if (!admin) return '';
      return b('rank', 'مرتبه') + b('edit', 'ویرایش', '') + b('avatar', 'عکس') + b('message', 'ارسال پیام') + b('status', h.status === 'suspended' ? 'فعال‌سازی حساب' : 'تعلیق حساب') +
        b('staff', 'حساب پنل') + (h.status === 'archived' ? b('restore', 'بازگردانی') : b('archive', 'بایگانی', 'dng')) + b('purge', 'حذف کامل', 'dng');
    }

    function headAct(a) {
      const h = S.main.header;
      if (a === 'edit') return editPersonal();
      if (a === 'rank') return formModal('مرتبه‌ی ' + h.full_name, [{ n: 'rank', l: 'مرتبه (خالی = حذف)', max: 100 }], { rank: h.member_rank || '' }, async ({ data }, close, err) => {
        const r = await o.act('member', 'set_rank', { data }); if (!r.success) return err(r.message); toast(r.message); close(); reload();
      });
      if (a === 'avatar' || a === 'rmavatar') {
        if (a === 'rmavatar') return act('avatar', 'remove', {}, 'عکس حذف شد').then(r => r.success && reload());
        const i = document.createElement('input'); i.type = 'file'; i.accept = 'image/jpeg,image/png,image/webp';
        i.onchange = async () => { if (!i.files[0]) return; const f = window.pickAvatar ? await pickAvatar(i.files[0]) : i.files[0]; if (!f) return; const r = await act('avatar', 'upload', { file: f }); if (r.success) reload(); };
        return i.click();
      }
      if (a === 'message') return formModal('ارسال پیام به کاربر', [{ n: 'title', l: 'عنوان', req: 1 }, { n: 'body', l: 'متن', t: 'area', max: 500 }], {}, async ({ data }, close, err) => {
        const r = await o.act('member', 'message', { data }); if (!r.success) return err(r.message); toast(r.message); close();
      }, { ok: 'ارسال' });
      if (a === 'status') { const to = h.status === 'suspended' ? 'active' : 'suspended'; if (confirm(to === 'active' ? 'حساب فعال شود؟' : 'حساب تعلیق شود؟ کاربر از همه‌ی دستگاه‌ها خارج می‌شود.')) act('member', 'set_status', { data: { status: to } }).then(r => r.success && reload()); return; }
      if (a === 'archive') { if (confirm('کاربر بایگانی شود؟ همه‌ی سوابق (ثبت‌نام‌ها و فعالیت‌ها) حفظ می‌شود.')) act('member', 'archive').then(r => r.success && reload()); return; }
      if (a === 'purge') { if (confirm('⚠️ حذف کامل و بی‌بازگشت!\nاین کاربر با پروفایل، مدارک، عکس‌ها و همه‌ی ثبت‌نام‌هایش برای همیشه از دیتابیس پاک می‌شود. ادامه می‌دهید؟') && confirm('آخرین تأیید: واقعاً حذف شود؟')) act('member', 'delete').then(r => { if (r.success) location.hash = '#profiles'; }); return; }
      if (a === 'restore') return act('member', 'restore').then(r => r.success && reload());
      if (a === 'staff') {
        const cur = S.main.staff_link && +S.main.staff_link.active ? S.main.staff_link.role : '';
        return formModal('دسترسی به پنل مدیریت', [{ n: 'role', l: 'نقش در پنل (ورود با همین شماره موبایل و گذرواژه‌ی پروفایل)', t: 'sel', o: { '': '— بدون دسترسی به پنل —', media: 'مدیر رسانه (آلبوم، تصاویر، فایل‌ها، هیئت‌ها، پیامک هیئت)', editor: 'ویرایشگر (Editor)', admin: 'مدیر (Admin)' } }], { role: cur },
          async ({ data }, close, err) => { const r = await o.act('member', 'set_panel_role', { data }); if (!r.success) return err(r.message); toast(r.message); close(); reload(); });
      }
    }

    /* ── ویرایش اطلاعات شخصی ── */
    function editPersonal(only) {
      const fl = S.main.fields.filter(f => f.editable && (!only || only.includes(f.key)));
      const v = {}; fl.forEach(f => v[f.key] = f.raw ?? '');
      const fields = fl.map(f => {
        const x = { n: f.key, l: f.label, ltr: ['national_id', 'mobile', 'emergency_phone', 'email', 'birth_date'].includes(f.key) };
        if (f.key === 'gender') Object.assign(x, { t: 'sel', o: S.main.meta.gender, blank: 1 });
        else if (['address', 'internal_note'].includes(f.key)) Object.assign(x, { t: 'area', max: f.key === 'address' ? 300 : 2000 });
        else if (f.key === 'birth_date') { x.ph = '1385/05/20'; x.jd = true; }
        if (f.key === 'national_id' && self && f.raw) { x.dis = true; x.hint = 'کد ملی پس از ثبت فقط توسط مدیر قابل تغییر است'; }
        return x;
      });
      if (!only || only.includes('bio')) { fields.push({ n: 'bio', l: 'درباره من', t: 'area', max: 1000 }); v.bio = S.main.bio || ''; }
      if (only && only.includes('interests')) { fields.push({ n: 'interests', l: 'علایق (مثلاً ورزش، برنامه‌نویسی، کتاب…)', t: 'area', max: 500 }); v.interests = S.main.interests || ''; }
      if (self && fl.some(f => f.key === 'mobile')) fields.push({ n: 'current_password', l: 'گذرواژه‌ی فعلی (فقط هنگام تغییر موبایل)', t: 'password' });
      formModal('ویرایش اطلاعات', fields, v, async ({ data }, close, err) => {
        const r = await o.act('personal', 'save', { data });
        if (!r.success) return err(r.message);
        toast(r.message); close(); reload();
      });
    }

    /* ── پنل هر تب ── */
    async function panel() {
      const box = root.querySelector('#pvp'); if (!box) return;
      const tab = S.tab;
      try {
        const d = (tab === 'personal' || tab === 'education' || tab === 'interests') ? S.main : await load(sec(tab));
        if (tab !== S.tab) return;
        box.innerHTML = R[tab](d);
        bindPanel(box, d);
      } catch (e) { box.innerHTML = empty(e.message); }
    }

    const addBtn = (a, l = 'افزودن') => admin || (visitor && a.v) || (self && a.self) ? `<button class="btn sm" data-a="${a.k || a}" type="button">+ ${l}</button>` : '';
    const bar = (t, btn) => `<div class="pv-bar"><h2>${t}</h2><div>${btn || ''}</div></div>`;
    const docLink = id => id ? `<a href="${E(o.fileUrl(id))}" target="_blank" rel="noopener">مشاهده‌ی فایل</a>` : '';
    const mgmt = (sec2, id, extra = '') => admin || (visitor && ['responsibilities', 'courses', 'books'].includes(sec2)) ? `<div class="ac"><button class="btn alt sm" data-a="edit" data-s="${sec2}" data-id="${id}" type="button">ویرایش</button>${extra}<button class="btn alt sm dng" data-a="del" data-s="${sec2}" data-id="${id}" type="button">حذف</button></div>` : '';

    const R = {
      personal(d) {
        const filled = d.fields.filter(f => f.value !== null), miss = d.fields.length - filled.length - (admin ? 0 : 0);
        return bar('اطلاعات شخصی', (self || admin) ? `<button class="btn sm" data-a="editp" type="button">ویرایش</button>` : '') +
          (filled.length ? `<div class="pv-grid">${filled.map(f => `<div class="pv-field"><small><span>${E(f.label)}</span><span class="pv-lv ${f.level}">${E(f.level_label)}${f.masked ? ' · Mask' : ''}</span></small><div>${f.key === 'address' || f.key === 'internal_note' ? E(f.value) : `<bdi>${E(f.value)}</bdi>`}</div></div>`).join('')}</div>` : empty('هنوز اطلاعاتی ثبت نشده است'))
          + (d.bio ? `<h3 class="pv-sub">درباره من</h3><p style="white-space:pre-wrap">${E(d.bio)}</p>` : '')
          + (miss > 0 && (self || admin) ? `<p class="pv-warn">${miss.toLocaleString('fa-IR')} مورد از اطلاعات هنوز تکمیل نشده است.</p>` : '')
          + (editor ? '<p class="pv-warn">اطلاعات حساس برای نقش ویرایشگر پنهان یا Mask شده است.</p>' : '');
      },
      interests(d) {
        return bar('علایق', (self || admin) ? '<button class="btn sm" data-a="editi" type="button">ویرایش</button>' : '') + (d.interests ? `<p style="white-space:pre-wrap">${E(d.interests)}</p>` : empty('علایقی ثبت نشده است'));
      },
      education(d) {
        const cur = d.education.current, hist = d.education.history;
        const row = e => `<div class="pv-item"><div class="bd"><h3>${E(e.school || '—')}</h3><p>پایه: ${E(e.grade || '—')} · رشته: ${E(e.field_of_study || '—')}${e.academic_year ? ' · سال تحصیلی: ' + E(e.academic_year) : ''}</p>
          <p>${e.started_at ? 'از ' + E(pd(e.started_at)) : ''} ${e.ended_at ? 'تا ' + E(pd(e.ended_at)) : ''}</p>${e.note ? `<p>${E(e.note)}</p>` : ''}${e.is_current == 1 ? badge('active', 'فعلی') : badge('ended', 'سابقه')}
          ${admin ? `<div class="ac"><button class="btn alt sm" data-a="edit" data-s="education" data-id="${e.id}" type="button">ویرایش</button></div>` : ''}</div></div>`;
        return bar('اطلاعات تحصیلی', (self || admin) ? '<button class="btn sm" data-a="neweducation" type="button">ثبت اطلاعات تحصیلی جدید</button>' : '') +
          (cur ? `<div class="pv-list">${row(cur)}</div>` : empty('اطلاعات تحصیلی ثبت نشده است')) +
          (hist.filter(e => !cur || e.id != cur.id).length ? `<h3 class="pv-sub">سوابق تحصیلی</h3><div class="pv-list">${hist.filter(e => !cur || e.id != cur.id).map(row).join('')}</div>` : '') +
          (self || admin ? '<p class="pv-warn">با تغییر پایه یا رشته، اطلاعات جدید ثبت و اطلاعات قبلی در سوابق نگه‌داری می‌شود.</p>' : '');
      },
      camps(d) {
        return bar('سوابق اردوها و ثبت‌نام‌ها', self ? '<button class="btn alt sm" data-a="claim" type="button">افزودن ثبت‌نام با کد پیگیری</button>' : '') + (d.items.length ? `<div class="pv-list">${d.items.map(r => `<div class="pv-item click" data-a="reg" data-id="${r.id}" tabindex="0" role="button">
          <div class="cv" style="${r.camp.cover ? `background-image:url('${E(o.upl(r.camp.cover))}')` : ''}"></div><div class="bd"><h3>${E(r.camp.title)}</h3>
          <p>${r.camp.start ? E(pd(r.camp.start)) : ''}${r.camp.end ? ' تا ' + E(pd(r.camp.end)) : ''}${r.camp.location ? ' · ' + E(r.camp.location) : ''}</p>
          <p>ثبت‌نام: ${badge(r.status, r.status_label)} حضور: ${badge(r.attendance, r.attendance_label)}${r.payment ? ' پرداخت: ' + badge(r.payment.status, r.payment.label) : ''}</p>
          <p>کد ثبت‌نام: <bdi>${E(r.code)}</bdi> · تاریخ ثبت‌نام: ${E(pd(r.created_at))}</p><p><b>وضعیت نهایی: ${E(r.final_label)}</b></p></div></div>`).join('')}</div>` : empty('هنوز در اردویی ثبت‌نام نشده است'));
      },
      activities(d) {
        return bar('سوابق فعالیت در کانون', addBtn({ k: 'add', self: false })) + (d.items.length ? `<div class="pv-list">${d.items.map(a => `<div class="pv-item"><div class="bd"><h3>${E(a.title)}</h3>
          <p>${badge('', a.kind_label)} ${badge(a.status, a.status_label)} ${a.date ? E(pd(a.date)) : ''}</p>${a.description ? `<p>${E(a.description)}</p>` : ''}${docLink(a.doc_id)}${a.editable ? mgmt('activities', a.id) : ''}</div></div>`).join('')}</div>` : empty('فعالیتی ثبت نشده است'));
      },
      responsibilities(d) {
        const it = r => `<div class="pv-item"><div class="bd"><h3>${E(r.title)}</h3><p>${r.unit ? 'بخش/مجموعه: ' + E(r.unit) : ''}</p>
          <p>${badge(r.status, r.status_label)}</p>${r.description ? `<p>${E(r.description)}</p>` : ''}${mgmt('responsibilities', r.id,
          r.status === 'ended' ? '' : `<button class="btn alt sm" data-a="end" data-id="${r.id}" type="button">پایان مسئولیت</button><button class="btn alt sm" data-a="toggle" data-id="${r.id}" type="button">${r.status === 'active' ? 'غیرفعال' : 'فعال'}</button>`)}</div></div>`;
        const col = b => { const c = d.current.filter(r => r.body === b), h = d.history.filter(r => r.body === b);
          return `<div><h3>${E(d.meta.bodies[b])}</h3>${c.length ? `<h4 class="pv-sub">مسئولیت فعلی</h4><div class="pv-list">${c.map(it).join('')}</div>` : empty('مسئولیت فعلی ندارد')}${h.length ? `<h4 class="pv-sub">سوابق</h4><div class="pv-list">${h.map(it).join('')}</div>` : ''}</div>`; };
        const addR = admin || visitor ? '<button class="btn sm" data-a="add" data-body="kanoon" type="button">+ مسئولیت کانون</button> <button class="btn sm" data-a="add" data-body="heyat" type="button">+ مسئولیت هیئت</button>' : '';
        return bar('مسئولیت‌ها و سمت‌ها', addR) + `<div class="pv-cols">${col('kanoon')}${col('heyat')}</div>`;
      },
      courses(d) {
        return bar('دوره‌ها و آموزش‌ها', addBtn({ k: 'add', self: false, v: true })) + (d.items.length ? `<div class="pv-list">${d.items.map(c => `<div class="pv-item"><div class="bd"><h3>${E(c.title)}</h3>
          <p>${c.organizer ? 'برگزارکننده: ' + E(c.organizer) + ' · ' : ''}${c.course_date ? E(pd(c.course_date)) : ''}</p><p>${badge(c.status, c.status_label)} ${c.result ? 'نتیجه: ' + E(c.result) : ''}</p>
          ${c.doc_id ? '<p>گواهی: ' + docLink(c.doc_id) + '</p>' : ''}${mgmt('courses', c.id)}</div></div>`).join('')}</div>` : empty('دوره‌ای ثبت نشده است'));
      },
      books(d) {
        return bar('کتاب‌های خوانده‌شده', addBtn({ k: 'add', self: false, v: true })) + (d.items.length ? `<div class="pv-list">${d.items.map(b => `<div class="pv-item"><div class="bd"><h3>${E(b.title)}</h3>
          <p>${b.author ? 'نویسنده: ' + E(b.author) + ' · ' : ''}${b.read_date ? E(pd(b.read_date)) : ''}</p>${b.note ? `<p>${E(b.note)}</p>` : ''}${mgmt('books', b.id)}</div></div>`).join('')}</div>` : empty('کتابی ثبت نشده است'));
      },
      achievements(d) {
        return bar('دستاوردها و افتخارات', addBtn({ k: 'add', self: true }, self ? 'درخواست ثبت دستاورد' : 'افزودن')) + (d.items.length ? `<div class="pv-list">${d.items.map(a => `<div class="pv-item"><div class="bd"><h3>${E(a.title)}</h3>
          <p>${badge('', a.kind_label)} ${badge(a.status, a.status_label)} ${a.achieved_at ? E(pd(a.achieved_at)) : ''}</p>${a.description ? `<p>${E(a.description)}</p>` : ''}${docLink(a.doc_id)}
          ${admin ? mgmt('achievements', a.id, a.status === 'pending' ? `<button class="btn sm" data-a="approve" data-id="${a.id}" type="button">تأیید</button><button class="btn alt sm" data-a="reject" data-id="${a.id}" type="button">رد</button>` : '') : ''}
          ${self && a.submitted_by === 'member' && a.status !== 'approved' ? `<div class="ac"><button class="btn alt sm dng" data-a="selfdel" data-id="${a.id}" type="button">حذف درخواست</button></div>` : ''}</div></div>`).join('')}</div>` : empty('دستاوردی ثبت نشده است'));
      },
      documents(d) {
        return bar('مدارک و فایل‌های من', addBtn({ k: 'upload', self: true }, 'افزودن مدرک')) + (d.items.length ? `<div class="pv-list">${d.items.map(x => {
          const url = x.source === 'registration' ? x.url : o.fileUrl(x.id);
          return `<div class="pv-item"><div class="bd"><h3>${E(x.title)}</h3><p>${badge('', x.category_label)} <span class="pv-lv ${x.visibility === 'staff' ? 'staff' : x.visibility === 'admin' ? 'admin' : 'private'}">${E(x.visibility_label)}</span> ${x.size ? E(fsize(x.size)) : ''} ${E(pd(x.created_at))}</p>
          <div class="ac"><a class="btn alt sm" href="${E(url)}" target="_blank" rel="noopener">مشاهده</a>${x.removable ? `<button class="btn alt sm dng" data-a="deldoc" data-id="${x.id}" type="button">حذف</button>` : ''}</div></div></div>`; }).join('')}</div>` : empty('مدرکی ثبت نشده است'))
          + '<p class="pv-warn">فایل‌های خصوصی فقط با کنترل دسترسی در دسترس‌اند و آدرس عمومی ندارند.</p>';
      },
      images(d) {
        return bar('تصاویر من') + (d.items.length ? `<div class="pv-imgs">${d.items.map(x => `<a class="pv-img" href="${E(x.url || o.fileUrl(x.id))}" target="_blank" rel="noopener"><img loading="lazy" alt="${E(x.title)}" src="${E(x.url || o.fileUrl(x.id))}">
          <div>${E(x.title)}<br><span class="pv-lv ${x.level}">${E(x.level_label)}</span></div></a>`).join('')}</div>` : empty('تصویری وجود ندارد'));
      },
      notifications(d) {
        return bar('اعلان‌ها', self && d.unread ? '<button class="btn sm" data-a="readall" type="button">علامت‌گذاری همه به‌عنوان خوانده‌شده</button>' : '') +
          (d.items.length ? `<div class="pv-list">${d.items.map(n => `<div class="pv-item" style="${n.is_read ? 'opacity:.75' : 'border-color:var(--plum)'}"><div class="bd"><h3>${E(n.title)}</h3>${n.body ? `<p>${E(n.body)}</p>` : ''}
          <p>${badge('', n.type_label)} ${n.is_read ? badge('', 'خوانده‌شده') : badge('pending', 'خوانده‌نشده')} ${E(pdt(n.created_at))}</p>
          ${self && !n.is_read ? `<div class="ac"><button class="btn alt sm" data-a="read" data-id="${n.id}" type="button">خوانده شد</button></div>` : ''}</div></div>`).join('')}</div>` : empty('اعلانی وجود ندارد'));
      },
      timeline(d) {
        return bar('Timeline فعالیت') + (d.items.length ? `<ul class="pv-tl">${d.items.map(t => `<li><time>${E(pd(t.occurred_at))}</time>${E(t.title)}</li>`).join('')}</ul>` : empty('رویدادی ثبت نشده است'));
      },
      history(d) {
        return bar('سوابق فعالیت حساب') + (d.items.length ? `<div class="pv-list">${d.items.map(h => `<div class="pv-item"><div class="bd"><h3>${E(h.label)}${h.detail ? ' — ' + E(h.detail) : ''}</h3><p>${E(pdt(h.created_at))}${h.by ? ' · توسط ' + E(h.by) : ''}${h.ip ? ' · <bdi>' + E(h.ip) + '</bdi>' : ''}</p></div></div>`).join('')}</div>` : empty('سابقه‌ای ثبت نشده است'));
      },
      security(d) {
        return bar('امنیت حساب', self ? '<button class="btn sm" data-a="chpass" type="button">تغییر گذرواژه</button>' : '<button class="btn sm" data-a="resetpass" type="button">تعیین گذرواژه‌ی جدید</button>') +
          `<dl class="pv-dl"><dt>آخرین ورود</dt><dd>${E(pdt(d.last_login_at)) || '—'}</dd><dt>آخرین فعالیت</dt><dd>${E(pdt(d.last_activity_at)) || '—'}</dd><dt>آخرین تغییر گذرواژه</dt><dd>${E(pdt(d.password_changed_at)) || '—'}</dd></dl>
          <h3 class="pv-sub">نشست‌های فعال</h3>${d.sessions.length ? `<div class="pv-list">${d.sessions.map(s => `<div class="pv-item"><div class="bd"><h3>${E(s.device)} ${s.current ? badge('active', 'این دستگاه') : ''}</h3>
          <p><bdi>${E(s.ip)}</bdi> · شروع: ${E(pdt(s.created_at))} · آخرین فعالیت: ${E(pdt(s.last_seen_at))}</p>${self && !s.current ? `<div class="ac"><button class="btn alt sm" data-a="revoke" data-id="${s.id}" type="button">خروج این نشست</button></div>` : ''}</div></div>`).join('')}</div>` : empty('نشست فعالی نیست')}
          <div style="margin-top:12px">${self ? '<button class="btn alt sm" data-a="others" type="button">خروج از همه‌ی نشست‌های دیگر</button>' : (visitor ? '' : '<button class="btn alt sm dng" data-a="revokeall" type="button">بستن همه‌ی نشست‌ها</button>')}</div>
          <p class="pv-warn">گذرواژه هیچ‌گاه نمایش داده نمی‌شود.</p>`;
      },
    };

    /* ── فرم بخش‌ها ── */
    function sectionFields(s, d) {
      const m = d.meta || {};
      if (s === 'activities') return [{ n: 'title', l: 'عنوان', req: 1 }, { n: 'kind', l: 'نوع', t: 'sel', o: m.kinds }, { n: 'activity_date', l: 'تاریخ', t: 'date' }, { n: 'status', l: 'وضعیت', t: 'sel', o: m.statuses }, { n: 'description', l: 'توضیح', t: 'area' }, { n: 'file', l: 'فایل/مدرک (اختیاری)', t: 'file' }];
      if (s === 'courses') return [{ n: 'title', l: 'نام دوره', req: 1 }, { n: 'organizer', l: 'برگزارکننده' }, { n: 'course_date', l: 'تاریخ', t: 'date' }, { n: 'status', l: 'وضعیت', t: 'sel', o: m.statuses }, { n: 'result', l: 'نتیجه' }, { n: 'file', l: 'گواهی (اختیاری)', t: 'file' }];
      if (s === 'books') return [{ n: 'title', l: 'نام کتاب', req: 1 }, { n: 'author', l: 'نویسنده' }, { n: 'read_date', l: 'تاریخ مطالعه', t: 'date' }, { n: 'note', l: 'یادداشت', t: 'area', max: 500 }];
      if (s === 'achievements') return [{ n: 'title', l: 'عنوان', req: 1 }, { n: 'kind', l: 'نوع', t: 'sel', o: m.kinds }, { n: 'achieved_at', l: 'تاریخ', t: 'date' }, { n: 'description', l: 'توضیح', t: 'area' }, { n: 'file', l: 'تصویر/مدرک (اختیاری)', t: 'file' }];
      if (s === 'education') return [{ n: 'school', l: 'مدرسه' }, { n: 'grade', l: 'پایه' }, { n: 'field_of_study', l: 'رشته' }, { n: 'academic_year', l: 'سال تحصیلی' }, { n: 'started_at', l: 'تاریخ شروع', t: 'date' }, { n: 'ended_at', l: 'تاریخ پایان', t: 'date' }, { n: 'note', l: 'یادداشت' }, { n: 'is_current', l: 'اطلاعات فعلی است', t: 'check' }];
      if (s === 'responsibilities') {
        const om = {}; Object.entries(m.types || {}).forEach(([b, l]) => { om[b] = {}; l.forEach(t => om[b][t.id] = t.title); });
        return [{ n: 'body', l: 'مربوط به', t: 'sel', o: m.bodies, req: 1 }, { n: 'type_id', l: 'نوع مسئولیت', t: 'sel', dep: 'body', om, blank: 1 }, { n: 'title', l: 'عنوان (در صورت انتخاب نوع، خودکار پر می‌شود)' },
          { n: 'unit', l: 'بخش/مجموعه' }, { n: 'status', l: 'وضعیت', t: 'sel', o: m.statuses }, { n: 'description', l: 'توضیحات', t: 'area' }];
      }
      return [];
    }
    const findRow = (s, d, id) => (s === 'responsibilities' ? [...d.current, ...d.history] : s === 'education' ? d.education.history : d.items).find(x => x.id == id) || {};

    async function openForm(s, id, preset) {
      const d = s === 'education' ? S.main : await load(s);
      const row = id ? findRow(s, d, id) : { status: s === 'responsibilities' ? 'active' : undefined, is_current: 1, body: (preset && preset.body) || 'kanoon' };
      const fields = sectionFields(s, s === 'education' ? { meta: {} } : d);
      const T = { activities: 'فعالیت', courses: 'دوره', books: 'کتاب', achievements: 'دستاورد', education: 'سابقه‌ی تحصیلی', responsibilities: 'مسئولیت' };
      formModal((id ? 'ویرایش ' : 'افزودن ') + T[s], fields, row, async ({ data, file }, close, err) => {
        if (s === 'responsibilities' && data.type_id === '') data.type_id = null;
        const r = await o.act(s, 'save', { id, data, file });
        if (!r.success) return err(r.message);
        toast(r.message); close(); S.cache = {}; reload();
      });
    }

    function bindPanel(box, d) {
      box.onclick = async e => {
        const b = e.target.closest('[data-a]'); if (!b) return;
        const a = b.dataset.a, id = +b.dataset.id || 0, s = b.dataset.s;
        const done = async r => { if (r && r.success) { S.cache = {}; reload(); } };
        if (a === 'claim') { // اتصال ثبت‌نام مهمان به این حساب با کد پیگیری
          return formModal('افزودن ثبت‌نام با کد پیگیری', [{ n: 'code', l: 'کد پیگیری (۱۲ نویسه‌ی انگلیسی/عدد که پس از ثبت‌نام نمایش داده شد)', req: true, ltr: true, max: 20, ph: 'A1B2C3D4E5F6' }], {},
            async ({ data }, close, err) => { const r = await o.act('registrations', 'claim', { data }); if (!r.success) return err(r.message); toast(r.message); close(); reload(); });
        }
        if (a === 'editp') return editPersonal();
        if (a === 'editi') return editPersonal(['interests']);
        if (a === 'neweducation') return editPersonal(['school', 'grade', 'field_of_study']);
        if (a === 'add') return openForm(S.tab === 'camps' ? 'camps' : S.tab, 0, { body: b.dataset.body });
        if (a === 'edit') return openForm(s, id);
        if (a === 'del') { if (confirm('این مورد حذف شود؟ (سابقه بایگانی می‌شود)')) done(await act(s, 'delete', { id })); return; }
        if (a === 'end') { if (confirm('این مسئولیت پایان یابد؟')) done(await act('responsibilities', 'end', { id })); return; }
        if (a === 'toggle') return done(await act('responsibilities', 'toggle', { id }));
        if (a === 'approve' || a === 'reject') return done(await act('achievements', 'review', { id, data: { status: a === 'approve' ? 'approved' : 'rejected' } }));
        if (a === 'selfdel') { if (confirm('درخواست حذف شود؟')) done(await act('achievements', 'delete', { id })); return; }
        if (a === 'reg') return regDetail(id);
        if (a === 'read') { await o.act('notifications', 'read', { id }); S.unread = Math.max(0, (S.unread || 1) - 1); S.cache = {}; return panel(); }
        if (a === 'readall') { await o.act('notifications', 'read_all', {}); S.unread = 0; S.cache = {}; return reload(); }
        if (a === 'deldoc') { if (confirm('این مدرک حذف شود؟')) done(await act('documents', 'delete', { id })); return; }
        if (a === 'upload') {
          const cats = admin ? { ...d.meta.categories } : d.meta.categories;
          const f = [{ n: 'title', l: 'عنوان' }, { n: 'category', l: 'دسته', t: 'sel', o: cats }, ...(admin ? [{ n: 'visibility', l: 'سطح دسترسی', t: 'sel', o: d.meta.visibility }] : []), { n: 'file', l: 'فایل (تصویر یا PDF)', t: 'file', req: 1 }];
          return formModal('افزودن مدرک', f, {}, async ({ data, file }, close, err) => {
            if (!file) return err('فایل را انتخاب کنید');
            const r = await o.act('documents', 'upload', { data, file }); if (!r.success) return err(r.message); toast(r.message); close(); done(r);
          });
        }
        if (a === 'chpass') return formModal('تغییر گذرواژه', [{ n: 'current', l: 'گذرواژه‌ی فعلی', t: 'password' }, { n: 'new', l: 'گذرواژه‌ی جدید (حداقل ۸ کاراکتر)', t: 'password' }, { n: 'confirm', l: 'تکرار گذرواژه‌ی جدید', t: 'password' }], {},
          async ({ data }, close, err) => { const r = await o.act('security', 'change_password', { data }); if (!r.success) return err(r.message); toast(r.message); close(); S.cache = {}; panel(); });
        if (a === 'resetpass') return formModal('تعیین گذرواژه‌ی جدید برای کاربر', [{ n: 'password', l: 'گذرواژه‌ی جدید (حداقل ۸ کاراکتر)', t: 'password' }], {},
          async ({ data }, close, err) => { const r = await o.act('member', 'reset_password', { data }); if (!r.success) return err(r.message); toast(r.message); close(); S.cache = {}; panel(); });
        if (a === 'others') return done(await act('security', 'logout_others', {}));
        if (a === 'revoke') return done(await act('security', 'revoke', { id }));
        if (a === 'revokeall') { if (confirm('همه‌ی نشست‌های کاربر بسته شود؟')) done(await act('member', 'revoke_sessions', {})); }
      };
      box.onkeydown = e => { if ((e.key === 'Enter' || e.key === ' ') && e.target.dataset?.a === 'reg') { e.preventDefault(); regDetail(+e.target.dataset.id); } };
    }

    /* ── جزئیات ثبت‌نام ── */
    async function regDetail(id) {
      let r;
      try { r = await load('registrations', { reg: id }); } catch (e) { return toast(e.message, true); }
      const c = r.camp, dl = (rows) => `<dl class="pv-dl">${rows.filter(x => x[1] !== '' && x[1] != null).map(([k, v]) => `<dt>${E(k)}</dt><dd>${v}</dd>`).join('')}</dl>`;
      const html = `<h3 class="pv-sub">اطلاعات اردو</h3>${dl([['نام اردو', c.slug ? `<a href="${E(o.campUrl(c.slug))}" target="_blank" rel="noopener">${E(c.title)}</a>` : E(c.title)], ['تاریخ', E(pd(c.start)) + (c.end ? ' تا ' + E(pd(c.end)) : '')], ['محل', E(c.location || '')]])}
        <h3 class="pv-sub">اطلاعات ثبت‌نام</h3>${dl([['شناسه‌ی ثبت‌نام', `<bdi>${E(r.code)}</bdi>`], ['تاریخ ثبت', E(pdt(r.created_at))], ['وضعیت ثبت‌نام', badge(r.status, r.status_label)], ['وضعیت بررسی', E(r.review_label) + (r.reviewed_at ? ' (' + E(pd(r.reviewed_at)) + ')' : '')],
          ['وضعیت حضور', badge(r.attendance, r.attendance_label)], ['وضعیت نهایی', `<b>${E(r.final_label)}</b>`]])}
        ${r.photo ? `<h3 class="pv-sub">عکس ثبت‌نامی</h3><a href="${E(r.photo)}" target="_blank" rel="noopener"><img alt="عکس ثبت‌نام" style="max-width:160px;border-radius:10px" src="${E(r.photo)}"></a>` : ''}
        <h3 class="pv-sub">پاسخ‌های فرم</h3>${r.answers.length ? dl(r.answers.map(a => [a.label, a.is_file ? `<a href="${E(a.url)}" target="_blank" rel="noopener">مشاهده‌ی فایل</a>` : E(a.value || '—')])) : '<p class="pv-warn">پاسخی ثبت نشده است.</p>'}
        ${r.note ? `<div class="pv-note"><b>توضیحات مدیر${r.note_visible === 0 ? ' (برای کاربر نمایش داده نمی‌شود)' : ''}:</b><br>${E(r.note)}</div>` : ''}`;
      const m = modal('جزئیات ثبت‌نام', html + (admin ? `<h3 class="pv-sub">مدیریت</h3><div class="pv-ac-sel"><label for="pv-att">حضور:</label><select id="pv-att" style="width:auto">${Object.entries({ unknown: 'نامشخص', attended: 'شرکت کرده', absent: 'غایب' }).map(([k, l]) => `<option value="${k}"${k === r.attendance ? ' selected' : ''}>${l}</option>`).join('')}</select></div>
        <label for="pv-note">توضیحات مدیر</label><textarea id="pv-note" rows="3" maxlength="2000">${E(r.note || '')}</textarea><label><input type="checkbox" id="pv-nv"${r.note_visible ? ' checked' : ''}> نمایش توضیحات به کاربر</label>` : ''),
        admin ? async (_v, close, err) => {
          const x = await o.act('camps', 'attendance', { id, data: { attendance: m.f.querySelector('#pv-att').value } }); if (!x.success) return err(x.message);
          const y = await o.act('camps', 'note', { id, data: { note: m.f.querySelector('#pv-note').value, visible: m.f.querySelector('#pv-nv').checked ? 1 : 0 } }); if (!y.success) return err(y.message);
          toast('ذخیره شد'); close(); S.cache = {}; reload();
        } : null, { noSubmit: !admin });
    }

    init();
  }

  window.PV = { mount, modal, formModal, E, pd, pdt, toast, badge };
})();
