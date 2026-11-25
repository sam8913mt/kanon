<?php
/**
 * form_defaults.php — فرم اولیه‌ی ثبت‌نام اردو
 * هر اردوی جدید (ساخته‌شده از پنل) این فرم را خودکار می‌گیرد تا ثبت‌نام بدون هیچ تغییر کدی قابل استفاده باشد:
 *   نام، نام خانوادگی، کد ملی، پایه (هفتم/هشتم/نهم)، محله (سی‌متری/مهرآباد/استاد معین)
 * عکس دانش‌آموز با تنظیم «photo_required» اردو مدیریت می‌شود (پیش‌فرض الزامی).
 * سؤال‌ها و گزینه‌ها بعداً از «فرم‌ساز» قابل ویرایش‌اند.
 */
function default_form_spec(): array
{
    return [
        ['first_name', 'نام', 'text', []],
        ['last_name', 'نام خانوادگی', 'text', []],
        ['national_id', 'کد ملی', 'national_id', []],
        ['grade', 'پایه', 'select', ['هفتم', 'هشتم', 'نهم']],
        ['area', 'محله', 'select', ['سی‌متری', 'مهرآباد', 'استاد معین']],
    ];
}

/** فرم اولیه را برای اردو می‌سازد؛ اگر اردو قبلاً سؤالی دارد هیچ تغییری نمی‌دهد (Idempotent). */
function seed_default_form(PDO $pdo, int $campId): void
{
    $has = $pdo->prepare('SELECT COUNT(*) FROM form_fields WHERE camp_id=?');
    $has->execute([$campId]);
    if ((int)$has->fetchColumn() > 0) {
        return;
    }
    $pdo->prepare("INSERT IGNORE INTO camp_forms(camp_id,title,intro,enabled) VALUES(?,'فرم ثبت‌نام','',1)")->execute([$campId]);
    $f = $pdo->prepare('INSERT INTO form_fields(camp_id,name,label,type,required,enabled,sort_order) VALUES(?,?,?,?,1,1,?)');
    $o = $pdo->prepare('INSERT INTO form_options(field_id,label,enabled,sort_order) VALUES(?,?,1,?)');
    foreach (default_form_spec() as $i => [$name, $label, $type, $options]) {
        $f->execute([$campId, $name, $label, $type, $i + 1]);
        $fid = (int)$pdo->lastInsertId();
        foreach ($options as $k => $opt) {
            $o->execute([$fid, $opt, $k + 1]);
        }
    }
}
