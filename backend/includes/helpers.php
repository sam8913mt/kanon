<?php
/**
 * helpers.php — توابع کمکی عمومی API
 */

/** آیا درخواست روی HTTPS است؟ (پشت پروکسی/CDN/لودبالانسر هم درست تشخیص می‌دهد) */
function is_https(): bool
{
    $s = $_SERVER;
    return (!empty($s['HTTPS']) && strtolower((string)$s['HTTPS']) !== 'off')
        || (int)($s['SERVER_PORT'] ?? 0) === 443
        || strtolower((string)($s['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || strtolower((string)($s['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
        || strpos((string)($s['HTTP_CF_VISITOR'] ?? ''), '"https"') !== false
        || strtolower((string)($s['REQUEST_SCHEME'] ?? '')) === 'https';
}

/** پاسخ JSON استاندارد و پایان اجرا: {success, message, data} */
function out(bool $ok, string $msg = '', $data = null, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

/** بدنه‌ی JSON درخواست به‌صورت آرایه (در صورت نامعتبر بودن، آرایه‌ی خالی) */
function json_input(): array
{
    $d = json_decode((string)file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}

/** ثبت یک رویداد در Activity Log (user_id = کاربر پنل جاری؛ member_id = پروفایلِ مربوط در صورت وجود) */
function log_act(string $action, string $detail = '', ?int $memberId = null): void
{
    db()->prepare('INSERT INTO logs(user_id,member_id,action,detail,ip) VALUES(?,?,?,?,?)')
        ->execute([$_SESSION['user']['id'] ?? null, $memberId, $action, mb_substr($detail, 0, 2000), $_SERVER['REMOTE_ADDR'] ?? '']);
}

/** رویداد مربوط به خودِ کاربر عمومی (بدون هویت کاربر پنل؛ هرگز گذرواژه یا مقدار حساس در detail نگذارید) */
function log_member(int $memberId, string $action, string $detail = ''): void
{
    db()->prepare('INSERT INTO logs(user_id,member_id,action,detail,ip) VALUES(NULL,?,?,?,?)')
        ->execute([$memberId, $action, mb_substr($detail, 0, 2000), $_SERVER['REMOTE_ADDR'] ?? '']);
}

/** آدرس‌های خطرناک (javascript:, data:, vbscript:) */
function is_unsafe_url(string $u): bool
{
    return (bool)preg_match('/^\s*(javascript|data|vbscript):/i', $u);
}

/** نامک (slug) معتبر: فقط حروف انگلیسی، عدد، خط‌تیره و زیرخط (سازگار با قوانین Rewrite در .htaccess) */
function is_valid_slug(string $s): bool
{
    return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-]{0,118}$/', $s);
}


/** تاریخ میلادی ('YYYY-MM-DD[ HH:MM:SS]') ← رشته‌ی شمسی «1405/09/02[ 14:30]» (برای Export/چاپ؛ ورودی نامعتبر بدون تغییر برمی‌گردد) */
function jdate_str(?string $s, bool $time = false): string
{
    $s = (string)$s;
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $s, $m) || (int)$m[1] === 0) {
        return $s;
    }
    [$gy, $gm, $gd] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }
    $gy2 = $gm > 2 ? $gy + 1 : $gy;
    $days = 365 * $gy + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) - 80 + $gd + $gdm[$gm - 1];
    $jy += 33 * intdiv($days, 12053);
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + $days % 31;
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + ($days - 186) % 30;
    }
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd) . ($time && isset($m[4]) ? " {$m[4]}:{$m[5]}" : '');
}
