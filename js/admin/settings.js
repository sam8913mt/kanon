/** settings.js — تنظیمات سایت (فرم ساختاریافته با گروه‌بندی؛ بدون نیاز به دانستن کلیدها) */
const SET_GROUPS = [
  ['عمومی', [['site_name', 'نام سایت'], ['site_title', 'عنوان سایت (SEO Title)'], ['site_description', 'توضیح سایت (Meta Description)', 'area']]],
  ['هویت بصری', [['logo', 'لوگو (خالی = لوگوی پیش‌فرض کانون)', 'img'], ['favicon', 'Favicon', 'img'], ['og_image', 'تصویر اشتراک‌گذاری (OG)', 'img'], ['color_navy', 'رنگ اصلی', 'color'], ['color_plum', 'رنگ دوم', 'color']]],
  ['صفحه‌ی اصلی', [['hero_title', 'عنوان Hero'], ['hero_text', 'متن Hero', 'area'],
    ['home_stats', 'آمار کانون — قالب «عنوان|عدد» با ; جدا شود. مثال: اردو|12;دانش‌آموز|480 (خالی = شمارش خودکار)'],
    ['activities', 'فعالیت‌های کانون — هر خط: «عنوان|توضیح» (خالی = بخش نمایش داده نمی‌شود)', 'area']]],
  ['ثبت‌نام اردو', [['registration_requires_login', 'ثبت‌نام اردو فقط برای کاربران واردشده (خاموش = مهمان هم با کد پیگیری می‌تواند ثبت‌نام کند)', 'bool']]],
  ['فوتر و تماس', [['footer_text', 'متن فوتر', 'area'], ['copyright', 'متن کپی‌رایت'], ['contact_description', 'توضیح صفحه‌ی تماس', 'area']]],
];
VIEWS.settings = async () => {
  const cur = Object.fromEntries(((await list('settings')).data || []).map(r => [r.k, r.v]));
  $('#c').innerHTML = `<form class="f wide" id="ff">${SET_GROUPS.map(([g, fs]) => `<fieldset class="fs"><legend>${g}</legend>${fs.map(([k, l, t]) => {
    const v = esc(cur[k] || ''), lab = `<label for="s_${k}">${l}</label>`;
    if (t === 'bool') return `<label class="op"><input type="checkbox" id="s_${k}" ${cur[k] === '1' ? 'checked' : ''}> ${l}</label>`;
    if (t === 'area') return lab + `<textarea id="s_${k}">${v}</textarea>`;
    if (t === 'img') return lab + `<div class="row"><input id="s_${k}" value="${v}" dir="ltr"><button type="button" class="btn alt sm" data-pk="s_${k}">انتخاب از رسانه‌ها</button></div>`;
    if (t === 'color') return lab + `<div class="row"><input type="color" id="s_${k}" value="${/^#[0-9a-f]{6}$/i.test(cur[k] || '') ? v : '#000000'}" style="width:70px;padding:2px"><label class="op"><input type="checkbox" id="u_${k}" ${cur[k] ? 'checked' : ''}> اعمال این رنگ</label></div>`;
    return lab + `<input id="s_${k}" value="${v}">`;
  }).join('')}</fieldset>`).join('')}<button class="btn">ذخیره‌ی تنظیمات</button></form>`;
  $('#ff').querySelectorAll('[data-pk]').forEach(b => b.onclick = () => pickImage($('#' + b.dataset.pk)));
  $('#ff').onsubmit = async e => {
    e.preventDefault();
    let ok = true;
    for (const [, fs] of SET_GROUPS) for (const [k, , t] of fs) {
      const val = t === 'bool' ? ($('#s_' + k).checked ? '1' : '0') : t === 'color' ? ($('#u_' + k).checked ? $('#s_' + k).value : '') : $('#s_' + k).value;
      if (val !== (cur[k] || '')) { const r = await save('settings', { k, v: val }); if (!r.success) { ok = false; toast(r.message, false); } else cur[k] = val; }
    }
    if (ok) toast('تنظیمات ذخیره شد');
  };
};
