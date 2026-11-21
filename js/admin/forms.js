/**
 * forms.js — فرم‌ساز یکپارچه: هر اردو فرم اختصاصی خودش را دارد.
 * اردو ← تنظیمات فرم ← سؤال‌ها (Drag & Drop، نوع، الزامی، فعال) ← گزینه‌ها (افزودن/ویرایش/حذف/فعال/ترتیب)
 * هیچ تغییری در PHP/HTML لازم نیست؛ پاسخ‌های قبلی با حذف/غیرفعال‌شدن سؤال یا گزینه حفظ می‌شوند.
 */
VIEWS.forms = async () => {
  const camps = (await list('camps')).data || [];
  if (!camps.length) { $('#c').innerHTML = '<p>ابتدا یک اردو بسازید.</p>'; return; }
  const want = sessionStorage.getItem('form_camp');
  $('#c').innerHTML = `<div class="row"><label for="fcamp">اردو:</label><select id="fcamp">${camps.map(c => `<option value="${c.id}" ${c.id == want ? 'selected' : ''}>${esc(c.title)}</option>`).join('')}</select></div><div id="fb"></div>`;
  $('#fcamp').onchange = () => { sessionStorage.setItem('form_camp', $('#fcamp').value); build(); };

  async function build() {
    const cid = +$('#fcamp').value, camp = camps.find(c => c.id === cid);
    const cf = ((await list('camp_forms', { camp_id: cid })).data || [])[0] || null;
    const fields = ((await list('form_fields', { camp_id: cid })).data || []);
    const allOpts = (await list('form_options')).data || [];
    const optsOf = fid => allOpts.filter(o => +o.field_id === +fid);
    const hasOpts = t => t === 'select' || t === 'radio';
    const typeSel = cur => Object.entries(LBL.field_type).map(([k, l]) => `<option value="${k}" ${k === cur ? 'selected' : ''}>${l}</option>`).join('');

    const optRow = (o, ids) => `<li class="orow" data-id="${o.id}" draggable="true"><span class="grip" aria-hidden="true">⠿</span>
        <input value="${esc(o.label)}" data-ol="${o.id}" aria-label="عنوان گزینه"><label class="op"><input type="checkbox" data-oe="${o.id}" ${+o.enabled ? 'checked' : ''}> فعال</label>
        <button class="btn alt sm" data-omv="${o.id}:-1" aria-label="بالا">↑</button><button class="btn alt sm" data-omv="${o.id}:1" aria-label="پایین">↓</button>
        <button class="btn alt sm" data-od="${o.id}">حذف</button></li>`;

    const fieldRow = f => `<div class="frow card" data-id="${f.id}" draggable="true"><div class="bd">
        <div class="row"><span class="grip" aria-hidden="true">⠿</span><input value="${esc(f.label)}" data-fl="${f.id}" aria-label="عنوان سؤال" style="flex:1;min-width:160px">
          <select data-ft="${f.id}" aria-label="نوع">${typeSel(f.type)}</select>
          <label class="op"><input type="checkbox" data-fr="${f.id}" ${+f.required ? 'checked' : ''}> الزامی</label>
          <label class="op"><input type="checkbox" data-fe="${f.id}" ${+f.enabled ? 'checked' : ''}> فعال</label>
          <button class="btn alt sm" data-fmv="${f.id}:-1" aria-label="بالا">↑</button><button class="btn alt sm" data-fmv="${f.id}:1" aria-label="پایین">↓</button>
          <button class="btn alt sm" data-fd="${f.id}">حذف</button></div>
        ${hasOpts(f.type) ? `<div class="opts"><b>گزینه‌ها</b><ul class="olist" data-of="${f.id}">${optsOf(f.id).map(optRow).join('')}</ul>
          <div class="row"><input placeholder="گزینه‌ی جدید (مثلاً دهم)" data-on="${f.id}" aria-label="گزینه‌ی جدید"><button class="btn sm" data-oa="${f.id}">+ افزودن گزینه</button></div></div>` : ''}</div></div>`;

    $('#fb').innerHTML = `<form class="f wide" id="cfm"><h3>تنظیمات فرم «${esc(camp.title)}»</h3>
        <label for="cft">عنوان فرم</label><input id="cft" value="${esc(cf ? cf.title : 'فرم ثبت‌نام')}">
        <label for="cfi">توضیح بالای فرم</label><textarea id="cfi">${esc(cf ? cf.intro : '')}</textarea>
        <label class="op"><input type="checkbox" id="cfe" ${!cf || +cf.enabled ? 'checked' : ''}> فرم فعال باشد</label>
        <label class="op"><input type="checkbox" id="cfp" ${+camp.photo_required ? 'checked' : ''}> عکس دانش‌آموز (فیلد ثابت «photo») الزامی باشد</label>
        <button class="btn">ذخیره‌ی تنظیمات فرم</button></form>
      <h3>سؤال‌ها</h3><p class="hint">سؤال‌ها را بکشید تا ترتیبشان عوض شود. حذف یا غیرفعال‌کردن سؤال/گزینه، پاسخ‌های ثبت‌نام‌های قبلی را از بین نمی‌برد.</p>
      <div id="fl">${fields.map(fieldRow).join('') || '<p>هنوز سؤالی ندارد.</p>'}</div>
      <form class="f wide" id="nf"><h3>افزودن سؤال</h3><label for="nfl">عنوان سؤال</label><input id="nfl" required>
        <label for="nft">نوع</label><select id="nft">${typeSel('text')}</select><label class="op"><input type="checkbox" id="nfr"> الزامی</label><button class="btn">+ افزودن سؤال</button></form>`;

    // ذخیره‌ی تنظیمات فرم و الزامی بودن عکس
    $('#cfm').onsubmit = async e => {
      e.preventDefault();
      const d = { camp_id: cid, title: $('#cft').value, intro: $('#cfi').value, enabled: +$('#cfe').checked };
      const a = await call('crud.php', { e: 'camp_forms', id: null, data: d });
      const b = await save('camps', { photo_required: +$('#cfp').checked }, cid);
      if (report(a) && report(b)) camp.photo_required = +$('#cfp').checked;
    };
    // سؤال جدید (نام فنی خودکار و یکتا)
    $('#nf').onsubmit = async e => {
      e.preventDefault();
      const name = 'q_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 5);
      const r = await save('form_fields', { camp_id: cid, name, label: $('#nfl').value, type: $('#nft').value, required: +$('#nfr').checked, enabled: 1, sort_order: fields.length + 1 });
      if (report(r)) build();
    };
    // ترتیب سؤال‌ها و گزینه‌ها با Drag & Drop
    makeSortable($('#fl'), '.frow', async ids => report(await reorder('form_fields', ids)));
    document.querySelectorAll('.olist').forEach(ul => makeSortable(ul, '.orow', async ids => report(await reorder('form_options', ids))));

    const box = $('#fb');
    box.onchange = async e => {
      const t = e.target, d = t.dataset;
      if (d.fl) report(await save('form_fields', { label: t.value }, d.fl));
      else if (d.ft) { if (report(await save('form_fields', { type: t.value }, d.ft))) build(); }
      else if (d.fr) report(await save('form_fields', { required: +t.checked }, d.fr));
      else if (d.fe) report(await save('form_fields', { enabled: +t.checked }, d.fe));
      else if (d.ol) report(await save('form_options', { label: t.value }, d.ol));
      else if (d.oe) report(await save('form_options', { enabled: +t.checked }, d.oe));
    };
    box.onclick = async e => {
      const b = e.target.closest('button'); if (!b) return;
      const d = b.dataset;
      if (d.fd && confirm('این سؤال از فرم حذف شود؟ (پاسخ‌های قبلی حفظ می‌شود)')) { if (report(await call('crud.php', { e: 'form_fields', op: 'delete', id: d.fd }))) build(); }
      else if (d.od && confirm('این گزینه حذف شود؟ (پاسخ‌های قبلی حفظ می‌شود)')) { if (report(await call('crud.php', { e: 'form_options', op: 'delete', id: d.od }))) build(); }
      else if (d.oa) {
        const inp = box.querySelector(`[data-on="${d.oa}"]`);
        if (!inp.value.trim()) return;
        if (report(await save('form_options', { field_id: d.oa, label: inp.value.trim(), enabled: 1, sort_order: optsOf(d.oa).length + 1 }))) build();
      } else if (d.fmv) { const [id, dir] = d.fmv.split(':'); if (report(await reorder('form_fields', moveId(fields.map(f => +f.id), +id, +dir)))) build(); }
      else if (d.omv) {
        const [id, dir] = d.omv.split(':'), o = allOpts.find(x => x.id == id);
        if (report(await reorder('form_options', moveId(optsOf(o.field_id).map(x => +x.id), +id, +dir)))) build();
      }
    };
  }
  build();
};
