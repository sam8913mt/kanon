<?php
/**
 * crud.php — API عمومی مدیریت موجودیت‌ها (فقط ستون‌های allowlist شده)
 *
 * GET  ?e=camps[&f_camp_id=1]      لیست
 * POST {e, op:'save', id?, data}   ایجاد/ویرایش
 * POST {e, op:'delete', id}        حذف
 * POST {e, op:'duplicate', id}     تکثیر اردو (با فرم)
 * POST {e, op:'reorder', ids:[…]}  ذخیره‌ی ترتیب (Drag & Drop)
 *
 * دسترسی: require_role(area) + برای Editor محدودیت به اردوهای مجاز (camp_editors)
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/entities.php';
require __DIR__ . '/../includes/form_defaults.php';

const NULLABLE_COLS = ['start_date', 'end_date', 'deadline', 'album_date', 'album_id', 'entity_id', 'event_date', 'event_time'];
const ZERO_COLS     = ['capacity', 'sort_order'];
const URL_COLS      = ['url', 'link', 'canonical', 'og_image', 'cover', 'path', 'image'];
const ENUMS = [
    'camp_status'         => ['upcoming', 'running', 'done', 'cancelled'],
    'registration_status' => ['open', 'soon', 'full', 'done', 'cancelled'],
    'type'                => ['text', 'textarea', 'number', 'national_id', 'phone', 'date', 'image', 'file', 'select', 'radio', 'checkbox', 'yesno', 'url'],
    'role'                => ['admin', 'editor', 'media'],
    'body'                => ['kanoon', 'heyat'],
];

/** آیا ردیف (با کلید اصلی) در محدوده‌ی دسترسی کاربر است؟ */
function row_in_scope(PDO $pdo, string $table, string $pk, $id, ?string $scope): bool
{
    if ($scope === null || is_admin()) {
        return true;
    }
    $s = $pdo->prepare("SELECT 1 FROM `$table` WHERE `$pk`=? AND (" . camp_scope_sql($scope) . ')');
    $s->execute([$id]);
    return (bool)$s->fetchColumn();
}

/** آیا اردوی والدِ مقدار ارسالی (camp_id یا field_id) مجاز است؟ */
function parent_ok(PDO $pdo, ?string $scope, array $row, bool $required): bool
{
    if ($scope === null || $scope === 'self' || is_admin()) {
        return true;
    }
    if ($scope === 'album_self') { // آلبوم: مجاز بودن با album_editors در row_in_scope بررسی می‌شود؛ ایجاد برای Editor آزاد است
        return true;
    }
    if ($scope === 'media_own') { // انتقال رسانه فقط به آلبوم مجاز خود Editor
        return empty($row['album_id']) || album_allowed((int)$row['album_id']);
    }
    if (!isset($row[$scope])) {
        return !$required;
    }
    if ($scope === 'camp_id') {
        return camp_allowed((int)$row['camp_id']);
    }
    $s = $pdo->prepare('SELECT camp_id FROM form_fields WHERE id=?');
    $s->execute([$row['field_id']]);
    $c = $s->fetchColumn();
    return $c !== false && camp_allowed((int)$c);
}

function insert_row(PDO $pdo, string $table, array $row): int
{
    $cols = array_keys($row);
    $sql  = "INSERT INTO `$table`(" . implode(',', array_map(fn($c) => "`$c`", $cols)) . ') VALUES(' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($row));
    return (int)$pdo->lastInsertId();
}

