-- ============================================================
-- royesh-update.sql — تغییرات دیتابیس از نسخه‌ی 3.0.0 به بعد
-- هر نسخه‌ی جدید که دیتابیس را تغییر دهد، بخش خودش به انتهای این فایل اضافه می‌شود.
-- این فایل را (در phpMyAdmin ← تب SQL، روی دیتابیس royesh) می‌توان چندبار اجرا کرد؛ بخش‌های انجام‌شده نادیده گرفته می‌شوند.
-- نیازمند MariaDB (XAMPP) — دستورهای IF NOT EXISTS
-- ============================================================

-- ───── 3.0.0 ─────
-- لینک نقشه/مسیریابی اردو
ALTER TABLE camps ADD COLUMN IF NOT EXISTS map_url VARCHAR(255) NULL AFTER photo_required;

-- نظرات شرکت‌کنندگان
CREATE TABLE IF NOT EXISTS testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  role VARCHAR(120) NULL,
  body TEXT NOT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ───── 3.2.0 ─────
-- تأیید نظرها در پنل، ثبت نظر توسط بازدیدکننده
ALTER TABLE testimonials ADD COLUMN IF NOT EXISTS approved TINYINT(1) NOT NULL DEFAULT 0 AFTER rating;
ALTER TABLE testimonials ADD COLUMN IF NOT EXISTS ip VARCHAR(45) NULL;
ALTER TABLE testimonials ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;
UPDATE testimonials SET approved=1 WHERE created_at IS NULL OR ip IS NULL; -- نظرهای قدیمی (ثبت‌شده توسط مدیر) تأییدشده حساب شوند

-- ───── 3.3.0 ─────
-- بدون تغییر دیتابیس
