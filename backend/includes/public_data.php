<?php
/**
 * public_data.php — خواندن داده‌های عمومی (منتشرشده) برای APIهای Frontend
 */

/** اردوهای منتشرشده همراه با فرم (سؤال‌ها و گزینه‌های فعال). با $slug فقط یک اردو. */
function public_camps(PDO $pdo, ?string $slug = null, bool $withDrafts = false): array
{
    $sql = 'SELECT id,slug,title,short_desc,full_desc,cover,location,start_date AS start,end_date AS end,capacity,cost,
                   conditions,schedule,facilities,manager,phone,deadline,registration_status AS status,camp_status,photo_required,map_url,published
            FROM camps WHERE ' . ($withDrafts ? '1=1' : 'published=1');
    $params = [];
    if ($slug !== null) {
        $sql .= ' AND slug=?';
        $params[] = $slug;
    }
    $s = $pdo->prepare($sql . ' ORDER BY start_date DESC, id DESC');
    $s->execute($params);
    $camps = $s->fetchAll();
    if (!$camps) {
        return [];
    }
    $in = implode(',', array_map('intval', array_column($camps, 'id')));

    $fields = $pdo->query("SELECT id,camp_id,name,label,type,required FROM form_fields WHERE camp_id IN ($in) AND enabled=1 ORDER BY sort_order,id")->fetchAll();
    $options = [];
    if ($fields) {
        $fin = implode(',', array_map('intval', array_column($fields, 'id')));
        foreach ($pdo->query("SELECT field_id,label FROM form_options WHERE field_id IN ($fin) AND enabled=1 ORDER BY sort_order,id") as $o) {
            $options[$o['field_id']][] = $o['label'];
        }
    }
    $byCamp = [];
    foreach ($fields as $f) {
        $f['options'] = $options[$f['id']] ?? [];
        $byCamp[$f['camp_id']][] = $f;
    }
    $forms = [];
    foreach ($pdo->query("SELECT camp_id,title,intro FROM camp_forms WHERE camp_id IN ($in) AND enabled=1") as $r) {
        $forms[$r['camp_id']] = $r;
    }
    $counts = $pdo->query("SELECT camp_id,COUNT(*) n FROM registrations WHERE camp_id IN ($in) AND status NOT IN('rejected','cancelled') GROUP BY camp_id")
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($camps as &$c) {
        $c['fields']     = $byCamp[$c['id']] ?? [];
        $c['form_title'] = $forms[$c['id']]['title'] ?? '';
        $c['form_intro'] = $forms[$c['id']]['intro'] ?? '';
        $c['registered'] = (int)($counts[$c['id']] ?? 0);
        $c['photo_required'] = (int)$c['photo_required'];
        $c['published']  = (int)$c['published'];
    }
    unset($c);
    return $camps;
}

/** تنظیمات عمومی (بدون کلیدهای secret*) */
function public_settings(PDO $pdo): array
{
    return $pdo->query("SELECT k,v FROM settings WHERE k NOT LIKE 'secret%'")->fetchAll(PDO::FETCH_KEY_PAIR);
}
