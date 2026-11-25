/** about.js — درباره‌ی کانون (صفحه‌ی «about» از بخش صفحات پنل) */
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/pages.php?slug=about'), a = r.data;
  if (!r.success || !a) { byId('t').textContent = failText(r, 'صفحه'); return; }
  byId('t').textContent = a.title;
  byId('b').textContent = a.content || '';
  seoFor('page', 'about', { title: a.title, description: (a.content || '').slice(0, 160) });
});
