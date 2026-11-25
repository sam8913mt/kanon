<?php
/**
 * register.php — ثبت‌نام عمومی در اردو
 * GET  → توکن CSRF و وضعیت «الزام حساب» (requires_login) و ورود کاربر (member)
 * POST (multipart) → اعتبارسنجی کامل سمت سرور، قفل ظرفیت، ذخیره‌ی امن
 *
 * ثبت‌نام مهمان (بدون حساب) و عضو هر دو پشتیبانی می‌شود:
 *  - عضو واردشده: ثبت‌نام به حساب او وصل می‌شود (پروفایل، اعلان‌ها، Timeline).
 *  - مهمان: registrations.member_id = NULL؛ با «کد پیگیری» بعداً از پروفایل می‌تواند به حساب وصل شود (profile/claim.php).
 *  - اگر تنظیم registration_requires_login=1 باشد، ثبت‌نام بدون ورود رد می‌شود.
 * فایل‌های ثبت‌نام (عکس دانش‌آموز و پاسخ‌های تصویر/فایل) در storage/private ذخیره می‌شوند و فقط با /api/reg_file.php و کنترل دسترسی در دسترس‌اند.
 */
require __DIR__ . '/../includes/bootstrap.php';
$pdo = db();
$reqLogin = $pdo->query("SELECT v FROM settings WHERE k='registration_requires_login'")->fetchColumn() === '1';
$memberId = current_member_id();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    out(true, '', ['csrf' => csrf_token(), 'requires_login' => $reqLogin, 'member' => $memberId > 0]);
}
csrf_check();
if ($reqLogin && !$memberId) {
    out(false, 'برای ثبت‌نام در اردو ابتدا وارد پروفایل خود شوید', null, 401);
}
$ip  = $_SERVER['REMOTE_ADDR'] ?? '';

// ─── محدودیت نرخ: هم تلاش‌ها (حتی ناموفق) و هم ثبت‌نام‌های موفق در ساعت
$limit = (int)$CFG['rate_limit_per_hour'];
$a = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE action='register_attempt' AND ip=? AND created_at>NOW()-INTERVAL 1 HOUR");
$a->execute([$ip]);
$n = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE ip=? AND created_at>NOW()-INTERVAL 1 HOUR');
$n->execute([$ip]);
if ((int)$a->fetchColumn() >= $limit * 2 || (int)$n->fetchColumn() >= $limit) {
    out(false, 'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید', null, 429);
}
log_act('register_attempt', (string)($_POST['camp'] ?? ''));

// ─── اردو و وضعیت ثبت‌نام
$c = $pdo->prepare('SELECT * FROM camps WHERE slug=? AND published=1');
$c->execute([(string)($_POST['camp'] ?? '')]);
$camp = $c->fetch();
if (!$camp || $camp['registration_status'] !== 'open') {
    out(false, 'ثبت‌نام این اردو فعال نیست', null, 422);
}
if (!empty($camp['deadline']) && strtotime($camp['deadline'] . ' 23:59:59') < time()) {
    out(false, 'مهلت ثبت‌نام این اردو به پایان رسیده است', null, 422);
}
$cf = $pdo->prepare('SELECT enabled FROM camp_forms WHERE camp_id=?');
$cf->execute([$camp['id']]);
$formEnabled = $cf->fetchColumn();
if ($formEnabled !== false && !(int)$formEnabled) {
    out(false, 'فرم ثبت‌نام این اردو فعال نیست', null, 422);
}

// ─── اعتبارسنجی پاسخ‌ها بر اساس نوع هر سؤال
$fs = $pdo->prepare('SELECT * FROM form_fields WHERE camp_id=? AND enabled=1 ORDER BY sort_order,id');
$fs->execute([$camp['id']]);
$answers = [];
$nid = null;
foreach ($fs->fetchAll() as $f) {
    $v = trim((string)($_POST[$f['name']] ?? ''));
    $isFile = in_array($f['type'], ['image', 'file'], true);
    if ($isFile && !empty($_FILES[$f['name']]['name'])) {
        $v = '@file';
    }
    if ($f['required'] && $v === '') {
        out(false, "«{$f['label']}» الزامی است", null, 422);
    }
    if ($v !== '' && $v !== '@file' && !$isFile && is_reg_file_ref($v)) { // جلوگیری از اشاره‌ی پاسخ متنی به فایل خصوصی
        out(false, "«{$f['label']}» نامعتبر است", null, 422);
    }
    if ($v !== '' && $v !== '@file') {
        $err = validate_answer($f, $v, $pdo);
        if ($err) {
            out(false, $err, null, 422);
        }
        if ($f['type'] === 'national_id') {
            $nid = $v;
        }
    }
    $answers[] = [$f, $v];
}

