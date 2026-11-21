/** article.js — صفحه‌ی یک خبر (/news/slug) */
document.addEventListener('DOMContentLoaded', async () => {
  const slug = qs('slug') || '';
  const r = await api('/news.php?slug=' + encodeURIComponent(slug));
  const n = r.data;
  if (!r.success || !n) { byId('t').textContent = 'خبر پیدا نشد'; return; }
  byId('t').textContent = n.title;
  byId('m').textContent = [fmtDate(n.date), n.author].filter(Boolean).join(' — ');
  if (n.cover) byId('cv').innerHTML = `<img class="camp-cover" src="${esc(upl(n.cover))}" alt="${esc(n.title)}">`;
  byId('b').textContent = n.content || '';
  seoFor('news', n.slug, {
    title: n.title, description: n.summary, og_image: absUrl(n.cover), og_type: 'article',
    schema: { '@context': 'https://schema.org', '@type': 'Article', headline: n.title, datePublished: n.date, author: { '@type': 'Person', name: n.author || '' } },
  });
});
