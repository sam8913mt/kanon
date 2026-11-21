/** signup.js — ساخت پروفایل و ورود: signup.html → POST /api/member.php */
document.addEventListener('DOMContentLoaded', () => {
  let mode = 'up';
  const set = m => {
    mode = m;
    byId('nm').hidden = m === 'in';
    byId('ttl').textContent = m === 'up' ? 'ساخت پروفایل' : 'ورود به پروفایل';
    byId('sb').textContent = m === 'up' ? 'ساخت پروفایل' : 'ورود';
    byId('p').autocomplete = m === 'up' ? 'new-password' : 'current-password';
    byId('t-up').className = 'btn' + (m === 'up' ? '' : ' alt'); byId('t-in').className = 'btn' + (m === 'in' ? '' : ' alt');
    byId('t-up').setAttribute('aria-selected', m === 'up'); byId('t-in').setAttribute('aria-selected', m === 'in');
    byId('e').textContent = '';
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
      body: JSON.stringify({ op: mode === 'up' ? 'signup' : 'login', first_name: byId('fn').value, last_name: byId('ln').value, mobile: faToEn(byId('m').value), password: byId('p').value }),
    });
    byId('sb').disabled = false;
    if (!r.success) return e.textContent = r.message;
    const next = qs('next');
    const safeNext = next && /^\/(?!\/)/.test(next) ? next : '';
    // بعد از ساخت پروفایل: صفحه‌ی تکمیل پروفایل؛ بعد از ورود عادی: مقصد قبلی یا داشبورد
    if (mode === 'up') location.href = U.completeProfile() + (safeNext ? '?next=' + encodeURIComponent(safeNext) : '');
    else location.href = safeNext || U.dashboard();
  };
});
