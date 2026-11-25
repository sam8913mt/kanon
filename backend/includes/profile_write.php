<?php
/**
 * profile_write.php — نوشتن اطلاعات پروفایل: اعلان‌ها، Timeline، اعتبارسنجی، رکوردهای بخش‌ها، فایل‌ها و هوک‌های ثبت‌نام/اردو
 * اعتبارسنجی نهایی همیشه سمت سرور است. حذف = Soft Delete (deleted_at/archived_at)؛ سوابق هرگز پاک نمی‌شوند.
 */

/* ───────────── اعلان و Timeline ───────────── */

function pf_notify(int $mid, string $type, string $title, ?string $body = null, ?string $link = null): void
{
    db()->prepare('INSERT INTO member_notifications(member_id,type,title,body,link) VALUES(?,?,?,?,?)')
        ->execute([$mid, $type, mb_substr($title, 0, 200), $body === null ? null : mb_substr($body, 0, 500), $link]);
}

function pf_timeline(int $mid, string $type, string $title, ?string $refType = null, ?int $refId = null, ?string $at = null): void
{
    db()->prepare('INSERT INTO member_timeline(member_id,event_type,title,ref_type,ref_id,occurred_at) VALUES(?,?,?,?,?,?)')
        ->execute([$mid, $type, mb_substr($title, 0, 255), $refType, $refId, $at ?: date('Y-m-d H:i:s')]);
}

/** پس از ثبت‌نام موفق در اردو */
function pf_after_register(int $mid, int $regId, string $campTitle): void
{
    pf_timeline($mid, 'registration', "ثبت‌نام در اردوی «$campTitle»", 'registration', $regId);
    pf_notify($mid, 'registration', "ثبت‌نام شما در «$campTitle» ثبت شد", 'پس از بررسی، وضعیت ثبت‌نام از همین‌جا اعلام می‌شود.', '/profile#camps');
    log_member($mid, 'member_register', $campTitle);
}

/**
 * تغییر وضعیت ثبت‌نام‌ها (تکی یا گروهی) همراه با اعلان و Timeline برای ثبت‌نام‌هایی که واقعاً تغییر کرده‌اند.
 * دسترسی اردو قبل از فراخوانی در registrations.php بررسی شده است.
 */
function pf_reg_status_apply(PDO $pdo, array $ids, string $status): void
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return;
    }
    $in = implode(',', $ids);
    $rows = $pdo->query("SELECT r.id,r.member_id,r.status,c.title FROM registrations r JOIN camps c ON c.id=r.camp_id WHERE r.id IN ($in)")->fetchAll();
    $pdo->prepare("UPDATE registrations SET status=?, reviewed_at=IF(?='new', NULL, NOW()) WHERE id IN ($in)")->execute([$status, $status]);
    $msg = ['approved' => 'تأیید شد', 'rejected' => 'رد شد', 'cancelled' => 'لغو شد', 'reviewed' => 'بررسی شد'];
    foreach ($rows as $r) {
        if (!$r['member_id'] || $r['status'] === $status || !isset($msg[$status])) {
            continue;
        }
        $t = "ثبت‌نام شما در «{$r['title']}» {$msg[$status]}";
        pf_notify((int)$r['member_id'], 'registration_status', $t, null, '/profile#camps');
        pf_timeline((int)$r['member_id'], 'registration_status', $t, 'registration', (int)$r['id']);
    }
}

/** ثبت وضعیت حضور */
function pf_reg_attendance(PDO $pdo, int $regId, string $att): void
{
    $s = $pdo->prepare('SELECT r.member_id,r.attendance,c.title FROM registrations r JOIN camps c ON c.id=r.camp_id WHERE r.id=?');
    $s->execute([$regId]);
    $r = $s->fetch();
    if (!$r) {
        out(false, 'یافت نشد', null, 404);
    }
    $pdo->prepare('UPDATE registrations SET attendance=? WHERE id=?')->execute([$att, $regId]);
    if ($r['member_id'] && $r['attendance'] !== $att && $att === 'attended') {
        pf_timeline((int)$r['member_id'], 'attendance', "شرکت در اردوی «{$r['title']}»", 'registration', $regId);
    }
}

