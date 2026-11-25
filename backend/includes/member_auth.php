<?php
/**
 * member_auth.php — احراز هویت و نشست کاربران عمومی (members)
 * نشست‌ها در جدول member_sessions (فقط هش شناسه) ثبت می‌شوند تا «نشست‌های فعال» و «خروج از نشست‌های دیگر» ممکن باشد.
 * این فایل از احراز هویت کاربران پنل (users: admin/editor) کاملاً جداست.
 */

/** ارقام فارسی/عربی → انگلیسی */
function fa_digits(string $s): string
{
    return strtr($s, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
}

function member_sid_hash(): string
{
    return hash('sha256', session_id());
}

/** خلاصه‌ی امن دستگاه/مرورگر (فقط نام مرورگر و سیستم‌عامل؛ نه User-Agent کامل) */
function member_device(): string
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $br = 'مرورگر ناشناس';
    foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Safari' => 'Safari'] as $k => $v) {
        if (stripos($ua, $k) !== false) {
            $br = $v;
            break;
        }
    }
    $os = 'سیستم ناشناس';
    foreach (['Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iOS', 'Mac OS' => 'macOS', 'Linux' => 'Linux'] as $k => $v) {
        if (stripos($ua, $k) !== false) {
            $os = $v;
            break;
        }
    }
    return "$br / $os";
}

/** IP با ماسک (دو بخش آخر پنهان) */
function member_ip_masked(string $ip): string
{
    if (str_contains($ip, '.')) {
        $p = explode('.', $ip);
        return $p[0] . '.' . ($p[1] ?? '*') . '.*.*';
    }
    return $ip === '' ? '' : substr($ip, 0, 9) . '…';
}

/** پس از session_regenerate_id فراخوانی شود: ثبت نشست جدید */
function member_session_start(int $memberId): void
{
    db()->prepare('INSERT INTO member_sessions(member_id,sid_hash,device,ip,last_seen_at) VALUES(?,?,?,?,NOW())')
        ->execute([$memberId, member_sid_hash(), member_device(), $_SERVER['REMOTE_ADDR'] ?? '']);
}

/**
 * شناسه‌ی کاربر عمومی واردشده یا ۰ (بدون خطا). نشست باطل‌شده/حساب غیرفعال → خروج.
 * هر بار «آخرین فعالیت» را حداکثر هر ۶۰ ثانیه به‌روز می‌کند.
 */
function current_member_id(): int
{
    $id = (int)($_SESSION['member']['id'] ?? 0);
    if (!$id) {
        return 0;
    }
    static $cache = [];
    $h = member_sid_hash();
    if (isset($cache[$h])) {
        return $cache[$h];
    }
    $s = db()->prepare('SELECT s.id sid, m.status FROM member_sessions s JOIN members m ON m.id=s.member_id
                        WHERE s.sid_hash=? AND s.member_id=? AND s.revoked_at IS NULL');
    $s->execute([$h, $id]);
    $r = $s->fetch();
    if (!$r || $r['status'] !== 'active') {
        unset($_SESSION['member']);
        return $cache[$h] = 0;
    }
    db()->prepare('UPDATE member_sessions SET last_seen_at=NOW() WHERE id=? AND last_seen_at < NOW() - INTERVAL 60 SECOND')->execute([$r['sid']]);
    db()->prepare('UPDATE members SET last_activity_at=NOW() WHERE id=? AND (last_activity_at IS NULL OR last_activity_at < NOW() - INTERVAL 60 SECOND)')->execute([$id]);
    return $cache[$h] = $id;
}

/** ورود کاربر عمومی لازم است؛ در غیر این صورت ۴۰۱ */
function require_member(): int
{
    $id = current_member_id();
    if (!$id) {
        out(false, 'ورود لازم است', null, 401);
    }
    return $id;
}

/** خروج نشست جاری */
function member_logout_session(): void
{
    if (!empty($_SESSION['member']['id'])) {
        db()->prepare('UPDATE member_sessions SET revoked_at=NOW() WHERE sid_hash=? AND revoked_at IS NULL')->execute([member_sid_hash()]);
    }
    unset($_SESSION['member']);
}

/** ابطال نشست‌های یک کاربر (به‌جز نشست جاری در صورت $exceptCurrent). تعداد نشست‌های باطل‌شده را برمی‌گرداند. */
function member_revoke_sessions(int $memberId, bool $exceptCurrent): int
{
    $sql = 'UPDATE member_sessions SET revoked_at=NOW() WHERE member_id=? AND revoked_at IS NULL';
    $p   = [$memberId];
    if ($exceptCurrent) {
        $sql .= ' AND sid_hash<>?';
        $p[]  = member_sid_hash();
    }
    $q = db()->prepare($sql);
    $q->execute($p);
    return $q->rowCount();
}

/** قانون گذرواژه: پیام خطا یا null */
function password_error(string $p): ?string
{
    if (mb_strlen($p) < 8) {
        return 'گذرواژه باید حداقل ۸ کاراکتر باشد';
    }
    if (mb_strlen($p) > 200) {
        return 'گذرواژه بیش از حد طولانی است';
    }
    return null;
}

/** شماره موبایل معتبر (۰۹xxxxxxxxx) */
function valid_mobile(string $m): bool
{
    return (bool)preg_match('/^09\d{9}$/', $m);
}


/** حساب پنل فعالِ متصل به این عضو (members.staff_user_id) یا null */
function member_panel_user(int $memberId): ?array
{
    $s = db()->prepare("SELECT u.id,u.username,u.role FROM members m JOIN users u ON u.id=m.staff_user_id
                        WHERE m.id=? AND m.status='active' AND u.active=1 AND u.role IN ('admin','editor','media')");
    $s->execute([$memberId]);
    $r = $s->fetch();
    return $r ?: null;
}

/**
 * نشست پنل را برای عضوِ دارای نقش می‌سازد و نقش را برمی‌گرداند (بدون نقش: null).
 * $onlyIfNone=true: اگر کاربر پنل دیگری واردشده باشد، دست نمی‌زند.
 */
function member_panel_login(int $memberId, bool $onlyIfNone = false): ?string
{
    $u = member_panel_user($memberId);
    if (!$u) {
        return null;
    }
    if ($onlyIfNone && !empty($_SESSION['user'])) {
        // نشست پنل از قبل هست: اگر همین عضو باشد نقش را برمی‌گردانیم، وگرنه (کاربر پنل دیگر) دست نمی‌زنیم
        return (int)($_SESSION['user']['mid'] ?? 0) === $memberId ? $u['role'] : null;
    }
    $_SESSION['user'] = ['id' => (int)$u['id'], 'username' => $u['username'], 'role' => $u['role'], 'mid' => $memberId];
    if (!$onlyIfNone) {
        log_act('login', 'member_panel');
    }
    return $u['role'];
}
