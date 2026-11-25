<?php
/**
 * auth.php — نشست (Session) کاربر
 * تنظیمات کوکی امن، انقضای نشست و هدرهای امنیتی پایه.
 */

function start_secure_session(array $cfg): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => $cfg['session']['lifetime'],
        'httponly' => true,
        'secure'   => is_https(),
        'samesite' => 'Lax',
    ]);
    session_start();
    // انقضای نشست بر اساس آخرین فعالیت
    if (isset($_SESSION['last']) && time() - $_SESSION['last'] > $cfg['session']['lifetime']) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last'] = time();
}

function current_user(): ?array
{
    $u = $_SESSION['user'] ?? null;
    if (!$u) {
        return null;
    }
    static $ok = null;
    $key = session_id() . ':' . (int)($u['id'] ?? 0) . ':' . ($u['role'] ?? '');
    if ($ok === $key) {
        return $_SESSION['user'] ?? null;
    }
    global $CFG;
    $cu   = trim((string)($CFG['admin']['username'] ?? ''));
    $ch   = $CFG['admin']['password_hash'] ?? '';
    $name = (string)($u['username'] ?? '');
    // نشست ادمین پیکربندی‌شده در config.php فقط تا وقتی معتبر است که با config.php بخواند (تغییر نام‌کاربری/خالی‌کردن هش = خروج فوری)
    if (!empty($u['cfg']) && (!is_string($ch) || $ch === '' || $cu === '' || !hash_equals($cu, $name))) {
        unset($_SESSION['user']);
        return null;
    }
    // همه‌ی نشست‌ها: حساب باید در جدول users فعال باشد؛ نقش همیشه از دیتابیس خوانده می‌شود (تغییر نقش فوری اعمال می‌شود)
    try {
        $s = db()->prepare('SELECT username,role,active FROM users WHERE id=?');
        $s->execute([(int)($u['id'] ?? 0)]);
        $r = $s->fetch();
    } catch (Throwable $e) {
        $r = null;
    }
    if (!$r || !(int)$r['active'] || !hash_equals((string)$r['username'], $name) || !in_array($r['role'], ['admin', 'editor', 'media'], true)) {
        unset($_SESSION['user']);
        return null;
    }
    // نشستی که با ورود عضو (شماره موبایل) ساخته شده، به نشست خودِ عضو و فعال‌بودن او وابسته است
    if (!empty($u['mid']) && current_member_id() !== (int)$u['mid']) {
        unset($_SESSION['user']);
        return null;
    }
    $_SESSION['user']['role'] = $r['role'];
    $ok = session_id() . ':' . (int)$u['id'] . ':' . $r['role'];
    return $_SESSION['user'];
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

/**
 * ادمین پیکربندی‌شده در config.php (کلید 'admin' => ['username'=>…, 'password_hash'=>…]).
 * - اگر password_hash خالی/نامعتبر باشد این مسیر ورود کاملاً غیرفعال است.
 * - فقط password_verify روی هش انجام می‌شود؛ هش به هیچ پاسخ، لاگ یا پیام خطایی نمی‌رود.
 * نتیجه: true فقط وقتی نام کاربری دقیقاً برابر admin.username و گذرواژه با admin.password_hash تأیید شود.
 */
function config_admin_verify(array $cfg, string $username, string $password): bool
{
    $a = $cfg['admin'] ?? null;
    if (!is_array($a)) {
        return false;
    }
    $cu = $a['username'] ?? '';
    $ch = $a['password_hash'] ?? '';
    if (!is_string($cu) || !is_string($ch) || $ch === '') {
        return false; // خالی = غیرفعال
    }
    $cu = trim($cu);
    if ($cu === '' || !hash_equals($cu, $username)) {
        return false; // نام کاربری دیگر؛ مسیر عادی جدول users ادامه می‌یابد
    }
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,60}$/', $cu) || password_get_info($ch)['algo'] === null) {
        // پیکربندی نامعتبر (مثلاً گذرواژه‌ی خام به‌جای هش). مقدار هرگز لاگ نمی‌شود.
        error_log('config.php: admin.username/admin.password_hash نامعتبر است؛ ورود ادمین پیکربندی غیرفعال شد.');
        return false;
    }
    if ($password === '' || strlen($password) > 4096) {
        return false;
    }
    return password_verify($password, $ch);
}

/**
 * ردیف واقعی users برای ادمین پیکربندی‌شده (اولین ورود موفق آن را می‌سازد).
 * چرا ردیف واقعی؟ شناسه‌ی کاربر در Session برای کلیدهای خارجی (media.created_by، albums.created_by،
 * logs.user_id، camp_editors/album_editors) و محافظت «حذف/کاهش نقش خودِ کاربر» لازم است.
 * گذرواژه‌ی این ردیف تصادفی و ناشناخته است (هش config هرگز در دیتابیس کپی نمی‌شود)، پس ورود از مسیر جدول users
 * با آن ممکن نیست و تغییر/پاک‌کردن هش در config فوراً ورود را باطل می‌کند.
 * اگر همین نام‌کاربری برای کاربری با نقش غیر admin وجود داشته باشد، هیچ ردیفی تغییر نمی‌کند و null برمی‌گردد.
 *
 * @return array{id:int,username:string,role:string}|null
 */
function config_admin_account(string $username): ?array
{
    $pdo = db();
    $find = $pdo->prepare('SELECT id,username,role FROM users WHERE username=?');
    $find->execute([$username]);
    $r = $find->fetch();
    if (!$r) {
        try {
            $pdo->prepare("INSERT INTO users(username,password_hash,role,active) VALUES(?,?,'admin',1)")
                ->execute([$username, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') { // 23000 = ساخته‌شدن هم‌زمان توسط درخواست دیگر
                throw $e;
            }
        }
        $find->execute([$username]);
        $r = $find->fetch();
    }
    if (!$r || $r['role'] !== 'admin') {
        return null;
    }
    return ['id' => (int)$r['id'], 'username' => (string)$r['username'], 'role' => 'admin'];
}
