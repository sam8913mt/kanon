/** seo.js — مدیریت SEO مستقل: Global و برای هر صفحه/اردو/آلبوم/خبر (Title, Meta, Canonical, OG, Schema) */
VIEWS.seo = async () => {
  const SRC = { global: null, page: 'pages', camp: 'camps', album: 'albums', news: 'news' };
  const NAMES = { global: 'عمومی سایت (Global)', page: 'صفحه', camp: 'اردو', album: 'آلبوم', news: 'خبر' };
  const rows = (await list('seo')).data || [];
  $('#c').innerHTML = `<div class="row"><label for="se">نوع:</label><select id="se">${Object.entries(NAMES).map(([k, l]) => `<option value="${k}">${l}</option>`).join('')}</select>
    <label for="si" id="sil">مورد:</label><select id="si"></select></div><div id="sf"></div>
    <p class="hint">اگر برای موردی مقداری وارد نکنید، سایت به‌صورت خودکار از عنوان/توضیح همان مورد استفاده می‌کند. Schema خودکار: Organization و WebSite (همه‌ی صفحات)، Event (اردو)، Article (خبر).</p>`;
  const items = async () => {
    const ent = $('#se').value, src = SRC[ent];
    $('#si').hidden = $('#sil').hidden = !src;
    if (src) $('#si').innerHTML = ((await list(src)).data || []).map(x => `<option value="${x.id}">${esc(x.title)}</option>`).join('');
    draw();
  };
  const draw = () => {
    const ent = $('#se').value, id = SRC[ent] ? +($('#si').value || 0) : 0;
    if (SRC[ent] && !id) { $('#sf').innerHTML = '<p>موردی وجود ندارد.</p>'; return; }
    const r = rows.find(x => x.entity === ent && +x.entity_id === id) || {};
    const F = [['seo_title', 'SEO Title'], ['meta_description', 'Meta Description', 'area'], ['canonical', 'Canonical (آدرس کامل)'], ['og_title', 'OG Title'], ['og_description', 'OG Description', 'area'], ['og_image', 'OG Image', 'img'], ['schema_json', 'Schema (JSON-LD سفارشی)', 'area']];
    $('#sf').innerHTML = `<form class="f wide" id="sx">${F.map(([k, l, t]) => t === 'area' ? `<label for="x_${k}">${l}</label><textarea id="x_${k}">${esc(r[k] || '')}</textarea>`
      : t === 'img' ? `<label for="x_${k}">${l}</label><div class="row"><input id="x_${k}" value="${esc(r[k] || '')}" dir="ltr"><button type="button" class="btn alt sm" data-pk="x_${k}">انتخاب</button></div>`
      : `<label for="x_${k}">${l}</label><input id="x_${k}" value="${esc(r[k] || '')}">`).join('')}<p class="err" id="xe"></p><button class="btn">ذخیره</button></form>`;
    $('#sx').querySelectorAll('[data-pk]').forEach(b => b.onclick = () => pickImage($('#' + b.dataset.pk)));
    $('#sx').onsubmit = async e => {
      e.preventDefault();
      const d = { entity: ent, entity_id: id };
      F.forEach(([k]) => d[k] = $('#x_' + k).value);
      if (d.schema_json) { try { JSON.parse(d.schema_json); } catch (x) { $('#xe').textContent = 'Schema باید JSON معتبر باشد'; return; } }
      const res = await save('seo', d, r.id || null);
      if (report(res)) { const all = (await list('seo')).data || []; rows.length = 0; rows.push(...all); }
    };
  };
  $('#se').onchange = items; $('#si').onchange = draw;
  items();
};
