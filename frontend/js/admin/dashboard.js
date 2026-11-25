/** dashboard.js — داشبورد: کارت‌ها، نمودار ۱۴ روزه، ظرفیت اردوها، فعالیت اخیر، وضعیت سیستم */
VIEWS.dashboard = async () => {
  const s = (await call('stats.php')).data;
  if (!s) return;
  const L = { camps: 'اردوها', open_camps: 'اردوهای فعال', registrations: 'ثبت‌نام‌ها', new: 'ثبت‌نام‌های جدید', today: 'ثبت‌نام امروز', albums: 'آلبوم‌ها', media: 'رسانه‌ها' };
  if (A.me.role === 'media') {
    $('#c').innerHTML = `<div class="cards">${['albums', 'media'].map(k => `<div class="card"><b>${s[k] ?? 0}</b>${L[k]}</div>`).join('')}</div>
      <p class="hint">از منوی «رسانه»: آلبوم‌ها، تصاویر، فایل‌ها و هیئت‌ها را مدیریت کنید؛ در «هیئت‌ها» دکمه‌ی «ارسال پیامک» پیام هیئت را برای اعضا می‌فرستد.</p>`;
    return;
  }
  if (isAdmin()) { L.members = 'کاربران/پروفایل‌ها'; L.users = 'کاربران پنل'; }
  const mx = Math.max(1, ...s.daily.map(x => +x.n)), w = 30;
  const chart = s.daily.length ? `<svg viewBox="0 0 ${s.daily.length * w + 10} 110" role="img" aria-label="ثبت‌نام‌های ۱۴ روز اخیر" class="chart">${s.daily.map((x, i) => {
    const h = Math.round(80 * x.n / mx);
    return `<rect x="${i * w + 8}" y="${90 - h}" width="22" height="${h}" rx="3" fill="var(--plum)"><title>${esc(jd(x.d))}: ${x.n}</title></rect><text x="${i * w + 19}" y="105" font-size="8" text-anchor="middle" fill="currentColor">${esc(jd(x.d).slice(-2))}</text>`;
  }).join('')}</svg>` : '<p>هنوز ثبت‌نامی نیست.</p>';
  const sys = s.system, chk = (ok, t, warn) => `<li class="${ok ? 'ok' : (warn ? 'warn' : 'bad')}">${ok ? '✔' : (warn ? '⚠' : '✖')} ${t}</li>`;
  $('#c').innerHTML = `<div class="cards">${Object.keys(L).map(k => `<div class="card"><b>${s[k] ?? 0}</b>${L[k]}</div>`).join('')}</div>
    <h3>ثبت‌نام‌های ۱۴ روز اخیر</h3>${chart}
    <h3>پر شدن ظرفیت اردوها</h3>${s.per_camp.map(x => { const p = x.capacity > 0 ? Math.min(100, Math.round(100 * x.n / x.capacity)) : 0; return `<p>${esc(x.title)} — ${x.n}/${x.capacity}<br><progress max="100" value="${p}"></progress></p>`; }).join('') || '<p>اردویی نیست.</p>'}
    ${s.recent.length ? `<h3>فعالیت اخیر</h3><div class="tw"><table>${s.recent.map(x => `<tr><td>${esc(jd(x.created_at, true))}</td><td>${esc(x.action)}</td><td>${esc(x.detail)}</td></tr>`).join('')}</table></div>` : ''}
    ${sys ? `<h3>وضعیت سیستم</h3><ul class="sys">${chk(true, 'نسخه‌ی رویش ' + esc(sys.version || '—') + ' — PHP ' + esc(sys.php) + ' — MySQL ' + esc(sys.mysql))}${chk(sys.uploads_ok, 'پوشه‌ی uploads قابل نوشتن است')}${chk(sys.storage_ok, 'پوشه‌ی backend/storage قابل نوشتن است')}${chk(sys.private_ok, 'پوشه‌ی backend/storage/private (فایل‌های خصوصی ثبت‌نام و پروفایل) قابل نوشتن است')}
      ${chk(sys.gd, 'افزونه‌ی GD (بهینه‌سازی تصاویر)', true)}${chk(sys.zip, 'افزونه‌ی ZipArchive (Backup فایل‌ها و Excel)')}${chk(sys.https, 'اتصال HTTPS', true)}${chk(!sys.debug, 'حالت debug خاموش است')}
      <li>فضای آزاد دیسک: ${sys.disk_free_mb} MB — محدودیت آپلود PHP: ${esc(sys.upload_max)} / POST: ${esc(sys.post_max)}</li></ul>` : ''}`;
};
