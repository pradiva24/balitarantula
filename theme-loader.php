<?php
/* Global theme loader - generated for Bali Tarantula Adventure v106. */
function bt_theme_settings() {
    static $theme = null;
    if ($theme !== null) return $theme;
    $defaults = [
      'primary'=>'#ff5500','secondary'=>'#111111','accent'=>'#ff5500',
      'background'=>'#ffffff','surface'=>'#f7f7f7','text'=>'#222222','heading'=>'#111111',
      'button'=>'#ff5500','button_hover'=>'#e04800','header'=>'#ffffff','footer'=>'#111111',
      'overlay'=>'rgba(0,0,0,.35)','body_font'=>"'Inter', sans-serif",
      'heading_font'=>"'Barlow Condensed', sans-serif",'body_size'=>'15px','menu_size'=>'15px',
      'button_radius'=>'4px','sticky_header'=>'1'
    ];
    $candidates = [
      __DIR__.'/theme-settings.json',
      dirname(__DIR__).'/theme-settings.json',
      dirname(__DIR__).'/admin/../theme-settings.json'
    ];
    $file = null;
    foreach ($candidates as $candidate) {
      if (is_file($candidate)) { $file = $candidate; break; }
    }
    if ($file) {
      $data = json_decode(@file_get_contents($file), true);
      if (is_array($data)) $defaults = array_merge($defaults, $data);
    }
    $theme = $defaults;
    return $theme;
}
function bt_theme_css() {
    $t = bt_theme_settings();
    $esc = function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
    return ':root{'.
      '--bt-primary:'.$esc($t['primary']).';'.
      '--bt-secondary:'.$esc($t['secondary']).';'.
      '--bt-accent:'.$esc($t['accent']).';'.
      '--bt-bg:'.$esc($t['background']).';'.
      '--bt-surface:'.$esc($t['surface']).';'.
      '--bt-text:'.$esc($t['text']).';'.
      '--bt-heading:'.$esc($t['heading']).';'.
      '--bt-button:'.$esc($t['button']).';'.
      '--bt-button-hover:'.$esc($t['button_hover']).';'.
      '--bt-header:'.$esc($t['header']).';'.
      '--bt-footer:'.$esc($t['footer']).';'.
      '--bt-overlay:'.$esc($t['overlay']).';'.
      '--bt-body-font:'.$esc($t['body_font']).';'.
      '--bt-heading-font:'.$esc($t['heading_font']).';'.
      '--bt-body-size:'.$esc($t['body_size']).';'.
      '--bt-menu-size:'.$esc($t['menu_size']).';'.
      '--bt-button-radius:'.$esc($t['button_radius']).';'.
      '}';
}
?>
<style id="bt-global-theme"><?=bt_theme_css()?>
body{font-family:var(--bt-body-font);font-size:var(--bt-body-size);color:var(--bt-text);background:var(--bt-bg)}
h1,h2,h3,h4,h5,h6{font-family:var(--bt-heading-font);color:var(--bt-heading)}
button,.btn,.button,.book-now,a.button,a.btn{border-radius:var(--bt-button-radius)}
.site-header,.header,header,.navbar{background:var(--bt-header)}
.site-footer,footer{background:var(--bt-footer)}
</style>
