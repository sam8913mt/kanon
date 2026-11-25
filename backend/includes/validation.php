<?php
/**
 * validation.php — اعتبارسنجی سمت سرور (منبع اصلی حقیقت؛ JS فقط کمکی است)
 */

/** کد ملی ایران: ۱۰ رقم، غیرتکراری، رقم کنترل */
function valid_national_id(string $v): bool
{
    if (!preg_match('/^\d{10}$/', $v) || preg_match('/^(\d)\1{9}$/', $v)) {
        return false;
    }
    $s = 0;
    for ($i = 0; $i < 9; $i++) {
        $s += (int)$v[$i] * (10 - $i);
    }
    $r = $s % 11;
    return $r < 2 ? (int)$v[9] === $r : (int)$v[9] === 11 - $r;
}

/**
 * اعتبارسنجی پاسخ یک سؤال فرم بر اساس نوع آن.
 * خروجی: پیام خطا یا null در صورت معتبر بودن. (مقدار خالی توسط فراخواننده مدیریت می‌شود)
 */
function validate_answer(array $f, string $v, PDO $pdo): ?string
{
    $label = $f['label'];
    switch ($f['type']) {
        case 'national_id':
            return valid_national_id($v) ? null : 'کد ملی معتبر نیست';
        case 'select':
        case 'radio':
            $q = $pdo->prepare('SELECT 1 FROM form_options WHERE field_id=? AND enabled=1 AND label=?');
            $q->execute([$f['id'], $v]);
            return $q->fetch() ? null : "گزینه «{$label}» نامعتبر است";
        case 'yesno':
            return in_array($v, ['yes', 'no'], true) ? null : "«{$label}» نامعتبر است";
        case 'number':
            return preg_match('/^-?\d{1,12}(\.\d{1,4})?$/', $v) ? null : "«{$label}» باید عدد باشد";
        case 'phone':
            return preg_match('/^(\+98|0)?9\d{9}$|^0\d{10}$/', $v) ? null : "شماره‌ی «{$label}» معتبر نیست";
        case 'date':
            return preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $v) ? null : "تاریخ «{$label}» معتبر نیست";
        case 'checkbox':
            return in_array($v, ['1', 'on', 'yes'], true) ? null : "«{$label}» نامعتبر است";
    }
    $max = $f['type'] === 'textarea' ? 2000 : 300;
    return mb_strlen($v) > $max ? "«{$label}» بیش از حد طولانی است" : null;
}