/** اعلان تغییر اردو برای ثبت‌نام‌شدگان فعال (فقط وقتی عنوان/تاریخ/مکان/لغو تغییر کرده باشد) */
function pf_camp_changed(PDO $pdo, int $campId, array $old, array $new): void
{
    $diff = fn(string $k) => array_key_exists($k, $new) && (string)($new[$k] ?? '') !== (string)($old[$k] ?? '');
    $title = $new['title'] ?? $old['title'];
    if ($diff('camp_status') && $new['camp_status'] === 'cancelled') {
        [$type, $text] = ['camp_changed', "اردوی «$title» لغو شد"];
    } elseif ($diff('start_date') || $diff('end_date')) {
        [$type, $text] = ['camp_time_changed', "زمان اردوی «$title» تغییر کرد"];
    } elseif ($diff('location') || $diff('title')) {
        [$type, $text] = ['camp_changed', "اطلاعات اردوی «$title» تغییر کرد"];
    } else {
        return;
    }
    $q = $pdo->prepare("SELECT DISTINCT member_id FROM registrations WHERE camp_id=? AND member_id IS NOT NULL AND status NOT IN('rejected','cancelled')");
    $q->execute([$campId]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $mid) {
        pf_notify((int)$mid, $type, $text, null, '/profile#camps');
        pf_timeline((int)$mid, 'camp_change', $text, 'camp', $campId);
    }
}

/* ───────────── اعتبارسنجی پروفایل ───────────── */

/**
 * ورودی ویرایش پروفایل (ویرایش جزئی: فقط کلیدهای ارسال‌شده). خروجی: [ستون‌های member_profiles, آرایه‌ی تحصیلی یا null, خطا].
 * $vt: self | admin. کد ملی پس از ثبت فقط توسط Admin قابل تغییر است.
 */