/** تکثیر اردو همراه با فرم (سؤال‌ها، گزینه‌ها، تنظیمات فرم). نسخه‌ی کپی پیش‌نویس است. */
function duplicate_camp(PDO $pdo, int $id): int
{
    $o = $pdo->prepare('SELECT * FROM camps WHERE id=?');
    $o->execute([$id]);
    $c = $o->fetch();
    if (!$c) {
        out(false, 'یافت نشد', null, 404);
    }
    unset($c['id'], $c['created_at']);
    $c['slug']     .= '-copy-' . substr(bin2hex(random_bytes(2)), 0, 4);
    $c['title']    .= ' (کپی)';
    $c['published'] = 0;
    $new = insert_row($pdo, 'camps', $c);

    $fs = $pdo->prepare('SELECT * FROM form_fields WHERE camp_id=?');
    $fs->execute([$id]);
    foreach ($fs->fetchAll() as $f) {
        $old = $f['id'];
        unset($f['id']);
        $f['camp_id'] = $new;
        $fid = insert_row($pdo, 'form_fields', $f);
        $pdo->prepare('INSERT INTO form_options(field_id,label,enabled,sort_order) SELECT ?,label,enabled,sort_order FROM form_options WHERE field_id=?')
            ->execute([$fid, $old]);
    }
    $pdo->prepare('INSERT INTO camp_forms(camp_id,title,intro,enabled) SELECT ?,title,intro,enabled FROM camp_forms WHERE camp_id=?')
        ->execute([$new, $id]);
    if (!is_admin()) {
        $pdo->prepare('INSERT IGNORE INTO camp_editors(camp_id,user_id) VALUES(?,?)')->execute([$new, current_user()['id']]);
    }
    return $new;
}

// ───────────────────────────── ورودی و دسترسی
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$in     = $isPost ? json_input() : $_GET;
$name   = (string)($in['e'] ?? '');
$ent    = entities()[$name] ?? null;
if (!$ent) {
    out(false, 'موجودیت نامعتبر', null, 404);
}
$user  = require_role($ent['area']);
$pdo   = db();
$pk    = $ent['pk'] ?? 'id';
$scope = $ent['scope'] ?? null;
$restricted = !is_admin() && $scope !== null;

// ───────────────────────────── GET: لیست
if (!$isPost) {
    $where = ['1=1'];
    $params = [];
    foreach ($ent['cols'] as $c) {
        if (isset($_GET["f_$c"])) {
            $where[]  = "`$c`=?";
            $params[] = $_GET["f_$c"];
        }
    }
    if ($restricted) {
        $where[] = camp_scope_sql($scope);
    }
    if ($name === 'users') { // حساب‌های خودکارِ اعضای دارای نقش پنل (member-…) در این فهرست نیستند؛ از پروفایل عضو مدیریت می‌شوند
        $where[] = "username NOT LIKE 'member-%'";
    }
    $s = $pdo->prepare("SELECT * FROM `$name` WHERE " . implode(' AND ', $where) . " ORDER BY {$ent['order']} LIMIT 500");
    $s->execute($params);
    $rows = $s->fetchAll();
    if ($name === 'users') {
        foreach ($rows as &$r) {
            unset($r['password_hash']);
        }
        unset($r);
    }
    out(true, '', $rows);
}

// ───────────────────────────── POST
csrf_check();
if (!empty($ent['ro'])) {
    out(false, 'فقط خواندنی', null, 403);
}
$op = (string)($in['op'] ?? 'save');

