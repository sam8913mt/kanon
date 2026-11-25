<?php
/**
 * payment.php — صفحه‌ی پرداخت پس از ثبت‌نام (کارت‌به‌کارت)
 * GET  ?id=R000123[&t=کد-پیگیری] → اطلاعات ثبت‌نام، شماره کارت و وضعیت رسید
 * POST (multipart) id, t, receipt(تصویر) → ذخیره‌ی امن رسید؛ ارسال مجدد فقط تا پیش از تأیید ممکن است
 * دسترسی: عضو واردشده (مالک ثبت‌نام) یا مهمان با کد پیگیری محرمانه. نامجاز و ناموجود هر دو ۴۰۴ می‌دهند.
 */
require __DIR__ . '/../includes/bootstrap.php';
$pdo = db();
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$src  = $post ? $_POST : $_GET;
$id   = pay_reg_id((string)($src['id'] ?? ''));
$reg  = $id ? pay_registration($pdo, $id, (string)($src['t'] ?? '')) : null;
if (!$reg) {
    out(false, 'ثبت‌نام یافت نشد', null, 404);
}
$code = 'R' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);

if (!$post) {
    $card = pay_card($pdo);
    out(true, '', [
        'csrf'    => csrf_token(),
        'code'    => $code,
        'camp'    => $reg['title'],
        'cost'    => $reg['cost'],
        'card'    => $card,
        'receipt' => pay_receipt_info($pdo, $id),
        'max_mb'  => round($CFG['upload']['max_bytes'] / 1048576),
    ]);
}

csrf_check();
if ($reg['status'] === 'cancelled' || $reg['status'] === 'rejected') {
    out(false, 'این ثبت‌نام فعال نیست و امکان ثبت رسید ندارد', null, 422);
}
$cur = pay_receipt_info($pdo, $id);
if ($cur && $cur['status'] === 'approved') {
    out(false, 'رسید شما قبلاً تأیید شده است', null, 409);
}
if (empty($_FILES['receipt']) || ($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    out(false, 'تصویر رسید را انتخاب کنید (اگر انتخاب کرده‌اید، حجم فایل بیش از حد مجاز است)', null, 422);
}
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$n = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE action='receipt_upload' AND ip=? AND created_at>NOW()-INTERVAL 1 HOUR");
$n->execute([$ip]);
if ((int)$n->fetchColumn() >= 10) {
    out(false, 'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید', null, 429);
}
log_act('receipt_upload', $code, $reg['member_id'] ? (int)$reg['member_id'] : null);

// فقط تصویر (JPG/PNG/WEBP) — MIME واقعی بررسی، دوباره کدگذاری، فشرده و بدون EXIF ذخیره می‌شود
$file = save_private_file($_FILES['receipt'], false, 1600)[0];
if ($cur) {
    $old = $pdo->prepare('SELECT file FROM payment_receipts WHERE registration_id=?');
    $old->execute([$id]);
    $pdo->prepare("UPDATE payment_receipts SET file=?, status='pending', admin_note=NULL, archived=0, approved_at=NULL, ip=?, created_at=NOW() WHERE registration_id=?")
        ->execute([$file, $ip, $id]);
    delete_reg_file((string)$old->fetchColumn());
} else {
    $pdo->prepare('INSERT INTO payment_receipts(registration_id,file,ip) VALUES(?,?,?)')->execute([$id, $file, $ip]);
}
if ($reg['member_id']) {
    pf_notify((int)$reg['member_id'], 'payment', "رسید پرداخت «{$reg['title']}» ثبت شد", 'پس از بررسی، نتیجه از همین‌جا اعلام می‌شود.', '/profile#notifications');
}
out(true, 'رسید شما ثبت شد و پس از بررسی مدیر نتیجه اعلام می‌شود.', ['receipt' => pay_receipt_info($pdo, $id)]);
