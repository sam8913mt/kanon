/** reports.js — گزارش‌ها: ثبت‌نام هر اردو، ظرفیت، وضعیت‌ها، تعداد آلبوم/رسانه، فعالیت کاربران */
VIEWS.reports = async () => {
  const s = (await call('stats.php')).data;
  if (!s) return;
  const bar = (n, total) => `<progress max="${Math.max(1, total)}" value="${n}"></progress> ${n}`;
  const total = Math.max(1, s.registrations);
  const table = (head, rows) => `<div class="tw"><table><tr>${head.map(h => `<th>${h}</th>`).join('')}</tr>${rows.join('') || `<tr><td colspan="${head.length}">داده‌ای نیست.</td></tr>`}</table></div>`;
  $('#c').innerHTML = `<div class="cards">
      <div class="card"><b>${s.camps}</b>تعداد اردوها</div><div class="card"><b>${s.registrations}</b>تعداد ثبت‌نام‌ها</div>
      <div class="card"><b>${s.albums}</b>آلبوم‌ها</div><div class="card"><b>${s.media}</b>رسانه‌ها</div></div>
    <h3>ثبت‌نام هر اردو و ظرفیت</h3>${table(['اردو', 'ثبت‌نام', 'ظرفیت', 'درصد پر شدن'], s.per_camp.map(x => `<tr><td>${esc(x.title)}</td><td>${x.n}</td><td>${x.capacity}</td><td>${x.capacity > 0 ? Math.round(100 * x.n / x.capacity) + '٪' : '—'}</td></tr>`))}
    <h3>وضعیت ثبت‌نام‌ها</h3>${table(['وضعیت', 'تعداد'], Object.entries(LBL.reg_status).map(([k, l]) => `<tr><td>${l}</td><td>${bar(+(s.by_status[k] || 0), total)}</td></tr>`))}
    <h3>وضعیت اردوها</h3>${table(['وضعیت ثبت‌نام اردو', 'تعداد'], Object.entries(s.camp_status).map(([k, n]) => `<tr><td>${esc(LBL.registration_status[k] || k)}</td><td>${n}</td></tr>`))}
    ${isAdmin() ? `<h3>فعالیت کاربران (۳۰ روز اخیر)</h3>${table(['کاربر', 'تعداد رویداد'], s.activity.map(x => `<tr><td>${esc(x.username)}</td><td>${x.n}</td></tr>`))}` : ''}`;
};
