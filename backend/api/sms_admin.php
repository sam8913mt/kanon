<?php
// POST {op:"test", mobile} — ارسال پیامک آزمایشی با تنظیمات ذخیره‌شده (فقط Admin)
require __DIR__ . '/../includes/bootstrap.php';
require_role('sms');
csrf_check();
$d = json_input();
if (($d['op'] ?? '') !== 'test') {
    out(false, 'عملیات نامعتبر', null, 422);
}
$mobile = fa_digits(trim((string)($d['mobile'] ?? '')));
if (!valid_mobile($mobile)) {
    out(false, 'شماره موبایل معتبر نیست (مثل 09123456789)', null, 422);
}
$s = sms_settings(db());
if (!sms_ready($s)) {
    out(false, 'ابتدا کلید API را وارد و ذخیره کنید', null, 422);
}
$ok = sms_send($s, [$mobile], 'پیامک آزمایشی کانون نخبه‌پروری رویش', $err);
log_act('sms_test', $mobile);
$ok ? out(true, 'پیامک آزمایشی ارسال شد') : out(false, 'ارسال ناموفق: ' . ($err ?: 'نامشخص'), null, 502);
