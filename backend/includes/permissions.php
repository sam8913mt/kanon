<?php
/**
 * permissions.php — سطح دسترسی (Admin / Editor)
 * همه‌ی بررسی‌ها سمت سرور انجام می‌شود؛ مخفی‌کردن منو در UI کافی نیست.
 */

// بخش‌هایی که Editor اجازه‌ی دسترسی به آن‌ها را دارد.
// profiles = فقط مشاهده‌ی محدود و Mask‌شده‌ی پروفایل ثبت‌نام‌کنندگان اردوهای مجاز؛ profiles_manage فقط Admin.
// Editor: همه‌چیز به‌جز تنظیمات سایت (SEO، متن‌ها/صفحات، منو، فوتر، کانال‌ها، اطلاعات تماس، پیامک و کد ورود، کاربران، لاگ، Backup).
const EDITOR_CAN = ['dashboard', 'registrations', 'camps', 'forms', 'albums', 'heyats', 'heyat_sms', 'media', 'profiles', 'testimonials', 'news'];
// مدیر رسانه (media): فقط آلبوم‌ها، تصاویر/فایل‌ها (کل بخش رسانه)، هیئت‌ها و ارسال پیامک هیئت.
const MEDIA_CAN  = ['dashboard', 'albums', 'heyats', 'heyat_sms', 'media'];

/** بخش‌های مجاز هر نقش (admin = همه، در me.php/require_role جداگانه) */
function role_areas(string $role): array
{
    return match ($role) {
        'editor' => EDITOR_CAN,
        'media'  => MEDIA_CAN,
        default  => [],
    };
}

/** دسترسی کامل به آلبوم‌ها و رسانه‌ها (admin و مدیر رسانه) */
function has_full_media(): bool
{
    return in_array(current_user()['role'] ?? '', ['admin', 'media'], true);
}

/** ورود لازم است و کاربر باید به «area» دسترسی داشته باشد؛ در غیر این صورت ۴۰۱/۴۰۳ */
function require_role(string $area): array
{
    $u = current_user();
    if (!$u) {
        out(false, 'ابتدا وارد شوید', null, 401);
    }
    if ($u['role'] !== 'admin' && !in_array($area, role_areas((string)$u['role']), true)) {
        out(false, 'دسترسی ندارید', null, 403);
    }
    return $u;
}

/**
 * شناسه‌ی اردوهایی که کاربر جاری اجازه‌ی مدیریت آن‌ها را دارد.
 * null = همه (Admin).
 */
function allowed_camp_ids(): ?array
{
    if (is_admin()) {
        return null;
    }
    static $ids;
    if ($ids === null) {
        $s = db()->prepare('SELECT camp_id FROM camp_editors WHERE user_id=?');
        $s->execute([(int)(current_user()['id'] ?? 0)]);
        $ids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    }
    return $ids;
}

/** آیا این اردو برای کاربر جاری مجاز است؟ */
function camp_allowed(int $campId): bool
{
    $ids = allowed_camp_ids();
    return $ids === null || in_array($campId, $ids, true);
}

function require_camp(int $campId): void
{
    if (!camp_allowed($campId)) {
        out(false, 'این اردو برای شما مجاز نیست', null, 403);
    }
}

/**
 * شناسه‌ی آلبوم‌هایی که کاربر جاری اجازه‌ی مدیریت آن‌ها را دارد (جدول album_editors؛ سازنده‌ی آلبوم خودکار اضافه می‌شود).
 * null = همه (Admin).
 */
function allowed_album_ids(): ?array
{
    if (is_admin() || has_full_media()) {
        return null;
    }
    static $ids;
    if ($ids === null) {
        $s = db()->prepare('SELECT album_id FROM album_editors WHERE user_id=?');
        $s->execute([(int)(current_user()['id'] ?? 0)]);
        $ids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    }
    return $ids;
}

function album_allowed(int $albumId): bool
{
    $ids = allowed_album_ids();
    return $ids === null || in_array($albumId, $ids, true);
}

/** آیا این رسانه برای کاربر جاری مجاز است؟ (عضو آلبوم مجاز، یا ساخته‌ی خودِ کاربر) */
function media_allowed(array $m): bool
{
    if (is_admin() || has_full_media()) {
        return true;
    }
    if (!empty($m['album_id']) && album_allowed((int)$m['album_id'])) {
        return true;
    }
    return !empty($m['created_by']) && (int)$m['created_by'] === (int)(current_user()['id'] ?? 0);
}

/** افزودن آلبوم به فهرست مجاز یک Editor (سازنده‌ی آلبوم) */
function grant_album(int $albumId): void
{
    if (!is_admin() && current_user()) {
        db()->prepare('INSERT IGNORE INTO album_editors(album_id,user_id) VALUES(?,?)')->execute([$albumId, (int)current_user()['id']]);
    }
}

/**
 * قطعه‌ی SQL برای محدودکردن جدول‌ها به اردوهای مجاز.
 * scope: self (جدول camps) | camp_id | field_id | album_self (جدول albums) | media_own (جدول media) | null
 */
function camp_scope_sql(?string $scope, string $alias = ''): string
{
    if ($scope === 'album_self' || $scope === 'media_own') { // محدوده‌ی آلبوم/رسانه برای Editor
        $aids = allowed_album_ids();
        if ($aids === null) {
            return '1=1';
        }
        $a   = $alias !== '' ? "$alias." : '';
        $in  = $aids ? implode(',', $aids) : '0';
        $uid = (int)(current_user()['id'] ?? 0);
        return $scope === 'album_self' ? "{$a}id IN ($in)" : "({$a}created_by=$uid OR {$a}album_id IN ($in))";
    }
    $ids = allowed_camp_ids();
    if ($ids === null || $scope === null) {
        return '1=1';
    }
    $in = $ids ? implode(',', $ids) : '0';
    $a  = $alias !== '' ? "$alias." : '';
    return match ($scope) {
        'self'     => "{$a}id IN ($in)",
        'camp_id'  => "{$a}camp_id IN ($in)",
        'field_id' => "field_id IN (SELECT id FROM form_fields WHERE camp_id IN ($in))",
        default    => '1=1',
    };
}
