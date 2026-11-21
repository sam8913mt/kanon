/**
 * media.js — مدیریت فایل‌ها (Media Manager): تصویر، ویدئو (آپلود یا لینک)، سند و فایل
 * آپلود، جستجو، فیلتر نوع، مرتب‌سازی، پیش‌نمایش، تغییر نام، جایگزینی، منتخب و حذف.
 * Editor فقط رسانه‌ی مجاز خودش را می‌بیند (کنترل اصلی در media.php است).
 */
async function mediaView(preset) {
  $('#c').innerHTML = `<div class="row"><input id="mq" type="search" placeholder="جستجو" style="max-width:200px" aria-label="جستجو">
      <select id="mt" aria-label="نوع"><option value="">همه نوع‌ها</option>${Object.entries(LBL.media_type).map(([k, v]) => `<option value="${k}">${v}</option>`).join('')}</select>
      <select id="ms" aria-label="مرتب‌سازی"><option value="">جدیدترین</option><option value="old">قدیمی‌ترین</option><option value="size">حجم</option><option value="name">نام</option></select>
      <label class="btn">آپلود فایل<input type="file" id="mu" multiple accept="${MEDIA_ACCEPT}" hidden></label>
      <button class="btn alt" id="mv" type="button">افزودن لینک ویدئو</button><span class="hint" id="mst" role="status"></span></div>
    <p class="hint">تصویر JPG/PNG/WEBP، ویدئو MP4/WEBM، سند PDF/DOCX/XLSX/PPTX/TXT و ZIP. برای ویدئوهای حجیم از لینک آپارات/یوتیوب/ویمیو یا آدرس مستقیم MP4 استفاده کنید.</p>
    <div class="tw" id="mw"></div>`;
  if (preset) $('#mt').value = preset;
  const load = async () => {
    const r = (await call(`media.php?q=${encodeURIComponent($('#mq').value)}&type=${$('#mt').value}&sort=${$('#ms').value}`)).data || [];
    $('#mw').innerHTML = `<table><tr><th>پیش‌نمایش</th><th>عنوان</th><th>مسیر / لینک</th><th>نوع</th><th>حجم</th><th>آلبوم</th><th></th></tr>${r.map(m => `<tr>
      <td>${mediaThumb(m)}</td>
      <td>${esc(m.title)}</td><td dir="ltr"><a href="${esc(mediaHref(m))}" target="_blank" rel="noopener">${esc(m.path || m.url)}</a></td><td>${esc(LBL.media_type[m.type] || m.type)}</td><td>${m.size ? fmtSize(m.size) : '—'}</td><td>${m.album_id || '—'}</td>
      <td class="acts"><button class="btn alt sm" data-rn="${m.id}">تغییر نام</button>
        ${['image', 'video'].includes(m.type) ? `<button class="btn alt sm" data-ft="${m.id}:${+m.featured ? 0 : 1}">${+m.featured ? 'حذف از منتخب' : 'منتخب'}</button>` : ''}
        ${m.path && !/^https?:/.test(m.path) ? `<label class="btn alt sm">جایگزین<input type="file" data-rp="${m.id}" accept="${MEDIA_ACCEPT}" hidden></label>` : ''}
        <button class="btn alt sm" data-dl="${m.id}">حذف</button></td></tr>`).join('') || '<tr><td colspan="7">فایلی نیست.</td></tr>'}</table>`;
  };
  ['input', 'change'].forEach(ev => [$('#mq'), $('#mt'), $('#ms')].forEach(x => x.addEventListener(ev, load)));
  $('#mu').onchange = async e => {
    const files = [...e.target.files];
    for (let i = 0; i < files.length; i++) {
      $('#mst').textContent = `آپلود ${i + 1} از ${files.length}…`;
      const fd = new FormData(); fd.append('file', files[i]); fd.append('title', files[i].name.replace(/\.[^.]+$/, ''));
      const r = await upload('upload.php', fd);
      if (!r.success) toast(files[i].name + ': ' + r.message, false);
    }
    $('#mst').textContent = ''; e.target.value = ''; load();
  };
  $('#mv').onclick = async () => {
    const url = prompt('آدرس ویدئو (https): آپارات، یوتیوب، ویمیو یا فایل مستقیم MP4/WEBM');
    if (!url) return;
    const title = prompt('عنوان ویدئو (اختیاری):') || '';
    if (report(await call('media.php', { op: 'add_video_url', url, title }))) load();
  };
  $('#mw').onclick = async e => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.rn) { const t = prompt('نام جدید:'); if (t !== null && report(await call('media.php', { op: 'rename', id: b.dataset.rn, title: t }))) load(); }
    else if (b.dataset.ft) { const [id, v] = b.dataset.ft.split(':'); if (report(await call('media.php', { op: 'feature', id, value: +v }))) load(); }
    else if (b.dataset.dl && confirm('حذف شود؟')) { if (report(await call('media.php', { op: 'delete', id: b.dataset.dl }))) load(); }
  };
  $('#mw').onchange = async e => {
    const id = e.target.dataset.rp; if (!id) return;
    const fd = new FormData(); fd.append('file', e.target.files[0]); fd.append('id', id);
    if (report(await upload('media.php', fd))) load();
  };
  load();
}
VIEWS.media = () => mediaView('');
VIEWS.images = () => mediaView('image');