function pf_validate_profile(array $d, string $vt, array $cur, ?array $edu): array
{
    $has = fn(string $k) => array_key_exists($k, $d);
    $t   = fn(string $k) => trim(preg_replace('/\s+/u', ' ', (string)($d[$k] ?? '')));
    $cols = [];
    foreach (['first_name' => 60, 'last_name' => 60] as $k => $max) {
        if ($has($k)) {
            $v = $t($k);
            if (mb_strlen($v) < 2 || mb_strlen($v) > $max) {
                return [null, null, 'نام و نام خانوادگی را کامل وارد کنید'];
            }
            $cols[$k] = $v;
        }
    }
    foreach (['father_name' => 60, 'neighborhood' => 80, 'city' => 80, 'address' => 300] as $k => $max) {
        if ($has($k)) {
            $v = $t($k);
            if (mb_strlen($v) > $max) {
                return [null, null, 'متن یکی از فیلدها بیش از حد طولانی است'];
            }
            $cols[$k] = $v === '' ? null : $v;
        }
    }
    if ($has('national_id')) {
        $v = fa_digits($t('national_id'));
        if ($v !== '' && !valid_national_id($v)) {
            return [null, null, 'کد ملی معتبر نیست'];
        }
        if ($vt === 'self' && !empty($cur['national_id']) && $v !== $cur['national_id']) {
            return [null, null, 'کد ملی پس از ثبت فقط توسط مدیر قابل تغییر است'];
        }
        $cols['national_id'] = $v === '' ? null : $v;
    }
    if ($has('birth_date')) {
        $v = str_replace('-', '/', fa_digits($t('birth_date')));
        if ($v !== '' && !preg_match('#^\d{4}/\d{1,2}/\d{1,2}$#', $v)) {
            return [null, null, 'تاریخ تولد را مثل 1385/05/20 وارد کنید'];
        }
        $cols['birth_date'] = $v === '' ? null : $v;
    }
    if ($has('gender')) {
        $v = $t('gender');
        if ($v !== '' && !isset(PF_GENDER[$v])) {
            return [null, null, 'جنسیت نامعتبر است'];
        }
        $cols['gender'] = $v === '' ? null : $v;
    }
    if ($has('email')) {
        $v = $t('email');
        if ($v !== '' && (mb_strlen($v) > 150 || !filter_var($v, FILTER_VALIDATE_EMAIL))) {
            return [null, null, 'ایمیل معتبر نیست'];
        }
        $cols['email'] = $v === '' ? null : $v;
    }
    if ($has('emergency_phone')) {
        $v = fa_digits($t('emergency_phone'));
        if ($v !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $v)) {
            return [null, null, 'شماره‌ی تماس اضطراری معتبر نیست'];
        }
        $cols['emergency_phone'] = $v === '' ? null : $v;
    }
    if ($has('bio')) {
        $v = trim((string)$d['bio']);
        if (mb_strlen($v) > 1000) {
            return [null, null, 'متن «درباره من» بیش از ۱۰۰۰ کاراکتر است'];
        }
        $cols['bio'] = $v === '' ? null : $v;
    }
    if ($has('interests')) {
        $v = trim((string)$d['interests']);
        if (mb_strlen($v) > 500) {
            return [null, null, 'متن «علایق» بیش از ۵۰۰ کاراکتر است'];
        }
        $cols['interests'] = $v === '' ? null : $v;
    }
    if ($vt === 'admin' && $has('internal_note')) {
        $v = trim((string)$d['internal_note']);
        $cols['internal_note'] = $v === '' ? null : mb_substr($v, 0, 2000);
    }
    // تحصیلی: اگر مقداری تغییر کند، رکورد جدید ساخته می‌شود (سابقه حفظ می‌شود)
    $newEdu = null;
    $lim = ['school' => 150, 'grade' => 60, 'field_of_study' => 100, 'academic_year' => 20];
    $merged = [];
    $changed = false;
    foreach ($lim as $k => $max) {
        $merged[$k] = $edu[$k] ?? null;
        if ($has($k)) {
            $v = $t($k);
            if (mb_strlen($v) > $max) {
                return [null, null, 'متن یکی از فیلدهای تحصیلی بیش از حد طولانی است'];
            }
            $v = $v === '' ? null : $v;
            if ($v !== $merged[$k]) {
                $changed = true;
            }
            $merged[$k] = $v;
        }
    }
    if ($changed) {
        $newEdu = $merged;
    }
    return [$cols, $newEdu, null];
}

/** اعمال ویرایش پروفایل. خروجی: کلیدهای تغییر‌یافته (برای لاگ؛ بدون مقدار) */
function pf_apply_profile(PDO $pdo, int $mid, array $cols, ?array $edu): array
{
    $changed = array_keys($cols);
    if ($cols) {
        $pdo->prepare('UPDATE member_profiles SET ' . implode(',', array_map(fn($c) => "`$c`=?", array_keys($cols))) . ' WHERE member_id=?')
            ->execute([...array_values($cols), $mid]);
    }
    if ($edu) {
        pf_edu_set_current($pdo, $mid, $edu);
        $changed[] = 'education';
    }
    return $changed;
}

/** رکورد تحصیلی جدید را «فعلی» می‌کند؛ رکورد قبلی با تاریخ پایان در سابقه می‌ماند */
function pf_edu_set_current(PDO $pdo, int $mid, array $edu): int
{
    $pdo->prepare('UPDATE member_education SET is_current=0, ended_at=COALESCE(ended_at,CURDATE()) WHERE member_id=? AND is_current=1 AND deleted_at IS NULL')->execute([$mid]);
    $pdo->prepare('INSERT INTO member_education(member_id,school,grade,field_of_study,academic_year,is_current,started_at) VALUES(?,?,?,?,?,1,CURDATE())')
        ->execute([$mid, $edu['school'], $edu['grade'], $edu['field_of_study'], $edu['academic_year']]);
    $id = (int)$pdo->lastInsertId();
    pf_timeline($mid, 'education', 'به‌روزرسانی اطلاعات تحصیلی', 'education', $id);
    return $id;
}

