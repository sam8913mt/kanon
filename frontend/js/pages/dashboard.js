/** dashboard.js — /dashboard: خلاصه‌ی وضعیت کاربر (Dashboard = خلاصه؛ Profile = اطلاعات کامل) ← GET /api/profile/dashboard.php */
document.addEventListener('DOMContentLoaded', async () => {
  const root = byId('db-root'), E = PV.E, pd = PV.pd, pdt = PV.pdt;
  if (CFG.DEMO_MODE) { root.innerHTML = '<div class="pv-empty">در حالت Demo داشبورد غیرفعال است؛ سایت را روی هاست اجرا کنید.</div>'; return; }
  const m = (await api('/member.php')).data;
  if (!m || !m.member) { location.href = U.signup() + '?mode=login&next=' + encodeURIComponent(location.pathname); return; }
  const r = await api('/profile/dashboard.php');
  if (!r.success) { root.innerHTML = `<div class="pv-empty">${E(r.message || 'خطا در دریافت اطلاعات')}</div>`; return; }
  const d = r.data, h = d.header, st = d.stats;
  const av = h.avatar_id ? `<img src="${E(CFG.API + '/profile/file.php?id=' + h.avatar_id)}" alt="">` : E((h.first_name || '؟').slice(0, 1));
  const P = t => U.profile() + '#' + t;
  const card = (title, link, body) => `<div class="db-card"><h2>${title}${link ? `<a href="${E(link[0])}">${link[1]}</a>` : ''}</h2>${body}</div>`;
  const empty = t => `<div class="pv-empty">${E(t)}</div>`;
  const regs = d.recent_registrations.map(x => `<div class="pv-item"><div class="bd"><h3>${E(x.camp.title)}</h3><p>${PV.badge(x.status, x.status_label)} ${PV.badge(x.attendance, x.attendance_label)}</p><p>${E(pd(x.created_at))}</p></div></div>`).join('');
  const up = d.upcoming_camps.map(x => `<div class="pv-item"><div class="bd"><h3>${E(x.camp.title)}</h3><p>${E(pd(x.camp.start))}${x.camp.location ? ' · ' + E(x.camp.location) : ''}</p><p>${PV.badge(x.status, x.status_label)}</p></div></div>`).join('');
  const op = d.open_camps.map(x => `<div class="pv-item"><div class="bd"><h3><a href="${E(U.camp(x.slug))}">${E(x.title)}</a></h3><p>${E(pd(x.start))}${x.location ? ' · ' + E(x.location) : ''}</p></div></div>`).join('');
  const nt = d.notifications.map(n => `<div class="pv-item"><div class="bd"><h3>${E(n.title)}</h3><p>${E(pdt(n.created_at))}</p></div></div>`).join('');
  const rs = d.responsibilities.map(x => `<span class="pv-chip ${x.body === 'kanoon' ? 'k' : 'h'}">${E(x.title)} — ${E(x.body_label)}</span>`).join(' ');
  const ac = d.recent_activities.map(x => `<div class="pv-item"><div class="bd"><h3>${E(x.title)}</h3><p>${E(x.kind_label)} ${x.date ? '· ' + E(pd(x.date)) : ''}</p></div></div>`).join('');
  const S = [['camps', 'اردوها'], ['attended', 'شرکت‌کرده'], ['registrations', 'ثبت‌نام‌ها'], ['activities', 'فعالیت‌ها'], ['responsibilities', 'مسئولیت‌ها'], ['courses', 'دوره‌ها'], ['books', 'کتاب‌ها'], ['achievements', 'دستاوردها']];
  root.innerHTML = `<div class="pv-head"><div class="pv-av">${av}</div><div class="pv-hi"><h1>خوش آمدید، ${E(h.first_name || h.full_name)}</h1>
      <div class="pv-chips"><span class="pv-chip">${E(h.role_title)}</span>${PV.badge(h.status, h.status_label)}</div>
      <div class="pv-meta"><span>تکمیل اطلاعات پروفایل: ${E(d.completeness)}</span>${d.unread ? `<span>${d.unread.toLocaleString('fa-IR')} اعلان خوانده‌نشده</span>` : ''}</div></div>
      <div class="pv-acts"><a class="btn sm" href="${E(U.profile())}">مشاهده‌ی پروفایل کامل</a><button class="btn alt sm" id="lo" type="button">خروج</button></div></div>
    ${(() => { const [a, b] = String(d.completeness).split('/').map(Number); return b && a < b ? `<div class="pv-warn" role="status">پروفایل شما کامل نیست (${E(d.completeness)}). <a href="${E(U.completeProfile())}">تکمیل پروفایل</a></div>` : ''; })()}
    <div class="pv-stats">${S.map(([k, l]) => `<div class="pv-stat"><b>${(+st[k]).toLocaleString('fa-IR')}</b><span>${l}</span></div>`).join('')}</div>
    <div class="db-short"><a href="${E(U.profile())}">پروفایل من</a><a href="${E(U.camps())}">اردوها</a><a href="${E(P('camps'))}">ثبت‌نام‌های من</a><a href="${E(P('notifications'))}">اعلان‌ها</a>${m.member.panel ? '<a href="/admin/">پنل مدیریت</a>' : ''}</div>
    <div class="db-grid">
      ${card('اردوهای آینده', [P('camps'), 'همه'], up || empty('اردوی پیش‌رویی ندارید'))}
      ${card('ثبت‌نام‌های اخیر', [P('camps'), 'همه'], regs || empty('هنوز ثبت‌نامی ندارید'))}
      ${card('اعلان‌های جدید', [P('notifications'), 'همه'], nt || empty('اعلان جدیدی نیست'))}
      ${card('مسئولیت فعلی', [P('responsibilities'), 'جزئیات'], rs || empty('مسئولیت فعلی ندارید'))}
      ${card('فعالیت‌های اخیر', [P('activities'), 'همه'], ac || empty('فعالیتی ثبت نشده است'))}
      ${card('اردوهای در حال ثبت‌نام', [U.camps(), 'همه'], op || empty('اردوی بازی برای ثبت‌نام نیست'))}
    </div>`;
  byId('lo').onclick = async () => { await api('/member.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': d.csrf }, body: JSON.stringify({ op: 'logout' }) }); location.href = U.home(); };
});
