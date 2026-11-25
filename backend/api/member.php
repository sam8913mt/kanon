<?php
/**
 * member.php — ساخت حساب، ورود و خروج کاربران عمومی
 * GET  → {csrf, member|null}
 * POST JSON {op: signup|login|logout}
 * (اطلاعات پروفایل در /api/profile/* است)
 */
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $m   = null;
    $mid = current_member_id();
    if ($mid) {
        $s = db()->prepare("SELECT m.id, CONCAT_WS(' ', p.first_name, p.last_name) full_name, p.avatar_doc_id avatar
                            FROM members m JOIN member_profiles p ON p.member_id=m.id WHERE m.id=?");
        $s->execute([$mid]);
        $m = $s->fetch() ?: null;
        if ($m) {
            $m['panel'] = member_panel_login($mid, true);
        }
    }
    out(true, '', ['csrf' => csrf_token(), 'member' => $m, 'otp' => sms_otp_enabled(db())]);
}
csrf_check();
$pdo = db();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '';
$d   = json_input();
$op  = (string)($d['op'] ?? '');

if ($op === 'logout') {
    $mid = (int)($_SESSION['member']['id'] ?? 0);
    member_logout_session();
    if ($mid) {
        log_member($mid, 'member_logout');
    }
    out(true, 'خارج شدید');
}

$mobile = fa_digits(trim((string)($d['mobile'] ?? '')));
$pass   = (string)($d['password'] ?? '');

