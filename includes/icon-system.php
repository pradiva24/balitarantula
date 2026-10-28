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
    $value = site_icon_value($key, $default);
    // Font Awesome 6 no longer ships the Tripadvisor brand glyph. Keep the
    // existing editable class in Icon Manager, but render its legacy glyph
    // locally so the icon remains visible without loading another icon pack.
    if ($key === 'footer.tripadvisor' && trim($value) === 'fa-brands fa-tripadvisor') {
        return '<svg class="site-tripadvisor-icon '.e(trim($extra)).'" viewBox="0 0 2304 1316" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="".M651 539Q651 500 623.5 472.5Q596 445 558 445Q519 445 491.5 472.5Q464 500 464 539Q464 577 491.5 604.5Q519 632 558 632Q596 632 623.5 604.5Q651 577 651 539ZM1777.5 606.0Q1805 579 1805.0 540.0Q1805 501 1777.5 473.5Q1750 446 1711.0 446.0Q1672 446 1644.5 473.5Q1617 501 1617.0 540.0Q1617 579 1644.5 606.0Q1672 633 1711.0 633.0Q1750 633 1777.5 606.0ZM765 539Q765 618 708.5 675.0Q652 732 572.0 732.0Q492 732 435.5 675.5Q379 619 379.0 539.0Q379 459 435.5 402.5Q492 346 572.0 346.0Q652 346 708.5 402.5Q765 459 765 539ZM1725 733Q1646 733 1589.0 676.5Q1532 620 1532.0 540.0Q1532 460 1588.5 403.5Q1645 347 1725.0 347.0Q1805 347 1861.5 403.5Q1918 460 1918.0 540.0Q1918 620 1861.5 676.5Q1805 733 1725 733ZM850 539Q850 423 768.5 341.5Q687 260 572 260Q456 260 374.5 342.0Q293 424 293.0 539.0Q293 654 375.0 735.5Q457 817 572.0 817.0Q687 817 768.5 735.5Q850 654 850 539ZM1725 262Q1610 262 1528.5 343.5Q1447 425 1447.0 540.0Q1447 655 1528.5 736.5Q1610 818 1725 818Q1841 818 1922.5 736.5Q2004 655 2004.0 540.0Q2004 425 1922.5 343.5Q1841 262 1725 262ZM578 999Q453 999 347.0 937.0Q241 875 179.0 768.5Q117 662 117.0 537.0Q117 412 179.0 305.5Q241 199 347.0 137.0Q453 75 578 75Q769 75 904.5 210.5Q1040 346 1040.0 537.0Q1040 728 904.5 863.5Q769 999 578 999ZM1708 1110Q1454 1221 1152 1221Q833 1221 579 1111Q696 1111 802.0 1065.5Q908 1020 984.5 943.0Q1061 866 1106.5 760.0Q1152 654 1152 537Q1152 652 1195.5 756.5Q1239 861 1313.5 937.0Q1388 1013 1491.0 1060.0Q1594 1107 1708 1110ZM2052.0 210.5Q2187 346 2187.0 537.0Q2187 728 2052.0 863.5Q1917 999 1726.0 999.0Q1535 999 1399.5 863.5Q1264 728 1264.0 537.0Q1264 346 1399.5 210.5Q1535 75 1726.0 75.0Q1917 75 2052.0 210.5ZM1921 1103H2304Q2260 1052 2229.0 988.5Q2198 925 2189 874Q2299 723 2299 537Q2299 381 2222.0 249.0Q2145 117 2013.0 40.5Q1881 -36 1726 -36Q1593 -36 1477.0 20.0Q1361 76 1281 175Q1234 119 1152 -4Q1141 18 1098.5 78.5Q1056 139 1024 176Q944 77 827.5 20.5Q711 -36 578 -36Q423 -36 291.0 40.5Q159 117 82.0 249.0Q5 381 5 537Q5 723 115 874Q106 925 75.0 988.5Q44 1052 0 1103H365Q514 1203 720.0 1259.5Q926 1316 1152 1316Q1376 1316 1573.0 1260.0Q1770 1204 1921 1103Z"/></svg>';
    }
    $classes = trim($value.' '.$extra);
    return '<i class="'.e($classes).'" aria-hidden="true"></i>';
}

/** Backward-compatible renderer used by older pages. */
function fa_icon(string $key, string $default='fa-solid fa-circle', string $extra=''): string {
    return site_icon_html($key, $default, $extra);
}
