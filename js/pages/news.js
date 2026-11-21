/** news.js — فهرست اخبار */
document.addEventListener('DOMContentLoaded', async () => {
  byId('g').innerHTML = ((await api('/news.php')).data || []).map(newsCard).join('') || '<p class="muted">خبری منتشر نشده است.</p>';
});