if ($op === 'signup') {
    $a = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE action='member_signup' AND ip=? AND created_at>NOW()-INTERVAL 1 HOUR");
    $a->execute([$ip]);
    if ((int)$a->fetchColumn() >= 10) {
        out(false, 'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید', null, 429);
    }
    $clean = fn(string $k) => trim(preg_replace('/\s+/u', ' ', (string)($d[$k] ?? '')));
    $first = $clean('first_name');
    $last  = $clean('last_name');
    if (mb_strlen($first) < 2 || mb_strlen($first) > 60 || mb_strlen($last) < 2 || mb_strlen($last) > 60) {
        out(false, 'نام و نام خانوادگی را کامل وارد کنید', null, 422);
    }
    if (!valid_mobile($mobile)) {
        out(false, 'شماره موبایل معتبر نیست (مثل 09123456789)', null, 422);
    }
    if ($e = password_error($pass)) {
        out(false, $e, null, 422);
    }
    $x = $pdo->prepare('SELECT 1 FROM members WHERE mobile=?');
    $x->execute([$mobile]);
    if ($x->fetchColumn()) {
        out(false, 'با این شماره قبلاً پروفایل ساخته شده است؛ وارد شوید', null, 409);
    }
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO members(mobile,password_hash,password_changed_at,last_login_at,last_activity_at) VALUES(?,?,NOW(),NOW(),NOW())')
        ->execute([$mobile, password_hash($pass, PASSWORD_DEFAULT)]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO member_profiles(member_id,first_name,last_name) VALUES(?,?,?)')->execute([$id, $first, $last]);
    $pdo->commit();
    session_regenerate_id(true);
    $_SESSION['member'] = ['id' => $id];
    member_session_start($id);
    pf_timeline($id, 'membership', 'عضویت در کانون');
    pf_notify($id, 'welcome', 'به کانون رویش خوش آمدید', 'پروفایل خود را تکمیل کنید تا ثبت‌نام اردوها سریع‌تر انجام شود.', '/profile');
    log_member($id, 'member_signup');
    out(true, 'پروفایل شما ساخته شد', ['id' => $id, 'full_name' => "$first $last"]);
}

if ($op === 'login') {
    $n = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE action='member_login_fail' AND ip=? AND created_at>NOW()-INTERVAL 15 MINUTE");
    $n->execute([$ip]);
    if ((int)$n->fetchColumn() >= 5) {
        out(false, 'تلاش‌های ناموفق زیاد است؛ ۱۵ دقیقه بعد دوباره تلاش کنید', null, 429);
    }
    $s = $pdo->prepare("SELECT m.id, m.status, m.password_hash, CONCAT_WS(' ', p.first_name, p.last_name) full_name
                        FROM members m JOIN member_profiles p ON p.member_id=m.id WHERE m.mobile=?");
    $s->execute([$mobile]);
    $m = $s->fetch();
    if (!$m || !password_verify($pass, $m['password_hash'])) {
        log_act('member_login_fail', $mobile);
        usleep(400000);
        out(false, 'شماره موبایل یا گذرواژه نادرست است', null, 401);
    }
    if ($m['status'] !== 'active') {
        out(false, $m['status'] === 'suspended' ? 'حساب شما تعلیق شده است؛ با مدیر کانون تماس بگیرید' : 'این حساب فعال نیست', null, 403);
    }
    session_regenerate_id(true);
    $_SESSION['member'] = ['id' => (int)$m['id']];
    member_session_start((int)$m['id']);
    $pdo->prepare('UPDATE members SET last_login_at=NOW(), last_activity_at=NOW() WHERE id=?')->execute([$m['id']]);
    log_member((int)$m['id'], 'member_login');
    $panel = member_panel_login((int)$m['id']);
    out(true, 'خوش آمدید', ['id' => (int)$m['id'], 'full_name' => $m['full_name'], 'panel' => $panel]);
}

if ($op === 'otp_send' || $op === 'otp_verify') {
    $set = sms_settings($pdo);
    if (!sms_otp_enabled($pdo)) {
        out(false, 'ورود با کد یکبار مصرف فعال نیست', null, 403);
    }
    if (!valid_mobile($mobile)) {
        out(false, 'شماره موبایل معتبر نیست (مثل 09123456789)', null, 422);
    }
    $ms = $pdo->prepare("SELECT m.id, m.status, CONCAT_WS(' ', p.first_name, p.last_name) full_name FROM members m JOIN member_profiles p ON p.member_id=m.id WHERE m.mobile=?");
    $ms->execute([$mobile]);
    $m = $ms->fetch();
}

if ($op === 'otp_send') {
    $pdo->exec('DELETE FROM login_codes WHERE created_at < NOW() - INTERVAL 1 DAY');
    $c = $pdo->prepare('SELECT COUNT(*) FROM login_codes WHERE ip=? AND created_at>NOW()-INTERVAL 1 HOUR');
    $c->execute([$ip]);
    $c2 = $pdo->prepare('SELECT COUNT(*) FROM login_codes WHERE mobile=? AND created_at>NOW()-INTERVAL 1 HOUR');
    $c2->execute([$mobile]);
    $c3 = $pdo->prepare('SELECT COUNT(*) FROM login_codes WHERE mobile=? AND created_at>NOW()-INTERVAL 60 SECOND');
    $c3->execute([$mobile]);
    if ((int)$c3->fetchColumn() > 0) {
        out(false, 'کد قبلی تازه ارسال شده است؛ ۱ دقیقه صبر کنید', null, 429);
    }
    if ((int)$c->fetchColumn() >= 10 || (int)$c2->fetchColumn() >= 5) {
        out(false, 'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید', null, 429);
    }
    if (!$m) {
        out(false, 'با این شماره پروفایلی ساخته نشده است؛ ابتدا «ساخت پروفایل» را بزنید', null, 404);
    }
    if ($m['status'] !== 'active') {
        out(false, $m['status'] === 'suspended' ? 'حساب شما تعلیق شده است؛ با مدیر کانون تماس بگیرید' : 'این حساب فعال نیست', null, 403);
    }
    $ttl  = max(1, min(10, (int)$set['sms_otp_ttl'] ?: 2));
    $code = (string)random_int(10000, 99999);
    $pdo->prepare('UPDATE login_codes SET used=1 WHERE mobile=?')->execute([$mobile]);
    $pdo->prepare('INSERT INTO login_codes(mobile,code_hash,expires_at,ip) VALUES(?,?,NOW() + INTERVAL ? MINUTE,?)')
        ->execute([$mobile, sms_otp_hash($set, $mobile, $code), $ttl, $ip]);
    $newId = (int)$pdo->lastInsertId();
    $text = strtr(trim((string)$set['sms_otp_text']) ?: 'کد ورود شما به کانون نخبه‌پروری رویش: {code}', ['{code}' => $code]);
    if (!sms_send($set, [$mobile], $text)) {
        $pdo->prepare('DELETE FROM login_codes WHERE id=?')->execute([$newId]);
        out(false, 'ارسال پیامک ناموفق بود؛ کمی بعد دوباره تلاش کنید', null, 502);
    }
    log_member((int)$m['id'], 'member_otp_send');
    out(true, 'کد برای شما پیامک شد', ['ttl' => $ttl * 60, 'cooldown' => 60]);
}

if ($op === 'otp_verify') {
    $n = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE action='member_login_fail' AND ip=? AND created_at>NOW()-INTERVAL 15 MINUTE");
    $n->execute([$ip]);
    if ((int)$n->fetchColumn() >= 5) {
        out(false, 'تلاش‌های ناموفق زیاد است؛ ۱۵ دقیقه بعد دوباره تلاش کنید', null, 429);
    }
    $code = fa_digits(trim((string)($d['code'] ?? '')));
    $q = $pdo->prepare('SELECT id,code_hash,attempts FROM login_codes WHERE mobile=? AND used=0 AND expires_at>NOW() ORDER BY id DESC LIMIT 1');
    $q->execute([$mobile]);
    $row = $q->fetch();
    if ($row) {
        $pdo->prepare('UPDATE login_codes SET attempts=attempts+1 WHERE id=?')->execute([$row['id']]);
    }
    if (!$m || !$row || (int)$row['attempts'] >= 5 || !hash_equals($row['code_hash'], sms_otp_hash($set, $mobile, $code))) {
        log_act('member_login_fail', $mobile);
        usleep(400000);
        out(false, 'کد نادرست یا منقضی شده است', null, 401);
    }
    if ($m['status'] !== 'active') {
        out(false, $m['status'] === 'suspended' ? 'حساب شما تعلیق شده است؛ با مدیر کانون تماس بگیرید' : 'این حساب فعال نیست', null, 403);
    }
    $pdo->prepare('UPDATE login_codes SET used=1 WHERE mobile=?')->execute([$mobile]);
    session_regenerate_id(true);
    $_SESSION['member'] = ['id' => (int)$m['id']];
    member_session_start((int)$m['id']);
    $pdo->prepare('UPDATE members SET last_login_at=NOW(), last_activity_at=NOW() WHERE id=?')->execute([$m['id']]);
    log_member((int)$m['id'], 'member_login_otp');
    $panel = member_panel_login((int)$m['id']);
    out(true, 'خوش آمدید', ['id' => (int)$m['id'], 'full_name' => $m['full_name'], 'panel' => $panel]);
}

out(false, 'عملیات نامعتبر', null, 422);
