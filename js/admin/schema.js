/**
 * schema.js — تعریف فرم و ستون‌های هر بخش و منوی کناری
 * بخش‌های جدید با افزودن یک ورودی به SCHEMA بدون تغییر PHP/HTML اضافه می‌شوند.
 * نوع‌های فیلد: text | area | number | date | check | sel | img | ref | password
 */
const T = (n, l, t = 'text', o) => ({ n, l, t, o });
const PUB = T('published', 'منتشر شود', 'check');

// برچسب فارسی مقادیر (value → label)
const LBL = {
  camp_status: { upcoming: 'به‌زودی', running: 'در حال برگزاری', done: 'برگزار شده', cancelled: 'لغو شده' },
  registration_status: { open: 'ثبت‌نام فعال', soon: 'به‌زودی', full: 'تکمیل ظرفیت', done: 'برگزار شده', cancelled: 'لغو شده' },
  field_type: { text: 'متن کوتاه', textarea: 'متن بلند', number: 'عدد', national_id: 'کد ملی', phone: 'تلفن', date: 'تاریخ', image: 'آپلود تصویر', file: 'آپلود فایل', select: 'لیست کشویی', radio: 'چندگزینه‌ای', checkbox: 'چک‌باکس', yesno: 'بله/خیر' },
  role: { admin: 'مدیر (Admin)', editor: 'ویرایشگر (Editor)' },
  media_type: { image: 'تصویر', video: 'ویدئو', document: 'سند', file: 'فایل' },
  body: { kanoon: 'کانون', heyat: 'هیئت' },
  reg_status: { new: 'جدید', reviewed: 'بررسی شده', approved: 'تأیید شده', rejected: 'رد شده', cancelled: 'لغو شده' },
};

const SCHEMA = {
  camps: { t: 'اردوها', cols: ['title', 'slug', 'camp_status', 'registration_status', 'published'], inline: 'registration_status', slugFrom: 'camp',
    f: [T('title', 'عنوان'), T('slug', 'نامک (slug) — فقط انگلیسی، مثل mashhad-1403'), T('short_desc', 'توضیح کوتاه', 'area'), T('full_desc', 'توضیح کامل', 'area'),
      T('cover', 'تصویر کاور', 'img'), T('start_date', 'تاریخ شروع', 'date'), T('end_date', 'تاریخ پایان', 'date'), T('location', 'مکان'), T('capacity', 'ظرفیت', 'number'),
      T('cost', 'هزینه'), T('conditions', 'شرایط شرکت', 'area'), T('schedule', 'برنامه', 'area'), T('facilities', 'امکانات', 'area'), T('manager', 'مدیر اردو'),
      T('phone', 'شماره‌ی تماس'), T('deadline', 'مهلت ثبت‌نام', 'date'), T('camp_status', 'وضعیت اردو', 'sel', LBL.camp_status),
      T('registration_status', 'وضعیت ثبت‌نام', 'sel', LBL.registration_status), T('block_duplicates', 'جلوگیری از ثبت‌نام تکراری با کد ملی', 'check'),
      T('photo_required', 'عکس دانش‌آموز الزامی باشد', 'check'), T('map_url', 'لینک نقشه/مسیریابی (نشان، بلد، گوگل‌مپ یا OSM)'), PUB] },
  albums: { t: 'آلبوم‌ها', cols: ['title', 'slug', 'album_date', 'published'], slugFrom: 'album',
    actions: [['رسانه‌ها', r => { location.hash = '#albums/' + r.id; }]],
    f: [T('title', 'عنوان'), T('slug', 'نامک (انگلیسی)'), T('description', 'توضیح', 'area'), T('album_date', 'تاریخ', 'date'), T('cover', 'کاور', 'img'), PUB] },
  news: { t: 'اخبار', cols: ['title', 'slug', 'author', 'published'], slugFrom: 'news',
    f: [T('title', 'عنوان'), T('slug', 'نامک (انگلیسی)'), T('summary', 'خلاصه', 'area'), T('content', 'متن', 'area'), T('cover', 'کاور', 'img'), T('author', 'نویسنده'), PUB] },
  pages: { t: 'صفحات عمومی', cols: ['title', 'slug', 'published'], slugFrom: 'page',
    f: [T('title', 'عنوان'), T('slug', 'نامک (انگلیسی)'), T('content', 'محتوا', 'area'), PUB] },
  contacts: { t: 'اطلاعات تماس', cols: ['label', 'value', 'kind', 'enabled'],
    f: [T('label', 'عنوان (مثلاً تلفن)'), T('value', 'مقدار'), T('kind', 'نوع', 'sel', { phone: 'تلفن', mobile: 'موبایل', email: 'ایمیل', address: 'نشانی', hours: 'ساعات پاسخگویی', social: 'شبکه‌ی اجتماعی', other: 'سایر' }), T('enabled', 'فعال', 'check'), T('sort_order', 'ترتیب', 'number')] },
  channels: { t: 'کانال‌ها و شبکه‌های اجتماعی', cols: ['title', 'url', 'icon', 'enabled'],
    f: [T('title', 'عنوان'), T('url', 'لینک'), T('icon', 'آیکن (نام یا ایموجی)'), T('enabled', 'فعال', 'check'), T('sort_order', 'ترتیب', 'number')] },
  menu: { t: 'منوی اصلی', cols: ['title', 'link', 'enabled'],
    f: [T('title', 'عنوان'), T('link', 'لینک (مثل /camps)'), T('enabled', 'فعال', 'check'), T('sort_order', 'ترتیب', 'number')] },
  footer_links: { t: 'پیوندهای فوتر', cols: ['title', 'link', 'enabled'],
    f: [T('title', 'عنوان'), T('link', 'لینک'), T('enabled', 'فعال', 'check'), T('sort_order', 'ترتیب', 'number')] },
  media: { t: 'رسانه‌ها', cols: ['title', 'type', 'path', 'album_id'],
    f: [T('title', 'عنوان'), T('album_id', 'آلبوم', 'ref', ['albums', 'title']), T('sort_order', 'ترتیب', 'number'), T('featured', 'منتخب (نمایش در صفحه‌ی اصلی)', 'check')] },
  responsibility_types: { t: 'انواع مسئولیت (کانون / هیئت)', cols: ['body', 'title', 'enabled'],
    f: [T('body', 'مربوط به', 'sel', LBL.body), T('title', 'عنوان مسئولیت'), T('enabled', 'فعال', 'check'), T('sort_order', 'ترتیب', 'number')] },
  users: { t: 'ویرایشگرهای پنل (Editor)', cols: ['username', 'role', 'active'],
    actions: [['اردوهای مجاز', r => VIEWS.__access(r), r => r.role === 'editor']],
    f: [T('username', 'نام کاربری'), T('password', 'گذرواژه (حداقل ۸ کاراکتر؛ خالی = بدون تغییر)', 'password'), T('active', 'فعال', 'check')] },
  logs: { t: 'لاگ فعالیت‌ها', ro: 1, cols: ['created_at', 'user_id', 'action', 'detail', 'ip'], f: [] },
};

