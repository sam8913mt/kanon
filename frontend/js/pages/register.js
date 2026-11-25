/** register.js — صفحه‌ی مستقل ثبت‌نام اردو: فرم پویا، اعتبارسنجی اولیه، ارسال AJAX به PHP */
document.addEventListener('DOMContentLoaded', async () => {
  const slug = qs('camp'), F = byId('f');
  let guest = false;
  if (!CFG.DEMO_MODE) { // ثبت‌نام مهمان مجاز است مگر اینکه Admin «الزام حساب» را فعال کرده باشد
    const info = (await api('/register.php')).data || {};
    if (info.requires_login && !info.member) { location.href = U.signup() + '?next=' + encodeURIComponent(location.pathname); return; }
    guest = !info.member;
  }
  const r = await api('/camp.php?slug=' + encodeURIComponent(slug || ''));
  const camp = r.data;
  if (!r.success || !camp || camp.status !== 'open') {
    F.innerHTML = '<p class="err">ثبت‌نام این اردو فعال نیست. از صفحه‌ی <a href="camps.html">اردوها</a> انتخاب کنید.</p>';
    cleanStaticLinks();
    return;
  }
  byId('ttl').textContent = camp.form_title || 'ثبت‌نام در ' + camp.title;
  document.title = 'ثبت‌نام ' + camp.title + ' | ' + (document.title.split('|')[1] || '').trim();
  byId('intro').textContent = camp.form_intro || '';
  const back = byId('back'); back.href = U.camp(slug); back.textContent = '← ' + camp.title;

  /** رندر یک سؤال بر اساس نوع آن (همه‌ی نوع‌های فرم‌ساز) */
  const field = x => {
    const req = x.required ? 'required aria-required="true"' : '', id = esc(x.name), L = esc(x.label);
    const lab = `<label for="${id}">${L}${x.required ? ' <span aria-hidden="true">*</span>' : ''}</label>`;
    const grp = inner => `<fieldset class="fs"><legend>${L}${x.required ? ' *' : ''}</legend>${inner}</fieldset>`;
    switch (x.type) {
      case 'select': return lab + `<select id="${id}" name="${id}" ${req}><option value="">انتخاب کنید</option>${x.options.map(o => `<option>${esc(o)}</option>`).join('')}</select>`;
      case 'radio': return grp(x.options.map((o, i) => `<label class="op"><input type="radio" name="${id}" value="${esc(o)}" ${i === 0 && x.required ? 'required' : ''}> ${esc(o)}</label>`).join(''));
      case 'yesno': return grp(`<label class="op"><input type="radio" name="${id}" value="yes" ${x.required ? 'required' : ''}> بله</label><label class="op"><input type="radio" name="${id}" value="no"> خیر</label>`);
      case 'checkbox': return `<label class="op"><input type="checkbox" id="${id}" name="${id}" value="1" ${req}> ${L}</label>`;
      case 'textarea': return lab + `<textarea id="${id}" name="${id}" rows="4" maxlength="2000" ${req}></textarea>`;
      case 'image': case 'file': return lab + `<input id="${id}" name="${id}" type="file" accept="${x.type === 'file' ? 'application/pdf,' : ''}image/jpeg,image/png,image/webp" ${req}>`;
      case 'number': return lab + `<input id="${id}" name="${id}" type="text" inputmode="decimal" dir="ltr" ${req}>`;
      case 'phone': return lab + `<input id="${id}" name="${id}" type="tel" inputmode="tel" dir="ltr" placeholder="09123456789" ${req}>`;
      case 'date': return lab + `<input id="${id}" name="${id}" type="text" dir="ltr" data-jdate="text" placeholder="1403/05/20" ${req}>`;
      case 'national_id': return lab + `<input id="${id}" name="${id}" type="text" inputmode="numeric" maxlength="10" dir="ltr" autocomplete="off" ${req}>`;
      default: return lab + `<input id="${id}" name="${id}" type="text" maxlength="300" ${req}>`;
    }
  };
  const value = x => {
    const d = new FormData(F).get(x.name);
    if (x.type === 'image' || x.type === 'file') return d && d.name ? 'file' : '';
    const v = String(d ?? '').trim();
    return ['national_id', 'number', 'phone', 'date'].includes(x.type) ? faToEn(v) : v;
  };

  F.innerHTML = camp.fields.map(field).join('')
    + `<label for="photo">عکس دانش‌آموز${camp.photo_required ? ' <span aria-hidden="true">*</span>' : ''}</label>
       <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" ${camp.photo_required ? 'required' : ''}>
       <p class="hint">فرمت JPG، PNG یا WEBP — حداکثر ۳ مگابایت</p>
       ${guest ? `<p class="hint">بدون حساب هم می‌توانید ثبت‌نام کنید. پس از ثبت‌نام یک «کد پیگیری» دریافت می‌کنید؛ با آن می‌توانید بعداً ثبت‌نام را به حساب خود وصل کنید. <a href="${U.signup()}?mode=login&next=${encodeURIComponent(location.pathname + location.search)}">ورود / ساخت حساب</a></p>` : ''}
       <p class="err" id="e" role="alert"></p><button class="btn" id="sb">ثبت‌نام</button>`;

  F.onsubmit = async ev => {
    ev.preventDefault();
    const e = byId('e'); e.textContent = '';
    for (const x of camp.fields) { const m = fieldError(x, value(x)); if (m) return e.textContent = m; }
    const ph = F.photo.files[0];
    if (camp.photo_required && !ph) return e.textContent = 'عکس دانش‌آموز الزامی است';
    if (ph && ph.size > 3 * 1024 * 1024) return e.textContent = 'حجم عکس نباید بیشتر از ۳ مگابایت باشد';

    const fd = new FormData(F);
    if (ph && window.compressImage) { const c = await compressImage(ph, { maxDim: 1200, quality: 0.82 }); fd.set('photo', c, c.name); }
    camp.fields.filter(x => ['national_id', 'number', 'phone', 'date'].includes(x.type)).forEach(x => fd.set(x.name, value(x)));
    fd.append('camp', slug);
    if (!CFG.DEMO_MODE) fd.append('csrf', (await api('/register.php')).data.csrf);
    byId('sb').disabled = true;
    const res = await api('/register.php', { method: 'POST', body: fd });
    byId('sb').disabled = false;
    if (!res.success) return e.textContent = res.message;
    try { sessionStorage.setItem('reg_id', res.data.id); sessionStorage.setItem('reg_track', res.data.guest ? (res.data.tracking || '') : ''); sessionStorage.setItem('reg_camp', slug); } catch (x) {}
    location.href = CFG.DEMO_MODE ? U.regDone(slug) + '?id=' + encodeURIComponent(res.data.id) : U.pay(); // پس از ثبت مشخصات ← صفحه‌ی پرداخت
  };
});
