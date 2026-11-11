<?php
require_once __DIR__.'/includes/auth.php';

$defaults=[
'primary'=>'#0f5132','secondary'=>'#198754','accent'=>'#f4b400',
'background'=>'#ffffff','surface'=>'#f7f7f7','text'=>'#222222','heading'=>'#111111',
'button'=>'#f4b400','button_hover'=>'#d99d00','header'=>'#ffffff','footer'=>'#111111',
'overlay'=>'rgba(0,0,0,.35)','body_font'=>'Arial, Helvetica, sans-serif',
'heading_font'=>'Arial, Helvetica, sans-serif','body_size'=>'15px','menu_size'=>'15px',
'button_radius'=>'6px','sticky_header'=>'1'
];
$file=__DIR__.'/../theme-settings.json';
$theme=$defaults;
if(is_file($file)){
  $x=json_decode(file_get_contents($file),true);
  if(is_array($x)) $theme=array_merge($theme,$x);
}
$saved=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
  admin_verify_csrf();
  foreach($defaults as $k=>$v){
    if($k==='sticky_header') continue;
    if(isset($_POST[$k])) $theme[$k]=trim((string)$_POST[$k]);
  }
  $theme['sticky_header']=isset($_POST['sticky_header'])?'1':'0';
  file_put_contents($file,json_encode($theme,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);
  $saved=true;
}
function color_value($v){return preg_match('/^#[0-9a-fA-F]{6}$/',$v)?$v:'#ffffff';}
?>
<!doctype html><html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Appearance</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="assets/css/admin.css">
<style>
.appearance{max-width:1050px;margin:24px auto;padding:0 18px}.card{background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:20px;margin:0 0 18px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.field label{display:block;font-weight:700;margin-bottom:7px}
.field input[type=text],.field select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ddd;border-radius:7px}
.field input[type=color]{width:100%;height:42px;padding:2px;border:1px solid #ddd;border-radius:7px}
.save{background:#198754;color:#fff;border:0;border-radius:7px;padding:11px 18px;font-weight:700;cursor:pointer}.notice{padding:12px;background:#eaf7ee;border:1px solid #b9dfc4;border-radius:8px;margin-bottom:18px}
@media(max-width:700px){.grid{grid-template-columns:1fr}}
</style></head><body>
<?php include __DIR__.'/includes/header.php'; ?>
<main class="appearance"><p><a href="customizer.php"><?=fa_icon('settings','fa-solid fa-palette')?> Open Theme Customizer / Live Preview</a></p>
<h1>Appearance / Theme Settings</h1>
<p>Atur warna, tipografi, tombol, dan header website dari admin.</p>
<?php if($saved):?><div class="notice">Theme settings berhasil disimpan.</div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>">
<section class="card"><h2>Global Colors</h2><div class="grid">
<?php foreach([
'primary'=>'Primary Color','secondary'=>'Secondary Color','accent'=>'Accent Color',
'background'=>'Background','surface'=>'Surface','text'=>'Text Color','heading'=>'Heading Color',
'button'=>'Button Color','button_hover'=>'Button Hover','header'=>'Header Color','footer'=>'Footer Color'
] as $k=>$label):?>
<div class="field"><label><?=admin_e($label)?></label>
<input type="color" value="<?=admin_e(color_value($theme[$k]))?>" oninput="this.nextElementSibling.value=this.value">
<input type="text" name="<?=$k?>" value="<?=admin_e($theme[$k])?>">
</div>
<?php endforeach;?>
<div class="field"><label>Hero Overlay</label><input type="text" name="overlay" value="<?=admin_e($theme['overlay'])?>"></div>
</div></section>

<section class="card"><h2>Typography</h2><div class="grid">
<div class="field"><label>Body Font</label><input type="text" name="body_font" value="<?=admin_e($theme['body_font'])?>"></div>
<div class="field"><label>Heading Font</label><input type="text" name="heading_font" value="<?=admin_e($theme['heading_font'])?>"></div>
<div class="field"><label>Body Size</label><input type="text" name="body_size" value="<?=admin_e($theme['body_size'])?>"></div>
<div class="field"><label>Menu Size</label><input type="text" name="menu_size" value="<?=admin_e($theme['menu_size'])?>"></div>
<div class="field"><label>Button Radius</label><input type="text" name="button_radius" value="<?=admin_e($theme['button_radius'])?>"></div>
</div></section>

<section class="card"><h2>Header</h2>
<label><input type="checkbox" name="sticky_header" value="1" <?=$theme['sticky_header']==='1'?'checked':''?>> Sticky Header</label>
</section>
<button class="save" type="submit">Save Theme Settings</button>
</form></main></body></html>