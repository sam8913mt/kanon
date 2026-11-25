<?php
/**
 * heyat_send.php — ارسال فوری پیامک هیئت به اعضای فعال (Admin / Editor / مدیر رسانه)
 * GET ?id=  → پیش‌نمایش: متن پیش‌فرض، تعداد گیرنده، آماده‌بودن سامانه‌ی پیامک
 * POST JSON {id, text?} → ارسال. کلید API هرگز به کاربر نمایش داده نمی‌شود.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_role('heyat_sms');
$pdo  = db();
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$in   = $post ? json_input() : $_GET;
$id   = (int)($in['id'] ?? 0);

$q = $pdo->prepare('SELECT id,title,occasion,TIME_FORMAT(event_time,"%H:%i") t,sms_sent_at FROM heyats WHERE id=?');
$q->execute([$id]);
$h = $q->fetch();
if (!$h) {
    out(false, 'هیئت پیدا نشد', null, 404);
}
$set  = sms_settings($pdo);
$tpl  = trim((string)($set['sms_heyat_text'] ?? ''));
$text = $tpl !== ''
    ? strtr($tpl, ['{title}' => $h['title'], '{occasion}' => (string)$h['occasion'], '{time}' => (string)$h['t']])
    : 'هیئت «' . $h['title'] . '» — کانون نخبه‌پروری رویش';
$mobiles = array_values(array_filter(
    $pdo->query("SELECT mobile FROM members WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN),
    fn($m) => valid_mobile((string)$m)
));

if (!$post) {
    out(true, '', ['text' => $text, 'count' => count($mobiles), 'ready' => sms_ready($set), 'sent_at' => $h['sms_sent_at']]);
}

csrf_check();
$custom = trim((string)($in['text'] ?? ''));
if ($custom !== '') {
    $text = $custom;
}
if (mb_strlen($text) < 3 || mb_strlen($text) > 500) {
    out(false, 'متن پیامک باید بین ۳ تا ۵۰۰ کاراکتر باشد', null, 422);
}
if (!sms_ready($set)) {
    out(false, 'سامانه‌ی پیامک هنوز تنظیم نشده است؛ از مدیر بخواهید کلید API را وارد کند', null, 422);
}
if (!$mobiles) {
    out(false, 'گیرنده‌ی فعالی وجود ندارد', null, 422);
}
$r = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE action='heyat_sms_send' AND detail LIKE ? AND created_at>NOW()-INTERVAL 2 MINUTE");
$r->execute([$id . ':%']);
if ((int)$r->fetchColumn() > 0) {
    out(false, 'این پیامک همین چند لحظه پیش ارسال شده است؛ دو دقیقه صبر کنید', null, 429);
}
@set_time_limit(120);
$ok = 0;
$chunks = array_chunk($mobiles, 100);
foreach ($chunks as $chunk) {
    if (sms_send($set, $chunk, $text, $err)) {
        $ok++;
    } else {
        error_log("heyat_send: heyat $id: $err");
    }
}
log_act('heyat_sms_send', $id . ':' . $ok . '/' . count($chunks));
if ($ok === 0) {
    out(false, 'ارسال ناموفق بود؛ تنظیمات پیامک را بررسی کنید', null, 502);
}
$pdo->prepare('UPDATE heyats SET sms_sent_at=NOW() WHERE id=?')->execute([$id]);
out(true, $ok === count($chunks) ? 'پیامک برای ' . count($mobiles) . ' عضو ارسال شد' : "ارسال جزئی: $ok از " . count($chunks) . ' دسته انجام شد');
