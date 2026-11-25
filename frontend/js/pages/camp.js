/** camp.js — جزئیات اردو (مستقل از صفحه‌ی ثبت‌نام) */
document.addEventListener('DOMContentLoaded', async () => {
  const slug = qs('slug'), box = byId('m');
  const r = await api('/camp.php?slug=' + encodeURIComponent(slug || ''));
  const c = r.data;
  if (!r.success || !c) { box.innerHTML = failHtml(r, 'اردو'); return; }
  seoFor('camp', slug, { title: c.title, description: c.short_desc, og_image: absUrl(c.cover), og_type: 'article' });

  const row = (l, v) => v ? `<p><b>${l}:</b> ${esc(v)}</p>` : '';
  const blk = (l, v) => v ? `<h3>${l}</h3><p class="pre">${esc(v)}</p>` : '';
  const alb = (await api('/albums.php?slug=' + encodeURIComponent(slug))).data; // آلبومِ هم‌نام اردو
  const media = alb && alb.media ? alb.media : [];
  const left = c.capacity > 0 && c.registered != null ? Math.max(0, c.capacity - c.registered) : null;

  const draft = c.published === 0;
  const draftBar = draft ? '<p class="muted" style="background:rgba(255,180,0,.15);border:1px dashed #e0a800;border-radius:10px;padding:10px 14px;margin:0 0 14px">⚠️ پیش‌نمایش: این اردو هنوز منتشر نشده و فقط برای شما قابل مشاهده است. برای نمایش عمومی و فعال‌شدن ثبت‌نام، در پنل مدیریت «منتشر شود» را بزنید.</p>' : '';
  box.innerHTML = `${draftBar}<div class="det"><div>
      <h1>${esc(c.title)}</h1>
      ${c.cover ? `<img class="camp-cover" src="${esc(upl(c.cover))}" alt="${esc(c.title)}" width="800" height="450">` : ''}
      <p class="lead">${esc(c.short_desc || '')}</p>
      ${blk('توضیحات', c.full_desc)}${blk('برنامه', c.schedule)}${blk('شرایط شرکت', c.conditions)}${blk('امکانات', c.facilities)}
      <div id="g1"></div>
    </div>
    <aside class="card side"><div class="bd">${tag(c.status)}
      ${row('تاریخ شروع', fmtDate(c.start))}${row('تاریخ پایان', fmtDate(c.end))}${row('مکان', c.location)}${row('ظرفیت', c.capacity)}
      ${left !== null && c.status === 'open' ? row('جای خالی', left) : ''}${row('هزینه', c.cost)}${row('مدیر اردو', c.manager)}${row('تماس', c.phone)}${row('مهلت ثبت‌نام', fmtDate(c.deadline))}
      ${c.map_url ? `<a class="btn alt block" href="${esc(safeUrl(c.map_url))}" target="_blank" rel="noopener">${ic('pin')} نقشه و مسیریابی</a>` : ''}
      ${c.status === 'open' && !draft ? `<a class="btn block" href="${esc(U.reg(slug))}">ثبت‌نام در اردو</a>` : `<p class="muted">${esc(draft ? 'پیش‌نویس (منتشر نشده)' : (ST[c.status] || ''))}</p>`}
    </div></aside></div>`;
  cleanStaticLinks();

  const imgs = media.filter(x => x.type === 'image');
  if (imgs.length) { byId('g1').innerHTML = '<h3>گالری</h3><div id="g1c"></div><p><a href="' + esc(U.album(slug)) + '">مشاهده‌ی همه‌ی تصاویر ←</a></p>'; renderGallery(byId('g1c'), imgs, 6); }
});
