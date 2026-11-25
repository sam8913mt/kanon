/**
 * profile.js — صفحه‌ی /profile: آداپتور «خود کاربر» برای PV (profile-view.js)
 * profile.html → profile.js → PHP API (/api/profile/*) → MySQL → JSON → UI
 * همه‌ی دسترسی‌ها در PHP کنترل می‌شود؛ این فایل فقط درخواست می‌فرستد.
 */
document.addEventListener('DOMContentLoaded', async () => {
  const root = byId('pv-root');
  if (CFG.DEMO_MODE) { root.innerHTML = '<div class="pv-empty">در حالت Demo پروفایل غیرفعال است؛ سایت را روی هاست اجرا کنید.</div>'; return; }
  const me = (await api('/member.php')).data;
  if (!me || !me.member) { location.href = U.signup() + '?mode=login&next=' + encodeURIComponent(location.pathname); return; }
  let csrf = me.csrf;

  const post = (path, body, form) => api(path, { method: 'POST', headers: form ? { 'X-CSRF-Token': csrf } : { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: form ? body : JSON.stringify(body) });
  const fd = (obj, file) => { const f = new FormData(); Object.entries(obj || {}).forEach(([k, v]) => v != null && f.append(k, v)); if (file) f.append('file', file); return f; };

  const get = async (section, params) => {
    const q = params ? '?' + new URLSearchParams(params) : '';
    const r = await api(`/profile/${section === 'profile' ? 'me' : section}.php${q}`);
    if (r.success && r.data && r.data.csrf) csrf = r.data.csrf;
    return r;
  };

  const act = (section, op, p) => {
    const { id, data = {}, file } = p;
    switch (section) {
      case 'personal': return post('/profile/update.php', data);
      case 'avatar': return op === 'remove' ? post('/profile/avatar.php', { op: 'remove' }) : post('/profile/avatar.php', fd({}, file), true);
      case 'notifications': return post('/profile/notifications.php', { op, id });
      case 'documents': return op === 'delete' ? post('/profile/documents.php', { op: 'delete', id }) : post('/profile/documents.php', fd(data, file), true);
      case 'achievements': return op === 'delete' ? post('/profile/achievements.php', { op: 'delete', id }) : post('/profile/achievements.php', fd(data, file), true);
      case 'security': return post('/profile/security.php', { op, id, ...data });
      case 'registrations': return op === 'claim' ? post('/profile/claim.php', { code: data.code }) : Promise.resolve({ success: false, message: 'عملیات مجاز نیست' });
    }
    return Promise.resolve({ success: false, message: 'عملیات مجاز نیست' });
  };

  const tab = location.hash.slice(1);
  PV.mount(root, {
    mode: 'self', tab, get, act,
    fileUrl: id => CFG.API + '/profile/file.php?id=' + id, upl: upl, campUrl: U.camp,
  });
  if (document.title) document.title = 'پروفایل من | کانون نخبه‌پروری رویش';
});
