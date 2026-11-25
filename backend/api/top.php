<?php
/**
 * افراد برتر (عمومی)
 *  - لیست:  /api/top.php           → نام، عکس، مسئولیت کانون/هیئت (فعلی) و مرتبه؛ مقدار خالی = ''
 *  - عکس:   /api/top.php?avatar=ID → فقط عکس پروفایل «افراد برتر» (بقیه ۴۰۴)
 */
require __DIR__ . '/../includes/bootstrap.php';
$pdo = db();

if (isset($_GET['avatar'])) {
    $s = $pdo->prepare("SELECT d.path,d.mime FROM member_documents d JOIN member_profiles p ON p.avatar_doc_id=d.id AND p.member_id=d.member_id
        JOIN members m ON m.id=p.member_id WHERE d.id=? AND d.category='avatar' AND d.archived_at IS NULL AND p.is_top=1 AND m.status='active'");
    $s->execute([(int)$_GET['avatar']]);
    $d = $s->fetch();
    $path = $d ? private_path((string)$d['path']) : null;
    if (!$path || !in_array($d['mime'], ['image/webp', 'image/jpeg', 'image/png'], true)) {
        out(false, 'فایل یافت نشد', null, 404);
    }
    header_remove('Content-Type');
    header('Content-Type: ' . $d['mime']);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=300');
    readfile($path);
    exit;
}

$rows = $pdo->query("SELECT m.id,p.first_name,p.last_name,p.avatar_doc_id,p.member_rank FROM member_profiles p JOIN members m ON m.id=p.member_id
    WHERE p.is_top=1 AND m.status='active' ORDER BY p.top_order,m.id")->fetchAll();
$resp = $pdo->prepare("SELECT body,title FROM member_responsibilities WHERE member_id=? AND deleted_at IS NULL AND status='active' AND (end_date IS NULL OR end_date>=CURDATE()) ORDER BY id DESC");
$out = [];
foreach ($rows as $r) {
    $resp->execute([$r['id']]);
    $by = ['kanoon' => [], 'heyat' => []];
    foreach ($resp->fetchAll() as $x) {
        $by[$x['body']][] = $x['title'];
    }
    $out[] = ['name' => trim($r['first_name'] . ' ' . $r['last_name']),
        'avatar' => $r['avatar_doc_id'] ? '/api/top.php?avatar=' . (int)$r['avatar_doc_id'] : '',
        'kanoon' => implode('، ', $by['kanoon']), 'heyat' => implode('، ', $by['heyat']), 'rank' => (string)($r['member_rank'] ?? '')];
}
out(true, '', $out);
