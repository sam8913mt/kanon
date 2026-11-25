<?php
/**
 * registrations.php — مدیریت ثبت‌نام‌ها (Admin و Editor؛ Editor فقط اردوهای مجاز)
 * GET: لیست/فیلتر/Export(csv|xlsx|print) — POST: تغییر وضعیت، ویرایش پاسخ‌ها، حذف (فقط Admin)
 */
require __DIR__ . '/../includes/bootstrap.php';
require_role('registrations');
$pdo = db();
const REG_STATUSES = ['new', 'reviewed', 'approved', 'rejected', 'cancelled'];

/** فیلتر اردوهای مجاز برای کاربر جاری */
function reg_scope(string $alias = 'r'): string
{
    $ids = allowed_camp_ids();
    return $ids === null ? '1=1' : "$alias.camp_id IN (" . ($ids ? implode(',', $ids) : '0') . ')';
}

/** مطمئن می‌شود همه‌ی ثبت‌نام‌ها در اردوهای مجاز کاربر هستند */
function assert_regs_allowed(PDO $pdo, array $ids): void
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        out(false, 'موردی انتخاب نشده', null, 422);
    }
    $q = $pdo->query('SELECT COUNT(*) FROM registrations r WHERE r.id IN (' . implode(',', $ids) . ') AND ' . reg_scope());
    if ((int)$q->fetchColumn() !== count($ids)) {
        out(false, 'دسترسی به این ثبت‌نام‌ها ندارید', null, 403);
    }
}

