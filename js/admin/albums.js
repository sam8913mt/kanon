/**
 * albums.js — مدیریت رسانه‌ی یک آلبوم (#albums/ID):
 * آپلود چندتایی تصویر، انتخاب کاور، منتخب، جایگزینی، تغییر نام، حذف، ترتیب (Drag & Drop)
 */
VIEWS.__album = async id => {
  const albums = (await list('albums')).data || [];
  const A0 = albums.find(a => +a.id === +id);
  if (!A0) { $('#c').innerHTML = '<p>آلبوم پیدا نشد.</p>'; return; }
  const draw = async () => {
    const cur = ((await list('albums')).data || []).find(a => +a.id === +id) || A0;
    const media = (await call('media.php?album_id=' + id + '&sort=order')).data || [];
    $('#ttl').textContent = 'رسانه‌های آلبوم: ' + cur.title;
    $('#c').innerHTML = `<div class="row"><a class="btn alt" href="#albums">← آلبوم‌ها</a>
        <label class="btn">آپلود تصویر / ویدئو / سند<input type="file" id="up" multiple accept="${MEDIA_ACCEPT}" hidden></label>
        <button class="btn alt" id="av" type="button">افزودن لینک ویدئو</button>
        <span id="upst" class="hint" role="status"></span></div>
      <p class="hint">برای تغییر ترتیب، موارد را بکشید یا از ↑↓ استفاده کنید. تصویر کاور: دکمه‌ی «کاور».</p>
      <div class="mgrid" id="mg">${media.map(m => `<div class="mitem" data-id="${m.id}" draggable="true">
        <div class="mth">${mediaThumb(m, 120)}${m.type === 'image' && m.path === cur.cover ? '<span class="badge">کاور</span>' : ''}${+m.featured ? '<span class="badge b2">منتخب</span>' : ''}</div>
        <div class="mt">${esc(m.title || LBL.media_type[m.type] || 'رسانه')}</div>
        <div class="acts">${m.type === 'image' ? `<button class="btn alt sm" data-cv="${m.id}">کاور</button>` : ''}${['image', 'video'].includes(m.type) ? `<button class="btn alt sm" data-ft="${m.id}:${+m.featured ? 0 : 1}">${+m.featured ? 'حذف از منتخب' : 'منتخب'}</button>` : ''}
          <button class="btn alt sm" data-rn="${m.id}">نام</button>${m.path && !/^https?:/.test(m.path) ? `<label class="btn alt sm">جایگزین<input type="file" data-rp="${m.id}" accept="${MEDIA_ACCEPT}" hidden></label>` : ''}
          <button class="btn alt sm" data-mv="${m.id}:-1" aria-label="قبلی">↑</button><button class="btn alt sm" data-mv="${m.id}:1" aria-label="بعدی">↓</button><button class="btn alt sm" data-dl="${m.id}">حذف</button></div></div>`).join('') || '<p>هنوز رسانه‌ای اضافه نشده است.</p>'}</div>`;

    makeSortable($('#mg'), '.mitem', async ids => report(await reorder('media', ids)), true);
    $('#up').onchange = async e => {
      const files = [...e.target.files];
      for (let i = 0; i < files.length; i++) {
        $('#upst').textContent = `در حال آپلود ${i + 1} از ${files.length}…`;
        const fd = new FormData();
        fd.append('file', files[i]); fd.append('album_id', id); fd.append('sub', 'albums'); fd.append('title', files[i].name.replace(/\.[^.]+$/, ''));
        const r = await upload('upload.php', fd);
        if (!r.success) toast(files[i].name + ': ' + r.message, false);
      }
      toast('آپلود تمام شد'); draw();
    };
    $('#av').onclick = async () => {
      const url = prompt('آدرس ویدئو (https): آپارات، یوتیوب، ویمیو یا فایل مستقیم MP4/WEBM');
      if (!url) return;
      const title = prompt('عنوان ویدئو (اختیاری):') || '';
      if (report(await call('media.php', { op: 'add_video_url', url, title, album_id: +id }))) draw();
    };
    $('#mg').onclick = async e => {
      const b = e.target.closest('button'); if (!b) return;
      const d = b.dataset;
      if (d.cv) { if (report(await call('media.php', { op: 'set_cover', id: d.cv }))) draw(); }
      else if (d.ft) { const [mid, v] = d.ft.split(':'); if (report(await call('media.php', { op: 'feature', id: mid, value: +v }))) draw(); }
      else if (d.rn) { const t = prompt('عنوان جدید:'); if (t !== null && report(await call('media.php', { op: 'rename', id: d.rn, title: t }))) draw(); }
      else if (d.dl && confirm('این رسانه حذف شود؟')) { if (report(await call('media.php', { op: 'delete', id: d.dl }))) draw(); }
      else if (d.mv) { const [mid, dir] = d.mv.split(':'); if (report(await reorder('media', moveId(media.map(m => +m.id), +mid, +dir)))) draw(); }
    };
    $('#mg').onchange = async e => {
      const mid = e.target.dataset.rp; if (!mid) return;
      const fd = new FormData(); fd.append('file', e.target.files[0]); fd.append('id', mid);
      if (report(await upload('media.php', fd))) draw();
    };
  };
  draw();
};
