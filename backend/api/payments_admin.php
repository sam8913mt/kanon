<?php
/**
 * payments_admin.php — مدیریت رسیدهای پرداخت (Admin و Editor؛ Editor فقط اردوهای مجاز)
 * GET ?cat=reserved|approved|old[&camp=slug][&q=]  → فهرست رسیدهای آن دسته + شمارنده‌ی هر دسته
 * GET ?card=1 (فقط Admin)                          → شماره و نام صاحب کارت
 * POST {op: approve|reject|archive|restore, id[, note]}   — id = شناسه‌ی رسید
 * POST {op: card, number, holder} (فقط Admin)      → ذخیره‌ی شماره کارت (۱۶ رقم) و نام صاحب کارت
 */
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('registrations');
$pdo = db();
$ids = allowed_camp_ids();
$scope = $ids === null ? '1=1' : 'r.camp_id IN (' . ($ids ? implode(',', array_map('intval', $ids)) : '0') . ')';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d  = json_input();
    $op = (string)($d['op'] ?? '');
    if ($op === 'card') {
        if (!is_admin()) {
            out(false, 'دسترسی ندارید', null, 403);
        }
        $num = preg_replace('/\D+/', '', fa_digits((string)($d['number'] ?? '')));
        $holder = trim(preg_replace('/\s+/u', ' ', (string)($d['holder'] ?? '')));
        if ($num !== '' && strlen($num) !== 16) {
            out(false, 'شماره کارت باید ۱۶ رقم باشد', null, 422);
        }
        if (mb_strlen($holder) > 100) {
            out(false, 'نام صاحب کارت حداکثر ۱۰۰ کاراکتر باشد', null, 422);
        }
        $st = $pdo->prepare('REPLACE INTO settings(k,v) VALUES(?,?)');
        $st->execute(['secret_pay_card_number', $num]);
        $st->execute(['secret_pay_card_holder', $holder]);
        log_act('pay_card_update', '');
        out(true, 'اطلاعات کارت ذخیره شد');
    }
    if (!in_array($op, ['approve', 'reject', 'archive', 'restore'], true)) {
        out(false, 'عملیات نامعتبر', null, 422);
    }
    $q = $pdo->prepare("SELECT pr.id,pr.status,r.member_id,c.title FROM payment_receipts pr JOIN registrations r ON r.id=pr.registration_id
        JOIN camps c ON c.id=r.camp_id WHERE pr.id=? AND $scope");
    $q->execute([(int)($d['id'] ?? 0)]);
    $row = $q->fetch();
    if (!$row) {
        out(false, 'رسید یافت نشد', null, 404);
    }
    $rid = (int)$row['id'];
    if ($op === 'archive' || $op === 'restore') {
        $pdo->prepare('UPDATE payment_receipts SET archived=?, created_at=IF(?=0 AND created_at < NOW() - INTERVAL ' . PAY_OLD_DAYS . ' DAY, NOW(), created_at) WHERE id=?')
            ->execute([$op === 'archive' ? 1 : 0, $op === 'archive' ? 1 : 0, $rid]);
        log_act('receipt_' . $op, (string)$rid);
        out(true, $op === 'archive' ? 'به «قدیمی» منتقل شد' : 'از «قدیمی» خارج شد');
    }
    $note = trim((string)($d['note'] ?? ''));
    if ($op === 'approve') {
        $pdo->prepare("UPDATE payment_receipts SET status='approved', admin_note=NULL, approved_at=NOW(), archived=0 WHERE id=?")->execute([$rid]);
        if ($row['member_id'] && $row['status'] !== 'approved') {
            $t = "رسید پرداخت شما برای «{$row['title']}» تأیید شد";
            pf_notify((int)$row['member_id'], 'payment_approved', $t, 'پرداخت شما با موفقیت تأیید شد.', '/profile#notifications');
            pf_timeline((int)$row['member_id'], 'payment', $t, 'receipt', $rid);
        }
    } else {
        $pdo->prepare("UPDATE payment_receipts SET status='rejected', admin_note=?, approved_at=NULL WHERE id=?")
            ->execute([$note === '' ? null : mb_substr($note, 0, 500), $rid]);
        if ($row['member_id'] && $row['status'] !== 'rejected') {
            pf_notify((int)$row['member_id'], 'payment_rejected', "رسید پرداخت «{$row['title']}» تأیید نشد", $note === '' ? 'لطفاً رسید معتبر را دوباره بارگذاری کنید.' : mb_substr($note, 0, 500), '/profile#notifications');
        }
    }
    log_act('receipt_' . $op, (string)$rid);
    out(true, $op === 'approve' ? 'رسید تأیید شد' : 'رسید رد شد');
}

if (isset($_GET['card'])) {
    if (!is_admin()) {
        out(false, 'دسترسی ندارید', null, 403);
    }
    out(true, '', pay_card($pdo));
}

$counts = [];
foreach (['reserved', 'approved', 'old'] as $c) {
    $n = $pdo->query('SELECT COUNT(*) FROM payment_receipts pr JOIN registrations r ON r.id=pr.registration_id WHERE ' . pay_cat_sql($c) . " AND $scope");
    $counts[$c] = (int)$n->fetchColumn();
}
$cat = in_array($_GET['cat'] ?? '', ['reserved', 'approved', 'old'], true) ? $_GET['cat'] : 'reserved';
$where = [pay_cat_sql($cat), $scope];
$par = [];
if (($_GET['camp'] ?? '') !== '') {
    $where[] = 'c.slug=?';
    $par[] = (string)$_GET['camp'];
}
$qs = trim((string)($_GET['q'] ?? ''));
if ($qs !== '') {
    $where[] = '(r.national_id=? OR r.tracking_code=? OR r.id=?)';
    $par[] = fa_digits($qs);
    $par[] = strtoupper($qs);
    $par[] = pay_reg_id($qs);
}
$s = $pdo->prepare('SELECT pr.id,pr.registration_id,pr.status,pr.admin_note,pr.archived,pr.approved_at,pr.created_at,r.national_id,r.member_id,c.title camp,c.cost,
        COALESCE(NULLIF(CONCAT_WS(\' \',p.first_name,p.last_name),\'\'),
            (SELECT a.answer FROM registration_answers a WHERE a.registration_id=r.id AND a.field_label LIKE \'%نام%\' AND COALESCE(a.field_type,\'\') NOT IN(\'image\',\'file\') AND a.answer<>\'\' ORDER BY a.id LIMIT 1)) name
    FROM payment_receipts pr JOIN registrations r ON r.id=pr.registration_id JOIN camps c ON c.id=r.camp_id
    LEFT JOIN member_profiles p ON p.member_id=r.member_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY pr.created_at DESC LIMIT 500');
$s->execute($par);
$rows = array_map(fn($x) => [
    'id' => (int)$x['id'], 'reg_id' => (int)$x['registration_id'], 'code' => 'R' . str_pad((string)$x['registration_id'], 6, '0', STR_PAD_LEFT),
    'status' => $x['status'], 'status_label' => PAY_LABELS[$x['status']] ?? '', 'note' => $x['admin_note'], 'archived' => (int)$x['archived'],
    'approved_at' => $x['approved_at'], 'created_at' => $x['created_at'], 'camp' => $x['camp'], 'cost' => $x['cost'],
    'national_id' => $x['national_id'], 'name' => $x['name'], 'member_id' => $x['member_id'] ? (int)$x['member_id'] : null,
], $s->fetchAll());
out(true, '', ['counts' => $counts, 'cat' => $cat, 'items' => $rows, 'old_days' => PAY_OLD_DAYS]);
