/** login.js — ورود به پنل: login.html → POST /api/login.php → Session */
document.addEventListener('DOMContentLoaded', () => {
  byId('f').onsubmit = async ev => {
    ev.preventDefault();
    const e = byId('e'); e.textContent = '';
    if (CFG.DEMO_MODE) return e.textContent = 'ورود در حالت Demo غیرفعال است؛ DEMO_MODE را در js/config.js روی false بگذارید.';
    const t = (await api('/login.php')).data;
    if (!t) return e.textContent = 'ارتباط با سرور برقرار نشد';
    const r = await api('/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': t.csrf },
      body: JSON.stringify({ username: byId('u').value, password: byId('p').value }),
    });
    if (r.success) location.href = '/admin/'; else e.textContent = r.message;
  };
});
