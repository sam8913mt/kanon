<?php
/**
 * PUT|POST /api/profile/update.php — ویرایش اطلاعات مجاز توسط خود کاربر (ویرایش جزئی: فقط کلیدهای ارسالی)
 * قابل ویرایش: نام، نام پدر، کد ملی (فقط اگر هنوز ثبت نشده)، تاریخ تولد، جنسیت، ایمیل، تماس اضطراری، آدرس، محله، شهر، «درباره من»،
 *   اطلاعات تحصیلی (تغییر = رکورد جدید + حفظ سابقه) و شماره موبایل (با گذرواژه‌ی فعلی).
 * غیرقابل ویرایش توسط کاربر: نقش، دسترسی، مسئولیت، وضعیت حساب، سوابق ثبت‌نام، یادداشت داخلی.
 */
require __DIR__ . '/../../includes/bootstrap.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'], true)) {
    out(false, 'متد نامعتبر', null, 405);
}
$mid = require_member();
csrf_check();
$pdo = db();
$d   = json_input();

$cur = $pdo->prepare('SELECT national_id FROM member_profiles WHERE member_id=?');
$cur->execute([$mid]);
$cur = $cur->fetch() ?: [];
[$cols, $edu, $err] = pf_validate_profile($d, 'self', $cur, pf_edu_current($pdo, $mid));
if ($err) {
    out(false, $err, null, 422);
}

$changed = [];
$pdo->beginTransaction();
if (isset($d['mobile']) && $d['mobile'] !== '') {
    $m = $pdo->prepare('SELECT mobile,password_hash FROM members WHERE id=?');
    $m->execute([$mid]);
    $m = $m->fetch();
    if (fa_digits(trim((string)$d['mobile'])) !== $m['mobile']) {
        if (!password_verify((string)($d['current_password'] ?? ''), $m['password_hash'])) {
            $pdo->rollBack();
            out(false, 'برای تغییر شماره موبایل، گذرواژه‌ی فعلی را درست وارد کنید', null, 422);
        }
        if ($e = pf_change_mobile($pdo, $mid, (string)$d['mobile'])) {
            $pdo->rollBack();
            out(false, $e, null, 422);
        }
        $changed[] = 'mobile';
    }
}
$changed = array_merge($changed, pf_apply_profile($pdo, $mid, $cols, $edu));
$pdo->commit();
if ($changed) {
    log_member($mid, in_array('education', $changed, true) ? 'profile_education' : 'profile_update', implode(',', $changed));
}
out(true, $changed ? 'اطلاعات ذخیره شد' : 'تغییری برای ذخیره نبود');
