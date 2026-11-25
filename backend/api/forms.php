<?php
// فرم ثبت‌نام یک اردو: /api/forms.php?camp=slug  → {title,intro,photo_required,fields:[…]}
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_data.php';
$r = public_camps(db(), (string)($_GET['camp'] ?? ''));
if (!$r) {
    out(false, 'اردو پیدا نشد', null, 404);
}
$c = $r[0];
out(true, '', ['title' => $c['form_title'], 'intro' => $c['form_intro'], 'photo_required' => $c['photo_required'], 'fields' => $c['fields']]);
