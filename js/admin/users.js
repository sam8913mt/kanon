/** users.js — تعیین اردوهای مجاز هر Editor (کاربران خودشان با crud عمومی مدیریت می‌شوند) */
VIEWS.__access = async user => {
  const camps = (await list('camps')).data || [];
  const albums = (await list('albums')).data || [];
  const acc = (await call('camp_access.php?user_id=' + user.id)).data || {};
  const cur = new Set(acc.camp_ids || []), curA = new Set(acc.album_ids || []);
  $('#c').innerHTML = `<form class="f wide" id="ac"><h3>اردوهای مجاز برای «${esc(user.username)}»</h3>
    <p class="hint">این کاربر فقط اردوهای انتخاب‌شده (و اردوهایی که خودش می‌سازد) را می‌بیند و مدیریت می‌کند.</p>
    ${camps.map(c => `<label class="op"><input type="checkbox" value="${c.id}" ${cur.has(c.id) ? 'checked' : ''}> ${esc(c.title)}</label>`).join('') || '<p>اردویی نیست.</p>'}
    <h3>آلبوم‌های مجاز</h3><p class="hint">Editor فقط آلبوم‌های انتخاب‌شده (و آلبوم‌هایی که خودش می‌سازد) و رسانه‌های آن‌ها را مدیریت می‌کند. آلبوم‌های قدیمی تا انتخاب در اینجا فقط برای Admin قابل مدیریت‌اند.</p>
    ${albums.map(a => `<label class="op"><input type="checkbox" data-album value="${a.id}" ${curA.has(a.id) ? 'checked' : ''}> ${esc(a.title)}</label>`).join('') || '<p>آلبومی نیست.</p>'}
    <button class="btn">ذخیره</button> <a class="btn alt" href="#users">بازگشت</a></form>`;
  $('#ac').onsubmit = async e => {
    e.preventDefault();
    const ids = [...document.querySelectorAll('#ac input:checked:not([data-album])')].map(i => +i.value);
    const aids = [...document.querySelectorAll('#ac input[data-album]:checked')].map(i => +i.value);
    report(await call('camp_access.php', { user_id: user.id, camp_ids: ids, album_ids: aids }));
  };
};
