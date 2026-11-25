<?php
/**
 * مدیریت نظرها در پنل
 *  GET                      → همه‌ی نظرها (جدیدترین اول)
 *  POST {id, op}            → op = approve | delete | hide | show
 */
require __DIR__ . '/../includes/bootstrap.php';
require_role('testimonials');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $in = json_input();
    $id = (int)($in['id'] ?? 0);
    $op = (string)($in['op'] ?? '');
    $q = $pdo->prepare('SELECT id,approved FROM testimonials WHERE id=?');
    $q->execute([$id]);
    $row = $q->fetch();
    if (!$row) {
        out(false, 'نظر یافت نشد', null, 404);
    }
    switch ($op) {
        case 'approve':
            $pdo->prepare('UPDATE testimonials SET approved=1 WHERE id=?')->execute([$id]);
            $msg = 'نظر تأیید شد';
            break;
        case 'hide':
        case 'show':
            $pdo->prepare('UPDATE testimonials SET enabled=? WHERE id=?')->execute([$op === 'show' ? 1 : 0, $id]);
            $msg = $op === 'show' ? 'نظر نمایش داده می‌شود' : 'نظر مخفی شد';
            break;
        case 'delete':
            $pdo->prepare('DELETE FROM testimonials WHERE id=?')->execute([$id]);
            $msg = 'نظر حذف شد';
            break;
        default:
            out(false, 'عملیات نامعتبر', null, 422);
    }
    log_act('testimonial_' . $op, (string)$id);
    out(true, $msg);
}

out(true, '', $pdo->query('SELECT id,name,role,body,rating,approved,enabled,created_at FROM testimonials ORDER BY approved ASC, id DESC LIMIT 200')->fetchAll());