// منوی پنل: گروه‌ها و آیتم‌ها به‌ترتیب نمایش. هر آیتم = [کلید، عنوان، بخش دسترسی]
const NAV_GROUPS = [
  ['نمای کلی', [
    ['dashboard', 'داشبورد', 'registrations'],
    ['reports', 'گزارش‌ها', 'registrations'],
  ]],
  ['اردوها و ثبت‌نام', [
    ['camps', 'اردوها', 'camps'],
    ['forms', 'فرم‌های ثبت‌نام', 'forms'],
    ['registrations', 'ثبت‌نام‌ها', 'registrations'],
  ]],
  ['اعضا', [
    ['profiles', 'کاربران / پروفایل‌ها', 'profiles'],
    ['responsibility_types', 'انواع مسئولیت', 'profiles_manage'],
  ]],
  ['رسانه', [
    ['albums', 'آلبوم‌ها', 'albums'],
    ['images', 'تصاویر', 'media'],
    ['media', 'فایل‌ها (ویدئو و سند)', 'media'],
    ['testimonials', 'نظرات شرکت‌کنندگان', 'testimonials'],
  ]],
  ['محتوای سایت', [
    ['news', 'اخبار', 'news'],
    ['pages', 'صفحات', 'pages'],
    ['about', 'درباره کانون', 'pages'],
  ]],
  ['ظاهر و ارتباط', [
    ['menu', 'منوی سایت', 'menu'],
    ['footer_links', 'فوتر', 'footer'],
    ['channels', 'کانال‌ها', 'channels'],
    ['contacts', 'اطلاعات تماس', 'contacts'],
  ]],
  ['تنظیمات', [
    ['settings', 'تنظیمات سایت', 'settings'],
    ['seo', 'SEO', 'seo'],
  ]],
  ['سیستم', [
    ['users', 'ویرایشگرهای پنل', 'users'],
    ['logs', 'لاگ فعالیت‌ها', 'logs'],
    ['backup', 'Backup', 'backup'],
  ]],
];
const NAV = NAV_GROUPS.flatMap(g => g[1]); // فهرست تخت برای جست‌وجوی عنوان/دسترسی
const VIEWS = {}; // ثبت نمای هر بخش (ماژول‌های جدا)