if ($op === 'delete') {
    $id = $in['id'] ?? '';
    if ($name === 'users') {
        $ur = $pdo->prepare('SELECT username FROM users WHERE id=?');
        $ur->execute([$id]);
        $un = $ur->fetchColumn();
        if ($un === false) {
            out(false, 'کاربر پیدا نشد', null, 404);
        }
        if ((int)$id === (int)$user['id']) {
            out(false, 'حساب خودتان را نمی‌توانید حذف کنید', null, 403);
        }
        if (hash_equals(trim((string)($CFG['admin']['username'] ?? '')), (string)$un)) {
            out(false, 'ادمین اصلی (config.php) از اینجا حذف نمی‌شود', null, 403);
        }
    }
    if (!row_in_scope($pdo, $name, $pk, $id, $scope)) {
        out(false, 'این مورد برای شما مجاز نیست', null, 403);
    }
    // حذف کامل (فیزیکی): ردیف + وابسته‌ها (ثبت‌نام‌ها، رسانه‌ی آلبوم، پاسخ‌های سؤال، SEO) + فایل‌های بدون استفاده
    $regFiles = [];
    $pubFiles = [];
    $own = null;
    $coverCol = ['camps' => 'cover', 'albums' => 'cover', 'news' => 'cover', 'heyats' => 'image', 'media' => 'path'][$name] ?? null;
    try {
        $pdo->beginTransaction();
        if ($coverCol) {
            $cq = $pdo->prepare("SELECT `$coverCol` FROM `$name` WHERE `$pk`=?");
            $cq->execute([$id]);
            $own = $cq->fetchColumn() ?: null;
        }
        if ($name === 'camps') {
            $regFiles = purge_registrations($pdo, 'camp_id=?', [$id]);
        }
        if ($name === 'albums') {
            $mq = $pdo->prepare('SELECT path FROM media WHERE album_id=?');
            $mq->execute([$id]);
            $pubFiles = $mq->fetchAll(PDO::FETCH_COLUMN);
            $pdo->prepare('DELETE FROM media WHERE album_id=?')->execute([$id]);
        }
        if ($name === 'form_fields') {
            $aq = $pdo->prepare('SELECT answer FROM registration_answers WHERE field_id=?');
            $aq->execute([$id]);
            $regFiles = array_filter($aq->fetchAll(PDO::FETCH_COLUMN), 'is_reg_file_ref');
            $pdo->prepare('DELETE FROM registration_answers WHERE field_id=?')->execute([$id]);
        }
        $pdo->prepare("DELETE FROM `$name` WHERE `$pk`=?")->execute([$id]);
        $seoEnt = ['camps' => 'camp', 'albums' => 'album', 'news' => 'news', 'pages' => 'page'][$name] ?? null;
        if ($seoEnt) {
            $pdo->prepare('DELETE FROM seo WHERE entity=? AND entity_id=?')->execute([$seoEnt, $id]);
        }
        $pdo->commit();
    } catch (PDOException $x) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($x->getCode() === '23000') {
            out(false, 'این مورد به داده‌های دیگری وابسته است و حذف نشد', null, 409);
        }
        throw $x;
    }
    foreach ($regFiles as $f) {
        delete_reg_file($f);
    }
    foreach ($pubFiles as $f) {
        purge_public_file($pdo, $f);
    }
    if ($own) {
        if ($name === 'media') {
            delete_upload($own);
            $pdo->prepare('UPDATE albums SET cover=NULL WHERE cover=?')->execute([$own]);
        } else {
            purge_public_file($pdo, $own);
        }
    }
    log_act("delete_$name", (string)$id);
    out(true, 'حذف شد');
}

if ($op === 'duplicate' && $name === 'camps') {
    $id = (int)($in['id'] ?? 0);
    if (!row_in_scope($pdo, 'camps', 'id', $id, 'self')) {
        out(false, 'این اردو برای شما مجاز نیست', null, 403);
    }
    $new = duplicate_camp($pdo, $id);
    log_act('duplicate_camp', (string)$new);
    out(true, 'کپی شد (پیش‌نویس)', ['id' => $new]);
}

if ($op === 'reorder') {
    if (!in_array('sort_order', $ent['cols'], true)) {
        out(false, 'این بخش ترتیب‌پذیر نیست', null, 422);
    }
    $ids = array_values(array_filter(array_map('intval', (array)($in['ids'] ?? []))));
    if (!$ids || count($ids) > 500) {
        out(false, 'فهرست نامعتبر است', null, 422);
    }
    if ($restricted) {
        $c = $pdo->query("SELECT COUNT(*) FROM `$name` WHERE id IN (" . implode(',', $ids) . ') AND (' . camp_scope_sql($scope) . ')');
        if ((int)$c->fetchColumn() !== count($ids)) {
            out(false, 'دسترسی ندارید', null, 403);
        }
    }
    $pdo->beginTransaction();
    $u = $pdo->prepare("UPDATE `$name` SET sort_order=? WHERE id=?");
    foreach ($ids as $i => $rid) {
        $u->execute([$i + 1, $rid]);
    }
    $pdo->commit();
    log_act("reorder_$name", implode(',', $ids));
    out(true, 'ترتیب ذخیره شد');
}

