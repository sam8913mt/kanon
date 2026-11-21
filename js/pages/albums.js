/** albums.js — فهرست آلبوم‌ها */
document.addEventListener('DOMContentLoaded', async () => {
  byId('g').innerHTML = ((await api('/albums.php')).data || []).map(albumCard).join('') || '<p class="muted">هنوز آلبومی منتشر نشده است.</p>';
});
