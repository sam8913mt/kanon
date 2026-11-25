<?php
/**
 * sms.php — تنظیمات و ارسال پیامک (کاوه‌نگار). مشترک بین «ورود با کد یکبار مصرف»، «یادآوری هیئت» و تست پنل.
 * کلید API فقط در جدول settings با نام secret_sms_apikey نگهداری می‌شود (هرگز به API عمومی نمی‌رود).
 */
const SMS_KEYS = ['sms_enabled', 'sms_otp_enabled', 'secret_sms_apikey', 'sms_sender', 'sms_heyat_text', 'sms_otp_text', 'sms_otp_ttl'];

function sms_settings(PDO $pdo): array
{
    $q = $pdo->prepare('SELECT k,v FROM settings WHERE k IN (' . implode(',', array_fill(0, count(SMS_KEYS), '?')) . ')');
    $q->execute(SMS_KEYS);
    return $q->fetchAll(PDO::FETCH_KEY_PAIR) + array_fill_keys(SMS_KEYS, '');
}

/** کلید API وارد شده است؟ */
function sms_ready(array $s): bool
{
    return trim((string)$s['secret_sms_apikey']) !== '';
}

/** ورود با کد یکبار مصرف: هم تیک فعال و هم کلید API لازم است؛ در غیر این صورت ورود مثل قبل است */
function sms_otp_enabled(PDO $pdo): bool
{
    $s = sms_settings($pdo);
    return $s['sms_otp_enabled'] === '1' && sms_ready($s);
}

function sms_otp_hash(array $s, string $mobile, string $code): string
{
    return hash_hmac('sha256', $mobile . ':' . $code, (string)$s['secret_sms_apikey']);
}

/** ارسال با کاوه‌نگار. true = پذیرفته شد؛ در غیر این صورت $err پر می‌شود */
function sms_send(array $s, array $to, string $text, ?string &$err = null): bool
{
    if (!sms_ready($s)) {
        $err = 'کلید API تنظیم نشده است';
        return false;
    }
    $url = 'https://api.kavenegar.com/v1/' . rawurlencode(trim((string)$s['secret_sms_apikey'])) . '/sms/send.json';
    $f = ['receptor' => implode(',', $to), 'message' => $text];
    if (trim((string)$s['sms_sender']) !== '') {
        $f['sender'] = trim((string)$s['sms_sender']);
    }
    $r = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($f), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $r = curl_exec($ch);
        if ($r === false) {
            $err = 'عدم اتصال به سرویس پیامک';
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => http_build_query($f), 'timeout' => 20]]);
        $r = @file_get_contents($url, false, $ctx);
    }
    $j = is_string($r) ? json_decode($r, true) : null;
    if ((int)($j['return']['status'] ?? 0) === 200) {
        return true;
    }
    $err = $err ?: (string)($j['return']['message'] ?? 'پاسخ نامعتبر از سرویس پیامک');
    error_log('sms_send: ' . $err);
    return false;
}
