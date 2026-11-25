<?php
// داده‌های مشترک همه‌ی صفحات: تنظیمات، منو، فوتر، کانال‌ها، تماس، آمار (بدون اطلاعات حساس)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_data.php';
$p = db();
$count = fn(string $sql) => (int)$p->query($sql)->fetchColumn();
$settings = public_settings($p);

// آمار صفحه‌ی اصلی: اگر در تنظیمات «home_stats» (قالب «عنوان|عدد;عنوان|عدد») تعریف شده باشد همان، وگرنه خودکار
$stats = [];
foreach (array_filter(explode(';', (string)($settings['home_stats'] ?? ''))) as $part) {
    [$label, $val] = array_pad(explode('|', $part, 2), 2, '');
    if (trim($label) !== '') {
        $stats[] = [trim($label), trim($val)];
    }
}
if (!$stats) {
    $stats = [['اردو', $count('SELECT COUNT(*) FROM camps WHERE published=1')],
              ['ثبت‌نام', $count('SELECT COUNT(*) FROM registrations')],
              ['آلبوم', $count('SELECT COUNT(*) FROM albums WHERE published=1')]];
}
// «فعالیت‌ها»: خط‌به‌خط «عنوان|توضیح»
$activities = [];
foreach (array_filter(array_map('trim', explode("\n", (string)($settings['activities'] ?? '')))) as $line) {
    [$t, $d] = array_pad(explode('|', $line, 2), 2, '');
    $activities[] = ['title' => $t, 'text' => $d];
}
$g = $p->query("SELECT seo_title,meta_description,og_image FROM seo WHERE entity='global' AND entity_id=0")->fetch() ?: null;

out(true, '', [
    'settings'     => $settings,
    'activities'   => $activities,
    'global_seo'   => $g,
    'pages'        => $p->query("SELECT slug,title FROM pages WHERE published=1 AND content<>'' AND slug<>'about' ORDER BY id")->fetchAll(),
    'menu'         => $p->query('SELECT title,link FROM menu WHERE enabled=1 ORDER BY sort_order,id')->fetchAll(),
    'footer_links' => $p->query('SELECT title,link FROM footer_links WHERE enabled=1 ORDER BY sort_order,id')->fetchAll(),
    'channels'     => $p->query('SELECT title,url,icon FROM channels WHERE enabled=1 ORDER BY sort_order,id')->fetchAll(),
    'contacts'     => $p->query("SELECT label,value,kind FROM contacts WHERE enabled=1 AND value<>'' ORDER BY sort_order,id")->fetchAll(),
    'stats'        => $stats,
]);
