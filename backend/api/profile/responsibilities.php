<?php
/** GET /api/profile/responsibilities.php — بخش «responsibilities» پروفایل خود کاربر (فقط‌خواندنی) */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
out(true, '', pf_section('responsibilities', $v['member_id'], $v));
