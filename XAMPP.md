# اجرا روی XAMPP
1. Apache و MySQL را در XAMPP روشن کنید؛ در Apache ماژول `mod_rewrite` و در `httpd.conf` برای پوشه‌ی پروژه `AllowOverride All` فعال باشد.
2. بهتر است پروژه ریشه‌ی یک VirtualHost باشد (مثل http://royesh.local) چون آدرس‌ها با `/` شروع می‌شوند. یا محتوا را مستقیم در `htdocs` بگذارید و با http://localhost باز کنید.
3. در phpMyAdmin دیتابیس `royesh` (utf8mb4) بسازید و `database/royesh-all.sql` را Import کنید.
4. `backend/config/config.php` از قبل برای root بدون رمز تنظیم شده است. هش رمز ادمین: `php -r "echo password_hash('رمز', PASSWORD_DEFAULT);"` را در `admin.password_hash` بگذارید (تا خالی است ورود ادمین ممکن نیست).
5. PHP 8.x با افزونه‌های pdo_mysql، gd (با webp) و fileinfo فعال باشد.
