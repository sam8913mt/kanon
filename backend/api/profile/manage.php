<?php
/**
 * POST /api/profile/manage.php — مدیریت پروفایل کاربران (فقط Admin؛ هر عملیات با لاگ)
 * ورودی: JSON یا multipart (فیلدهای member_id, section, op, id, data[JSON], file)
 *
 * section=member : create | update | set_status | archive | restore | delete | reset_password | link_staff | revoke_sessions | avatar | remove_avatar | message
 * section=education|activities|responsibilities|courses|books|achievements : save | delete | end | toggle | review
 * section=documents : upload | delete       section=broadcast : send
 * حذف از پنل = حذف کامل (فیزیکی) از دیتابیس و فایل‌ها؛ فقط «بایگانی» سوابق را نگه می‌دارد.
 */
require __DIR__ . '/../../includes/bootstrap.php';
$staff = require_role('profiles_manage');
csrf_check();
$pdo = db();
$in  = $_POST ? $_POST : json_input();
$sec = (string)($in['section'] ?? '');
$op  = (string)($in['op'] ?? 'save');
$mid = (int)($in['member_id'] ?? 0);
$id  = (int)($in['id'] ?? 0);
$data = $in['data'] ?? [];
if (is_string($data)) {
    $data = json_decode($data, true);
}
$data = is_array($data) ? $data : [];
$sid  = (int)$staff['id'];

/** لاگ فعالیت مدیر روی پروفایل (بدون مقدار حساس) */
$log = fn(string $detail) => log_act('admin_profile', "$sec:$op$detail", $mid);

if ($sec === 'broadcast') {
    $title = trim((string)($data['title'] ?? ''));
    if ($title === '' || mb_strlen($title) > 200) {
        out(false, 'عنوان اطلاعیه لازم است (حداکثر ۲۰۰ کاراکتر)', null, 422);
    }
    $n = pf_broadcast($pdo, $title, trim((string)($data['body'] ?? '')) ?: null);
    log_act('admin_broadcast', (string)$n);
    out(true, "اطلاعیه برای $n کاربر ارسال شد");
}

if ($sec === 'member' && $op === 'create') {
    $first = trim((string)($data['first_name'] ?? ''));
    $last  = trim((string)($data['last_name'] ?? ''));
    $mob   = fa_digits(trim((string)($data['mobile'] ?? '')));
    $pass  = (string)($data['password'] ?? '');
    if (mb_strlen($first) < 2 || mb_strlen($last) < 2) {
        out(false, 'نام و نام خانوادگی را کامل وارد کنید', null, 422);
    }
    if (!valid_mobile($mob)) {
        out(false, 'شماره موبایل معتبر نیست', null, 422);
    }
    if ($e = password_error($pass)) {
        out(false, $e, null, 422);
    }
    $x = $pdo->prepare('SELECT 1 FROM members WHERE mobile=?');
    $x->execute([$mob]);
    if ($x->fetchColumn()) {
        out(false, 'این شماره قبلاً ثبت شده است', null, 409);
    }
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO members(mobile,password_hash,password_changed_at,created_by_staff) VALUES(?,?,NOW(),?)')->execute([$mob, password_hash($pass, PASSWORD_DEFAULT), (string)($staff['username'] ?? '')]);
    $new = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO member_profiles(member_id,first_name,last_name) VALUES(?,?,?)')->execute([$new, mb_substr($first, 0, 60), mb_substr($last, 0, 60)]);
    $pdo->commit();
    pf_timeline($new, 'membership', 'عضویت در کانون');
    pf_notify($new, 'welcome', 'به کانون رویش خوش آمدید');
    log_act('admin_profile', 'member:create', $new);
    out(true, 'کاربر ساخته شد', ['id' => $new]);
}

// از اینجا به بعد، فرد هدف باید وجود داشته باشد
$t = $pdo->prepare('SELECT id,status,created_by_staff FROM members WHERE id=?');
$t->execute([$mid]);
$target = $t->fetch();
if (!$target) {
    out(false, 'کاربر یافت نشد', null, 404);
}

// مدیر در پروفایل دیگران «بازدیدکننده» است: فقط مسئولیت و مرتبه؛ دسترسی کامل = سازنده‌ی پروفایل (یا خود فرد از پروفایل شخصی)
if (!pf_admin_full($mid, $target)) {
    $visitorOk = in_array($sec, ['responsibilities', 'courses', 'books'], true) || ($sec === 'member' && in_array($op, ['set_rank', 'set_top', 'reset_password', 'set_panel_role'], true));
    if (!$visitorOk) {
        out(false, 'در این پروفایل فقط بازدیدکننده هستید؛ تنها مسئولیت، دوره، کتاب و مرتبه قابل تغییر است', null, 403);
    }
}

