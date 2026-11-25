/**
 * complete-profile.js — /complete-profile: صفحه‌ی تکمیل پروفایل بعد از ساخت حساب
 * GET /api/member.php (ورود؟) → GET /api/profile/me.php (مقادیر فعلی) → POST /api/profile/update.php (+ /api/profile/avatar.php برای عکس)
 */
document.addEventListener('DOMContentLoaded', async () => {
  const root = byId('cp-root');
  const next = (() => { const n = qs('next'); return n && /^\/(?!\/)/.test(n) ? n : U.profile(); })();
  const me = (await api('/member.php')).data;
  if (!me || !me.member) { location.href = U.signup() + '?mode=login&next=' + encodeURIComponent(location.pathname); return; }
  const r = await api('/profile/me.php');
  if (!r.success) { root.innerHTML = `<p class="err">${esc(r.message || 'خطا در دریافت اطلاعات')}</p>`; return; }
  let csrf = r.data.csrf;
  const cur = {};
  r.data.fields.forEach(f => { cur[f.key] = f.raw == null ? '' : String(f.raw); });
  cur.bio = r.data.bio || '';
  const hasNid = !!cur.national_id;

  const GRADES = ['هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم', 'سایر'];
  // [کلید، عنوان، گزینه‌ها]
  const F = [
    ['father_name', 'نام پدر', {}],
    ['national_id', 'کد ملی', { req: 1, ltr: 1, max: 10, ph: '0012345678', ro: hasNid }],
    ['birth_date', 'تاریخ تولد (شمسی)', { req: 1, ltr: 1, max: 10, ph: '1390/05/20', jd: 1 }],
    ['gender', 'جنسیت', { req: 1, sel: { male: 'آقا', female: 'خانم' } }],
    ['school', 'نام مدرسه', { req: 1, max: 150 }],
    ['grade', 'پایه‌ی تحصیلی', { req: 1, sel: Object.fromEntries(GRADES.map(g => [g, g])) }],
    ['field_of_study', 'رشته (اختیاری)', { max: 100 }],
    ['city', 'شهر', { max: 80 }],
    ['neighborhood', 'محله', { req: 1, max: 80 }],
    ['address', 'نشانی (اختیاری)', { area: 1, max: 300 }],
    ['emergency_phone', 'شماره‌ی تماس اضطراری (والدین)', { ltr: 1, max: 20, ph: '09123456789' }],
    ['email', 'ایمیل (اختیاری)', { ltr: 1, max: 150 }],
    ['bio', 'درباره‌ی من (اختیاری)', { area: 1, max: 1000 }],
  ];

  const input = ([k, l, o]) => {
    let v = cur[k] || '';
    let ctl;
    if (o.sel) {
      const opts = { ...o.sel };
      if (v && !(v in opts)) opts[v] = v;
      ctl = `<select id="cp-${k}"><option value="">انتخاب کنید</option>${Object.entries(opts).map(([a, b]) => `<option value="${esc(a)}"${a === v ? ' selected' : ''}>${esc(b)}</option>`).join('')}</select>`;
    } else if (o.area) {
      ctl = `<textarea id="cp-${k}" rows="3" maxlength="${o.max || 500}">${esc(v)}</textarea>`;
    } else {
      ctl = `<input id="cp-${k}" value="${esc(v)}"${o.ltr ? ' dir="ltr"' : ''}${o.jd ? ' data-jdate="text"' : ''}${o.ph ? ` placeholder="${esc(o.ph)}"` : ''}${o.max ? ` maxlength="${o.max}"` : ''}${o.ro ? ' readonly' : ''}>`;
    }
    return `<div><label for="cp-${k}">${esc(l)}${o.req ? ' <span class="req" aria-hidden="true">*</span>' : ''}</label>${ctl}</div>`;
  };

  const name = esc((r.data.header && r.data.header.first_name) || '');
  root.innerHTML = `<form class="f cp" id="cp-f" novalidate>
    <h1 style="text-align:center;font-size:22px">تکمیل پروفایل</h1>
    <p class="hint" style="text-align:center">${name ? name + ' عزیز، ' : ''}حساب شما ساخته شد. برای ثبت‌نام در اردوها لطفاً اطلاعات زیر را کامل کنید. فیلدهای ستاره‌دار الزامی‌اند.</p>
    <div class="cp-grid">${F.map(input).join('')}</div>
    <label for="cp-avatar">عکس پروفایل (اختیاری؛ JPG/PNG/WEBP)</label><input id="cp-avatar" type="file" accept="image/jpeg,image/png,image/webp">
    <p class="err" id="cp-e" role="alert"></p>
    <button class="btn block" id="cp-sb">ذخیره و ادامه</button>
    <p style="text-align:center;margin-top:10px"><a href="${esc(next)}" id="cp-skip">بعداً تکمیل می‌کنم</a></p>
  </form>`;

  let avFile = null; // عکس برش‌خورده‌ی انتخاب‌شده
  byId('cp-avatar').addEventListener('change', async ev => {
    const f0 = ev.target.files[0]; avFile = null;
    if (!f0 || !window.pickAvatar) return;
    const c = await pickAvatar(f0);
    if (c) avFile = c; else ev.target.value = '';
  });
  const val = k => faToEn((byId('cp-' + k).value || '').trim());
  byId('cp-f').onsubmit = async ev => {
    ev.preventDefault();
    const e = byId('cp-e'); e.textContent = '';
    const data = {};
    for (const [k, l, o] of F) {
      if (o.ro) continue;
      const v = ['bio', 'address', 'father_name', 'school', 'neighborhood', 'city', 'field_of_study'].includes(k) ? (byId('cp-' + k).value || '').trim() : val(k);
      if (o.req && !v) { e.textContent = `«${l}» را وارد کنید`; byId('cp-' + k).focus(); return; }
      data[k] = v;
    }
    if (!hasNid && !validNationalId(data.national_id)) { e.textContent = 'کد ملی معتبر نیست'; byId('cp-national_id').focus(); return; }
    if (!validDate(data.birth_date)) { e.textContent = 'تاریخ تولد را مثل 1390/05/20 وارد کنید'; byId('cp-birth_date').focus(); return; }
    if (data.emergency_phone && !/^[0-9+\- ]{7,20}$/.test(data.emergency_phone)) { e.textContent = 'شماره‌ی تماس اضطراری معتبر نیست'; return; }
    byId('cp-sb').disabled = true;
    const res = await api('/profile/update.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(data) });
    if (!res.success) { byId('cp-sb').disabled = false; e.textContent = res.message || 'خطا در ذخیره'; return; }
    const f = avFile || byId('cp-avatar').files[0];
    if (f) {
      const fd = new FormData(); fd.append('file', f);
      const av = await api('/profile/avatar.php', { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd });
      if (!av.success) { byId('cp-sb').disabled = false; e.textContent = 'اطلاعات ذخیره شد، اما عکس بارگذاری نشد: ' + (av.message || ''); byId('cp-skip').textContent = 'ادامه بدون عکس'; return; }
    }
    location.href = next;
  };
});
