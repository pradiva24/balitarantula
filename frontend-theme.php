<?php
/* v110: connect Admin Appearance settings to public frontend. */
require_once __DIR__.'/theme-loader.php';
$t=bt_theme_settings();
function bt_theme_val($key,$fallback=''){ global $t; return htmlspecialchars((string)($t[$key]??$fallback),ENT_QUOTES,'UTF-8'); }
?>
<style id="bt-frontend-theme">
:root{
 --bt-primary:<?=bt_theme_val('primary','#0f5132')?>;--bt-secondary:<?=bt_theme_val('secondary','#198754')?>;--bt-accent:<?=bt_theme_val('accent','#f4b400')?>;
 --bt-bg:<?=bt_theme_val('background','#fff')?>;--bt-surface:<?=bt_theme_val('surface','#f7f7f7')?>;--bt-text:<?=bt_theme_val('text','#222')?>;--bt-heading:<?=bt_theme_val('heading','#111')?>;
 --bt-button:<?=bt_theme_val('button','#f4b400')?>;--bt-button-hover:<?=bt_theme_val('button_hover','#d99d00')?>;--bt-header:<?=bt_theme_val('header','#fff')?>;--bt-footer:<?=bt_theme_val('footer','#111')?>;
 --bt-overlay:<?=bt_theme_val('overlay','rgba(0,0,0,.35)')?>;--bt-body-font:<?=bt_theme_val('body_font',"'Inter', sans-serif")?>;--bt-heading-font:<?=bt_theme_val('heading_font',"'Barlow Condensed', sans-serif")?>;
 --bt-body-size:<?=bt_theme_val('body_size','15px')?>;--bt-menu-size:<?=bt_theme_val('menu_size','15px')?>;--bt-button-radius:<?=bt_theme_val('button_radius','6px')?>;
}
html{scroll-behavior:smooth}body{font-family:var(--bt-body-font)!important;font-size:var(--bt-body-size)!important;color:var(--bt-text);background:var(--bt-bg)}
h1,h2,h3,h4,h5,h6{font-family:var(--bt-heading-font)!important;color:var(--bt-heading)}
.site-header{background:var(--bt-header)!important}
.site-header a:not(.nav-book){color:var(--bt-text)}
.site-header #mainNav a{font-size:var(--bt-menu-size)}
.site-header #mainNav a:hover{color:var(--bt-primary)!important}
.site-header #mainNav .nav-book{background:var(--bt-button)!important;color:var(--bt-heading)!important;border-radius:var(--bt-button-radius)!important}
.site-header #mainNav .nav-book:hover{background:var(--bt-button-hover)!important}
.btn,.button,button,.cta-button,.book-now,.book-now-btn{border-radius:var(--bt-button-radius)!important}
.btn,.button,.cta-button,.book-now,.book-now-btn{background:var(--bt-button)}
a{--link-color:var(--bt-primary)}
.site-footer,footer,.footer{background:var(--bt-footer)!important}

html, body, body p, body span, body div, body li, body label, body input, body textarea, body select, body option, body small, body strong, body em, body td, body th { font-family: 'Inter', sans-serif !important; }
h1, h2, h3, h4, h5, h6, .site-header, .site-header *, header, header *, nav, nav *, .nav, .nav *, .navbar, .navbar *, button, .btn, .button, .cta-button, .book-now, .book-now-btn { font-family: 'Barlow Condensed', sans-serif !important; }
</style>
