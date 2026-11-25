/**
 * crud.js — نمای عمومی لیست/فرم برای همه‌ی بخش‌های SCHEMA
 * ویژگی‌ها: جستجو، Drag & Drop ترتیب، انتشار/لغو انتشار، تغییر سریع وضعیت، تکثیر اردو، فرم پویا
 */
const pkOf = S => S.pk || 'id';

/** مقدار یک ستون را برای نمایش در جدول آماده می‌کند */
function cell(S, col, v) {
  const f = S.f.find(x => x.n === col);
  if (f && f.t === 'check') return +v ? '✔' : '—';
  if (f && f.t === 'sel' && f.o && !Array.isArray(f.o)) return esc(f.o[v] || v);
  if (['enabled', 'published', 'active'].includes(col)) return +v ? '✔' : '—';
  if ((f && f.t === 'date') || ['created_at', 'start_date', 'end_date', 'deadline', 'album_date', 'event_date'].includes(col)) return esc(jd(v, col === 'created_at'));
  return esc(v);
}

async function crud(k) {
  const S = SCHEMA[k];
  if (!S) return;
  const box = $('#c'), pk = pkOf(S);
  const sortable = S.cols.length && S.f.some(x => x.n === 'sort_order');
  const hasPub = S.f.some(x => x.n === 'published');

  async function draw() {
    const rows = (await list(k)).data || [];
    const inlineF = S.inline && S.f.find(x => x.n === S.inline);
    box.innerHTML = `<div class="row">${S.ro ? '' : '<button class="btn" id="nw">+ جدید</button>'}<input id="q" type="search" placeholder="جستجو در فهرست…" aria-label="جستجو" style="max-width:240px">
        ${sortable ? '<span class="hint">برای تغییر ترتیب، ردیف‌ها را بکشید یا از ↑↓ استفاده کنید.</span>' : ''}</div>
      <div class="tw"><table><thead><tr>${sortable ? '<th></th>' : ''}${S.cols.map(c => `<th>${esc((S.f.find(x => x.n === c) || {}).l || c)}</th>`).join('')}<th></th></tr></thead>
      <tbody id="tb">${rows.map(r => `<tr data-id="${esc(r[pk])}" ${sortable ? 'draggable="true"' : ''}>${sortable ? '<td class="grip" aria-hidden="true">⠿</td>' : ''}
        ${S.cols.map(c => c === S.inline && inlineF ? `<td><select data-in="${esc(r[pk])}" aria-label="${esc(inlineF.l)}">${Object.entries(inlineF.o).map(([v, l]) => `<option value="${v}" ${v === r[c] ? 'selected' : ''}>${l}</option>`).join('')}</select></td>` : `<td>${cell(S, c, r[c])}</td>`).join('')}
        <td class="acts">${S.ro ? '' : `<button class="btn alt sm" data-ed="${esc(r[pk])}">ویرایش</button>
          ${hasPub ? `<button class="btn alt sm" data-pub="${esc(r[pk])}">${+r.published ? 'لغو انتشار' : 'انتشار'}</button>` : ''}
          ${(S.actions || []).filter(a => !a[2] || a[2](r)).map((a, i) => `<button class="btn alt sm" data-act="${i}:${esc(r[pk])}">${a[0]}</button>`).join('')}
          ${sortable ? `<button class="btn alt sm" data-mv="${esc(r[pk])}:-1" aria-label="بالا">↑</button><button class="btn alt sm" data-mv="${esc(r[pk])}:1" aria-label="پایین">↓</button>` : ''}
          ${k === 'camps' ? `<button class="btn alt sm" data-dup="${esc(r[pk])}">تکثیر</button>` : ''}
          <button class="btn alt sm" data-del="${esc(r[pk])}">حذف</button>`}</td></tr>`).join('') || `<tr><td colspan="${S.cols.length + 2}">موردی ثبت نشده است.</td></tr>`}</tbody></table></div>`;

    const nw = $('#nw');
    if (nw) nw.onclick = () => form(null);
    $('#q').oninput = e => {
      const t = e.target.value.trim().toLowerCase();
      document.querySelectorAll('#tb tr').forEach(tr => tr.hidden = t && !tr.textContent.toLowerCase().includes(t));
    };
    if (sortable) makeSortable($('#tb'), 'tr[data-id]', async ids => { report(await reorder(k, ids)); });

    $('#tb').onchange = async e => {
      if (!e.target.dataset.in) return;
      report(await save(k, { [S.inline]: e.target.value }, e.target.dataset.in));
    };
    $('#tb').onclick = async e => {
      const b = e.target.closest('button');
      if (!b) return;
      const d = b.dataset, find = id => rows.find(r => String(r[pk]) === String(id));
      if (d.ed !== undefined) form(find(d.ed));
      else if (d.pub !== undefined) { const r = find(d.pub); if (report(await save(k, { published: +r.published ? 0 : 1 }, d.pub))) draw(); }
      else if (d.act) { const [i, id] = d.act.split(':'); S.actions[+i][1](find(id)); }
      else if (d.mv) {
        const [id, dir] = d.mv.split(':'), ids = moveId(rows.map(r => +r.id), +id, +dir);
        if (report(await reorder(k, ids))) draw();
      } else if (d.dup) { if (report(await call('crud.php', { e: k, op: 'duplicate', id: d.dup }))) draw(); }
      else if (d.del !== undefined && confirm('حذف شود؟ این کار بی‌بازگشت است و همه‌ی اطلاعات و فایل‌های وابسته هم برای همیشه پاک می‌شوند.')) { if (report(await call('crud.php', { e: k, op: 'delete', id: d.del }))) draw(); }
    };
  }

  /** فرم ایجاد/ویرایش بر اساس S.f */
  async function form(r) {
    const refs = {};
    for (const x of S.f.filter(x => x.t === 'ref')) refs[x.n] = (await list(x.o[0])).data || [];
    box.innerHTML = `<form class="f wide" id="ff"><h3>${r ? 'ویرایش' : 'ایجاد'} — ${esc(S.t)}</h3>${S.f.map(x => {
      const v = r ? r[x.n] ?? '' : (x.t === 'check' ? 1 : '');
      const id = 'fld_' + x.n, lab = `<label for="${id}">${esc(x.l)}</label>`;
      switch (x.t) {
        case 'area': return lab + `<textarea id="${id}">${esc(v)}</textarea>`;
        case 'check': return `<label class="op"><input type="checkbox" id="${id}" ${+v ? 'checked' : ''}> ${esc(x.l)}</label>`;
        case 'sel': return lab + `<select id="${id}">${(Array.isArray(x.o) ? x.o.map(o => [o, o]) : Object.entries(x.o)).map(([val, l]) => `<option value="${val}" ${val == v ? 'selected' : ''}>${l}</option>`).join('')}</select>`;
        case 'ref': return lab + `<select id="${id}"><option value="">— بدون —</option>${refs[x.n].map(o => `<option value="${o.id}" ${o.id == v ? 'selected' : ''}>${esc(o[x.o[1]])}</option>`).join('')}</select>`;
        case 'img': return lab + `<div class="row"><input id="${id}" value="${esc(v)}" dir="ltr"><button type="button" class="btn alt sm" data-pk="${id}">انتخاب از رسانه‌ها</button></div>`;
        default: return lab + `<input id="${id}" type="${x.t}" value="${x.t === 'password' ? '' : esc(v)}" ${x.t === 'password' ? 'autocomplete="new-password"' : ''}>`;
      }
    }).join('')}<p class="err" id="er" role="alert"></p><button class="btn">ذخیره</button> <button type="button" class="btn alt" id="cn">انصراف</button></form>`;
    $('#cn').onclick = draw;
    $('#ff').querySelectorAll('[data-pk]').forEach(b => b.onclick = () => pickImage($('#' + b.dataset.pk)));
    // پیشنهاد خودکار نامک هنگام ایجاد مورد جدید
    if (!r && S.slugFrom) $('#fld_title').addEventListener('blur', () => { const s = $('#fld_slug'); if (s && !s.value) s.value = slugify($('#fld_title').value, S.slugFrom); });
    $('#ff').onsubmit = async e => {
      e.preventDefault();
      const d = {};
      S.f.forEach(x => { const el = $('#fld_' + x.n); d[x.n] = x.t === 'check' ? +el.checked : el.value; });
      if (S.pk && r) d[S.pk] = r[S.pk];
      const res = await call('crud.php', { e: k, id: r ? r[pk] : null, data: d });
      if (res.success) { toast(res.message); draw(); } else $('#er').textContent = res.message;
    };
  }
  draw();
}
