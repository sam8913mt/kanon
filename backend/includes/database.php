<?php
/**
 * database.php — اتصال PDO و مدیریت استثناها
 * خطاهای دیتابیس هرگز به کاربر نمایش داده نمی‌شوند؛ فقط در storage/error.log ثبت می‌شوند.
 */

function db(): PDO
{
    global $CFG;
    static $pdo;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host={$CFG['db']['host']};dbname={$CFG['db']['name']};charset=utf8mb4",
            $CFG['db']['user'],
            $CFG['db']['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        schema_ensure($pdo);
    }
    return $pdo;
}

/** جدول هیئت‌ها (همگام با database/royesh-update.sql) */
const HEYATS_DDL = 'CREATE TABLE IF NOT EXISTS heyats (
    id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, caption TEXT NULL, image VARCHAR(255) NULL,
    event_date DATE NULL, event_time TIME NULL, occasion VARCHAR(150) NULL, published TINYINT(1) NOT NULL DEFAULT 0,
    sms_sent_at DATETIME NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

/** کدهای یکبار مصرف ورود (فقط هش ذخیره می‌شود) */
const LOGIN_CODES_DDL = 'CREATE TABLE IF NOT EXISTS login_codes (
    id INT AUTO_INCREMENT PRIMARY KEY, mobile CHAR(11) NOT NULL, code_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL, attempts TINYINT NOT NULL DEFAULT 0, used TINYINT(1) NOT NULL DEFAULT 0,
    ip VARCHAR(45) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX (mobile, id), INDEX (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

/** رسیدهای پرداخت ثبت‌نام (تصویر رسید در storage/private؛ فقط با /api/reg_file.php?r=1 و کنترل دسترسی) */
const RECEIPTS_DDL = 'CREATE TABLE IF NOT EXISTS payment_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY, registration_id INT NOT NULL, file VARCHAR(255) NOT NULL,
    status ENUM(\'pending\',\'approved\',\'rejected\') NOT NULL DEFAULT \'pending\', admin_note VARCHAR(500) NULL,
    archived TINYINT(1) NOT NULL DEFAULT 0, approved_at DATETIME NULL, ip VARCHAR(45) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX (registration_id), INDEX (status, created_at),
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

/** کتاب‌های خوانده‌شده‌ی اعضا (ثبت توسط مدیر) */
const BOOKS_DDL = 'CREATE TABLE IF NOT EXISTS member_books (
    id INT AUTO_INCREMENT PRIMARY KEY, member_id INT NOT NULL, title VARCHAR(200) NOT NULL, author VARCHAR(150) NULL,
    read_date DATE NULL, note VARCHAR(500) NULL, created_by INT NULL, deleted_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX (member_id, read_date),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

/** ستون‌های افزوده‌شده در نسخه‌های بعدی؛ [جدول، ستون، تعریف] — همگام با database/royesh-update.sql */
const SCHEMA_COLUMNS = [
    ['members', 'created_by_staff', 'VARCHAR(60) NULL'],
    ['member_profiles', 'member_rank', 'VARCHAR(100) NULL'],
    ['member_profiles', 'interests', 'VARCHAR(500) NULL'],
    ['member_profiles', 'is_top', 'TINYINT(1) NOT NULL DEFAULT 0'],
    ['member_profiles', 'top_order', 'INT NOT NULL DEFAULT 0'],
    ['camps', 'map_url', 'VARCHAR(255) NULL'],
    ['testimonials', 'approved', 'TINYINT(1) NOT NULL DEFAULT 0'],
    ['testimonials', 'ip', 'VARCHAR(45) NULL'],
    ['testimonials', 'created_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
    ['heyats', 'sms_sent_at', 'DATETIME NULL'],
    ['login_codes', 'attempts', 'TINYINT NOT NULL DEFAULT 0'],
    ['payment_receipts', 'admin_note', 'VARCHAR(500) NULL'],
    ['member_books', 'author', 'VARCHAR(150) NULL'],
];

/**
 * به‌روزرسانی خودکار ساختار دیتابیس: ستون/جدولِ جدیدِ نبوده ساخته می‌شود تا صفحه‌ها با دیتابیس قدیمی خطا ندهند.
 * هر درخواست فقط یک کوئری سبک دارد.
 */
function schema_ensure(PDO $pdo): void
{
    try {
        $or = [];
        foreach (SCHEMA_COLUMNS as [$t, $c]) {
            $or[] = "(TABLE_NAME='$t' AND COLUMN_NAME='$c')";
        }
        $n = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (' . implode(' OR ', $or) . ')')->fetchColumn();
        if ($n >= count(SCHEMA_COLUMNS)) {
            return;
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS testimonials (
            id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, role VARCHAR(120) NULL, body TEXT NOT NULL,
            rating TINYINT NOT NULL DEFAULT 5, approved TINYINT(1) NOT NULL DEFAULT 0, enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0, ip VARCHAR(45) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec(HEYATS_DDL);
        $pdo->exec(LOGIN_CODES_DDL);
        $pdo->exec(RECEIPTS_DDL);
        $pdo->exec(BOOKS_DDL);
        foreach (SCHEMA_COLUMNS as [$t, $c, $def]) {
            $tb = $pdo->query("SHOW TABLES LIKE '$t'")->fetch();
            if (!$tb || $pdo->query("SHOW COLUMNS FROM `$t` LIKE '$c'")->fetch()) {
                continue;
            }
            $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
            if ($t === 'testimonials' && $c === 'approved') {
                $pdo->exec('UPDATE testimonials SET approved=1'); // نظرهای قدیمی تأییدشده حساب شوند
            }
        }
    } catch (Throwable $e) {
        error_log('schema_ensure: ' . $e->getMessage());
    }
}

/** نقش «مدیر رسانه»: ستون users.role قدیمی (ENUM بدون media) را یک‌بار گسترش می‌دهد و نقش را در جدول roles ثبت می‌کند */
function ensure_role_enum(PDO $pdo): void
{
    try {
        $c = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
        $t = (string)($c['Type'] ?? '');
        if (stripos($t, 'enum') === 0 && stripos($t, "'media'") === false) {
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','editor','media') NOT NULL DEFAULT 'editor'");
        }
        $pdo->exec("INSERT IGNORE INTO roles(name,title,description) VALUES('media','مدیر رسانه','آلبوم‌ها، تصاویر، فایل‌ها، هیئت‌ها و ارسال پیامک هیئت')");
    } catch (Throwable $e) {
        error_log('ensure_role_enum: ' . $e->getMessage());
    }
}

set_exception_handler(function (Throwable $e) {
    error_log((string)$e);
    // ستون/جدول نبود (دیتابیس قدیمی): ساخت خودکار و درخواست تلاش دوباره
    if ($e instanceof PDOException && in_array((string)$e->getCode(), ['42S22', '42S02'], true)) {
        try {
            schema_ensure(db());
        } catch (Throwable $e2) {
        }
        out(false, 'ساختار دیتابیس به‌روزرسانی شد؛ صفحه را دوباره باز کنید', null, 503);
    }
    out(false, 'خطای داخلی سرور', null, 500);
});
