<?php
/**
 * entities.php — تعریف موجودیت‌های قابل مدیریت از طریق crud.php
 *
 * area  : بخش دسترسی (EDITOR_CAN در permissions.php)
 * cols  : ستون‌های قابل نوشتن/فیلتر — فقط همین‌ها وارد SQL می‌شوند (allowlist)
 * order : مرتب‌سازی پیش‌فرض
 * scope : محدودسازی Editor به اردوهای مجاز (self | camp_id | field_id) یا آلبوم/رسانه‌ی مجاز (album_self | media_own)
 * pk    : کلید اصلی (پیش‌فرض id)
 * ro    : فقط‌خواندنی
 */
function entities(): array
{
    return [
        'camps' => ['area' => 'camps', 'scope' => 'self', 'order' => 'id DESC', 'cols' => [
            'slug', 'title', 'short_desc', 'full_desc', 'cover', 'start_date', 'end_date', 'location', 'capacity', 'cost',
            'conditions', 'schedule', 'facilities', 'manager', 'phone', 'deadline', 'camp_status', 'registration_status',
            'block_duplicates', 'photo_required', 'map_url', 'published']],
        'camp_forms' => ['area' => 'forms', 'scope' => 'camp_id', 'pk' => 'camp_id', 'order' => 'camp_id', 'cols' => ['camp_id', 'title', 'intro', 'enabled']],
        'form_fields' => ['area' => 'forms', 'scope' => 'camp_id', 'order' => 'sort_order,id', 'cols' => [
            'camp_id', 'name', 'label', 'type', 'required', 'enabled', 'sort_order']],
        'form_options' => ['area' => 'forms', 'scope' => 'field_id', 'order' => 'sort_order,id', 'cols' => [
            'field_id', 'label', 'enabled', 'sort_order']],
        'albums' => ['area' => 'albums', 'scope' => 'album_self', 'order' => 'id DESC', 'cols' => ['slug', 'title', 'description', 'album_date', 'cover', 'published']],
        // path/type فقط از مسیر آپلود یا لینک ویدئو تعیین می‌شوند، نه از ویرایش دستی
        'heyats' => ['area' => 'heyats', 'order' => 'event_date DESC, event_time DESC, id DESC', 'cols' => ['title', 'caption', 'image', 'event_date', 'event_time', 'occasion', 'published']],
        'media' => ['area' => 'media', 'scope' => 'media_own', 'order' => 'sort_order,id', 'cols' => ['album_id', 'title', 'sort_order', 'featured']],
        'news' => ['area' => 'news', 'order' => 'id DESC', 'cols' => ['slug', 'title', 'summary', 'content', 'cover', 'author', 'published']],
        'pages' => ['area' => 'pages', 'order' => 'id', 'cols' => ['slug', 'title', 'content', 'published']],
        'contacts' => ['area' => 'contacts', 'order' => 'sort_order,id', 'cols' => ['label', 'value', 'kind', 'enabled', 'sort_order']],
        'channels' => ['area' => 'channels', 'order' => 'sort_order,id', 'cols' => ['title', 'url', 'icon', 'enabled', 'sort_order']],
        'menu' => ['area' => 'menu', 'order' => 'sort_order,id', 'cols' => ['title', 'link', 'enabled', 'sort_order']],
        'footer_links' => ['area' => 'footer', 'order' => 'sort_order,id', 'cols' => ['title', 'link', 'enabled', 'sort_order']],
        'seo' => ['area' => 'seo', 'order' => 'id', 'cols' => [
            'entity', 'entity_id', 'seo_title', 'meta_description', 'canonical', 'og_title', 'og_description', 'og_image', 'schema_json']],
        'settings' => ['area' => 'settings', 'pk' => 'k', 'order' => 'k', 'cols' => ['k', 'v']],
        'responsibility_types' => ['area' => 'profiles_manage', 'order' => 'body,sort_order,id', 'cols' => ['body', 'title', 'enabled', 'sort_order']],
        'users' => ['area' => 'users', 'order' => 'id', 'cols' => ['username', 'role', 'active']],
        'logs' => ['area' => 'logs', 'ro' => true, 'order' => 'id DESC', 'cols' => []],
    ];
}
