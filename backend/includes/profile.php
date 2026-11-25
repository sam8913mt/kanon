<?php
/**
 * profile.php — خواندن پروفایل (منطق مشترک APIهای کاربر، Admin و Editor)
 *
 * Viewer یکی از این‌هاست:  self (خود فرد) | admin | editor
 *  - self   : همه‌ی بخش‌ها به‌جز فیلدهای «فقط مدیر/کارکنان»
 *  - admin  : همه‌چیز
 *  - editor : فقط اطلاعات لازم (نام، تحصیل، ثبت‌نام‌های اردوهای مجاز) و فیلدهای حساس Mask/پنهان
 * همه‌ی بررسی‌ها همین‌جا در PHP انجام می‌شود؛ هیچ‌چیز به UI سپرده نشده است.
 */

const PF_LEVELS      = ['public' => 'عمومی', 'private' => 'خصوصی', 'staff' => 'فقط کارکنان', 'admin' => 'فقط مدیر'];
const PF_STATUS      = ['active' => 'فعال', 'suspended' => 'تعلیق‌شده', 'archived' => 'بایگانی‌شده'];
const PF_REG_STATUS  = ['new' => 'ثبت‌شده', 'reviewed' => 'در حال بررسی', 'approved' => 'تأیید شده', 'rejected' => 'رد شده', 'cancelled' => 'لغو شده'];
const PF_ATTEND      = ['unknown' => 'نامشخص', 'attended' => 'شرکت کرده', 'absent' => 'غایب'];
const PF_GENDER      = ['male' => 'آقا', 'female' => 'خانم'];
const PF_BODY        = ['kanoon' => 'کانون', 'heyat' => 'هیئت'];
const PF_RESP_STATUS = ['active' => 'فعال', 'ended' => 'پایان‌یافته', 'inactive' => 'غیرفعال'];
const PF_ACT_KINDS   = ['membership' => 'عضویت', 'camp' => 'شرکت در اردو', 'program' => 'حضور در برنامه', 'educational' => 'فعالیت آموزشی',
    'cultural' => 'فعالیت فرهنگی', 'responsibility' => 'مسئولیت', 'project' => 'مشارکت در پروژه', 'achievement' => 'افتخار/دستاورد',
    'course' => 'دوره', 'special' => 'برنامه‌ی ویژه', 'other' => 'سایر'];
const PF_ACT_STATUS  = ['planned' => 'برنامه‌ریزی‌شده', 'done' => 'انجام‌شده', 'cancelled' => 'لغو شده'];
const PF_COURSE_ST   = ['registered' => 'ثبت‌نام‌شده', 'in_progress' => 'در حال برگزاری', 'completed' => 'گذرانده', 'failed' => 'ناموفق', 'cancelled' => 'لغو شده'];
const PF_ACH_KINDS   = ['scientific' => 'علمی', 'cultural' => 'فرهنگی', 'sports' => 'ورزشی', 'religious' => 'مذهبی', 'executive' => 'اجرایی', 'other' => 'سایر'];
const PF_ACH_STATUS  = ['pending' => 'در انتظار تأیید', 'approved' => 'تأیید شده', 'rejected' => 'رد شده'];
const PF_DOC_CATS    = ['avatar' => 'عکس پروفایل', 'registration' => 'مدرک ثبت‌نام', 'certificate' => 'گواهی', 'training' => 'فایل آموزشی',
    'activity' => 'مدرک فعالیت', 'other' => 'سایر'];
const PF_DOC_VIS     = ['private' => 'خصوصی (فقط خود فرد و مدیر)', 'admin' => 'فقط مدیر'];
const PF_NOTIF_TYPES = ['welcome' => 'خوش‌آمدگویی', 'registration' => 'ثبت‌نام', 'registration_status' => 'وضعیت ثبت‌نام', 'camp_changed' => 'تغییر اردو',
    'camp_time_changed' => 'تغییر زمان اردو', 'responsibility' => 'مسئولیت', 'announcement' => 'اطلاعیه', 'admin_message' => 'پیام مدیر',
    'achievement' => 'دستاورد', 'account' => 'حساب کاربری'];
const PF_LOG_LABELS  = ['member_signup' => 'ساخت حساب', 'member_login' => 'ورود به حساب', 'member_logout' => 'خروج از حساب',
    'profile_update' => 'ویرایش اطلاعات پروفایل', 'profile_education' => 'ثبت اطلاعات تحصیلی جدید', 'profile_avatar' => 'تغییر عکس پروفایل',
    'member_password_change' => 'تغییر گذرواژه', 'member_sessions_revoke' => 'خروج از نشست‌های دیگر', 'member_register' => 'ثبت‌نام در اردو',
    'profile_doc' => 'مدیریت مدارک', 'profile_achievement' => 'درخواست ثبت دستاورد', 'admin_profile' => 'فعالیت مدیر روی پروفایل'];
