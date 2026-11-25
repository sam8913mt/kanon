<?php
/**
 * csrf.php — محافظت CSRF
 * توکن در Session ساخته می‌شود و از هدر X-CSRF-Token یا فیلد csrf فرم خوانده می‌شود.
 */

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_check(): void
{
    $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$t)) {
        out(false, 'توکن امنیتی نامعتبر است', null, 403);
    }
}
