/** page.js — صفحات عمومی (/page/slug): قوانین، حریم خصوصی، ... */
document.addEventListener('DOMContentLoaded', async () => {
  const slug = qs('slug') || 'about';
  const r = await api('/pages.php?slug=' + encodeURIComponent(slug));
  if (!r.success || !r.data) { byId('t').textContent = 'صفحه پیدا نشد'; return; }
  byId('t').textContent = r.data.title;
  byId('b').textContent = r.data.content || '';
  seoFor('page', slug, { title: r.data.title, description: (r.data.content || '').slice(0, 160) });
});