const PF_SECTION_LBL = ['member' => 'حساب و اطلاعات شخصی', 'education' => 'اطلاعات تحصیلی', 'activities' => 'فعالیت‌ها', 'responsibilities' => 'مسئولیت‌ها',
    'courses' => 'دوره‌ها', 'books' => 'کتاب‌ها', 'achievements' => 'دستاوردها', 'documents' => 'مدارک', 'notifications' => 'پیام', 'broadcast' => 'اطلاعیه'];

/** دسترسی هر بخش به‌ازای نوع Viewer */
const PF_SECTION_ACCESS = [
    'profile' => ['self', 'admin', 'editor'], 'registrations' => ['self', 'admin', 'editor'],
    'activities' => ['self', 'admin'], 'responsibilities' => ['self', 'admin'], 'courses' => ['self', 'admin'], 'books' => ['self', 'admin'],
    'achievements' => ['self', 'admin'], 'documents' => ['self', 'admin'], 'images' => ['self', 'admin'],
    'notifications' => ['self', 'admin'], 'timeline' => ['self', 'admin'], 'history' => ['self', 'admin'], 'security' => ['self', 'admin'],
];

/* ───────────── Viewer ───────────── */

/** Viewer = خودِ کاربر عمومی (ورود لازم) */
function pf_self(): array
{
    return ['type' => 'self', 'member_id' => require_member()];
}

/** Admin/Editor (با کنترل دامنه) یا خودِ فرد با شناسه‌ی مطابق؛ هر حالت دیگر ۴۰۳ */
function pf_viewer_for(int $targetId): array
{
    $u = current_user();
    if ($u) {
        if ($u['role'] === 'admin') {
            return ['type' => 'admin', 'member_id' => $targetId];
        }
        require_role('profiles');
        if (!pf_editor_may_see($targetId)) {
            out(false, 'دسترسی ندارید', null, 403);
        }
        return ['type' => 'editor', 'member_id' => $targetId];
    }
    $mid = require_member();
    if ($mid !== $targetId) {
        out(false, 'دسترسی ندارید', null, 403); // تغییر ID در URL اطلاعات دیگران را باز نمی‌کند
    }
    return ['type' => 'self', 'member_id' => $mid];
}

/** مدیر فقط روی پروفایلی که خودش ساخته دسترسی کامل دارد؛ در بقیه بازدیدکننده است (مسئولیت و مرتبه) */
function pf_admin_full(int $mid, ?array $row = null): bool
{
    if ($row === null) {
        $q = db()->prepare('SELECT created_by_staff FROM members WHERE id=?');
        $q->execute([$mid]);
        $row = $q->fetch() ?: [];
    }
    $by = (string)($row['created_by_staff'] ?? '');
    return $by !== '' && hash_equals($by, (string)(current_user()['username'] ?? ''));
}

/** Editor پروفایل همه‌ی اعضا را می‌بیند (فیلدهای حساس همچنان Mask/مخفی هستند) */
function pf_editor_may_see(int $memberId): bool
{
    $q = db()->prepare('SELECT 1 FROM members WHERE id=?');
    $q->execute([$memberId]);
    return (bool)$q->fetchColumn();
}

/** برای Admin/Editor تنها با نقش مجاز؛ بخش‌های ممنوع ۴۰۳ */
function pf_require_section(string $section, array $v): void
{
    if (!in_array($v['type'], PF_SECTION_ACCESS[$section] ?? [], true)) {
        out(false, 'دسترسی به این بخش ندارید', null, 403);
    }
}

/* ───────────── فیلدها، سطح دسترسی و Mask ───────────── */

/** key => [برچسب، سطح، رفتار Editor (show|mask|hide)، قابل ویرایش توسط خود فرد] */
function pf_catalog(): array
{
    return [
        'first_name' => ['نام', 'public', 'show', true], 'last_name' => ['نام خانوادگی', 'public', 'show', true],
        'father_name' => ['نام پدر', 'private', 'hide', true], 'national_id' => ['کد ملی', 'private', 'mask', true],
        'birth_date' => ['تاریخ تولد', 'private', 'hide', true], 'gender' => ['جنسیت', 'private', 'hide', true],
        'mobile' => ['شماره موبایل', 'private', 'mask', true], 'emergency_phone' => ['شماره تماس اضطراری', 'private', 'hide', true],
        'email' => ['ایمیل', 'private', 'hide', true], 'address' => ['آدرس', 'private', 'hide', true],
        'neighborhood' => ['محله', 'private', 'show', true], 'city' => ['شهر', 'private', 'show', true],
        'school' => ['مدرسه', 'private', 'show', true], 'grade' => ['پایه‌ی تحصیلی', 'private', 'show', true],
        'field_of_study' => ['رشته‌ی تحصیلی', 'private', 'show', true],
    ];
}

function pf_mask_nid(string $v): string
{
    return strlen($v) === 10 ? substr($v, 0, 3) . '****' . substr($v, 7) : '****';
}

function pf_mask_mobile(string $v): string
{
    return strlen($v) >= 11 ? substr($v, 0, 4) . '*****' . substr($v, 9) : '***';
}

