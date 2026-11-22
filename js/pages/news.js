/** news.js — فهرست اخبار */
document.addEventListener('DOMContentLoaded', async () => {
  skel(byId('g'));
  byId('g').className = 'grid';
  byId('g').innerHTML = ((await api('/news.php')).data || []).map(newsCard).join('') || '<p class="muted">خبری منتشر نشده است.</p>';
  reveal(byId('g').children);
});
