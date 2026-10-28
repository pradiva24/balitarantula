<?php
/* v111 Header Builder loader */
function bt_header_settings(){
  static $settings=null;
  if($settings!==null) return $settings;
  $defaults=[
    'logo'=>'','logo_width'=>'155px','logo_height'=>'48px','header_height'=>'76px',
    'header_background'=>'#ffffff','menu_color'=>'#234737','menu_hover'=>'#1f6a49','menu_active'=>'#1f6a49',
    'menu_size'=>'12px','menu_weight'=>'700','menu_gap'=>'28px','header_border_color'=>'#1f5f43','header_border_width'=>'5px',
    'button_background'=>'#28684c','button_hover'=>'#1f5a41','button_text'=>'#ffffff','button_radius'=>'5px',
    'show_button'=>'1','button_text_label'=>'Book Now','button_url'=>'/booking.php','sticky'=>'1','mobile_menu'=>'1',
    'mobile_breakpoint'=>'900px','mobile_background'=>'#ffffff','mobile_menu_color'=>'#234737','transparent_home'=>'0','header_shadow'=>'none'
  ];
  $file=__DIR__.'/header-settings.json';
  if(is_file($file)){$x=json_decode(@file_get_contents($file),true);if(is_array($x))$defaults=array_merge($defaults,$x);}
  $settings=$defaults; return $settings;
}
function bt_header_css(){
  $s=bt_header_settings();
  $esc=function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
  $sticky=$s['sticky']==='1'?'sticky':'relative';
  $mobile=$s['mobile_menu']==='1';
  $show=$s['show_button']==='1';
  $css=':root{--bt-header-height:'.$esc($s['header_height']).';--bt-header-bg:'.$esc($s['header_background']).';--bt-menu-color:'.$esc($s['menu_color']).';--bt-menu-hover:'.$esc($s['menu_hover']).';--bt-menu-active:'.$esc($s['menu_active']).';--bt-menu-size:'.$esc($s['menu_size']).';--bt-menu-weight:'.$esc($s['menu_weight']).';--bt-menu-gap:'.$esc($s['menu_gap']).';--bt-header-border:'.$esc($s['header_border_color']).';--bt-header-border-width:'.$esc($s['header_border_width']).';--bt-header-btn:'.$esc($s['button_background']).';--bt-header-btn-hover:'.$esc($s['button_hover']).';--bt-header-btn-text:'.$esc($s['button_text']).';--bt-header-btn-radius:'.$esc($s['button_radius']).';--bt-mobile-bg:'.$esc($s['mobile_background']).';--bt-mobile-menu-color:'.$esc($s['mobile_menu_color']).'}';
  $css.='.site-header{position:'.$sticky.'!important;height:'.$esc($s['header_height']).'!important;background:'.$esc($s['header_background']).'!important;border-bottom:'.$esc($s['header_border_width']).' solid '.$esc($s['header_border_color']).'!important;box-shadow:'.$esc($s['header_shadow']).'!important}.site-header .nav{height:calc('.$esc($s['header_height']).' - '.$esc($s['header_border_width']).')!important;min-height:0!important}.site-header #mainNav{gap:'.$esc($s['menu_gap']).'!important}.site-header #mainNav a{color:'.$esc($s['menu_color']).'!important;font-size:'.$esc($s['menu_size']).'!important;font-weight:'.$esc($s['menu_weight']).'!important}.site-header #mainNav a:hover{color:'.$esc($s['menu_hover']).'!important}.site-header #mainNav a.active{color:'.$esc($s['menu_active']).'!important}.site-header #mainNav .nav-book{background:'.$esc($s['button_background']).'!important;color:'.$esc($s['button_text']).'!important;border-radius:'.$esc($s['button_radius']).'!important}.site-header #mainNav .nav-book:hover{background:'.$esc($s['button_hover']).'!important}.site-logo-img{width:auto!important;max-width:'.$esc($s['logo_width']).'!important;height:'.$esc($s['logo_height']).'!important;object-fit:contain}.mobile-nav-btn{color:'.$esc($s['menu_color']).'!important}';
  if(!$show)$css.='.site-header #mainNav .nav-book{display:none!important}';
  if(!$mobile)$css.='.mobile-nav-btn{display:none!important}';
  $bp=preg_match('/^\d+(?:\.\d+)?px$/',$s['mobile_breakpoint'])?$s['mobile_breakpoint']:'900px';
  $css.='@media(max-width:'.$esc($bp).'){.site-header .nav{height:calc('.$esc($s['header_height']).' - '.$esc($s['header_border_width']).')!important}.site-header #mainNav{background:'.$esc($s['mobile_background']).'!important}.site-header #mainNav a{color:'.$esc($s['mobile_menu_color']).'!important}.site-header #mainNav a:hover{color:'.$esc($s['menu_hover']).'!important}.site-header #mainNav .nav-book{display:block!important}}';
  return $css;
}
?>
<style id="bt-header-builder"><?=bt_header_css()?></style>
