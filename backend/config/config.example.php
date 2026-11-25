<?php
/**
 * تنظیمات پروژه — این فایل از وب قابل دسترس نیست (backend/config/.htaccess).
 * نسخه‌ی آماده‌ی هاست (cPanel): قبل از استفاده، سه مقدار «دیتابیس»، «site_url» و «admin» را پر کنید.
 */
return [
    // پایگاه‌داده MySQL — در cPanel نام دیتابیس و کاربر معمولاً با پیشوند حساب شروع می‌شود (مثل cpuser_royesh)
    'db' => ['host' => 'localhost', 'name' => 'CPANELUSER_royesh', 'user' => 'CPANELUSER_royesh', 'pass' => 'CHANGE_ME_DB_PASSWORD'],

    // آدرس سایت بدون اسلش انتهایی (برای sitemap، canonical و ...)
    'site_url' => 'https://nokhbeparvari.ir',

    // در Production حتماً false باشد (خطاها فقط در backend/storage/error.log ثبت می‌شوند)
    'debug' => false,

    // آپلود
    'upload' => [
        'dir'             => __DIR__ . '/../../uploads',
        'max_bytes'       => 3 * 1024 * 1024,      // حداکثر حجم تصویر/PDF
        'video_max_bytes' => 50 * 1024 * 1024,     // حداکثر حجم ویدئو؛ upload_max_filesize و post_max_size در php.ini هم باید حداقل همین‌قدر باشند
        'file_max_bytes'  => 10 * 1024 * 1024,     // حداکثر حجم سند/فایل
        'max_dimension'   => 1920,
        'profile_dimension' => 600,
        'mimes'           => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
    ],

    // ورود به پنل مدیریت (فقط ادمین؛ فقط از همین‌جا)
    // - username: نام کاربری ادمین (حروف انگلیسی/عدد/_ . - ، ۳ تا ۶۰ نویسه)
    // - password_hash: خروجی password_hash() — گذرواژه‌ی خام اینجا نگذارید. مقدار را داخل کوتیشن تکی بگذارید.
    //   ساخت هش (روی کامپیوتر خودتان با XAMPP یا در ترمینال cPanel):  php -r "echo password_hash('رمز-دلخواه', PASSWORD_DEFAULT);"
    // - تا وقتی password_hash خالی باشد هیچ‌کس نمی‌تواند به‌عنوان ادمین وارد شود.
    // - با تغییر نام کاربری یا هش، نشست‌های ادمین قبلی هم بلافاصله باطل می‌شوند.
    'admin' => [
        'username'      => 'admin',
        'password_hash' => '',
    ],

    // نشست و امنیت
    'session' => ['lifetime' => 3600],
    'rate_limit_per_hour' => 20,
];
