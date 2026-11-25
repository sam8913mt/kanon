<?php
/**
 * مدیریت «افراد برتر» در پنل (فقط Admin)
 *  GET  ?q=…            → { top: [...], candidates: [...] }  (candidates: جست‌وجو در اعضا؛ حداکثر ۳۰)
 *  POST {op:'reorder', ids:[member_id,…]}  → ترتیب نمایش
 * افزودن/حذف و مرتبه: manage.php (section=member، op=set_top | set_rank)
 */
require __DIR__ . '/../../includes/bootstrap.php';
require_role('profiles_manage');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $in = json_input();
    if (($in['op'] ?? '') !== 'reorder' || !is_array($in['ids'] ?? null)) {
        out(false, 'عملیات نامعتبر', null, 422);
    }
    $u = $pdo->prepare('UPDATE member_profiles SET top_order=? WHERE member_id=? AND is_top=1');
    foreach (array_values($in['ids']) as $i => $mid) {
        $u->execute([$i + 1, (int)$mid]);
    }
    log_act('admin_top', 'reorder');
    out(true, 'ترتیب ذخیره شد');
}

$cols = "m.id,p.first_name,p.last_name,p.avatar_doc_id,p.member_rank,p.is_top,
    (SELECT GROUP_CONCAT(r.title SEPARATOR '، ') FROM member_responsibilities r WHERE r.member_id=m.id AND r.body='heyat' AND r.deleted_at IS NULL AND r.status='active' AND (r.end_date IS NULL OR r.end_date>=CURDATE())) heyat,
    (SELECT GROUP_CONCAT(r.title SEPARATOR '، ') FROM member_responsibilities r WHERE r.member_id=m.id AND r.body='kanoon' AND r.deleted_at IS NULL AND r.status='active' AND (r.end_date IS NULL OR r.end_date>=CURDATE())) kanoon";
$fmt = fn(array $r) => ['id' => (int)$r['id'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']), 'avatar_id' => $r['avatar_doc_id'] ? (int)$r['avatar_doc_id'] : null,
    'rank' => (string)($r['member_rank'] ?? ''), 'heyat' => (string)($r['heyat'] ?? ''), 'kanoon' => (string)($r['kanoon'] ?? ''), 'is_top' => (int)$r['is_top']];
$top = array_map($fmt, $pdo->query("SELECT $cols FROM member_profiles p JOIN members m ON m.id=p.member_id WHERE p.is_top=1 AND m.status<>'archived' ORDER BY p.top_order,m.id")->fetchAll());
$q = trim((string)($_GET['q'] ?? ''));
$like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
$s = $pdo->prepare("SELECT $cols FROM member_profiles p JOIN members m ON m.id=p.member_id WHERE p.is_top=0 AND m.status='active' AND (?='' OR CONCAT_WS(' ',p.first_name,p.last_name) LIKE ?) ORDER BY p.last_name,p.first_name LIMIT 30");
$s->execute([$q, $like]);
out(true, '', ['top' => $top, 'candidates' => array_map($fmt, $s->fetchAll())]);