// برچسب سؤال‌ها برای فیلتر پیشرفته
if (isset($_GET['labels'])) {
    $q = $pdo->prepare('SELECT DISTINCT a.field_label FROM registration_answers a
        JOIN registrations r ON r.id=a.registration_id JOIN camps c ON c.id=r.camp_id
        WHERE (?=\'\' OR c.slug=?) AND ' . reg_scope() . ' ORDER BY a.field_label');
    $q->execute([$_GET['camp'] ?? '', $_GET['camp'] ?? '']);
    out(true, '', $q->fetchAll(PDO::FETCH_COLUMN));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = json_input();

    if (($d['op'] ?? '') === 'delete') { // فقط Admin
        require_role('registrations_delete');
        $id = (int)($d['id'] ?? 0);
        $q = $pdo->prepare('SELECT photo FROM registrations WHERE id=?');
        $q->execute([$id]);
        $photo = $q->fetchColumn();
        if ($photo === false) {
            out(false, 'یافت نشد', null, 404);
        }
        $files = $pdo->prepare('SELECT answer FROM registration_answers WHERE registration_id=?');
        $files->execute([$id]);
        $files = array_filter($files->fetchAll(PDO::FETCH_COLUMN), 'is_reg_file_ref');
        $rc = $pdo->prepare('SELECT file FROM payment_receipts WHERE registration_id=?');
        $rc->execute([$id]);
        $files = array_merge($files, $rc->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare('DELETE FROM registrations WHERE id=?')->execute([$id]);
        delete_reg_file($photo ?: null);
        foreach ($files as $f) {
            delete_reg_file($f);
        }
        log_act('registration_delete', (string)$id);
        out(true, 'حذف شد');
    }

    if (($d['op'] ?? '') === 'attendance') { // وضعیت حضور
        assert_regs_allowed($pdo, [$d['id'] ?? 0]);
        if (!isset(PF_ATTEND[$d['attendance'] ?? ''])) {
            out(false, 'وضعیت حضور نامعتبر', null, 422);
        }
        pf_reg_attendance($pdo, (int)$d['id'], $d['attendance']);
        log_act('registration_attendance', $d['id'] . ':' . $d['attendance']);
        out(true, 'وضعیت حضور ذخیره شد');
    }
    if (($d['op'] ?? '') === 'note') { // توضیحات مدیر (نمایش به کاربر فقط با تیک «قابل مشاهده»)
        assert_regs_allowed($pdo, [$d['id'] ?? 0]);
        $note = trim((string)($d['note'] ?? ''));
        $pdo->prepare('UPDATE registrations SET review_note=?, note_visible=? WHERE id=?')
            ->execute([$note === '' ? null : mb_substr($note, 0, 2000), empty($d['visible']) ? 0 : 1, (int)$d['id']]);
        log_act('registration_note', (string)$d['id']);
        out(true, 'توضیحات ذخیره شد');
    }

    if (isset($d['answers']) && is_array($d['answers'])) { // ویرایش پاسخ‌ها
        assert_regs_allowed($pdo, [$d['id'] ?? 0]);
        // پاسخ‌های فایل/تصویر از این مسیر قابل ویرایش نیستند و مقدار جدید هم نباید به مسیر فایل اشاره کند
        // (جلوگیری از اشاره‌ی یک ثبت‌نام به فایل خصوصی ثبت‌نام دیگر)
        $cur = $pdo->prepare('SELECT field_label,answer FROM registration_answers WHERE registration_id=?');
        $cur->execute([(int)$d['id']]);
        $cur = $cur->fetchAll(PDO::FETCH_KEY_PAIR);
        $u = $pdo->prepare('UPDATE registration_answers SET answer=? WHERE registration_id=? AND field_label=?');
        foreach ($d['answers'] as $label => $v) {
            $v = mb_substr((string)$v, 0, 2000);
            if (!array_key_exists((string)$label, $cur) || is_reg_file_ref($cur[(string)$label]) || is_reg_file_ref($v)) {
                continue;
            }
            $u->execute([$v, (int)$d['id'], (string)$label]);
        }
        log_act('registration_edit', (string)$d['id']);
        out(true, 'ویرایش شد');
    }

    if (!in_array($d['status'] ?? '', REG_STATUSES, true)) {
        out(false, 'وضعیت نامعتبر', null, 422);
    }
    if (!empty($d['ids']) && is_array($d['ids'])) { // تغییر گروهی وضعیت
        $ids = array_slice(array_map('intval', $d['ids']), 0, 500);
        assert_regs_allowed($pdo, $ids);
        pf_reg_status_apply($pdo, $ids, $d['status']);
        log_act('registration_bulk_status', $d['status'] . ':' . implode(',', $ids));
        out(true, 'وضعیت تغییر کرد');
    }
    $id = (int)($d['id'] ?? 0);
    assert_regs_allowed($pdo, [$id]);
    pf_reg_status_apply($pdo, [$id], $d['status']);
    log_act('registration_status', $id . ':' . $d['status']);
    out(true, 'وضعیت تغییر کرد');
}

// ─── GET: فیلتر
$where = [reg_scope()];
$params = [];
if (!empty($_GET['camp']))   { $where[] = 'c.slug=?';        $params[] = $_GET['camp']; }
if (!empty($_GET['status'])) { $where[] = 'r.status=?';      $params[] = $_GET['status']; }
if (!empty($_GET['q']))      { $where[] = 'r.national_id LIKE ?'; $params[] = '%' . $_GET['q'] . '%'; }
if (!empty($_GET['af']) && isset($_GET['av']) && $_GET['av'] !== '') {
    $where[] = 'EXISTS(SELECT 1 FROM registration_answers x WHERE x.registration_id=r.id AND x.field_label=? AND x.answer=?)';
    $params[] = $_GET['af'];
    $params[] = $_GET['av'];
}
$s = $pdo->prepare('SELECT r.id,r.camp_id,r.member_id,r.national_id,r.photo,r.status,r.attendance,r.review_note,r.note_visible,r.tracking_code,r.created_at,c.slug camp_slug,c.title camp
    FROM registrations r JOIN camps c ON c.id=r.camp_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.id DESC LIMIT 2000');
$s->execute($params);
$rows = $s->fetchAll();
$a = $pdo->prepare('SELECT id,field_label,answer FROM registration_answers WHERE registration_id=? ORDER BY id');
foreach ($rows as &$r) {
    $a->execute([$r['id']]);
    $r['answers'] = [];
    $r['files']   = []; // برچسب سؤال → شناسه‌ی پاسخ فایل (دریافت از /api/reg_file.php؛ مسیر واقعی فایل هرگز به کلاینت نمی‌رود)
    foreach ($a->fetchAll() as $x) {
        if (is_reg_file_ref($x['answer'])) {
            $r['files'][$x['field_label']] = (int)$x['id'];
            $r['answers'][$x['field_label']] = 'فایل پیوست';
        } else {
            $r['answers'][$x['field_label']] = $x['answer'];
        }
    }
    $r['has_photo'] = !empty($r['photo']);
    unset($r['photo']);
}
unset($r);

// ─── Export پویا: ستون‌ها از برچسب پاسخ‌ها ساخته می‌شوند (سؤال جدید = ستون جدید)
$export = $_GET['export'] ?? '';
if (in_array($export, ['csv', 'xlsx', 'print'], true)) {
    require __DIR__ . '/../includes/xlsx.php';
    $cols = [];
    foreach ($rows as $r) {
        foreach (array_keys($r['answers']) as $k) {
            $cols[$k] = 1;
        }
    }
    $cols = array_keys($cols);
    $campName = $rows[0]['camp'] ?? ($_GET['camp'] ?? 'all');
    $base = preg_replace('/[^\w\-]/u', '_', (string)$campName) . '-' . str_replace('/', '-', jdate_str(date('Y-m-d')));
    $safe = fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v; // جلوگیری از Formula Injection
    $m = [array_merge(['شناسه', 'اردو', 'وضعیت', 'کد ملی', 'تاریخ'], $cols)];
    foreach ($rows as $r) {
        $m[] = array_merge([$r['id'], $r['camp'], $r['status'], $r['national_id'], jdate_str($r['created_at'], true)],
            array_map(fn($c) => $safe($r['answers'][$c] ?? ''), $cols));
    }
    log_act('export', $base);
    if ($export === 'print') {
        header_remove('Content-Type');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>' . htmlspecialchars($base) . '</title>'
           . '<style>body{font-family:Tahoma}table{border-collapse:collapse;width:100%;font-size:12px}td,th{border:1px solid #999;padding:4px}</style><table>';
        foreach ($m as $i => $row) {
            echo '<tr>';
            foreach ($row as $v) {
                echo ($i ? '<td>' : '<th>') . htmlspecialchars((string)$v) . ($i ? '</td>' : '</th>');
            }
            echo '</tr>';
        }
        echo '</table><script>print()</script>';
        exit;
    }
    if ($export === 'xlsx') {
        xlsx_output($m, "$base.xlsx");
    }
    header_remove('Content-Type');
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$base.csv\"");
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    foreach ($m as $row) {
        fputcsv($o, $row);
    }
    exit;
}
out(true, '', $rows);
