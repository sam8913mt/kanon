<?php
/** GET /api/profile/dashboard.php — خلاصه‌ی داشبورد کاربر (ثبت‌نام‌های اخیر، اردوهای آینده، اعلان‌ها، مسئولیت فعلی، فعالیت‌های اخیر، آمار) */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
$mid = $v['member_id'];
$pdo = db();
$p = pf_section('profile', $mid, $v);
$regs = pf_registrations($pdo, $mid, $v)['items'];
$today = date('Y-m-d');
$upcoming = array_values(array_filter($regs, fn($r) => !in_array($r['status'], ['cancelled', 'rejected'], true) && ($r['camp']['end'] ?: $r['camp']['start']) >= $today && $r['camp']['start']));
usort($upcoming, fn($a, $b) => strcmp($a['camp']['start'], $b['camp']['start']));
$n = pf_notifications($pdo, $mid);
$open = $pdo->prepare("SELECT slug,title,start_date start,location FROM camps WHERE published=1 AND registration_status='open'
                       AND id NOT IN (SELECT camp_id FROM registrations WHERE member_id=? AND status<>'cancelled') ORDER BY start_date LIMIT 4");
$open->execute([$mid]);
out(true, '', [
    'csrf' => csrf_token(), 'header' => $p['header'], 'stats' => $p['stats'],
    'recent_registrations' => array_slice($regs, 0, 5), 'upcoming_camps' => array_slice($upcoming, 0, 5),
    'notifications' => array_slice(array_values(array_filter($n['items'], fn($x) => !$x['is_read'])), 0, 5), 'unread' => $n['unread'],
    'responsibilities' => $p['header']['responsibilities'],
    'recent_activities' => array_slice(pf_activities($pdo, $mid), 0, 5), 'open_camps' => $open->fetchAll(),
    'completeness' => count(array_filter($p['fields'], fn($f) => $f['value'] !== null)) . '/' . count($p['fields']),
]);
