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

-- ───── 3.4.0 ─────
-- بدون تغییر ساختار؛ (اجرای خودکار تغییرات این فایل هم در برنامه انجام می‌شود: backend/includes/database.php ← schema_ensure)

-- ───── 3.4.1 ─────
-- هیئت‌ها (عکس، کپشن، تاریخ/ساعت، مناسبت، شمارشگر) + پیامک یادآوری یک روز قبل
CREATE TABLE IF NOT EXISTS heyats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  caption TEXT NULL,
  image VARCHAR(255) NULL,
  event_date DATE NULL,
  event_time TIME NULL,
  occasion VARCHAR(150) NULL,
  published TINYINT(1) NOT NULL DEFAULT 0,
  sms_sent_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO menu(title,link,sort_order) SELECT 'هیئت‌ها','/heyats',2 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM menu WHERE link='/heyats');
INSERT IGNORE INTO settings(k,v) VALUES
 ('sms_enabled','0'),('sms_sender',''),('secret_sms_apikey',''),
 ('sms_heyat_text','یادآوری: فردا ساعت {time} هیئت «{title}» ({occasion}) برگزار می‌شود. کانون نخبه‌پروری رویش');

-- ورود با کد یکبار مصرف (پیامک)
CREATE TABLE IF NOT EXISTS login_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mobile CHAR(11) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT NOT NULL DEFAULT 0,
  used TINYINT(1) NOT NULL DEFAULT 0,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (mobile, id),
  INDEX (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO settings(k,v) VALUES
 ('sms_otp_enabled','0'),('sms_otp_ttl','2'),
 ('sms_otp_text','کد ورود شما به کانون نخبه‌پروری رویش: {code}');

-- رسیدهای پرداخت ثبت‌نام (کارت‌به‌کارت) — v3.5.0
CREATE TABLE IF NOT EXISTS payment_receipts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  registration_id INT NOT NULL,
  file VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(500) NULL,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  approved_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (registration_id),
  INDEX (status, created_at),
  FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO settings(k,v) VALUES ('secret_pay_card_number',''),('secret_pay_card_holder','');

-- کتاب‌های خوانده‌شده‌ی اعضا (ثبت توسط مدیر) — v3.5.0
CREATE TABLE IF NOT EXISTS member_books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  author VARCHAR(150) NULL,
  read_date DATE NULL,
  note VARCHAR(500) NULL,
  created_by INT NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (member_id, read_date),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ───── 3.5.3 ─────
-- نقش جدید «مدیر رسانه» (media) و امکان ساخت ادمین از پنل؛ اعضا با نقش پنل (members.staff_user_id)
ALTER TABLE users MODIFY role ENUM('admin','editor','media') NOT NULL DEFAULT 'editor';
INSERT IGNORE INTO roles(name,title,description) VALUES ('media','مدیر رسانه','آلبوم‌ها، تصاویر، فایل‌ها، هیئت‌ها و ارسال پیامک هیئت');
UPDATE roles SET description='همه‌ی بخش‌ها به‌جز تنظیمات سایت (SEO، صفحات و متن‌ها، منو، فوتر، کانال‌ها، اطلاعات تماس، پیامک و کد ورود، کاربران، لاگ، Backup)' WHERE name='editor';
