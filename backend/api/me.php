<?php
// کاربر جاری، بخش‌های مجاز منو و توکن CSRF
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/entities.php';
$u = current_user();
if (!$u) {
    out(false, 'ورود لازم است', null, 401);
}
$areas = $u['role'] === 'admin'
    ? array_values(array_unique(array_merge(array_column(entities(), 'area'), ['registrations', 'backup', 'reports', 'profiles', 'profiles_manage', 'testimonials', 'sms', 'dashboard', 'heyat_sms'])))
    : role_areas((string)$u['role']);
out(true, '', ['user' => $u, 'areas' => $areas, 'csrf' => csrf_token()]);
