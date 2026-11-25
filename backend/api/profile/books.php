<?php
/** GET /api/profile/books.php — بخش «books» پروفایل خود کاربر (فقط‌خواندنی؛ ثبت و ویرایش با مدیر) */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
out(true, '', pf_section('books', $v['member_id'], $v));
