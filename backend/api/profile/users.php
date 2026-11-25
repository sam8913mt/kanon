<?php
/**
 * GET /api/profile/users.php — فهرست کاربران/پروفایل‌ها برای پنل (Admin کامل؛ Editor همه‌ی اعضا ولی با Mask)
 * فیلتر: q, status, school, grade, neighborhood, resp (kanoon|heyat|any|none) — صفحه‌بندی: page, per
 */
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_role('profiles');
$ed = $u['role'] !== 'admin';
$vt = $ed ? 'editor' : 'admin';
$pdo = db();

$where = ['1=1'];
$par = [];
$st = (string)($_GET['status'] ?? '');
if (isset(PF_STATUS[$st])) {
    $where[] = 'm.status=?';
    $par[] = $st;
} elseif ($st !== 'all') {
    $where[] = "m.status<>'archived'";
}
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    if ($ed) { // Editor فقط بر اساس نام جست‌وجو می‌کند (جلوگیری از حدس کد ملی/موبایل)
        $where[] = "CONCAT_WS(' ',p.first_name,p.last_name) LIKE ?";
        $par[] = $like;
    } else {
        $where[] = "(CONCAT_WS(' ',p.first_name,p.last_name) LIKE ? OR m.mobile LIKE ? OR p.national_id LIKE ? OR p.email LIKE ?)";
        array_push($par, $like, $like, $like, $like);
    }
}
foreach (['school' => 'e.school', 'neighborhood' => 'p.neighborhood'] as $k => $col) {
    if (($x = trim((string)($_GET[$k] ?? ''))) !== '') {
        $where[] = "$col LIKE ?";
        $par[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $x) . '%';
    }
}
if (($x = trim((string)($_GET['grade'] ?? ''))) !== '') {
    $where[] = 'e.grade=?';
    $par[] = $x;
}
$resp = (string)($_GET['resp'] ?? '');
if (!$ed && $resp !== '') {
    $cur = "SELECT 1 FROM member_responsibilities mr WHERE mr.member_id=m.id AND mr.deleted_at IS NULL AND mr.status='active' AND (mr.end_date IS NULL OR mr.end_date>=CURDATE())";
    if (isset(PF_BODY[$resp])) {
        $where[] = "EXISTS ($cur AND mr.body=?)";
        $par[] = $resp;
    } elseif ($resp === 'any') {
        $where[] = "EXISTS ($cur)";
    } elseif ($resp === 'none') {
        $where[] = "NOT EXISTS ($cur)";
    }
}
$from = "FROM members m JOIN member_profiles p ON p.member_id=m.id
         LEFT JOIN member_education e ON e.id=(SELECT MAX(id) FROM member_education WHERE member_id=m.id AND is_current=1 AND deleted_at IS NULL)
         LEFT JOIN users su ON su.id=m.staff_user_id LEFT JOIN roles ro ON ro.name=su.role
         WHERE " . implode(' AND ', $where);

$per  = max(10, min(100, (int)($_GET['per'] ?? 30)));
$cnt  = $pdo->prepare("SELECT COUNT(*) $from");
$cnt->execute($par);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page  = max(1, min($pages, (int)($_GET['page'] ?? 1)));

$s = $pdo->prepare("SELECT m.id,m.mobile,m.status,m.created_at,m.last_login_at,m.last_activity_at,p.first_name,p.last_name,p.national_id,p.email,p.neighborhood,p.avatar_doc_id,
        e.school,e.grade,e.field_of_study, COALESCE(ro.title,'عضو') role_title $from ORDER BY m.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$s->execute($par);
$rows = [];
foreach ($s->fetchAll() as $r) {
    $rows[] = ['id' => (int)$r['id'], 'avatar_id' => $r['avatar_doc_id'] ? (int)$r['avatar_doc_id'] : null, 'first_name' => $r['first_name'], 'last_name' => $r['last_name'],
        'national_id' => pf_mask('national_id', $r['national_id'], $vt), 'mobile' => pf_mask('mobile', $r['mobile'], $vt), 'email' => $ed ? null : $r['email'],
        'school' => $r['school'], 'grade' => $r['grade'], 'field_of_study' => $r['field_of_study'], 'neighborhood' => $r['neighborhood'],
        'status' => $r['status'], 'status_label' => PF_STATUS[$r['status']], 'role_title' => $r['role_title'], 'created_at' => $r['created_at'],
        'last_login_at' => $ed ? null : $r['last_login_at'], 'last_activity_at' => $ed ? null : $r['last_activity_at']];
}
out(true, '', ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'masked' => $ed, 'statuses' => PF_STATUS]);