/** Mask برای Editor؛ سایرین بدون تغییر */
function pf_mask(string $key, ?string $val, string $vt): ?string
{
    if ($vt !== 'editor' || $val === null || $val === '') {
        return $val;
    }
    return $key === 'national_id' ? pf_mask_nid($val) : ($key === 'mobile' ? pf_mask_mobile($val) : $val);
}

/** فهرست فیلدهای شخصی برای نمایش (ترتیب مطابق مستند؛ فیلدهای مخفی برای Editor حذف می‌شوند) */
function pf_fields(array $m, ?array $edu, string $vt): array
{
    $src = $m + ['school' => $edu['school'] ?? null, 'grade' => $edu['grade'] ?? null, 'field_of_study' => $edu['field_of_study'] ?? null];
    $out = [];
    foreach (pf_catalog() as $k => [$label, $level, $editor, $selfEdit]) {
        $val = $src[$k] ?? null;
        $val = ($val === '' || $val === null) ? null : (string)$val;
        if ($vt === 'editor' && $editor === 'hide') {
            continue;
        }
        $masked = $vt === 'editor' && $editor === 'mask' && $val !== null;
        if ($k === 'gender' && $val !== null) {
            $val = PF_GENDER[$val] ?? $val;
        }
        $out[] = ['key' => $k, 'label' => $label, 'value' => $masked ? pf_mask($k, $val, $vt) : $val, 'raw' => $vt === 'editor' ? null : ($src[$k] ?? null),
            'level' => $level, 'level_label' => PF_LEVELS[$level], 'masked' => $masked,
            'editable' => $vt === 'admin' || ($vt === 'self' && $selfEdit)];
    }
    if ($vt === 'admin') {
        $out[] = ['key' => 'internal_note', 'label' => 'یادداشت داخلی مدیر', 'value' => $m['internal_note'] ?: null, 'raw' => $m['internal_note'],
            'level' => 'admin', 'level_label' => PF_LEVELS['admin'], 'masked' => false, 'editable' => true];
    }
    return $out;
}

/* ───────────── خواندن بخش‌ها ───────────── */

function pf_edu_current(PDO $pdo, int $mid): ?array
{
    $s = $pdo->prepare('SELECT * FROM member_education WHERE member_id=? AND is_current=1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 1');
    $s->execute([$mid]);
    return $s->fetch() ?: null;
}

function pf_scalar(PDO $pdo, string $sql, array $p): int
{
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return (int)$s->fetchColumn();
}

function pf_stats(PDO $pdo, int $mid): array
{
    return [
        'camps' => pf_scalar($pdo, "SELECT COUNT(DISTINCT camp_id) FROM registrations WHERE member_id=? AND status<>'cancelled'", [$mid]),
        'attended' => pf_scalar($pdo, "SELECT COUNT(DISTINCT camp_id) FROM registrations WHERE member_id=? AND attendance='attended'", [$mid]),
        'registrations' => pf_scalar($pdo, 'SELECT COUNT(*) FROM registrations WHERE member_id=?', [$mid]),
        'activities' => count(pf_activities($pdo, $mid)),
        'responsibilities' => pf_scalar($pdo, 'SELECT COUNT(*) FROM member_responsibilities WHERE member_id=? AND deleted_at IS NULL', [$mid]),
        'courses' => pf_scalar($pdo, 'SELECT COUNT(*) FROM member_courses WHERE member_id=? AND deleted_at IS NULL', [$mid]),
        'books' => pf_scalar($pdo, 'SELECT COUNT(*) FROM member_books WHERE member_id=? AND deleted_at IS NULL', [$mid]),
        'achievements' => pf_scalar($pdo, "SELECT COUNT(*) FROM member_achievements WHERE member_id=? AND deleted_at IS NULL AND status='approved'", [$mid]),
    ];
}

