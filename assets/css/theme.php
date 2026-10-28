<?php
header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
$defaults=[
'primary'=>'#ff5500','secondary'=>'#111111','accent'=>'#ff5500','background'=>'#ffffff',
'surface'=>'#f7f7f7','text'=>'#222222','heading'=>'#111111','button'=>'#ff5500',
'button_hover'=>'#e04800','header'=>'#ffffff','footer'=>'#111111','overlay'=>'rgba(0,0,0,.35)',
'body_font'=>"'Inter', sans-serif",'heading_font'=>"'Barlow Condensed', sans-serif",
'body_size'=>'15px','menu_size'=>'15px','button_radius'=>'4px','sticky_header'=>'1'];
$file=__DIR__.'/../../theme-settings.json';
$t=$defaults;
if(is_file($file)){ $x=json_decode(@file_get_contents($file),true); if(is_array($x))$t=array_merge($t,$x); }
function cssv($v){return preg_replace('/[^#a-zA-Z0-9(),.%\s\-\.]/','',(string)$v);}
?>
@import url("https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700;1,800;1,900&family=Inter:wght@400;500;600;700;800&display=swap");
:root{
--bt-primary:<?=cssv($t['primary'])?>;--bt-secondary:<?=cssv($t['secondary'])?>;--bt-accent:<?=cssv($t['accent'])?>;
--bt-bg:<?=cssv($t['background'])?>;--bt-surface:<?=cssv($t['surface'])?>;--bt-text:<?=cssv($t['text'])?>;
--bt-heading:<?=cssv($t['heading'])?>;--bt-button:<?=cssv($t['button'])?>;--bt-button-hover:<?=cssv($t['button_hover'])?>;
--bt-header:<?=cssv($t['header'])?>;--bt-footer:<?=cssv($t['footer'])?>;--bt-overlay:<?=cssv($t['overlay'])?>;
--bt-body-font:'Inter', sans-serif;--bt-heading-font:'Barlow Condensed', sans-serif;
--bt-body-size:<?=cssv($t['body_size'])?>;--bt-menu-size:<?=cssv($t['menu_size'])?>;
--bt-button-radius:<?=cssv($t['button_radius'])?>}
body{font-family:var(--bt-body-font);font-size:var(--bt-body-size);color:var(--bt-text);background:var(--bt-bg)}
h1,h2,h3,h4,h5,h6{font-family:var(--bt-heading-font);color:var(--bt-heading);text-transform:uppercase}
header,.header,.site-header,.navbar,.nav-bar{background-color:var(--bt-header)}
footer,.footer,.site-footer{background-color:var(--bt-footer)}
button,.btn,.button,.book-now,.book-now-btn,.cta-button{font-family:var(--bt-heading-font);text-transform:uppercase;background-color:var(--bt-button);border-color:var(--bt-button);border-radius:var(--bt-button-radius)}
button:hover,.btn:hover,.button:hover,.book-now:hover,.book-now-btn:hover,.cta-button:hover{background-color:var(--bt-button-hover);border-color:var(--bt-button-hover)}
a{color:var(--bt-primary)}

/* Global font enforcement */
html, body, body p, body div, input, textarea, select, option { font-family: 'Inter', sans-serif !important; }
h1, h2, h3, h4, h5, h6, .site-header #mainNav a, header, header *, nav, nav *, .navbar, .navbar *, .nav-bar, .nav-bar *, button, .btn, .button, .book-now, .book-now-btn, .cta-button { font-family: 'Barlow Condensed', sans-serif !important; text-transform: uppercase !important; }
h1 span, h2 span, h3 span { color: #ff5500 !important; }

/* Font Awesome 6 font isolation */
.fa-solid, .fas, [class*="fa-solid"] { font-family: "Font Awesome 6 Free" !important; font-weight: 900 !important; font-style: normal !important; text-transform: none !important; }
.fa-regular, .far, [class*="fa-regular"] { font-family: "Font Awesome 6 Free" !important; font-weight: 400 !important; font-style: normal !important; text-transform: none !important; }
.fa-brands, .fab, [class*="fa-brands"] { font-family: "Font Awesome 6 Brands" !important; font-weight: 400 !important; font-style: normal !important; text-transform: none !important; }
i[class*="fa-"], span[class*="fa-"], [class^="fa-"], [class*=" fa-"], .fa, .fas, .far, .fab { font-style: normal !important; line-height: 1 !important; text-rendering: auto !important; text-transform: none !important; -webkit-font-smoothing: antialiased !important; }
