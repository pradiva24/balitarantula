<?php
require_once __DIR__.'/includes/auth.php';

$defaults=[
'primary'=>'#0f5132','secondary'=>'#198754','accent'=>'#f4b400','background'=>'#ffffff',
'surface'=>'#f7f7f7','text'=>'#222222','heading'=>'#111111','button'=>'#f4b400',
'button_hover'=>'#d99d00','header'=>'#ffffff','footer'=>'#111111','overlay'=>'rgba(0,0,0,.35)',
'body_font'=>"'Inter', sans-serif",'heading_font'=>"'Barlow Condensed', sans-serif",
'body_size'=>'15px','menu_size'=>'15px','button_radius'=>'4px','sticky_header'=>'1'
];
$file=__DIR__.'/../theme-settings.json';
$theme=$defaults;
if(is_file($file)){ $x=json_decode(file_get_contents($file),true); if(is_array($x))$theme=array_merge($theme,$x); }

if($_SERVER['REQUEST_METHOD']==='POST'){
  admin_verify_csrf();
  foreach($defaults as $k=>$v){
    if($k==='sticky_header') continue;
    if(isset($_POST[$k])) $theme[$k]=trim((string)$_POST[$k]);
  }
  $theme['sticky_header']=isset($_POST['sticky_header'])?'1':'0';
  file_put_contents($file,json_encode($theme,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);
  header('Location: customizer.php?saved=1');
  exit;
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Theme Customizer</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="assets/css/admin.css">
<style>
*{box-sizing:border-box}.customizer{height:calc(100vh - 20px);display:grid;grid-template-columns:380px 1fr;gap:0;background:#f3f4f6}
.panel{background:#fff;overflow:auto;padding:20px;border-right:1px solid #ddd}.preview{background:#dfe3e8;position:relative;min-width:0}
.previewbar{height:42px;background:#fff;border-bottom:1px solid #ccc;display:flex;align-items:center;gap:8px;padding:0 12px;font-size:13px}
.previewbar input{flex:1;padding:7px 9px;border:1px solid #ddd;border-radius:6px}
.frame{position:absolute;left:14px;right:14px;top:56px;bottom:14px;background:#fff;border:1px solid #bbb;border-radius:8px;overflow:hidden}
iframe{width:100%;height:100%;border:0;background:#fff}
.section{border:1px solid #e4e4e4;border-radius:10px;margin-bottom:12px;padding:14px}.section h3{margin:0 0 12px;font-size:15px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.field{margin-bottom:10px}.field label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}
.field input[type=text],.field select{width:100%;padding:8px;border:1px solid #d7d7d7;border-radius:6px}.field input[type=color]{width:100%;height:36px;padding:2px;border:1px solid #ccc;border-radius:6px}
.actions{position:sticky;bottom:-20px;background:#fff;padding:15px 0 0;border-top:1px solid #eee;margin-top:12px}.publish{width:100%;padding:11px;border:0;border-radius:7px;background:#198754;color:#fff;font-weight:700;cursor:pointer}
.presets{display:flex;gap:7px;flex-wrap:wrap}.preset{border:1px solid #ddd;background:#fff;border-radius:20px;padding:6px 10px;font-size:12px;cursor:pointer}
.saved{padding:9px;background:#eaf7ee;border:1px solid #b9dfc4;border-radius:7px;margin-bottom:12px}
@media(max-width:900px){.customizer{grid-template-columns:320px 1fr}}@media(max-width:700px){.customizer{grid-template-columns:1fr}.preview{display:none}}
</style></head><body>
<div class="customizer">
<aside class="panel">
<h2 style="margin-top:0">Theme Customizer</h2>
<p style="font-size:13px;color:#666">Atur tampilan dan lihat perubahan secara langsung. Klik Publish untuk menyimpan.</p>
<?php if(isset($_GET['saved'])):?><div class="saved">Theme berhasil dipublish.</div><?php endif;?>
<form id="themeForm" method="post">
<input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>">

<div class="section"><h3>Presets</h3><div class="presets">
<button type="button" class="preset" data-preset="jungle">Jungle</button>
<button type="button" class="preset" data-preset="adventure">Adventure</button>
<button type="button" class="preset" data-preset="sunset">Sunset</button>
<button type="button" class="preset" data-preset="clean">Clean</button>
</div></div>

<div class="section"><h3>Colors</h3><div class="grid">
<?php foreach([
'primary'=>'Primary','secondary'=>'Secondary','accent'=>'Accent','background'=>'Background',
'surface'=>'Surface','text'=>'Text','heading'=>'Heading','button'=>'Button',
'button_hover'=>'Button Hover','header'=>'Header','footer'=>'Footer'
] as $k=>$label):?>
<div class="field"><label><?=$label?></label>
<input class="color" data-key="<?=$k?>" type="color" value="<?=preg_match('/^#[0-9a-fA-F]{6}$/',$theme[$k])?$theme[$k]:'#ffffff'?>">
<input class="theme-input" data-key="<?=$k?>" type="text" name="<?=$k?>" value="<?=admin_e($theme[$k])?>">
</div>
<?php endforeach;?>
</div></div>

<div class="section"><h3>Typography</h3>
<div class="field"><label>Body Font</label><input class="theme-input" data-key="body_font" type="text" name="body_font" value="<?=admin_e($theme['body_font'])?>"></div>
<div class="field"><label>Heading Font</label><input class="theme-input" data-key="heading_font" type="text" name="heading_font" value="<?=admin_e($theme['heading_font'])?>"></div>
<div class="grid">
<div class="field"><label>Body Size</label><input class="theme-input" data-key="body_size" type="text" name="body_size" value="<?=admin_e($theme['body_size'])?>"></div>
<div class="field"><label>Menu Size</label><input class="theme-input" data-key="menu_size" type="text" name="menu_size" value="<?=admin_e($theme['menu_size'])?>"></div>
<div class="field"><label>Button Radius</label><input class="theme-input" data-key="button_radius" type="text" name="button_radius" value="<?=admin_e($theme['button_radius'])?>"></div>
</div></div>

<div class="section"><h3>Header</h3><label style="font-size:13px"><input type="checkbox" name="sticky_header" value="1" <?=$theme['sticky_header']==='1'?'checked':''?>> Sticky Header</label></div>

<div class="actions"><button class="publish" type="submit">Publish Changes</button></div>
</form>
</aside>
<section class="preview">
<div class="previewbar"><span>Preview</span><input id="previewUrl" value="<?=admin_e((defined('BASE_URL')?BASE_URL:''))?>/"><button type="button" id="reload">↻</button></div>
<div class="frame"><iframe id="siteFrame" src="<?=admin_e((defined('BASE_URL')?BASE_URL:''))?>/"></iframe></div>
</section>
</div>
<script>
const presets={
jungle:{primary:'#0f5132',secondary:'#198754',accent:'#f4b400',background:'#ffffff',surface:'#f7f7f7',text:'#222222',heading:'#111111',button:'#f4b400',button_hover:'#d99d00',header:'#ffffff',footer:'#111111'},
adventure:{primary:'#123b5d',secondary:'#1d6fa5',accent:'#ff8a00',background:'#ffffff',surface:'#f2f6f9',text:'#20252b',heading:'#102a43',button:'#ff8a00',button_hover:'#e87500',header:'#ffffff',footer:'#102a43'},
sunset:{primary:'#7a2e1f',secondary:'#c75b39',accent:'#f2a65a',background:'#fffaf5',surface:'#fff1e8',text:'#352722',heading:'#4b2018',button:'#f2a65a',button_hover:'#d88b47',header:'#ffffff',footer:'#321710'},
clean:{primary:'#1f2937',secondary:'#4b5563',accent:'#0ea5e9',background:'#ffffff',surface:'#f8fafc',text:'#334155',heading:'#0f172a',button:'#0ea5e9',button_hover:'#0284c7',header:'#ffffff',footer:'#0f172a'}
};
function val(k){let e=document.querySelector('.theme-input[data-key="'+k+'"]');return e?e.value:''}
function applyPreview(){
 const f=document.getElementById('siteFrame');
 try{
   const d=f.contentDocument; if(!d)return;
   let st=d.getElementById('bt-customizer-preview');
   if(!st){st=d.createElement('style');st.id='bt-customizer-preview';d.head.appendChild(st)}
   st.textContent=`:root{--bt-primary:${val('primary')};--bt-secondary:${val('secondary')};--bt-accent:${val('accent')};--bt-bg:${val('background')};--bt-surface:${val('surface')};--bt-text:${val('text')};--bt-heading:${val('heading')};--bt-button:${val('button')};--bt-button-hover:${val('button_hover')};--bt-header:${val('header')};--bt-footer:${val('footer')};--bt-body-font:${val('body_font')};--bt-heading-font:${val('heading_font')};--bt-body-size:${val('body_size')};--bt-menu-size:${val('menu_size')};--bt-button-radius:${val('button_radius')}}body{font-family:var(--bt-body-font);font-size:var(--bt-body-size);color:var(--bt-text);background:var(--bt-bg)}h1,h2,h3,h4,h5,h6{font-family:var(--bt-heading-font);color:var(--bt-heading)}button,.btn,.button,.book-now,.cta-button{background:var(--bt-button);border-radius:var(--bt-button-radius)}header,.header,.site-header,.navbar{background:var(--bt-header)}footer,.footer,.site-footer{background:var(--bt-footer)}`;
 }catch(e){}
}
document.querySelectorAll('.theme-input').forEach(e=>e.addEventListener('input',applyPreview));
document.querySelectorAll('.color').forEach(e=>e.addEventListener('input',()=>{
 const t=document.querySelector('.theme-input[data-key="'+e.dataset.key+'"]');t.value=e.value;applyPreview();
}));
document.querySelectorAll('.preset').forEach(b=>b.addEventListener('click',()=>{
 const p=presets[b.dataset.preset];Object.keys(p).forEach(k=>{const t=document.querySelector('.theme-input[data-key="'+k+'"]');const c=document.querySelector('.color[data-key="'+k+'"]');if(t)t.value=p[k];if(c)c.value=p[k]});applyPreview();
}));
document.getElementById('reload').onclick=()=>document.getElementById('siteFrame').src=document.getElementById('previewUrl').value;
document.getElementById('siteFrame').addEventListener('load',applyPreview);
</script>
</body></html>