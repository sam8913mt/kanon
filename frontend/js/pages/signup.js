/** signup.js — ساخت پروفایل و ورود: signup.html → POST /api/member.php */
document.addEventListener('DOMContentLoaded', () => {
  let mode = 'up', otp = false, otpMode = false, step = 1, cd = 0;
  byId('sb').insertAdjacentHTML('afterend', `<button type="button" class="btn alt block" id="otpT" style="margin-top:10px;display:none">ورود با کد یکبار مصرف</button>`);
  byId('p').insertAdjacentHTML('afterend', `<div id="oc" hidden><label for="oc_in">کد ارسال‌شده به موبایل</label><input id="oc_in" dir="ltr" inputmode="numeric" maxlength="5" autocomplete="one-time-code"><button type="button" class="btn alt sm" id="oc_re" disabled></button></div>`);
  const showT = v => { byId('otpT').style.display = v ? '' : 'none'; };
  const pLab = document.querySelector('label[for="p"]');
  const setOtp = on => {
    otpMode = on; step = 1;
    byId('p').style.display = pLab.style.display = on ? 'none' : ''; byId('oc').hidden = true; byId('m').readOnly = false;
    byId('sb').textContent = on ? 'ارسال کد' : (mode === 'in' ? 'ورود' : 'ساخت پروفایل');
    byId('otpT').textContent = on ? 'ورود با گذرواژه' : 'ورود با کد یکبار مصرف';
    byId('e').textContent = '';
  };
  const tickRe = () => { const b = byId('oc_re'); b.disabled = cd > 0; b.textContent = cd > 0 ? 'ارسال مجدد تا ' + cd + ' ثانیه' : 'ارسال مجدد کد'; if (cd > 0) { cd--; setTimeout(tickRe, 1000); } };
  byId('otpT').onclick = () => setOtp(!otpMode);
  byId('oc_re').onclick = () => { step = 1; byId('f').requestSubmit(); };
  if (!CFG.DEMO_MODE) api('/member.php').then(r => { otp = !!(r.data && r.data.otp); showT(otp && mode === 'in'); });
  const set = m => {
    mode = m;
    byId('nm').hidden = m === 'in'; byId('fg').hidden = m !== 'in';
    byId('ttl').textContent = m === 'up' ? 'ساخت پروفایل' : 'ورود به پروفایل';
    byId('sb').textContent = m === 'up' ? 'ساخت پروفایل' : 'ورود';
    byId('p').autocomplete = m === 'up' ? 'new-password' : 'current-password';
    byId('t-up').className = 'btn' + (m === 'up' ? '' : ' alt'); byId('t-in').className = 'btn' + (m === 'in' ? '' : ' alt');
    byId('t-up').setAttribute('aria-selected', m === 'up'); byId('t-in').setAttribute('aria-selected', m === 'in');
    byId('e').textContent = '';
    showT(otp && m === 'in'); if (m !== 'in') setOtp(false);
  };
  byId('t-up').onclick = () => set('up'); byId('t-in').onclick = () => set('in');
  if (qs('mode') === 'login') set('in');

  byId('f').onsubmit = async ev => {
    ev.preventDefault();
    const e = byId('e'); e.textContent = '';
    if (CFG.DEMO_MODE) return e.textContent = 'در حالت Demo پروفایل غیرفعال است؛ سایت را روی هاست اجرا کنید.';
    const t = (await api('/member.php')).data;
    if (!t) return e.textContent = 'ارتباط با سرور برقرار نشد';
    byId('sb').disabled = true;
    const r = await api('/member.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': t.csrf },
      body: JSON.stringify(otpMode && mode === 'in' ? (step === 1 ? { op: 'otp_send', mobile: faToEn(byId('m').value) } : { op: 'otp_verify', mobile: faToEn(byId('m').value), code: faToEn(byId('oc_in').value) }) : { op: mode === 'up' ? 'signup' : 'login', first_name: byId('fn').value, last_name: byId('ln').value, mobile: faToEn(byId('m').value), password: byId('p').value }),
    });
    byId('sb').disabled = false;
    if (!r.success) return e.textContent = r.message;
    if (otpMode && mode === 'in' && step === 1) { step = 2; byId('oc').hidden = false; byId('sb').textContent = 'ورود با کد'; cd = (r.data && r.data.cooldown) || 60; tickRe(); byId('oc_in').focus(); return; }
    const next = qs('next');
    const safeNext = next && /^\/(?!\/)/.test(next) ? next : '';
    // بعد از ساخت پروفایل: صفحه‌ی تکمیل پروفایل؛ بعد از ورود عادی: مقصد قبلی یا نمای کامل پروفایل
    if (mode === 'up') location.href = U.completeProfile() + (safeNext ? '?next=' + encodeURIComponent(safeNext) : '');
    else location.href = safeNext || (r.data && r.data.panel ? '/admin/' : U.profile());
  };
});
