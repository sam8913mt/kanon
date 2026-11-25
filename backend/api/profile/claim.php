<?php
/**
 * POST /api/profile/claim.php {code} — اتصال یک ثبت‌نام مهمان (بدون حساب) به حساب کاربر واردشده با «کد پیگیری»
 *
 *  - فقط ثبت‌نامی که هنوز به هیچ حسابی وصل نیست (member_id IS NULL) و کد پیگیری‌اش دقیقاً مطابق است.
 *  - کد پیگیری ۱۲ نویسه‌ی تصادفی و محرمانه است (به مهمان پس از ثبت‌نام نمایش داده می‌شود)؛ شناسه‌ی عمومی R000123 کافی نیست.
 *  - محدودیت نرخ: حداکثر ۱۰ تلاش در ساعت برای هر حساب (جلوگیری از حدس‌زدن کد).
 *  - هیچ پاسخ یا سابقه‌ای تغییر/حذف نمی‌شود؛ فقط member_id تنظیم و اعلان/Timeline ساخته می‌شود.
 */
require __DIR__ . '/../../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(false, 'متد نامعتبر', null, 405);
}
$mid = require_member();
csrf_check();
$pdo  = db();
$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)(json_input()['code'] ?? '')));
if (!preg_match('/^[A-F0-9]{12}$/', $code)) {
    out(false, 'کد پیگیری معتبر نیست (۱۲ نویسه‌ی انگلیسی/عدد)', null, 422);
}
$n = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE action='claim_attempt' AND member_id=? AND created_at>NOW()-INTERVAL 1 HOUR");
$n->execute([$mid]);
if ((int)$n->fetchColumn() >= 10) {
    out(false, 'تعداد تلاش‌ها زیاد است؛ کمی بعد دوباره تلاش کنید', null, 429);
}
log_member($mid, 'claim_attempt');

$pdo->beginTransaction();
$s = $pdo->prepare('SELECT r.id,c.title FROM registrations r JOIN camps c ON c.id=r.camp_id WHERE r.tracking_code=? AND r.member_id IS NULL FOR UPDATE');
$s->execute([$code]);
$r = $s->fetch();
if (!$r) {
    $pdo->rollBack();
    out(false, 'ثبت‌نامی با این کد پیدا نشد یا قبلاً به یک حساب وصل شده است', null, 404);
}
$pdo->prepare('UPDATE registrations SET member_id=? WHERE id=? AND member_id IS NULL')->execute([$mid, $r['id']]);
$pdo->commit();
pf_timeline($mid, 'registration', "اتصال ثبت‌نام اردوی «{$r['title']}» به حساب", 'registration', (int)$r['id']);
pf_notify($mid, 'registration', "ثبت‌نام شما در «{$r['title']}» به حساب شما اضافه شد", null, '/profile#camps');
log_member($mid, 'registration_claim', 'R' . str_pad((string)$r['id'], 6, '0', STR_PAD_LEFT));
out(true, 'ثبت‌نام به حساب شما اضافه شد', ['id' => (int)$r['id']]);