/** تغییر شماره موبایل (شناسه‌ی ورود): اعتبار و یکتایی. خطا یا null */
function pf_change_mobile(PDO $pdo, int $mid, string $mobile): ?string
{
    $mobile = fa_digits(trim($mobile));
    if (!valid_mobile($mobile)) {
        return 'شماره موبایل معتبر نیست (مثل 09123456789)';
    }
    $q = $pdo->prepare('SELECT id FROM members WHERE mobile=? AND id<>?');
    $q->execute([$mobile, $mid]);
    if ($q->fetchColumn()) {
        return 'این شماره برای کاربر دیگری ثبت شده است';
    }
    $pdo->prepare('UPDATE members SET mobile=? WHERE id=?')->execute([$mobile, $mid]);
    return null;
}

/* ───────────── فایل‌ها ───────────── */

function pf_doc_create(PDO $pdo, int $mid, string $cat, string $title, array $file, string $vis, string $by, bool $pdfOk = true): int
{
    [$rel, $mime, $size] = save_private_file($file, $pdfOk, $cat === 'avatar' ? 600 : 1600);
    $pdo->prepare('INSERT INTO member_documents(member_id,category,title,path,mime,size,visibility,uploaded_by) VALUES(?,?,?,?,?,?,?,?)')
        ->execute([$mid, $cat, mb_substr($title, 0, 200), $rel, $mime, $size, $vis, $by]);
    return (int)$pdo->lastInsertId();
}

/** بایگانی مدرک (فایل نگه داشته می‌شود؛ فقط از دید کاربر و لیست‌ها خارج می‌شود) */
function pf_doc_archive(PDO $pdo, ?int $docId, int $mid): void
{
    if ($docId) {
        $pdo->prepare('UPDATE member_documents SET archived_at=NOW() WHERE id=? AND member_id=? AND archived_at IS NULL')->execute([$docId, $mid]);
    }
}

/** تغییر عکس پروفایل؛ عکس قبلی Archive می‌شود */
function pf_set_avatar(PDO $pdo, int $mid, array $file, string $by): int
{
    $id  = pf_doc_create($pdo, $mid, 'avatar', 'عکس پروفایل', $file, 'private', $by, false);
    $old = $pdo->prepare('SELECT avatar_doc_id FROM member_profiles WHERE member_id=?');
    $old->execute([$mid]);
    pf_doc_archive($pdo, (int)$old->fetchColumn() ?: null, $mid);
    $pdo->prepare('UPDATE member_profiles SET avatar_doc_id=? WHERE member_id=?')->execute([$id, $mid]);
    return $id;
}

function pf_remove_avatar(PDO $pdo, int $mid): void
{
    $old = $pdo->prepare('SELECT avatar_doc_id FROM member_profiles WHERE member_id=?');
    $old->execute([$mid]);
    pf_doc_archive($pdo, (int)$old->fetchColumn() ?: null, $mid);
    $pdo->prepare('UPDATE member_profiles SET avatar_doc_id=NULL WHERE member_id=?')->execute([$mid]);
}

/* ───────────── رکوردهای بخش‌ها (Admin) ───────────── */

