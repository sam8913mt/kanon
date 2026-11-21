/** router.js — ورود، منوی کناری (بر اساس دسترسی)، مسیریابی hash (#بخش یا #بخش/شناسه) */
async function boot() {
  const r = await call('me.php');
  if (!r.success) return;
  A.csrf = r.data.csrf; A.me = r.data.user; A.areas = r.data.areas;
  $('#who').textContent = A.me.username + ' (' + A.me.role + ')';
  $('#menu').innerHTML = NAV_GROUPS.map(([title, items]) => {
    const ok = items.filter(n => A.areas.includes(n[2]));
    return ok.length ? `<div class="grp">${title}</div>` + ok.map(n => `<a href="#${n[0]}" data-k="${n[0]}">${n[1]}</a>`).join('') : '';
  }).join('') + '<div class="grp"></div><a href="#" id="out">خروج</a>';
  $('#out').onclick = async e => { e.preventDefault(); await call('logout.php', {}); location.href = '/login'; };
  route();
}

async function route() {
  const [k, arg, tab, body] = (location.hash.slice(1) || 'dashboard').split('/');
  document.querySelectorAll('#menu a').forEach(a => a.classList.toggle('on', a.dataset.k === k));
  $('#sb').classList.remove('open');
  const n = NAV.find(x => x[0] === k);
  $('#ttl').textContent = n ? n[1] : '';
  if (n && !A.areas.includes(n[2])) { $('#c').innerHTML = '<p class="err">به این بخش دسترسی ندارید.</p>'; return; }
  if (k === 'albums' && arg) return VIEWS.__album(arg);
  if (k === 'profiles' && arg === 'top') return VIEWS.__top();
  if (k === 'profiles' && arg) return VIEWS.__profile(+arg, tab, body);
  if (k === 'about') return aboutView();
  const v = VIEWS[k];
  v ? await v() : await crud(k);
}
addEventListener('hashchange', route);

/** «درباره کانون» = ویرایش مستقیم صفحه‌ی about از بخش صفحات */
async function aboutView() {
  const p = ((await list('pages')).data || []).find(x => x.slug === 'about');
  if (!p) { $('#c').innerHTML = '<p>صفحه‌ی about وجود ندارد؛ از «صفحات» با نامک about بسازید.</p>'; return; }
  $('#c').innerHTML = `<form class="f wide" id="ab"><label for="at">عنوان</label><input id="at" value="${esc(p.title)}">
    <label for="ac">متن درباره‌ی کانون</label><textarea id="ac" rows="12">${esc(p.content)}</textarea><button class="btn">ذخیره</button></form>`;
  $('#ab').onsubmit = async e => { e.preventDefault(); report(await save('pages', { title: $('#at').value, content: $('#ac').value }, p.id)); };
}
boot();
