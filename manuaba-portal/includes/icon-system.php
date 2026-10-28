<?php
/**
 * Central Font Awesome registry.
 * Page-specific editable icons are read from their real CMS tables;
 * shared/UI icons are stored in settings as icon_<key>.
 */
function icon_is_fa_class(string $value): bool {
    return (bool)preg_match('/^fa-(?:solid|regular|brands|light|thin|duotone)\s+fa-[a-z0-9-]+(?:\s+fa-[a-z0-9-]+)*$/i', trim($value));
}

function site_icon_value(string $key, string $default): string {
    $key = trim($key);
    $value = '';
    try {
        $db = db();
        if (strpos($key, 'home.') === 0) {
            $field = substr($key, 5) . '_icon';
            $allowed = ['why_1_icon','why_2_icon','why_3_icon','why_4_icon','value_1_icon','value_2_icon','value_3_icon','value_4_icon'];
            if (in_array($field, $allowed, true)) {
                $st = $db->query("SELECT `{$field}` FROM homepage_content WHERE id=1 LIMIT 1");
                $value = (string)($st->fetchColumn() ?: '');
            }
        } elseif (strpos($key, 'about.') === 0) {
            $field = substr($key, 6) . '_icon';
            $allowed = ['why_1_icon','why_2_icon','why_3_icon','why_4_icon','value_1_icon','value_2_icon','value_3_icon','value_4_icon'];
            if (in_array($field, $allowed, true)) {
                // About Us stores its editable content in about_page.
                $st = $db->query("SELECT `{$field}` FROM about_page WHERE id=1 LIMIT 1");
                $value = (string)($st->fetchColumn() ?: '');
            }
        } else {
            $value = (string)setting('icon_'.$key, '');
        }
    } catch (Throwable $e) {
        $value = '';
    }

    $value = trim($value);
    return icon_is_fa_class($value) ? $value : $default;
}

function site_icon(string $key, string $default): string {
    return site_icon_value($key, $default);
}

function site_icon_html(string $key, string $default, string $extra=''): string {
    $classes = trim(site_icon_value($key, $default).' '.$extra);
    return '<i class="'.e($classes).'" aria-hidden="true"></i>';
}

/** Backward-compatible renderer used by older pages. */
function fa_icon(string $key, string $default='fa-solid fa-circle', string $extra=''): string {
    return site_icon_html($key, $default, $extra);
}
