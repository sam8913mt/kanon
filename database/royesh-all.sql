-- ============================================================
-- کانون نخبه‌پروری رویش — royesh all (فایل کامل و یکپارچه) — MySQL 5.7+/8 یا MariaDB 10.3+
-- phpMyAdmin ← Import ← همین فایل. دیتابیس «royesh» خودکار ساخته می‌شود (در هاست واقعی خطوط CREATE DATABASE/USE را حذف و دیتابیس خود را انتخاب کنید).
-- فقط برای دیتابیس تازه و خالی است. ادمین: در backend/config/config.php (admin.username و admin.password_hash).
-- ============================================================
CREATE DATABASE IF NOT EXISTS `royesh` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `royesh`;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول مرجع نقش‌ها. نقش‌های پنل: admin / editor؛ نقش کاربر عمومی: member. بررسی دسترسی در backend/includes/permissions.php انجام می‌شود.
CREATE TABLE roles (
  name VARCHAR(20) PRIMARY KEY,
  title VARCHAR(60) NOT NULL,
  description VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  k VARCHAR(80) PRIMARY KEY,
  v TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE camps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  title VARCHAR(200) NOT NULL,
  short_desc TEXT,
  full_desc MEDIUMTEXT,
  cover VARCHAR(255),
  start_date DATE,
  end_date DATE,
  location VARCHAR(160),
  capacity INT DEFAULT 0,
  cost VARCHAR(80),
  conditions TEXT,
  schedule TEXT,
  facilities TEXT,
  manager VARCHAR(120),
  phone VARCHAR(30),
  deadline DATE,
  camp_status ENUM('upcoming','running','done','cancelled') DEFAULT 'upcoming',
  registration_status ENUM('open','soon','full','done','cancelled') DEFAULT 'soon',
  block_duplicates TINYINT(1) DEFAULT 1,
  photo_required TINYINT(1) NOT NULL DEFAULT 1,
  map_url VARCHAR(255) NULL,
  published TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (published, registration_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- تنظیمات فرم هر اردو (عنوان، توضیح بالای فرم، فعال/غیرفعال)
CREATE TABLE camp_forms (
  camp_id INT PRIMARY KEY,
  title VARCHAR(200),
  intro TEXT,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- اردوهای مجاز هر Editor
CREATE TABLE camp_editors (
  camp_id INT NOT NULL,
  user_id INT NOT NULL,
  PRIMARY KEY (camp_id, user_id),
  FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE form_fields (
  id INT AUTO_INCREMENT PRIMARY KEY,
  camp_id INT NOT NULL,
  name VARCHAR(60) NOT NULL,
  label VARCHAR(200) NOT NULL,
  type ENUM('text','textarea','number','national_id','phone','date','image','file','select','radio','checkbox','yesno') NOT NULL,
  required TINYINT(1) DEFAULT 0,
  enabled TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  UNIQUE KEY uq_camp_name (camp_id, name),
  FOREIGN KEY (camp_id) REFERENCES camps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE form_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  field_id INT NOT NULL,
  label VARCHAR(200) NOT NULL,
  enabled TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (field_id) REFERENCES form_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- حساب کاربران عمومی (فقط داده‌ی احراز هویت و وضعیت حساب). اطلاعات شخصی در member_profiles است.
-- حذف کاربر = بایگانی (status=archived + deleted_at)؛ هیچ‌گاه سوابق ثبت‌نام/فعالیت حذف نمی‌شود.
CREATE TABLE members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mobile CHAR(11) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'member',
  staff_user_id INT NULL UNIQUE,
  created_by_staff VARCHAR(60) NULL,
  status ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
  password_changed_at DATETIME NULL,
  last_login_at DATETIME NULL,
  last_activity_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (status),
  FOREIGN KEY (staff_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- پروفایل (۱ به ۱ با members)
CREATE TABLE member_profiles (
  member_id INT PRIMARY KEY,
  first_name VARCHAR(60) NOT NULL DEFAULT '',
  last_name VARCHAR(60) NOT NULL DEFAULT '',
  father_name VARCHAR(60) NULL,
  national_id CHAR(10) NULL,
  birth_date VARCHAR(20) NULL,
  gender ENUM('male','female') NULL,
  email VARCHAR(150) NULL,
  emergency_phone VARCHAR(20) NULL,
  address VARCHAR(300) NULL,
  neighborhood VARCHAR(80) NULL,
  city VARCHAR(80) NULL,
  bio TEXT NULL,
  avatar_doc_id INT NULL,
  member_rank VARCHAR(100) NULL,
  interests VARCHAR(500) NULL,
  is_top TINYINT(1) NOT NULL DEFAULT 0,
  top_order INT NOT NULL DEFAULT 0,
  internal_note TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (national_id), INDEX (last_name),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- اطلاعات تحصیلی با سابقه: تغییر پایه/رشته = ردیف جدید؛ ردیف قبلی is_current=0 می‌شود
CREATE TABLE member_education (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  school VARCHAR(150) NULL,
  grade VARCHAR(60) NULL,
  field_of_study VARCHAR(100) NULL,
  academic_year VARCHAR(20) NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 1,
  started_at DATE NULL,
  ended_at DATE NULL,
  note VARCHAR(500) NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (member_id, is_current),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- فایل‌های شخصی (در backend/storage/private؛ فقط با /api/profile/file.php و کنترل دسترسی)
CREATE TABLE member_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  category ENUM('avatar','registration','certificate','training','activity','other') NOT NULL DEFAULT 'other',
  title VARCHAR(200) NOT NULL DEFAULT '',
  path VARCHAR(255) NOT NULL,
  mime VARCHAR(60) NOT NULL,
  size INT NOT NULL DEFAULT 0,
  visibility ENUM('private','admin') NOT NULL DEFAULT 'private',
  uploaded_by ENUM('member','admin') NOT NULL DEFAULT 'member',
  archived_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (member_id, category),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- انواع مسئولیت (قابل مدیریت توسط Admin) — «کانون» و «هیئت» جدا هستند
CREATE TABLE responsibility_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  body ENUM('kanoon','heyat') NOT NULL,
  title VARCHAR(100) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_body_title (body, title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- مسئولیت‌ها و سوابق (title نسخه‌ی ثابت عنوان است تا با تغییر نوع، سابقه تغییر نکند)
CREATE TABLE member_responsibilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  body ENUM('kanoon','heyat') NOT NULL,
  type_id INT NULL,
  title VARCHAR(100) NOT NULL,
  unit VARCHAR(120) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('active','ended','inactive') NOT NULL DEFAULT 'active',
  description TEXT NULL,
  created_by INT NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (member_id, body, status),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (type_id) REFERENCES responsibility_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  kind VARCHAR(30) NOT NULL DEFAULT 'other',
  title VARCHAR(200) NOT NULL,
  activity_date DATE NULL,
  description TEXT NULL,
  status ENUM('planned','done','cancelled') NOT NULL DEFAULT 'done',
  document_id INT NULL,
  created_by INT NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (member_id, activity_date),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  organizer VARCHAR(150) NULL,
  course_date DATE NULL,
  status ENUM('registered','in_progress','completed','failed','cancelled') NOT NULL DEFAULT 'completed',
  result VARCHAR(100) NULL,
  document_id INT NULL,
  created_by INT NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (member_id, course_date),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- دستاورد: ثبت توسط Admin = تأییدشده؛ درخواست کاربر = pending تا بررسی Admin
CREATE TABLE member_achievements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  kind VARCHAR(40) NOT NULL DEFAULT 'other',
  achieved_at DATE NULL,
  description TEXT NULL,
  document_id INT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  submitted_by ENUM('member','admin') NOT NULL DEFAULT 'admin',
  created_by INT NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (member_id, status),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  type VARCHAR(30) NOT NULL,
  title VARCHAR(200) NOT NULL,
  body VARCHAR(500) NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (member_id, is_read, id),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- رویدادهای Timeline (ثبت‌نام، تأیید، شرکت، مسئولیت جدید، ...)
CREATE TABLE member_timeline (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  event_type VARCHAR(30) NOT NULL,
  title VARCHAR(255) NOT NULL,
  ref_type VARCHAR(30) NULL,
  ref_id INT NULL,
  occurred_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (member_id, occurred_at),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- نشست‌های کاربر (فقط هش شناسه‌ی نشست ذخیره می‌شود) برای نمایش نشست‌های فعال و خروج از نشست‌های دیگر
CREATE TABLE member_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  sid_hash CHAR(64) NOT NULL UNIQUE,
  device VARCHAR(80) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  INDEX (member_id, revoked_at),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  camp_id INT NOT NULL,
  member_id INT NULL,
  national_id CHAR(10),
  photo VARCHAR(255),
  status ENUM('new','reviewed','approved','rejected','cancelled') DEFAULT 'new',
  attendance ENUM('unknown','attended','absent') NOT NULL DEFAULT 'unknown',
  review_note TEXT NULL,
  note_visible TINYINT(1) NOT NULL DEFAULT 0,
  reviewed_at DATETIME NULL,
  tracking_code CHAR(12) NULL,
  ip VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tracking (tracking_code),
  INDEX (camp_id, national_id),
  INDEX (ip, created_at),
  INDEX (member_id),
  FOREIGN KEY (camp_id) REFERENCES camps(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- field_id عمداً بدون FK است تا حذف سؤال، پاسخ‌های قبلی را پاک نکند (برچسب سؤال هم ذخیره می‌شود)
CREATE TABLE registration_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  registration_id INT NOT NULL,
  field_id INT NULL,
  field_label VARCHAR(200) NOT NULL,
  field_name VARCHAR(60) NULL,   -- Snapshot نام/نوع سؤال هنگام ثبت؛ با حذف یا تغییر سؤال، پاسخ تاریخی قابل فهم می‌ماند
  field_type VARCHAR(20) NULL,
  answer TEXT,
  INDEX (registration_id),
  INDEX (field_id),
  FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE albums (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) UNIQUE,
  title VARCHAR(200),
  description TEXT,
  album_date DATE,
  cover VARCHAR(255),
  published TINYINT(1) DEFAULT 1,
  created_by INT NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- آلبوم‌های مجاز هر Editor (مشابه camp_editors). سازنده‌ی آلبوم خودکار اضافه می‌شود؛ Admin در «کاربران ← دسترسی» تغییر می‌دهد.
CREATE TABLE album_editors (
  album_id INT NOT NULL,
  user_id INT NOT NULL,
  PRIMARY KEY (album_id, user_id),
  FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- رسانه: تصویر، ویدئو (فایل آپلودی یا آدرس خارجی)، سند و فایل. path برای فایل‌های آپلودی (یا آدرس مستقیم mp4/webm)، url آدرس اصلی ویدئوی خارجی.
CREATE TABLE media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  album_id INT NULL,
  type ENUM('image','video','document','file') NOT NULL DEFAULT 'image',
  path VARCHAR(500),
  url VARCHAR(500) NULL,
  mime VARCHAR(100) NULL,
  title VARCHAR(200),
  size INT,
  sort_order INT DEFAULT 0,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (album_id, sort_order),
  INDEX (type),
  INDEX (created_by),
  FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) UNIQUE,
  title VARCHAR(200),
  content MEDIUMTEXT,
  published TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE news (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) UNIQUE,
  title VARCHAR(200),
  summary TEXT,
  content MEDIUMTEXT,
  cover VARCHAR(255),
  author VARCHAR(100),
  published TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(100),
  value VARCHAR(255),
  kind VARCHAR(30),
  enabled TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- نظرات شرکت‌کنندگان (اسلایدر صفحه‌ی اصلی)
CREATE TABLE testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  role VARCHAR(120) NULL,
  body TEXT NOT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  approved TINYINT(1) NOT NULL DEFAULT 0,   -- تأیید مدیر در پنل
  enabled TINYINT(1) NOT NULL DEFAULT 1,    -- نمایش/مخفی
  sort_order INT NOT NULL DEFAULT 0,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE channels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120),
  url VARCHAR(255),
  icon VARCHAR(60),
  enabled TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE menu (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  link VARCHAR(255) NOT NULL,
  enabled TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE footer_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  link VARCHAR(255) NOT NULL,
  enabled TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- entity: global | page | camp | album | news  — برای global مقدار entity_id صفر است
CREATE TABLE seo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity VARCHAR(30) NOT NULL,
  entity_id INT NOT NULL DEFAULT 0,
  seo_title VARCHAR(200),
  meta_description VARCHAR(320),
  canonical VARCHAR(255),
  og_title VARCHAR(200),
  og_description VARCHAR(320),
  og_image VARCHAR(255),
  schema_json TEXT,
  UNIQUE KEY uq_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  member_id INT NULL,
  action VARCHAR(60),
  detail TEXT,
  ip VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (action, ip, created_at),
  INDEX (member_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ===================== داده‌ی اولیه (همه از پنل قابل ویرایش‌اند) =====================
INSERT INTO roles(name,title,description) VALUES
 ('admin','مدیر','دسترسی کامل'),
 ('editor','ویرایشگر','ثبت‌نام‌ها، اردوهای مجاز و فرم آن‌ها، آلبوم و رسانه، مشاهده‌ی محدود (Mask) پروفایل ثبت‌نام‌کنندگان اردوهای مجاز'),
 ('member','عضو','کاربر عمومی با پروفایل شخصی');

INSERT INTO responsibility_types(body,title,sort_order) VALUES
 ('kanoon','مربی',1),('kanoon','ارشد',2),('kanoon','سرگروه',3),('kanoon','رابط',4),('kanoon','فرهنگی',5),
 ('heyat','دبیر کل',1),('heyat','معاونت راهبر',2),('heyat','مسئول هیئت',3),('heyat','اجرایی هیئت',4),('heyat','پذیرایی',5);

INSERT INTO channels(title,url,sort_order) VALUES
 ('کانال ایتا ۱','https://eitaa.com/EmamRaoof888',1),
 ('کانال ایتا ۲','https://eitaa.com/safarbeansoyedarya',2);

INSERT INTO settings(k,v) VALUES
 ('site_name','کانون نخبه‌پروری رویش'),
 ('hero_title','جایی برای کشف و رشد استعدادها'),
 ('hero_text','کانون نخبه‌پروری رویش؛ اردوهای علمی و آموزشی برای دانش‌آموزان مستعد'),
 ('footer_text','وب‌سایت رسمی کانون نخبه‌پروری رویش'),
 ('copyright','© کانون نخبه‌پروری رویش — همه‌ی حقوق محفوظ است'),
 ('home_stats',''),
 ('activities',''),
 ('registration_requires_login','0');

INSERT INTO pages(slug,title,content) VALUES
 ('about','درباره کانون','متن معرفی کانون را از پنل (صفحات) ویرایش کنید.'),
 ('rules','قوانین',''),
 ('privacy','حریم خصوصی',''),
 ('terms','شرایط استفاده','');

INSERT INTO contacts(label,value,kind,enabled,sort_order) VALUES
 ('تلفن','','phone',1,1),('موبایل','','mobile',1,2),('ایمیل','','email',1,3),
 ('نشانی','','address',1,4),('ساعات پاسخگویی','','hours',1,5);

INSERT INTO menu(title,link,sort_order) VALUES
 ('اردوها','/camps',1),('آلبوم‌ها','/albums',2),('اخبار','/news',3),
 ('درباره کانون','/about',4),('کانال‌ها','/channels',5),('تماس','/contact',6);

INSERT INTO footer_links(title,link,sort_order) VALUES
 ('درباره کانون','/about',1),('اردوها','/camps',2),('تماس','/contact',3);

-- اردوی نمونه (منتشر نشده) با فرم اولیه: نام، نام خانوادگی، کد ملی، پایه (هفتم/هشتم/نهم)، محله (سی‌متری/مهرآباد/استاد معین) + عکس دانش‌آموز
-- (برای اردوهای جدیدی که از پنل ساخته می‌شوند همین فرم اولیه خودکار ساخته می‌شود: backend/includes/form_defaults.php)
INSERT INTO camps(slug,title,short_desc,published) VALUES('sample-camp','اردوی نمونه (ویرایش یا حذف کنید)','نمونه‌ی اولیه',0);
SET @c = LAST_INSERT_ID();
INSERT INTO camp_forms(camp_id,title,intro,enabled) VALUES(@c,'فرم ثبت‌نام','',1);
INSERT INTO form_fields(camp_id,name,label,type,required,sort_order) VALUES
 (@c,'first_name','نام','text',1,1),(@c,'last_name','نام خانوادگی','text',1,2),
 (@c,'national_id','کد ملی','national_id',1,3),(@c,'grade','پایه','select',1,4),(@c,'area','محله','select',1,5);
SET @g = (SELECT id FROM form_fields WHERE camp_id=@c AND name='grade');
SET @a = (SELECT id FROM form_fields WHERE camp_id=@c AND name='area');
INSERT INTO form_options(field_id,label,sort_order) VALUES
 (@g,'هفتم',1),(@g,'هشتم',2),(@g,'نهم',3),(@a,'سی‌متری',1),(@a,'مهرآباد',2),(@a,'استاد معین',3);

-- ادمین در جدول users درج نمی‌شود؛ ورود ادمین فقط از config.php است.