$isDuplicate = function () use ($pdo, $camp, $nid, $memberId): bool {
    if (!$camp['block_duplicates']) {
        return false;
    }
    if ($nid) {
        $d = $pdo->prepare("SELECT 1 FROM registrations WHERE camp_id=? AND national_id=? AND status<>'cancelled'");
        $d->execute([$camp['id'], $nid]);
        if ($d->fetch()) {
            return true;
        }
    }
    if ($memberId) { // یک حساب برای هر اردو یک ثبت‌نام فعال
        $d = $pdo->prepare("SELECT 1 FROM registrations WHERE camp_id=? AND member_id=? AND status<>'cancelled'");
        $d->execute([$camp['id'], $memberId]);
        return (bool)$d->fetch();
    }
    return false;
};
if ($isDuplicate()) {
    out(false, 'با این کد ملی قبلاً ثبت‌نام شده است', null, 409);
}

$cap   = (int)$camp['capacity'];
$count = function () use ($pdo, $camp): int {
    $q = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE camp_id=? AND status NOT IN('rejected','cancelled')");
    $q->execute([$camp['id']]);
    return (int)$q->fetchColumn();
};
$markFull = fn() => $pdo->prepare("UPDATE camps SET registration_status='full' WHERE id=?")->execute([$camp['id']]);
if ($cap > 0 && $count() >= $cap) {
    $markFull();
    out(false, 'ظرفیت این اردو تکمیل شده است', null, 409);
}

// ─── عکس دانش‌آموز (الزامی بودن قابل تنظیم برای هر اردو)
$photo = null;
$hasPhoto = !empty($_FILES['photo']['name']);
if ($camp['photo_required'] && !$hasPhoto) {
    out(false, 'عکس دانش‌آموز الزامی است', null, 422);
}
$cleanup = function () use (&$photo, &$answers) { // پاک‌سازی فایل‌های یتیم در هر خطا
    delete_reg_file($photo);
    foreach ($answers as $x) {
        if (in_array($x[0]['type'], ['image', 'file'], true) && is_reg_file_ref($x[1])) {
            delete_reg_file($x[1]);
        }
    }
};
try {
    if ($hasPhoto) {
        $photo = save_reg_file($_FILES['photo']); // خصوصی، تصویر کدگذاری‌مجدد و کوچک‌شده
    }
    foreach ($answers as &$an) {
        if ($an[1] === '@file') {
            $an[1] = save_reg_file($_FILES[$an[0]['name']], $an[0]['type'] === 'file');
        }
    }
    unset($an);
} catch (Throwable $x) {
    $cleanup();
    throw $x;
}

// ─── ثبت با قفل ردیف اردو (جلوگیری از رقابت هم‌زمان روی ظرفیت و کد ملی تکراری)
$pdo->beginTransaction();
try {
$pdo->prepare('SELECT id FROM camps WHERE id=? FOR UPDATE')->execute([$camp['id']]);
if ($isDuplicate()) {
    $pdo->rollBack();
    $cleanup();
    out(false, 'با این کد ملی قبلاً ثبت‌نام شده است', null, 409);
}
if ($cap > 0 && $count() >= $cap) {
    $pdo->rollBack();
    $cleanup();
    $markFull();
    out(false, 'ظرفیت این اردو تکمیل شده است', null, 409);
}
// کد پیگیری تصادفی (۱۲ نویسه‌ی hex بزرگ)؛ برای اتصال ثبت‌نام مهمان به حساب استفاده می‌شود
$tracking = '';
do {
    $tracking = strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
    $t = $pdo->prepare('SELECT 1 FROM registrations WHERE tracking_code=?');
    $t->execute([$tracking]);
} while ($t->fetchColumn());
$pdo->prepare("INSERT INTO registrations(camp_id,member_id,national_id,photo,status,tracking_code,ip) VALUES(?,?,?,?,'new',?,?)")
    ->execute([$camp['id'], $memberId ?: null, $nid, $photo, $tracking, $ip]);
$id = (int)$pdo->lastInsertId();
$ins = $pdo->prepare('INSERT INTO registration_answers(registration_id,field_id,field_label,field_name,field_type,answer) VALUES(?,?,?,?,?,?)');
foreach ($answers as [$f, $v]) {
    // برچسب، نام و نوع سؤال هم ذخیره می‌شود (Snapshot) تا با ویرایش/غیرفعال/حذف سؤال، پاسخ‌های قبلی قابل فهم بمانند
    $ins->execute([$id, $f['id'], $f['label'], $f['name'], $f['type'], $v === '@file' ? '' : $v]);
}
$pdo->commit();
} catch (Throwable $x) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $cleanup();
    throw $x;
}
if ($memberId) {
    pf_after_register($memberId, $id, (string)$camp['title']);
} else {
    log_act('register_guest', 'R' . str_pad((string)$id, 6, '0', STR_PAD_LEFT));
}
if ($cap > 0 && $count() >= $cap) {
    $markFull();
}
out(true, 'ثبت‌نام شما با موفقیت انجام شد.', [
    'id'       => 'R' . str_pad((string)$id, 6, '0', STR_PAD_LEFT),
    'tracking' => $tracking,        // کد پیگیری محرمانه (برای اتصال به حساب)
    'guest'    => !$memberId,
]);
