/** camps.js — فهرست همه‌ی اردوها با فیلتر وضعیت */
document.addEventListener('DOMContentLoaded', async () => {
  skel(byId('c')); byId('c').className = 'skel-row';
  const camps = (await api('/camps')).data || [];
  const F = { all: 'همه', open: 'ثبت‌نام فعال', upcoming: 'آینده', done: 'برگزار شده', cancelled: 'لغو شده' };
  const draw = k => {
    byId('c').className = 'grid';
    const r = camps.filter(c => k === 'all' || campGroup(c) === k);
    byId('c').innerHTML = r.map(campCard).join('') || '<p class="muted">اردویی در این دسته نیست.</p>';
    reveal(byId('c').children);
    document.querySelectorAll('#flt button').forEach(b => b.setAttribute('aria-pressed', b.dataset.k === k));
  };
  byId('flt').innerHTML = Object.entries(F).map(([k, v]) => `<button type="button" class="chip" data-k="${k}">${v}</button>`).join('');
  byId('flt').onclick = e => { if (e.target.dataset.k) draw(e.target.dataset.k); };
  draw('all');
});
