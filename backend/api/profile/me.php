<?php
/** GET /api/profile/me.php — پروفایل جامع خود کاربر (Header، آمار، اطلاعات شخصی، تحصیلی) + توکن CSRF */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
$data = pf_section('profile', $v['member_id'], $v);
$data['csrf'] = csrf_token();
out(true, '', $data);