/** مسئولیت‌های فعلی (فعال و بدون تاریخ پایان گذشته) */
function pf_current_resps(PDO $pdo, int $mid): array
{
    $s = $pdo->prepare("SELECT body,title,unit FROM member_responsibilities WHERE member_id=? AND deleted_at IS NULL AND status='active'
                        AND (end_date IS NULL OR end_date>=CURDATE()) ORDER BY body,id");
    $s->execute([$mid]);
    return array_map(fn($r) => $r + ['body_label' => PF_BODY[$r['body']]], $s->fetchAll());
}

/** بخش اصلی: Header + آمار + فیلدهای شخصی + تحصیلی */
function pf_profile(int $mid, array $v): array
{
    $pdo = db();
    $s = $pdo->prepare('SELECT m.id,m.mobile,m.status,m.staff_user_id,m.created_at,m.last_login_at,m.last_activity_at,m.password_changed_at,
        p.first_name,p.last_name,p.father_name,p.national_id,p.birth_date,p.gender,p.email,p.emergency_phone,p.address,p.neighborhood,p.city,p.bio,p.avatar_doc_id,p.member_rank,p.interests,p.is_top,p.internal_note,m.created_by_staff,
        u.username staff_username, u.role staff_role, u.active staff_active, ro.title staff_role_title
        FROM members m JOIN member_profiles p ON p.member_id=m.id
        LEFT JOIN users u ON u.id=m.staff_user_id LEFT JOIN roles ro ON ro.name=u.role WHERE m.id=?');
    $s->execute([$mid]);
    $m = $s->fetch();
    if (!$m) {
        out(false, 'پروفایل یافت نشد', null, 404);
    }
    $vt  = $v['type'];
    $edu = pf_edu_current($pdo, $mid);
    $hist = $pdo->prepare('SELECT id,school,grade,field_of_study,academic_year,is_current,started_at,ended_at,note,created_at
                           FROM member_education WHERE member_id=? AND deleted_at IS NULL ORDER BY id DESC');
    $hist->execute([$mid]);

    $header = ['id' => (int)$m['id'], 'avatar_id' => $m['avatar_doc_id'] ? (int)$m['avatar_doc_id'] : null,
        'first_name' => $m['first_name'], 'last_name' => $m['last_name'], 'full_name' => pf_fullname($m['first_name'], $m['last_name']),
        'username' => $vt === 'editor' ? null : (($m['staff_username'] && strpos((string)$m['staff_username'], 'member-') !== 0) ? $m['staff_username'] : $m['mobile']),
        'mobile' => pf_mask('mobile', $m['mobile'], $vt), 'email' => $vt === 'editor' ? null : $m['email'],
        'joined_at' => $m['created_at'], 'status' => $m['status'], 'status_label' => PF_STATUS[$m['status']],
        'role' => $m['staff_user_id'] ? 'staff' : 'member', 'role_title' => $m['staff_role_title'] ?: 'عضو',
        'last_login_at' => $vt === 'editor' ? null : $m['last_login_at'], 'last_activity_at' => $vt === 'editor' ? null : $m['last_activity_at'],
        'member_rank' => $vt === 'editor' ? null : $m['member_rank'], 'is_top' => (int)$m['is_top'],
        'responsibilities' => $vt === 'editor' ? [] : pf_current_resps($pdo, $mid)];

    $res = ['header' => $header, 'stats' => $vt === 'editor' ? null : pf_stats($pdo, $mid),
        'fields' => pf_fields($m, $edu, $vt), 'bio' => $vt === 'editor' ? null : $m['bio'], 'interests' => $vt === 'editor' ? null : $m['interests'],
        'education' => ['current' => $edu, 'history' => $hist->fetchAll()],
        'meta' => ['levels' => PF_LEVELS, 'gender' => PF_GENDER, 'statuses' => PF_STATUS],
        'viewer' => ['type' => $vt, 'can_manage' => $vt === 'admin' && pf_admin_full($mid, $m), 'can_rank' => $vt === 'admin']];
    if ($vt === 'admin') {
        $res['staff_link'] = $m['staff_user_id'] ? ['user_id' => (int)$m['staff_user_id'], 'username' => $m['staff_username'], 'role' => $m['staff_role'], 'active' => (int)$m['staff_active']] : null;
    }
    return $res;
}

function pf_fullname(?string $f, ?string $l): string
{
    return trim(((string)$f) . ' ' . ((string)$l));
}

/** وضعیت نهایی خوانا برای ثبت‌نام */
function pf_final_label(string $status, string $att): string
{
    if (in_array($status, ['cancelled', 'rejected'], true)) {
        return PF_REG_STATUS[$status];
    }
    if ($status === 'approved') {
        return $att === 'attended' ? 'تأیید شده — شرکت کرده' : ($att === 'absent' ? 'تأیید شده — غایب' : 'تأیید شده');
    }
    return $status === 'reviewed' ? 'در حال بررسی' : 'در انتظار بررسی';
}

/** ثبت‌نام‌ها؛ با $regId جزئیات یک ثبت‌نام (پاسخ‌ها، عکس، یادداشت) */
function pf_registrations(PDO $pdo, int $mid, array $v, ?int $regId = null): array
{
    $scope = $v['type'] === 'editor' ? camp_scope_sql('camp_id', 'r') : '1=1';
    $sql = "SELECT r.id,r.camp_id,r.status,r.attendance,r.photo,r.created_at,r.reviewed_at,r.note_visible,r.review_note,
                   c.title,c.slug,c.cover,c.start_date,c.end_date,c.location
            FROM registrations r JOIN camps c ON c.id=r.camp_id WHERE r.member_id=? AND $scope" . ($regId ? ' AND r.id=?' : '') . ' ORDER BY r.id DESC';
    $s = $pdo->prepare($sql);
    $s->execute($regId ? [$mid, $regId] : [$mid]);
    $rows = $s->fetchAll();
    if ($regId && !$rows) {
        out(false, 'ثبت‌نام یافت نشد', null, 404);
    }
    $ans = $pdo->prepare('SELECT id, field_label label, answer FROM registration_answers WHERE registration_id=? ORDER BY id');
    $pay = $rows ? $pdo->query('SELECT registration_id,status FROM payment_receipts WHERE registration_id IN (' . implode(',', array_map(fn($r) => (int)$r['id'], $rows)) . ')')->fetchAll(PDO::FETCH_KEY_PAIR) : [];
    $items = [];
    foreach ($rows as $r) {
        $it = ['id' => (int)$r['id'], 'code' => 'R' . str_pad((string)$r['id'], 6, '0', STR_PAD_LEFT),
            'camp' => ['id' => (int)$r['camp_id'], 'title' => $r['title'], 'slug' => $r['slug'], 'cover' => $r['cover'],
                'start' => $r['start_date'], 'end' => $r['end_date'], 'location' => $r['location']],
            'status' => $r['status'], 'status_label' => PF_REG_STATUS[$r['status']],
            'review_label' => $r['status'] === 'new' ? 'در انتظار بررسی' : ($r['status'] === 'cancelled' ? 'لغو شده' : 'بررسی شده'),
            'attendance' => $r['attendance'], 'attendance_label' => PF_ATTEND[$r['attendance']],
            'final_label' => pf_final_label($r['status'], $r['attendance']), 'created_at' => $r['created_at'], 'reviewed_at' => $r['reviewed_at'],
            'payment' => isset($pay[$r['id']]) ? ['status' => $pay[$r['id']], 'label' => PAY_LABELS[$pay[$r['id']]] ?? ''] : null];
        if ($regId) {
            $ans->execute([$r['id']]);
            // فایل‌های ثبت‌نام فقط از /api/reg_file.php (با کنترل دسترسی) در دسترس‌اند؛ مسیر واقعی فایل هرگز به کلاینت نمی‌رود
            $it['answers'] = array_map(fn($a) => is_reg_file_ref($a['answer'])
                ? ['label' => $a['label'], 'value' => '', 'is_file' => true, 'url' => '/api/reg_file.php?id=' . (int)$r['id'] . '&a=' . (int)$a['id']]
                : ['label' => $a['label'], 'value' => $a['answer'], 'is_file' => false, 'url' => null], $ans->fetchAll());
            $it['photo'] = $r['photo'] ? '/api/reg_file.php?id=' . (int)$r['id'] : null;
            $showNote = $v['type'] !== 'self' || (int)$r['note_visible'] === 1;
            $it['note'] = $showNote && $r['review_note'] ? $r['review_note'] : null;
            if ($v['type'] !== 'self') {
                $it['note_visible'] = (int)$r['note_visible'];
            }
        } else {
            $it['photo'] = $r['photo'] ? '/api/reg_file.php?id=' . (int)$r['id'] : null;
        }
        $items[] = $it;
    }
    return $regId ? $items[0] : ['items' => $items, 'meta' => ['statuses' => PF_REG_STATUS, 'attendance' => PF_ATTEND]];
}

/** کتاب‌های خوانده‌شده (ثبت توسط مدیر) */
function pf_books(PDO $pdo, int $mid): array
{
    $s = $pdo->prepare('SELECT id,title,author,read_date,note FROM member_books WHERE member_id=? AND deleted_at IS NULL
        ORDER BY COALESCE(read_date,DATE(created_at)) DESC,id DESC');
    $s->execute([$mid]);
    return ['items' => array_map(fn($r) => ['id' => (int)$r['id'], 'title' => $r['title'], 'author' => $r['author'], 'read_date' => $r['read_date'], 'note' => $r['note']], $s->fetchAll()), 'meta' => []];
}

/** فعالیت‌ها: ردیف‌های ثبت‌شده + رویدادهای سیستمی (عضویت، اردوی شرکت‌کرده، مسئولیت، دوره، دستاورد) */
function pf_activities(PDO $pdo, int $mid): array
{
    $items = [];
    $q = $pdo->prepare('SELECT a.*,d.id doc FROM member_activities a LEFT JOIN member_documents d ON d.id=a.document_id AND d.archived_at IS NULL
                        WHERE a.member_id=? AND a.deleted_at IS NULL');
    $q->execute([$mid]);
    foreach ($q->fetchAll() as $a) {
        $items[] = ['id' => (int)$a['id'], 'source' => 'manual', 'editable' => true, 'kind' => $a['kind'], 'kind_label' => PF_ACT_KINDS[$a['kind']] ?? 'سایر',
            'title' => $a['title'], 'date' => $a['activity_date'] ?: substr((string)$a['created_at'], 0, 10), 'description' => $a['description'],
            'status' => $a['status'], 'status_label' => PF_ACT_STATUS[$a['status']] ?? '', 'doc_id' => $a['doc'] ? (int)$a['doc'] : null];
    }
    $sys = function (string $kind, string $title, ?string $date, string $desc = '') use (&$items) {
        $items[] = ['id' => null, 'source' => 'system', 'editable' => false, 'kind' => $kind, 'kind_label' => PF_ACT_KINDS[$kind], 'title' => $title,
            'date' => $date ? substr($date, 0, 10) : null, 'description' => $desc, 'status' => 'done', 'status_label' => PF_ACT_STATUS['done'], 'doc_id' => null];
    };
    $r = $pdo->prepare('SELECT created_at FROM members WHERE id=?');
    $r->execute([$mid]);
    $sys('membership', 'عضویت در کانون', $r->fetchColumn());
    $r = $pdo->prepare("SELECT c.title,c.start_date,r.created_at FROM registrations r JOIN camps c ON c.id=r.camp_id WHERE r.member_id=? AND r.attendance='attended'");
    $r->execute([$mid]);
    foreach ($r->fetchAll() as $x) {
        $sys('camp', 'شرکت در اردوی «' . $x['title'] . '»', $x['start_date'] ?: $x['created_at']);
    }
    $r = $pdo->prepare('SELECT body,title,unit,start_date,created_at FROM member_responsibilities WHERE member_id=? AND deleted_at IS NULL');
    $r->execute([$mid]);
    foreach ($r->fetchAll() as $x) {
        $sys('responsibility', $x['title'] . ' — ' . PF_BODY[$x['body']], $x['start_date'] ?: $x['created_at'], (string)$x['unit']);
    }
    $r = $pdo->prepare("SELECT title,course_date,created_at FROM member_courses WHERE member_id=? AND deleted_at IS NULL AND status='completed'");
    $r->execute([$mid]);
    foreach ($r->fetchAll() as $x) {
        $sys('course', 'دوره‌ی «' . $x['title'] . '»', $x['course_date'] ?: $x['created_at']);
    }
    $r = $pdo->prepare("SELECT title,achieved_at,created_at FROM member_achievements WHERE member_id=? AND deleted_at IS NULL AND status='approved'");
    $r->execute([$mid]);
    foreach ($r->fetchAll() as $x) {
        $sys('achievement', $x['title'], $x['achieved_at'] ?: $x['created_at']);
    }
    usort($items, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));
    return $items;
}

function pf_responsibilities(PDO $pdo, int $mid): array
{
    $s = $pdo->prepare('SELECT id,body,type_id,title,unit,start_date,end_date,status,description,created_at FROM member_responsibilities
                        WHERE member_id=? AND deleted_at IS NULL ORDER BY COALESCE(start_date,DATE(created_at)) DESC,id DESC');
    $s->execute([$mid]);
    $cur = $hist = [];
    foreach ($s->fetchAll() as $r) {
        $r['id'] = (int)$r['id'];
        $r['body_label'] = PF_BODY[$r['body']];
        $r['status_label'] = PF_RESP_STATUS[$r['status']];
        $r['is_current'] = $r['status'] === 'active' && (!$r['end_date'] || $r['end_date'] >= date('Y-m-d'));
        if ($r['is_current']) {
            $cur[] = $r;
        } else {
            $hist[] = $r;
        }
    }
    return ['current' => $cur, 'history' => $hist, 'meta' => ['bodies' => PF_BODY, 'statuses' => PF_RESP_STATUS, 'types' => pf_resp_types($pdo)]];
}

/** انواع فعال مسئولیت به تفکیک کانون/هیئت (قابل مدیریت از پنل) */
function pf_resp_types(PDO $pdo): array
{
    $out = ['kanoon' => [], 'heyat' => []];
    foreach ($pdo->query('SELECT id,body,title FROM responsibility_types WHERE enabled=1 ORDER BY sort_order,id') as $t) {
        $out[$t['body']][] = ['id' => (int)$t['id'], 'title' => $t['title']];
    }
    return $out;
}

/** مدرکِ پیوست‌شده‌ی یک رکورد (فعال) */
function pf_doc_ref($docId): ?int
{
    return $docId ? (int)$docId : null;
}

function pf_courses(PDO $pdo, int $mid): array
{
    $s = $pdo->prepare('SELECT c.id,c.title,c.organizer,c.course_date,c.status,c.result,d.id doc FROM member_courses c
        LEFT JOIN member_documents d ON d.id=c.document_id AND d.archived_at IS NULL WHERE c.member_id=? AND c.deleted_at IS NULL
        ORDER BY COALESCE(c.course_date,DATE(c.created_at)) DESC,c.id DESC');
    $s->execute([$mid]);
    $items = array_map(fn($r) => ['id' => (int)$r['id'], 'title' => $r['title'], 'organizer' => $r['organizer'], 'course_date' => $r['course_date'],
        'status' => $r['status'], 'status_label' => PF_COURSE_ST[$r['status']], 'result' => $r['result'], 'doc_id' => pf_doc_ref($r['doc'])], $s->fetchAll());
    return ['items' => $items, 'meta' => ['statuses' => PF_COURSE_ST]];
}

function pf_achievements(PDO $pdo, int $mid): array
{
    $s = $pdo->prepare('SELECT a.id,a.title,a.kind,a.achieved_at,a.description,a.status,a.submitted_by,d.id doc FROM member_achievements a
        LEFT JOIN member_documents d ON d.id=a.document_id AND d.archived_at IS NULL WHERE a.member_id=? AND a.deleted_at IS NULL
        ORDER BY COALESCE(a.achieved_at,DATE(a.created_at)) DESC,a.id DESC');
    $s->execute([$mid]);
    $items = array_map(fn($r) => ['id' => (int)$r['id'], 'title' => $r['title'], 'kind' => $r['kind'], 'kind_label' => PF_ACH_KINDS[$r['kind']] ?? 'سایر',
        'achieved_at' => $r['achieved_at'], 'description' => $r['description'], 'status' => $r['status'], 'status_label' => PF_ACH_STATUS[$r['status']],
        'submitted_by' => $r['submitted_by'], 'doc_id' => pf_doc_ref($r['doc'])], $s->fetchAll());
    return ['items' => $items, 'meta' => ['kinds' => PF_ACH_KINDS, 'statuses' => PF_ACH_STATUS]];
}

/** مدارک: فایل‌های خصوصی + عکس/فایل‌های ثبت‌نام (به ثبت‌نام مرتبط) */
function pf_documents(PDO $pdo, int $mid, array $v): array
{
    $items = [];
    $s = $pdo->prepare('SELECT id,category,title,mime,size,visibility,uploaded_by,created_at FROM member_documents
                        WHERE member_id=? AND archived_at IS NULL AND category<>\'avatar\' ORDER BY id DESC');
    $s->execute([$mid]);
    foreach ($s->fetchAll() as $d) {
        if ($d['visibility'] === 'admin' && $v['type'] !== 'admin') {
            continue;
        }
        $items[] = ['source' => 'document', 'id' => (int)$d['id'], 'category' => $d['category'], 'category_label' => PF_DOC_CATS[$d['category']],
            'title' => $d['title'] ?: PF_DOC_CATS[$d['category']], 'mime' => $d['mime'], 'size' => (int)$d['size'], 'is_image' => str_starts_with($d['mime'], 'image/'),
            'visibility' => $d['visibility'], 'visibility_label' => PF_DOC_VIS[$d['visibility']], 'created_at' => $d['created_at'],
            'removable' => $v['type'] === 'admin' || $d['uploaded_by'] === 'member'];
    }
    $r = $pdo->prepare('SELECT r.id,r.photo,r.created_at,c.title camp FROM registrations r JOIN camps c ON c.id=r.camp_id WHERE r.member_id=? ORDER BY r.id DESC');
    $r->execute([$mid]);
    $fa = $pdo->prepare('SELECT id,field_label,answer FROM registration_answers WHERE registration_id=?');
    foreach ($r->fetchAll() as $x) {
        $reg = function (string $path, string $title, string $url) use (&$items, $x) {
            $items[] = ['source' => 'registration', 'id' => null, 'category' => 'registration', 'category_label' => PF_DOC_CATS['registration'], 'title' => $title,
                'mime' => str_ends_with($path, '.pdf') ? 'application/pdf' : 'image/webp', 'size' => null, 'is_image' => !str_ends_with($path, '.pdf'),
                'url' => $url, 'visibility' => 'staff', 'visibility_label' => PF_LEVELS['staff'], 'created_at' => $x['created_at'], 'reg_id' => (int)$x['id'], 'removable' => false];
        };
        if ($x['photo']) {
            $reg($x['photo'], 'عکس ثبت‌نام — ' . $x['camp'], '/api/reg_file.php?id=' . (int)$x['id']);
        }
        $fa->execute([$x['id']]);
        foreach ($fa->fetchAll() as $f) {
            if (is_reg_file_ref($f['answer'])) {
                $reg($f['answer'], $f['field_label'] . ' — ' . $x['camp'], '/api/reg_file.php?id=' . (int)$x['id'] . '&a=' . (int)$f['id']);
            }
        }
    }
    return ['items' => $items, 'meta' => ['categories' => array_diff_key(PF_DOC_CATS, ['avatar' => 1, 'registration' => 1]), 'visibility' => PF_DOC_VIS]];
}

/** تصاویر: عکس پروفایل + عکس‌های ثبت‌نام + مدارک تصویری */
function pf_images(PDO $pdo, int $mid, array $v): array
{
    $items = [];
    $a = $pdo->prepare('SELECT d.id,d.created_at FROM member_documents d JOIN member_profiles p ON p.avatar_doc_id=d.id WHERE p.member_id=? AND d.archived_at IS NULL');
    $a->execute([$mid]);
    foreach ($a->fetchAll() as $d) {
        $items[] = ['id' => (int)$d['id'], 'url' => null, 'title' => 'عکس پروفایل', 'source_label' => 'پروفایل', 'level' => 'public', 'level_label' => PF_LEVELS['public'], 'created_at' => $d['created_at']];
    }
    foreach (pf_documents($pdo, $mid, $v)['items'] as $d) {
        if ($d['is_image']) {
            $items[] = ['id' => $d['id'], 'url' => $d['url'] ?? null, 'title' => $d['title'], 'source_label' => $d['source'] === 'registration' ? 'ثبت‌نام اردو' : $d['category_label'],
                'level' => $d['visibility'] === 'staff' ? 'staff' : ($d['visibility'] === 'admin' ? 'admin' : 'private'),
                'level_label' => $d['source'] === 'registration' ? PF_LEVELS['staff'] : $d['visibility_label'], 'created_at' => $d['created_at']];
        }
    }
    return ['items' => $items];
}

function pf_notifications(PDO $pdo, int $mid): array
{
    $s = $pdo->prepare('SELECT id,type,title,body,link,is_read,created_at FROM member_notifications WHERE member_id=? ORDER BY id DESC LIMIT 100');
    $s->execute([$mid]);
    $items = array_map(fn($r) => ['id' => (int)$r['id'], 'type' => $r['type'], 'type_label' => PF_NOTIF_TYPES[$r['type']] ?? 'اعلان', 'title' => $r['title'],
        'body' => $r['body'], 'link' => $r['link'], 'is_read' => (int)$r['is_read'], 'created_at' => $r['created_at']], $s->fetchAll());
    $unread = pf_scalar($pdo, 'SELECT COUNT(*) FROM member_notifications WHERE member_id=? AND is_read=0', [$mid]);
    return ['items' => $items, 'unread' => $unread];
}

function pf_timeline_list(PDO $pdo, int $mid): array
{
    $s = $pdo->prepare('SELECT id,event_type,title,occurred_at FROM member_timeline WHERE member_id=? ORDER BY occurred_at DESC,id DESC LIMIT 200');
    $s->execute([$mid]);
    return ['items' => $s->fetchAll()];
}

/** Activity Log خود فرد (فقط رویدادهای مجاز؛ بدون اطلاعات امنیتی حساس) */
function pf_history(PDO $pdo, int $mid, array $v): array
{
    $in = implode(',', array_map(fn($k) => $pdo->quote($k), array_keys(PF_LOG_LABELS)));
    $s = $pdo->prepare("SELECT l.action,l.detail,l.ip,l.created_at,u.username FROM logs l LEFT JOIN users u ON u.id=l.user_id
                        WHERE l.member_id=? AND l.action IN ($in) ORDER BY l.id DESC LIMIT 150");
    $s->execute([$mid]);
    $items = [];
    foreach ($s->fetchAll() as $l) {
        $detail = '';
        if ($l['action'] === 'admin_profile') {
            $detail = PF_SECTION_LBL[explode(':', (string)$l['detail'])[0]] ?? '';
        } elseif ($l['action'] === 'member_register') {
            $detail = (string)$l['detail'];
        }
        $it = ['action' => $l['action'], 'label' => PF_LOG_LABELS[$l['action']], 'detail' => $detail, 'created_at' => $l['created_at']];
        if ($v['type'] === 'admin') {
            $it['by'] = $l['username'];
            $it['ip'] = $l['ip'];
        }
        $items[] = $it;
    }
    return ['items' => $items];
}

/** امنیت حساب: آخرین ورود/فعالیت/تغییر رمز و نشست‌های فعال */
function pf_security(PDO $pdo, int $mid, array $v): array
{
    $m = $pdo->prepare('SELECT last_login_at,last_activity_at,password_changed_at,status FROM members WHERE id=?');
    $m->execute([$mid]);
    $s = $pdo->prepare('SELECT id,sid_hash,device,ip,created_at,last_seen_at FROM member_sessions WHERE member_id=? AND revoked_at IS NULL ORDER BY last_seen_at DESC LIMIT 20');
    $s->execute([$mid]);
    $cur = $v['type'] === 'self' ? member_sid_hash() : '';
    $sessions = array_map(fn($x) => ['id' => (int)$x['id'], 'device' => $x['device'], 'ip' => member_ip_masked($x['ip']), 'created_at' => $x['created_at'],
        'last_seen_at' => $x['last_seen_at'], 'current' => $cur !== '' && hash_equals($x['sid_hash'], $cur)], $s->fetchAll());
    return $m->fetch() + ['sessions' => $sessions];
}

/** بارگذاری یک بخش بر اساس نام (بعد از کنترل دسترسی) */
function pf_section(string $name, int $mid, array $v): array
{
    if (!isset(PF_SECTION_ACCESS[$name])) {
        out(false, 'بخش نامعتبر', null, 422);
    }
    pf_require_section($name, $v);
    $pdo = db();
    return match ($name) {
        'profile' => pf_profile($mid, $v),
        'registrations' => pf_registrations($pdo, $mid, $v, isset($_GET['reg']) ? (int)$_GET['reg'] : null),
        'activities' => ['items' => pf_activities($pdo, $mid), 'meta' => ['kinds' => array_diff_key(PF_ACT_KINDS, ['membership' => 1]), 'statuses' => PF_ACT_STATUS]],
        'responsibilities' => pf_responsibilities($pdo, $mid),
        'courses' => pf_courses($pdo, $mid),
        'books' => pf_books($pdo, $mid),
        'achievements' => pf_achievements($pdo, $mid),
        'documents' => pf_documents($pdo, $mid, $v),
        'images' => pf_images($pdo, $mid, $v),
        'notifications' => pf_notifications($pdo, $mid),
        'timeline' => pf_timeline_list($pdo, $mid),
        'history' => pf_history($pdo, $mid, $v),
        'security' => pf_security($pdo, $mid, $v),
    };
}
