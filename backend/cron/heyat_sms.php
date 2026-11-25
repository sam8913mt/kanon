<?php
/**
 * heyat_sms.php — ارسال پیامک یادآوری «یک روز قبل از هیئت» به اعضای فعال
 * فقط از خط فرمان/کرون اجرا می‌شود (روزی یک‌بار، مثلاً ساعت ۹ صبح):
 *   0 9 * * *  php /home/USER/public_html/backend/cron/heyat_sms.php
 * اگر در تنظیمات «سامانه‌ی پیامکی فعال» تیک نخورده یا کلید API خالی باشد، هیچ کاری انجام نمی‌شود.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
date_default_timezone_set('Asia/Tehran');
$CFG = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/database.php';
require __DIR__ . '/../includes/sms.php';

$pdo = db();
$set = sms_settings($pdo);
if ($set['sms_enabled'] !== '1' || !sms_ready($set) || trim($set['sms_heyat_text']) === '') {
    exit("SMS disabled\n");
}

$tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');
$q = $pdo->prepare('SELECT id,title,occasion,TIME_FORMAT(event_time,"%H:%i") AS t FROM heyats WHERE published=1 AND sms_sent_at IS NULL AND event_date=?');
$q->execute([$tomorrow]);
$events = $q->fetchAll();
if (!$events) { exit("No heyat tomorrow\n"); }

$mobiles = array_values(array_filter($pdo->query("SELECT mobile FROM members WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN), fn($m) => preg_match('/^09\d{9}$/', (string)$m)));
if (!$mobiles) { exit("No recipients\n"); }

foreach ($events as $e) {
    $text = strtr($set['sms_heyat_text'], ['{title}' => $e['title'], '{occasion}' => (string)$e['occasion'], '{time}' => (string)$e['t']]);
    $ok = 0;
    foreach (array_chunk($mobiles, 100) as $chunk) {
        if (sms_send($set, $chunk, $text, $err)) { $ok++; }
        else { error_log('heyat_sms: heyat ' . $e['id'] . ': ' . $err); }
    }
    if ($ok > 0) { $pdo->prepare('UPDATE heyats SET sms_sent_at=NOW() WHERE id=?')->execute([$e['id']]); }
    echo "heyat {$e['id']}: {$ok} batch(es) sent\n";
}
