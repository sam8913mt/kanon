<?php
/**
 * payments.php — پرداخت کارت‌به‌کارت ثبت‌نام و رسیدها
 * - شماره و نام صاحب کارت فقط در جدول settings (کلیدهای secret_pay_*) نگهداری می‌شود؛ هرگز در API عمومی نمی‌آید
 *   و فقط پس از اثبات مالکیت ثبت‌نام (عضو واردشده یا کد پیگیری مهمان) به صفحه‌ی پرداخت داده می‌شود.
 * - تصویر رسید با save_private_file (بررسی MIME واقعی + کدگذاری مجدد GD + فشرده‌سازی) در storage/private ذخیره می‌شود.
 */

const PAY_OLD_DAYS = 30; // رسید قدیمی‌تر از این تعداد روز (یا بایگانی‌شده) در دسته‌ی «قدیمی» می‌آید
const PAY_LABELS = ['pending' => 'در انتظار بررسی', 'approved' => 'تأیید شده', 'rejected' => 'رد شده'];

/** شماره و نام صاحب کارت: ['number' => ۱۶ رقم یا '', 'holder' => ''] */
function pay_card(PDO $pdo): array
{
    $r = $pdo->query("SELECT k,v FROM settings WHERE k IN ('secret_pay_card_number','secret_pay_card_holder')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $n = preg_replace('/\D+/', '', (string)($r['secret_pay_card_number'] ?? ''));
    return ['number' => strlen((string)$n) === 16 ? $n : '', 'holder' => trim((string)($r['secret_pay_card_holder'] ?? ''))];
}

/** «R000123» یا «123» ← شناسه‌ی عددی ثبت‌نام (۰ = نامعتبر) */
function pay_reg_id(string $code): int
{
    $code = fa_digits(trim($code));
    return preg_match('/^R?(\d{1,9})$/i', $code, $m) ? (int)$m[1] : 0;
}

/**
 * ثبت‌نامِ قابل‌دسترس برای کاربر جاری: عضوِ مالک، یا مهمان با کد پیگیری درست. در غیر این صورت null.
 * خروجی شامل عنوان و هزینه‌ی اردو هم هست.
 */
function pay_registration(PDO $pdo, int $id, string $track): ?array
{
    $s = $pdo->prepare('SELECT r.id,r.member_id,r.status,r.tracking_code,r.camp_id,c.title,c.cost FROM registrations r JOIN camps c ON c.id=r.camp_id WHERE r.id=?');
    $s->execute([$id]);
    $r = $s->fetch();
    if (!$r) {
        return null;
    }
    $mid = current_member_id();
    $ok  = $mid > 0 && $r['member_id'] !== null && $mid === (int)$r['member_id'];
    if (!$ok && $track !== '' && $r['tracking_code'] !== null) {
        $ok = hash_equals((string)$r['tracking_code'], strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $track)));
    }
    return $ok ? $r : null;
}

/** وضعیت رسید یک ثبت‌نام (بدون مسیر فایل) یا null */
function pay_receipt_info(PDO $pdo, int $regId): ?array
{
    $s = $pdo->prepare('SELECT id,status,admin_note,created_at FROM payment_receipts WHERE registration_id=?');
    $s->execute([$regId]);
    $r = $s->fetch();
    return $r ? ['id' => (int)$r['id'], 'status' => $r['status'], 'status_label' => PAY_LABELS[$r['status']] ?? '', 'note' => $r['admin_note'], 'created_at' => $r['created_at']] : null;
}

/** شرط SQL دسته‌ی رسید (pr = payment_receipts): reserved | approved | old */
function pay_cat_sql(string $cat): string
{
    $old = '(pr.archived=1 OR pr.created_at < NOW() - INTERVAL ' . PAY_OLD_DAYS . ' DAY)';
    return match ($cat) {
        'old'      => $old,
        'approved' => "NOT $old AND pr.status='approved'",
        default    => "NOT $old AND pr.status IN ('pending','rejected')",
    };
}
