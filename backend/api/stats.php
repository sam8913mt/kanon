<?php
/**
 * stats.php — آمار داشبورد و گزارش‌ها
 * Editor فقط آمار اردوهای مجاز خود را می‌بیند؛ گزارش فعالیت و وضعیت سیستم فقط Admin.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_role('dashboard');
$p = db();
$scopeCamp = camp_scope_sql('self');       // روی جدول camps
$scopeReg  = camp_scope_sql('camp_id');    // روی جدول registrations
$one = fn(string $sql) => (int)$p->query($sql)->fetchColumn();

$data = [
    'camps'         => $one("SELECT COUNT(*) FROM camps WHERE $scopeCamp"),
    'open_camps'    => $one("SELECT COUNT(*) FROM camps WHERE registration_status='open' AND $scopeCamp"),
    'registrations' => $one("SELECT COUNT(*) FROM registrations WHERE $scopeReg"),
    'new'           => $one("SELECT COUNT(*) FROM registrations WHERE status='new' AND $scopeReg"),
    'today'         => $one("SELECT COUNT(*) FROM registrations WHERE DATE(created_at)=CURDATE() AND $scopeReg"),
    'albums'        => $one('SELECT COUNT(*) FROM albums WHERE ' . camp_scope_sql('album_self')),
    'media'         => $one('SELECT COUNT(*) FROM media WHERE ' . camp_scope_sql('media_own')),
    'members'       => is_admin() ? $one("SELECT COUNT(*) FROM members WHERE status<>'archived'") : 0,
    'users'         => is_admin() ? $one('SELECT COUNT(*) FROM users') : 0,
    'daily'         => $p->query("SELECT DATE(created_at) d,COUNT(*) n FROM registrations WHERE created_at>=CURDATE()-INTERVAL 13 DAY AND $scopeReg GROUP BY d ORDER BY d")->fetchAll(),
    'per_camp'      => $p->query("SELECT c.title,c.capacity,COUNT(r.id) n FROM camps c LEFT JOIN registrations r ON r.camp_id=c.id WHERE " . camp_scope_sql('self', 'c') . " GROUP BY c.id ORDER BY c.id DESC")->fetchAll(),
    'by_status'     => $p->query("SELECT status,COUNT(*) n FROM registrations WHERE $scopeReg GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR),
    'camp_status'   => $p->query("SELECT registration_status s,COUNT(*) n FROM camps WHERE $scopeCamp GROUP BY registration_status")->fetchAll(PDO::FETCH_KEY_PAIR),
    'recent'        => [],
    'activity'      => [],
    'system'        => null,
];
if (is_admin()) {
    $data['recent']   = $p->query('SELECT action,detail,created_at FROM logs ORDER BY id DESC LIMIT 8')->fetchAll();
    $data['activity'] = $p->query("SELECT COALESCE(u.username,'(ناشناس)') username,COUNT(*) n FROM logs l LEFT JOIN users u ON u.id=l.user_id
                                   WHERE l.action NOT IN('register_attempt','login_fail') AND l.created_at>=NOW()-INTERVAL 30 DAY GROUP BY l.user_id ORDER BY n DESC LIMIT 20")->fetchAll();
    $up = $CFG['upload']['dir'];
    $data['system'] = [
        'version'        => ROYESH_VERSION,
        'php'            => PHP_VERSION,
        'mysql'          => (string)$p->query('SELECT VERSION()')->fetchColumn(),
        'uploads_ok'     => is_dir($up) && is_writable($up),
        'storage_ok'     => is_writable(__DIR__ . '/../storage'),
        'gd'             => function_exists('imagecreatefromstring') || class_exists('Imagick'),
        'zip'            => class_exists('ZipArchive') || function_exists('make_zip'),
        'private_ok'     => is_dir(__DIR__ . '/../storage/private') && is_writable(__DIR__ . '/../storage/private'),
        'disk_free_mb'   => (int)(@disk_free_space($up) / 1048576),
        'upload_max'     => ini_get('upload_max_filesize'),
        'post_max'       => ini_get('post_max_size'),
        'debug'          => (bool)$CFG['debug'],
        'https'          => is_https(),
    ];
}
out(true, '', $data);
