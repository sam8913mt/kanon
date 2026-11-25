<?php
require __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    out(true, '', ['csrf' => csrf_token()]);
}
csrf_check();
$d = json_decode(file_get_contents('php://input'), true) ?? [];

// محدودیت نرخ تلاش ناموفق (برای هر دو مسیر ورود)
$n = db()->prepare("SELECT COUNT(*) FROM logs WHERE action='login_fail' AND ip=? AND created_at>NOW()-INTERVAL 15 MINUTE");
$n->execute([$_SERVER['REMOTE_ADDR'] ?? '']);
if ((int)$n->fetchColumn() >= 5) {
    out(false, 'تلاش‌های ناموفق زیاد است؛ ۱۵ دقیقه بعد دوباره تلاش کنید', null, 429);
}

$username = trim((string)($d['username'] ?? ''));
$password = (string)($d['password'] ?? '');

/** نشست را با همان ساختار قبلی می‌سازد: ['id','username','role'] */
function finish_login(array $u, string $detail = '', bool $cfg = false): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int)$u['id'], 'username' => $u['username'], 'role' => $u['role']] + ($cfg ? ['cfg' => true] : []);
    log_act('login', $detail);
    out(true, 'خوش آمدید', ['role' => $u['role']]);
}

// ۱) ادمین تعریف‌شده در config.php (admin.username و admin.password_hash) — تنها راه ورود ادمین
if (config_admin_verify($CFG, $username, $password)) {
    $cu = config_admin_account($username);
    if ($cu === null) {
        // گذرواژه‌ی config درست بود (پس پیام صریح لو نمی‌دهد)؛ نام‌کاربری با کاربری غیرادمین تداخل دارد
        log_act('login_fail', 'config_admin_conflict');
        out(false, 'نام کاربری ادمین در config.php با کاربر دیگری در جدول users تداخل دارد؛ نام دیگری انتخاب کنید', null, 409);
    }
    finish_login($cu, 'config', true);
}

// ۲) ورود با شماره موبایل و گذرواژه‌ی پروفایل برای اعضایی که نقش پنل دارند (مدیر رسانه/ویرایشگر/مدیر)
$mob = fa_digits($username);
if (valid_mobile($mob)) {
    $ms = db()->prepare("SELECT id,status,password_hash FROM members WHERE mobile=?");
    $ms->execute([$mob]);
    $mr = $ms->fetch();
    if ($mr && $mr['status'] === 'active' && password_verify($password, $mr['password_hash']) && member_panel_user((int)$mr['id'])) {
        session_regenerate_id(true);
        $_SESSION['member'] = ['id' => (int)$mr['id']];
        member_session_start((int)$mr['id']);
        db()->prepare('UPDATE members SET last_login_at=NOW(), last_activity_at=NOW() WHERE id=?')->execute([$mr['id']]);
        log_member((int)$mr['id'], 'member_login');
        $role = member_panel_login((int)$mr['id']);
        out(true, 'خوش آمدید', ['role' => $role]);
    }
}

// ۳) حساب‌های جدول users (مدیر، ویرایشگر، مدیر رسانه) با نام کاربری و گذرواژه.
$s = db()->prepare("SELECT id,username,password_hash,role FROM users WHERE username=? AND active=1 AND role IN ('admin','editor','media')");
$s->execute([$username]);
$u = $s->fetch();
if (!$u || !password_verify($password, $u['password_hash'])) {
    log_act('login_fail', (string)($d['username'] ?? ''));
    usleep(400000);
    out(false, 'نام کاربری یا گذرواژه نادرست است', null, 401);
}
finish_login($u);
