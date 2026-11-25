<?php
/**
 * نظرات شرکت‌کنندگان (عمومی)
 *  GET  → نظرهای تأییدشده و نمایش‌داده‌شده
 *  POST → ثبت نظر جدید (JSON)؛ تا تأیید مدیر در پنل نمایش داده نمی‌شود
 */
require __DIR__ . '/../includes/bootstrap.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_input();
    if (trim((string)($in['website'] ?? '')) !== '') { // honeypot
        out(true, 'نظر شما ثبت شد و پس از تأیید نمایش داده می‌شود');
    }
    $name = trim(strip_tags((string)($in['name'] ?? '')));
    $role = trim(strip_tags((string)($in['role'] ?? '')));
    $body = trim(strip_tags((string)($in['body'] ?? '')));
    $rate = max(1, min(5, (int)($in['rating'] ?? 5)));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        out(false, 'نام باید بین ۲ تا ۶۰ کاراکتر باشد', null, 422);
    }
    if (mb_strlen($role) > 100) {
        out(false, 'عنوان حداکثر ۱۰۰ کاراکتر باشد', null, 422);
    }
    if (mb_strlen($body) < 10 || mb_strlen($body) > 500) {
        out(false, 'متن نظر باید بین ۱۰ تا ۵۰۰ کاراکتر باشد', null, 422);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $c = $pdo->prepare('SELECT COUNT(*) FROM testimonials WHERE ip=? AND created_at>NOW()-INTERVAL 1 HOUR');
    $c->execute([$ip]);
    if ((int)$c->fetchColumn() >= 2) {
        out(false, 'تعداد ارسال بیش از حد مجاز است؛ بعداً دوباره تلاش کنید', null, 429);
    }
    $pdo->prepare('INSERT INTO testimonials(name,role,body,rating,approved,enabled,ip) VALUES(?,?,?,?,0,1,?)')
        ->execute([$name, $role !== '' ? $role : null, $body, $rate, $ip]);
    out(true, 'نظر شما ثبت شد و پس از تأیید مدیر نمایش داده می‌شود');
}

out(true, '', $pdo->query('SELECT name,role,body,rating FROM testimonials WHERE approved=1 AND enabled=1 ORDER BY sort_order,id DESC LIMIT 20')->fetchAll());