/** مشخصات ستون‌ها: [نوع، (حداکثر|نقشه‌ی enum)، پیش‌فرض/الزامی] */
function pf_specs(): array
{
    return [
        'education' => ['table' => 'member_education', 'cols' => ['school' => ['s', 150], 'grade' => ['s', 60], 'field_of_study' => ['s', 100], 'academic_year' => ['s', 20],
            'started_at' => ['date'], 'ended_at' => ['date'], 'note' => ['s', 500], 'is_current' => ['bool']]],
        'activities' => ['table' => 'member_activities', 'cols' => ['kind' => ['enum', PF_ACT_KINDS, 'other'], 'title' => ['s', 200, true], 'activity_date' => ['date'],
            'description' => ['s', 2000], 'status' => ['enum', PF_ACT_STATUS, 'done']]],
        'responsibilities' => ['table' => 'member_responsibilities', 'cols' => ['body' => ['enum', PF_BODY, null, true], 'type_id' => ['int'], 'title' => ['s', 100],
            'unit' => ['s', 120], 'status' => ['enum', PF_RESP_STATUS, 'active'], 'description' => ['s', 2000]]],
        'courses' => ['table' => 'member_courses', 'cols' => ['title' => ['s', 200, true], 'organizer' => ['s', 150], 'course_date' => ['date'],
            'status' => ['enum', PF_COURSE_ST, 'completed'], 'result' => ['s', 100]]],
        'books' => ['table' => 'member_books', 'cols' => ['title' => ['s', 200, true], 'author' => ['s', 150], 'read_date' => ['date'], 'note' => ['s', 500]]],
        'achievements' => ['table' => 'member_achievements', 'cols' => ['title' => ['s', 200, true], 'kind' => ['enum', PF_ACH_KINDS, 'other'], 'achieved_at' => ['date'],
            'description' => ['s', 2000], 'status' => ['enum', PF_ACH_STATUS, 'approved']]],
    ];
}

/** پاکسازی و اعتبارسنجی ردیف یک بخش. خروجی: [ردیف، خطا] */
function pf_clean_section(string $sec, array $data, bool $isNew): array
{
    $spec = pf_specs()[$sec]['cols'] ?? null;
    if (!$spec) {
        return [null, 'بخش نامعتبر'];
    }
    $row = [];
    foreach ($spec as $col => $s) {
        $present = array_key_exists($col, $data);
        if (!$present) {
            if ($isNew) {
                $def = $s[0] === 'enum' ? ($s[2] ?? null) : ($s[0] === 'bool' ? 1 : null);
                if ($def !== null) {
                    $row[$col] = $def;
                }
                if (!empty($s[2]) && $s[0] === 's' || ($s[0] === 'enum' && !empty($s[3]))) {
                    return [null, 'فیلد الزامی تکمیل نشده است'];
                }
            }
            continue;
        }
        $v = $data[$col];
        switch ($s[0]) {
            case 's':
                $v = trim(preg_replace('/[ \t]+/u', ' ', (string)$v));
                if (mb_strlen($v) > $s[1]) {
                    return [null, 'متن یکی از فیلدها بیش از حد طولانی است'];
                }
                if ($v === '' && !empty($s[2])) {
                    return [null, 'فیلد الزامی تکمیل نشده است'];
                }
                $row[$col] = $v === '' ? null : $v;
                break;
            case 'date':
                $v = trim((string)$v);
                if ($v !== '' && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1]))) {
                    return [null, 'تاریخ نامعتبر است'];
                }
                $row[$col] = $v === '' ? null : $v;
                break;
            case 'enum':
                if (!isset($s[1][(string)$v])) {
                    return [null, 'مقدار یکی از فیلدهای انتخابی نامعتبر است'];
                }
                $row[$col] = (string)$v;
                break;
            case 'bool':
                $row[$col] = $v ? 1 : 0;
                break;
            case 'int':
                $row[$col] = $v === '' || $v === null ? null : (int)$v;
                break;
        }
    }
    return [$row, null];
}

/**
 * ایجاد/ویرایش ردیف یک بخش برای یک فرد. $docId (اختیاری) = مدرک پیوست‌شده.
 * خروجی: شناسه‌ی ردیف. خطا با out() پایان می‌یابد.
 */