// ─── ذخیره (ایجاد / ویرایش)
$data = is_array($in['data'] ?? null) ? $in['data'] : [];
$row  = array_intersect_key($data, array_flip($ent['cols']));
foreach ($row as $k => $v) {
    if ($v === '') {
        $row[$k] = in_array($k, NULLABLE_COLS, true) ? null : (in_array($k, ZERO_COLS, true) ? 0 : '');
    }
}
foreach (ENUMS as $k => $allowed) {
    if (isset($row[$k]) && !in_array($row[$k], $allowed, true)) {
        out(false, "مقدار «$k» نامعتبر است", null, 422);
    }
}
foreach (URL_COLS as $k) {
    if (!empty($row[$k]) && is_unsafe_url((string)$row[$k])) {
        out(false, 'آدرس نامعتبر است', null, 422);
    }
}
if (isset($row['slug']) && !is_valid_slug((string)$row['slug'])) {
    out(false, 'نامک (slug) فقط می‌تواند شامل حروف انگلیسی، عدد و خط‌تیره باشد (مثال: mashhad-1403)', null, 422);
}

if ($name === 'users') {
    $isNew = empty($in['id']);
    $pwd   = (string)($data['password'] ?? '');
    if (($isNew || $pwd !== '') && mb_strlen($pwd) < 8) {
        out(false, 'گذرواژه الزامی و حداقل ۸ کاراکتر است', null, 422);
    }
    if ($pwd !== '') {
        $row['password_hash'] = password_hash($pwd, PASSWORD_DEFAULT);
    }
    ensure_role_enum($pdo);
    $role = (string)($row['role'] ?? '');
    if ($isNew) {
        $uname = trim((string)($row['username'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_.\-]{3,60}$/', $uname) || stripos($uname, 'member-') === 0) {
            out(false, 'نام کاربری باید ۳ تا ۶۰ کاراکتر انگلیسی/عدد/._- باشد و با member- شروع نشود', null, 422);
        }
        $row['username'] = $uname;
        $row['role']     = in_array($role, ['admin', 'editor', 'media'], true) ? $role : 'editor';
    } else {
        $ur = $pdo->prepare('SELECT username,role FROM users WHERE id=?');
        $ur->execute([$in['id']]);
        $old = $ur->fetch();
        if (!$old) {
            out(false, 'کاربر پیدا نشد', null, 404);
        }
        if (hash_equals(trim((string)($CFG['admin']['username'] ?? '')), (string)$old['username'])) {
            out(false, 'ادمین اصلی (config.php) از اینجا ویرایش نمی‌شود', null, 403);
        }
        $row['role'] = in_array($role, ['admin', 'editor', 'media'], true) ? $role : $old['role'];
        if ((int)$in['id'] === (int)$user['id']) { // کاهش نقش/غیرفعال‌کردن خود ممنوع
            $row['role']   = $old['role'];
            $row['active'] = 1;
        }
    }
}

if ($name === 'settings') {
    $k = (string)($row['k'] ?? '');
    if (!preg_match('/^[a-z0-9_]{1,80}$/', $k)) {
        out(false, 'کلید تنظیمات نامعتبر است', null, 422);
    }
    $pdo->prepare('REPLACE INTO settings(k,v) VALUES(?,?)')->execute([$k, (string)($row['v'] ?? '')]);
    log_act('settings', $k);
    out(true, 'ذخیره شد');
}

if ($name === 'camp_forms') { // هر اردو یک تنظیم فرم دارد؛ ذخیره = ایجاد یا به‌روزرسانی (Upsert)
    if (!isset($row['camp_id']) || !parent_ok($pdo, $scope, $row, true)) {
        out(false, 'این اردو برای شما مجاز نیست', null, 403);
    }
    $cols = array_keys($row);
    $upd  = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", $cols));
    $pdo->prepare('INSERT INTO camp_forms(' . implode(',', array_map(fn($c) => "`$c`", $cols)) . ') VALUES(' . implode(',', array_fill(0, count($cols), '?')) . ") ON DUPLICATE KEY UPDATE $upd")
        ->execute(array_values($row));
    log_act('save_camp_forms', (string)$row['camp_id']);
    out(true, 'ذخیره شد', ['id' => $row['camp_id']]);
}

$id = $in['id'] ?? ($data[$pk] ?? null);
if ($name === 'media' && !$id) {
    out(false, 'رسانه‌ی جدید فقط با آپلود یا افزودن لینک ویدئو ساخته می‌شود', null, 422);
}
try {
    if ($id) {
        if (!row_in_scope($pdo, $name, $pk, $id, $scope) || !parent_ok($pdo, $scope, $row, false)) {
            out(false, 'این مورد برای شما مجاز نیست', null, 403);
        }
        if (!$row) {
            out(false, 'داده‌ای برای ذخیره ارسال نشد', null, 422);
        }
        $oldCamp = null;
        if ($name === 'camps') {
            $oc = $pdo->prepare('SELECT title,start_date,end_date,location,camp_status FROM camps WHERE id=?');
            $oc->execute([$id]);
            $oldCamp = $oc->fetch() ?: null;
        }
        $sets = implode(',', array_map(fn($c) => "`$c`=?", array_keys($row)));
        $pdo->prepare("UPDATE `$name` SET $sets WHERE `$pk`=?")->execute([...array_values($row), $id]);
        if ($oldCamp) {
            pf_camp_changed($pdo, (int)$id, $oldCamp, $row);
        }
        log_act("update_$name", (string)$id);
    } else {
        if (!parent_ok($pdo, $scope, $row, true)) {
            out(false, 'این اردو برای شما مجاز نیست', null, 403);
        }
        if ($name === 'seo') { // سئوی هر مورد یکتاست؛ ایجاد مجدد = به‌روزرسانی
            $row['entity_id'] = (int)($row['entity_id'] ?? 0);
            if (($row['entity'] ?? '') === 'global') {
                $row['entity_id'] = 0;
            }
            $upd = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", array_keys($row)));
            $cols = array_keys($row);
            $pdo->prepare('INSERT INTO seo(' . implode(',', array_map(fn($c) => "`$c`", $cols)) . ') VALUES(' . implode(',', array_fill(0, count($cols), '?')) . ") ON DUPLICATE KEY UPDATE $upd")
                ->execute(array_values($row));
            $id = (int)$pdo->lastInsertId();
        } else {
            $id = insert_row($pdo, $name, $row);
        }
        if ($name === 'camps') {
            seed_default_form($pdo, (int)$id); // فرم اولیه‌ی ثبت‌نام (نام، نام خانوادگی، کد ملی، پایه، محله)
            if (!is_admin()) { // سازنده‌ی اردو به‌صورت خودکار به آن دسترسی می‌یابد
                $pdo->prepare('INSERT IGNORE INTO camp_editors(camp_id,user_id) VALUES(?,?)')->execute([$id, $user['id']]);
            }
        }
        if ($name === 'albums') { // سازنده‌ی آلبوم ثبت می‌شود و Editor سازنده به آن دسترسی می‌یابد
            $pdo->prepare('UPDATE albums SET created_by=? WHERE id=?')->execute([$user['id'], $id]);
            grant_album((int)$id);
        }
        log_act("create_$name", (string)$id);
    }
} catch (PDOException $x) {
    if ($x->getCode() === '23000') {
        out(false, 'مقدار تکراری است (مثلاً نامک یا نام فیلد قبلاً استفاده شده)', null, 409);
    }
    throw $x;
}
out(true, 'ذخیره شد', ['id' => $id]);
