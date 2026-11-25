/** album.js — یک آلبوم: Gallery با Lightbox (زوم، تمام‌صفحه، قبلی/بعدی) */
document.addEventListener('DOMContentLoaded', async () => {
  const slug = qs('slug') || '';
  const r = await api('/albums.php?slug=' + encodeURIComponent(slug));
  const A = r.data;
  if (!r.success || !A) { byId('t').textContent = failText(r, 'آلبوم'); return; }
  byId('t').textContent = A.title;
  byId('d').textContent = [fmtDate(A.date), A.description].filter(Boolean).join(' — ');
  seoFor('album', A.slug, { title: A.title, description: A.description, og_image: absUrl(A.cover) });
  const M = A.media || [];
  if (byId('fl')) renderFiles(byId('fl'), A.files);
  if (!M.length) { byId('g').innerHTML = '<p class="muted">هنوز تصویر یا ویدئویی اضافه نشده است.</p>'; return; }
  renderGallery(byId('g'), M);
});