function pf_save_section(PDO $pdo, string $sec, int $mid, int $id, array $data, ?int $docId, int $staffId): int
{
    $table = pf_specs()[$sec]['table'] ?? null;
    if (!$table) {
        out(false, 'بخش نامعتبر', null, 422);
    }
    [$row, $err] = pf_clean_section($sec, $data, $id === 0);
    if ($err) {
        out(false, $err, null, 422);
    }
    if ($sec === 'responsibilities') {
        if (!empty($row['type_id'])) {
            $t = $pdo->prepare('SELECT body,title FROM responsibility_types WHERE id=?');
            $t->execute([$row['type_id']]);
            $type = $t->fetch();
            if (!$type || (isset($row['body']) && $type['body'] !== $row['body'])) {
                out(false, 'نوع مسئولیت با «کانون/هیئت» انتخاب‌شده سازگار نیست', null, 422);
            }
            if (empty($row['title'])) {
                $row['title'] = $type['title'];
            }
        }
        if ($id === 0 && empty($row['title'])) {
            out(false, 'عنوان مسئولیت لازم است', null, 422);
        }
        if (isset($row['status']) && $row['status'] === 'ended') { // تاریخ پایان دیگر از کاربر گرفته نمی‌شود؛ فقط ثبت داخلی
            $row['end_date'] = date('Y-m-d');
        }
    }
    if ($docId) {
        $row['document_id'] = $docId;
    }
    if ($sec === 'achievements' && $id === 0) {
        $row['submitted_by'] = 'admin';
    }
    if ($id) {
        $own = $pdo->prepare("SELECT 1 FROM `$table` WHERE id=? AND member_id=? AND deleted_at IS NULL");
        $own->execute([$id, $mid]);
        if (!$own->fetchColumn()) {
            out(false, 'مورد یافت نشد', null, 404);
        }
        if ($row) {
            $pdo->prepare("UPDATE `$table` SET " . implode(',', array_map(fn($c) => "`$c`=?", array_keys($row))) . ' WHERE id=?')
                ->execute([...array_values($row), $id]);
        }
        return $id;
    }
    $row['member_id'] = $mid;
    if ($sec !== 'education') {
        $row['created_by'] = $staffId ?: null;
    }
    $cols = array_keys($row);
    $pdo->prepare("INSERT INTO `$table`(" . implode(',', array_map(fn($c) => "`$c`", $cols)) . ') VALUES(' . implode(',', array_fill(0, count($cols), '?')) . ')')
        ->execute(array_values($row));
    $new = (int)$pdo->lastInsertId();
    if ($sec === 'education' && !empty($row['is_current'])) {
        $pdo->prepare('UPDATE member_education SET is_current=0, ended_at=COALESCE(ended_at,CURDATE()) WHERE member_id=? AND is_current=1 AND id<>? AND deleted_at IS NULL')->execute([$mid, $new]);
    }
    if ($sec === 'responsibilities') {
        $label = $row['title'] . ' — ' . PF_BODY[$row['body']];
        pf_notify($mid, 'responsibility', "مسئولیت جدید ثبت شد: $label", null, '/profile#responsibilities');
        pf_timeline($mid, 'responsibility', "ثبت مسئولیت جدید: $label", 'responsibility', $new);
    } elseif ($sec === 'achievements' && ($row['status'] ?? 'approved') === 'approved') {
        pf_notify($mid, 'achievement', 'دستاورد جدید ثبت شد: ' . $row['title'], null, '/profile#achievements');
        pf_timeline($mid, 'achievement', 'ثبت دستاورد: ' . $row['title'], 'achievement', $new);
    } elseif ($sec === 'courses') {
        pf_timeline($mid, 'course', 'ثبت دوره: ' . $row['title'], 'course', $new);
    }
    return $new;
}

/** ارسال اعلان به همه‌ی کاربران فعال (اطلاعیه). تعداد گیرندگان را برمی‌گرداند. */
function pf_broadcast(PDO $pdo, string $title, ?string $body): int
{
    $q = $pdo->prepare("INSERT INTO member_notifications(member_id,type,title,body) SELECT id,'announcement',?,? FROM members WHERE status='active'");
    $q->execute([mb_substr($title, 0, 200), $body === null ? null : mb_substr($body, 0, 500)]);
    return $q->rowCount();
}
