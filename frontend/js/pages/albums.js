/** albums.js — فهرست آلبوم‌ها */
document.addEventListener('DOMContentLoaded', async () => {
  skel(byId('g'));
  byId('g').className = 'grid';
  byId('g').innerHTML = ((await api('/albums.php')).data || []).map(albumCard).join('') || '<p class="muted">هنوز آلبومی منتشر نشده است.</p>';
  reveal(byId('g').children);
});