if ($sec === 'member') {
    switch ($op) {
        case 'set_rank':
            $rk = trim((string)($data['rank'] ?? ''));
            if (mb_strlen($rk) > 100) {
                out(false, 'مرتبه حداکثر ۱۰۰ کاراکتر باشد', null, 422);
            }
            $pdo->prepare('UPDATE member_profiles SET member_rank=? WHERE member_id=?')->execute([$rk !== '' ? $rk : null, $mid]);
            $log(':rank');
            out(true, 'مرتبه ذخیره شد');
        case 'set_top':
            $on = !empty($data['is_top']) ? 1 : 0;
            $next = (int)$pdo->query('SELECT COALESCE(MAX(top_order),0)+1 FROM member_profiles')->fetchColumn();
            $pdo->prepare('UPDATE member_profiles SET is_top=?, top_order=IF(?=1,?,0) WHERE member_id=?')->execute([$on, $on, $next, $mid]);
            $log(":top$on");
            out(true, $on ? 'به افراد برتر اضافه شد' : 'از افراد برتر حذف شد');
        case 'update':
            $cur = $pdo->prepare('SELECT national_id FROM member_profiles WHERE member_id=?');
            $cur->execute([$mid]);
            [$cols, $edu, $err] = pf_validate_profile($data, 'admin', $cur->fetch() ?: [], pf_edu_current($pdo, $mid));
            if ($err) {
                out(false, $err, null, 422);
            }
            $pdo->beginTransaction();
            if (isset($data['mobile']) && $data['mobile'] !== '' && ($e = pf_change_mobile($pdo, $mid, (string)$data['mobile']))) {
                $pdo->rollBack();
                out(false, $e, null, 422);
            }
            $changed = pf_apply_profile($pdo, $mid, $cols, $edu);
            $pdo->commit();
            $log(':' . implode(',', $changed));
            out(true, 'اطلاعات ذخیره شد');
        case 'set_status':
            $st = (string)($data['status'] ?? '');
            if (!in_array($st, ['active', 'suspended'], true)) {
                out(false, 'وضعیت نامعتبر است', null, 422);
            }
            $pdo->prepare('UPDATE members SET status=?, deleted_at=NULL WHERE id=?')->execute([$st, $mid]);
            if ($st !== 'active') {
                member_revoke_sessions($mid, false);
            }
            $log(":$st");
            out(true, 'وضعیت حساب تغییر کرد');
        case 'archive':
            $pdo->prepare("UPDATE members SET status='archived', deleted_at=NOW() WHERE id=?")->execute([$mid]);
            member_revoke_sessions($mid, false);
            $log('');
            out(true, 'کاربر بایگانی شد (سوابق حفظ شده است)');
        case 'delete': // حذف کامل و بی‌بازگشت کاربر + مدارک + ثبت‌نام‌ها
            member_revoke_sessions($mid, false);
            purge_member($pdo, $mid);
            log_act('admin_profile', "member:delete:$mid");
            out(true, 'کاربر و همه‌ی اطلاعاتش برای همیشه حذف شد');
        case 'restore':
            $pdo->prepare("UPDATE members SET status='active', deleted_at=NULL WHERE id=?")->execute([$mid]);
            $log('');
            out(true, 'کاربر بازگردانی شد');
        case 'reset_password':
            $pass = (string)($data['password'] ?? '');
            if ($e = password_error($pass)) {
                out(false, $e, null, 422);
            }
            $pdo->prepare('UPDATE members SET password_hash=?, password_changed_at=NOW() WHERE id=?')->execute([password_hash($pass, PASSWORD_DEFAULT), $mid]);
            member_revoke_sessions($mid, false);
            $log('');
            out(true, 'گذرواژه تغییر کرد و نشست‌های کاربر بسته شد');
        case 'set_panel_role': // نقش پنل برای این عضو (ورود با شماره موبایل و گذرواژه‌ی پروفایل)؛ خالی = بدون دسترسی
            if (!is_admin() || $mid === current_member_id() || $mid === (int)($staff['mid'] ?? 0)) { // فقط مدیر، و هیچ‌کس برای خودش
                out(false, 'نقش پنل فقط توسط مدیر و برای کاربر دیگر قابل تعیین است', null, 403);
            }
            ensure_role_enum($pdo);
            $role = (string)($data['role'] ?? '');
            if ($role !== '' && !in_array($role, ['admin', 'editor', 'media'], true)) {
                out(false, 'نقش نامعتبر است', null, 422);
            }
            $q = $pdo->prepare('SELECT m.mobile,m.staff_user_id uid,u.username,u.role FROM members m LEFT JOIN users u ON u.id=m.staff_user_id WHERE m.id=?');
            $q->execute([$mid]);
            $cur = $q->fetch();
            $uid = (int)($cur['uid'] ?? 0);
            if ($uid && (hash_equals(trim((string)($CFG['admin']['username'] ?? '')), (string)$cur['username']) || $uid === $sid)) {
                out(false, 'نقش حساب ادمین اصلی یا حساب خودتان از اینجا تغییر نمی‌کند', null, 403);
            }
            if ($role === '') {
                if ($uid) {
                    $pdo->prepare('UPDATE users SET active=0 WHERE id=?')->execute([$uid]);
                }
                $log(':role=none');
                out(true, 'دسترسی به پنل برداشته شد');
            }
            if ($uid) {
                $pdo->prepare('UPDATE users SET role=?, active=1 WHERE id=?')->execute([$role, $uid]);
            } else {
                $un = 'member-' . $cur['mobile'];
                $ex = $pdo->prepare('SELECT 1 FROM users WHERE username=?');
                $ex->execute([$un]);
                if ($ex->fetchColumn()) {
                    $un .= '-' . substr(bin2hex(random_bytes(3)), 0, 5);
                }
                // گذرواژه‌ی تصادفی و ناشناخته: ورود فقط با شماره موبایل و گذرواژه‌ی پروفایل ممکن است
                $pdo->prepare('INSERT INTO users(username,password_hash,role,active) VALUES(?,?,?,1)')
                    ->execute([$un, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $role]);
                $pdo->prepare('UPDATE members SET staff_user_id=? WHERE id=?')->execute([(int)$pdo->lastInsertId(), $mid]);
            }
            $log(':role=' . $role);
            out(true, 'دسترسی پنل ذخیره شد؛ کاربر با شماره موبایل و گذرواژه‌ی پروفایلش وارد می‌شود');
        case 'link_staff': // اتصال به حساب پنل (نقش Admin/Editor)؛ خالی = قطع اتصال
            $uid = (int)($data['user_id'] ?? 0);
            if ($uid) {
                $c = $pdo->prepare('SELECT 1 FROM users WHERE id=?');
                $c->execute([$uid]);
                $used = $pdo->prepare('SELECT 1 FROM members WHERE staff_user_id=? AND id<>?');
                $used->execute([$uid, $mid]);
                if (!$c->fetchColumn() || $used->fetchColumn()) {
                    out(false, 'این حساب پنل وجود ندارد یا به کاربر دیگری متصل است', null, 422);
                }
            }
            $pdo->prepare('UPDATE members SET staff_user_id=? WHERE id=?')->execute([$uid ?: null, $mid]);
            $log(':' . ($uid ?: 'none'));
            out(true, 'ذخیره شد');
        case 'revoke_sessions':
            $c = member_revoke_sessions($mid, false);
            $log('');
            out(true, "$c نشست بسته شد");
        case 'avatar':
            if (empty($_FILES['file'])) {
                out(false, 'فایلی ارسال نشد', null, 422);
            }
            pf_set_avatar($pdo, $mid, $_FILES['file'], 'admin');
            $log('');
            out(true, 'عکس پروفایل به‌روز شد');
        case 'remove_avatar': // حذف کامل عکس (ردیف + فایل)
            $av = $pdo->prepare('SELECT d.id,d.path FROM member_documents d JOIN member_profiles p ON p.avatar_doc_id=d.id WHERE p.member_id=?');
            $av->execute([$mid]);
            if ($row = $av->fetch()) {
                $pdo->prepare('UPDATE member_profiles SET avatar_doc_id=NULL WHERE member_id=?')->execute([$mid]);
                $pdo->prepare('DELETE FROM member_documents WHERE id=?')->execute([$row['id']]);
                delete_reg_file($row['path']);
            }
            $log('');
            out(true, 'عکس پروفایل حذف شد');
        case 'message':
            $title = trim((string)($data['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 200) {
                out(false, 'عنوان پیام لازم است', null, 422);
            }
            pf_notify($mid, 'admin_message', $title, trim((string)($data['body'] ?? '')) ?: null);
            $log('');
            out(true, 'پیام ارسال شد');
    }
    out(false, 'عملیات نامعتبر', null, 422);
}

if ($sec === 'documents') {
    if ($op === 'upload') {
        if (empty($_FILES['file'])) {
            out(false, 'فایلی ارسال نشد', null, 422);
        }
        $cat = (string)($data['category'] ?? 'other');
        $vis = (string)($data['visibility'] ?? 'private');
        if (!isset(PF_DOC_CATS[$cat]) || in_array($cat, ['avatar', 'registration'], true) || !isset(PF_DOC_VIS[$vis])) {
            out(false, 'دسته یا سطح دسترسی نامعتبر است', null, 422);
        }
        pf_doc_create($pdo, $mid, $cat, trim((string)($data['title'] ?? '')), $_FILES['file'], $vis, 'admin');
        $log('');
        out(true, 'مدرک افزوده شد');
    }
    if ($op === 'delete') {
        $dq = $pdo->prepare('SELECT path FROM member_documents WHERE id=? AND member_id=?');
        $dq->execute([$id, $mid]);
        $path = $dq->fetchColumn();
        if ($path !== false) {
            $pdo->prepare('UPDATE member_profiles SET avatar_doc_id=NULL WHERE member_id=? AND avatar_doc_id=?')->execute([$mid, $id]);
            $pdo->prepare('DELETE FROM member_documents WHERE id=? AND member_id=?')->execute([$id, $mid]);
            delete_reg_file($path);
        }
        $log('');
        out(true, 'مدرک برای همیشه حذف شد');
    }
    out(false, 'عملیات نامعتبر', null, 422);
}

$table = pf_specs()[$sec]['table'] ?? null;
if (!$table) {
    out(false, 'بخش نامعتبر', null, 422);
}
if ($op === 'delete') { // حذف کامل
    if ($sec === 'education') {
        out(false, 'سابقه‌ی تحصیلی حذف نمی‌شود؛ برای اصلاح، ویرایش کنید', null, 422);
    }
    $q = $pdo->prepare("DELETE FROM `$table` WHERE id=? AND member_id=?");
    $q->execute([$id, $mid]);
    $log(":$id");
    out(true, 'برای همیشه حذف شد');
}
if ($sec === 'responsibilities' && in_array($op, ['end', 'toggle'], true)) {
    $r = $pdo->prepare('SELECT status FROM member_responsibilities WHERE id=? AND member_id=? AND deleted_at IS NULL');
    $r->execute([$id, $mid]);
    $cur = $r->fetchColumn();
    if ($cur === false) {
        out(false, 'مورد یافت نشد', null, 404);
    }
    if ($op === 'end') {
        $pdo->prepare("UPDATE member_responsibilities SET status='ended', end_date=IF(end_date IS NULL OR end_date>CURDATE(), CURDATE(), end_date) WHERE id=?")->execute([$id]);
    } else {
        $pdo->prepare("UPDATE member_responsibilities SET status=?, end_date=IF(?='active', NULL, end_date) WHERE id=?")
            ->execute([$cur === 'active' ? 'inactive' : 'active', $cur === 'active' ? 'inactive' : 'active', $id]);
    }
    $log(":$id");
    out(true, 'ذخیره شد');
}
if ($sec === 'achievements' && $op === 'review') {
    $st = (string)($data['status'] ?? '');
    if (!in_array($st, ['approved', 'rejected'], true)) {
        out(false, 'وضعیت نامعتبر', null, 422);
    }
    $a = $pdo->prepare('SELECT title FROM member_achievements WHERE id=? AND member_id=? AND deleted_at IS NULL');
    $a->execute([$id, $mid]);
    $title = $a->fetchColumn();
    if ($title === false) {
        out(false, 'مورد یافت نشد', null, 404);
    }
    $pdo->prepare('UPDATE member_achievements SET status=? WHERE id=?')->execute([$st, $id]);
    pf_notify($mid, 'achievement', $st === 'approved' ? "دستاورد «$title» تأیید شد" : "درخواست دستاورد «$title» تأیید نشد", null, '/profile#achievements');
    if ($st === 'approved') {
        pf_timeline($mid, 'achievement', "ثبت دستاورد: $title", 'achievement', $id);
    }
    $log(":$id:$st");
    out(true, 'ذخیره شد');
}
if ($op === 'save') {
    $docId = null;
    if (!empty($_FILES['file']) && !in_array($sec, ['education', 'responsibilities', 'books'], true)) {
        $cat = ['activities' => 'activity', 'courses' => 'certificate', 'achievements' => 'certificate'][$sec] ?? 'other';
        $docId = pf_doc_create($pdo, $mid, $cat, (string)($data['title'] ?? ''), $_FILES['file'], 'private', 'admin');
        if ($id) {
            $old = $pdo->prepare("SELECT document_id FROM `$table` WHERE id=? AND member_id=?");
            $old->execute([$id, $mid]);
            pf_doc_archive($pdo, (int)$old->fetchColumn() ?: null, $mid);
        }
    }
    $new = pf_save_section($pdo, $sec, $mid, $id, $data, $docId, $sid);
    $log(":$new");
    out(true, 'ذخیره شد', ['id' => $new]);
}
out(false, 'عملیات نامعتبر', null, 422);
