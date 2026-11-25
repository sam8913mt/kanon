# کانون نخبه‌پروری رویش (royesh)

وب‌سایت و پنل مدیریت کانون — PHP 8 + MySQL + JavaScript خالص (بدون وابستگی ساخت).

## راه‌اندازی
1. `backend/config/config.example.php` را به `config.php` کپی و دیتابیس، `site_url` و `admin.password_hash` را پر کنید (فایل config در گیت نادیده گرفته می‌شود).
2. `database/royesh-all.sql` را در MySQL Import کنید.
3. پوشه‌ی پروژه را روی Apache (mod_rewrite) بگذارید؛ `uploads` و `backend/storage` قابل‌نوشتن باشند.
4. راهنمای کامل: `DEPLOY.md` (هاست) · تغییرات: `CHANGELOG.md`.

هرگز `config.php`، رمزها یا کلید API پیامک را commit نکنید.
