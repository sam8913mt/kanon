<?php
/**
 * GET → آخرین ورود/فعالیت، تاریخ آخرین تغییر رمز، نشست‌های فعال (بدون هیچ اطلاعات رمز)
 * POST JSON {op:'change_password', current, new, confirm} | {op:'logout_others'} | {op:'revoke', id}
 */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
$mid = $v['member_id'];
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = json_input();
    $op = (string)($d['op'] ?? '');
    if ($op === 'change_password') {
        $n = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE action='member_pwd_fail' AND member_id=? AND created_at>NOW()-INTERVAL 15 MINUTE");
        $n->execute([$mid]);
        if ((int)$n->fetchColumn() >= 5) {
            out(false, 'تلاش‌های ناموفق زیاد است؛ ۱۵ دقیقه بعد دوباره تلاش کنید', null, 429);
        }
        $h = $pdo->prepare('SELECT password_hash FROM members WHERE id=?');
        $h->execute([$mid]);
        $hash = (string)$h->fetchColumn();
        $new = (string)($d['new'] ?? '');
        if (!password_verify((string)($d['current'] ?? ''), $hash)) {
            log_member($mid, 'member_pwd_fail');
            usleep(400000);
            out(false, 'گذرواژه‌ی فعلی نادرست است', null, 422);
        }
        if ($e = password_error($new)) {
            out(false, $e, null, 422);
        }
        if ($new !== (string)($d['confirm'] ?? '')) {
            out(false, 'تکرار گذرواژه با گذرواژه‌ی جدید یکسان نیست', null, 422);
        }
        if (password_verify($new, $hash)) {
            out(false, 'گذرواژه‌ی جدید باید با گذرواژه‌ی فعلی متفاوت باشد', null, 422);
        }
        $pdo->prepare('UPDATE members SET password_hash=?, password_changed_at=NOW() WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $mid]);
        member_revoke_sessions($mid, true); // پس از تغییر رمز، نشست‌های دیگر بسته می‌شوند
        log_member($mid, 'member_password_change');
        out(true, 'گذرواژه تغییر کرد و از دستگاه‌های دیگر خارج شدید');
    }
    if ($op === 'logout_others') {
        $c = member_revoke_sessions($mid, true);
        log_member($mid, 'member_sessions_revoke');
        out(true, $c ? "از $c نشست دیگر خارج شدید" : 'نشست دیگری فعال نبود');
    }
    if ($op === 'revoke') {
        $q = $pdo->prepare('UPDATE member_sessions SET revoked_at=NOW() WHERE id=? AND member_id=? AND revoked_at IS NULL AND sid_hash<>?');
        $q->execute([(int)($d['id'] ?? 0), $mid, member_sid_hash()]);
        out((bool)$q->rowCount(), $q->rowCount() ? 'نشست بسته شد' : 'نشست یافت نشد', null, $q->rowCount() ? 200 : 404);
    }
    out(false, 'عملیات نامعتبر', null, 422);
}
out(true, '', pf_section('security', $mid, $v));
