<?php
require __DIR__.'/includes/auth.php';
$page_title='Hero Slider';

$settings_file=__DIR__.'/../hero-settings.json';
$defaults=[
 'enabled'=>'1','mode'=>'slider','autoplay'=>'1','interval'=>'5000','cover'=>'1','dots'=>'1','overlay'=>'45','video'=>'','video_poster'=>'','slides'=>[]
];
$settings=$defaults;
if(is_file($settings_file)){
  $raw=json_decode((string)file_get_contents($settings_file),true);
  if(is_array($raw)) $settings=array_replace($defaults,$raw);
}
if(!is_array($settings['slides'])) $settings['slides']=[];
function hs_save($s,$file){file_put_contents($file,json_encode($s,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),LOCK_EX);}
function hs_upload($field,$sub='hero'){
 $selected=admin_media_selected($field);
 if($selected!==null) return $selected;
 if(empty($_FILES[$field])||$_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return '';
 $f=$_FILES[$field];
 if($f['error']!==UPLOAD_ERR_OK) return '';
 $mime=@mime_content_type($f['tmp_name']);
 if($mime && str_starts_with($mime,'image/')){
   if((int)$f['size']>admin_image_max_upload_bytes()) return '';
   $dir=__DIR__.'/../uploads/'.$sub;
   if(!is_dir($dir)) mkdir($dir,0755,true);
   $name=bin2hex(random_bytes(8)).'.webp'; $dest=$dir.'/'.$name;
   try{ admin_image_optimize($f['tmp_name'],$dest,2000,2000,82); return 'uploads/'.$sub.'/'.$name; }catch(Throwable $e){ if(is_file($dest)) @unlink($dest); return ''; }
 }
 $allowed=['mp4','webm','ogg'];
 $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
 if(!in_array($ext,$allowed,true)) return '';
 $dir=__DIR__.'/../uploads/'.$sub;
 if(!is_dir($dir)) mkdir($dir,0755,true);
 $name=bin2hex(random_bytes(8)).'.'.$ext;
 if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name)) return '';
 return 'uploads/'.$sub.'/'.$name;
}
function hs_delete($path){
 if(!$path) return;
 $p=__DIR__.'/../'.ltrim(parse_url($path,PHP_URL_PATH)?:$path,'/');
 if(is_file($p)) @unlink($p);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 admin_verify_csrf();
 $action=$_POST['action']??'save';
 if($action==='delete_slide'){
   $i=(int)($_POST['index']??-1);
   if(isset($settings['slides'][$i])){ hs_delete($settings['slides'][$i]['image']??''); array_splice($settings['slides'],$i,1); }
 } elseif($action==='add_slide'){
   $path=hs_upload('slide_image');
   if($path){$settings['slides'][]=['image'=>$path,'alt'=>trim((string)($_POST['alt']??'')),'url'=>trim((string)($_POST['url']??''))];}
 } elseif($action==='save'){
   $settings['enabled']=isset($_POST['enabled'])?'1':'0';
   $settings['mode']=($_POST['mode']??'slider')==='video'?'video':'slider';
   $settings['autoplay']=isset($_POST['autoplay'])?'1':'0';
   $settings['dots']=isset($_POST['dots'])?'1':'0';
   $settings['cover']=isset($_POST['cover'])?'1':'0';
   $settings['interval']=max(2000,min(20000,(int)($_POST['interval']??5000)));
   $settings['overlay']=max(0,min(90,(int)($_POST['overlay']??45)));
   $settings['video']=$settings['video'] ?: '';
   if(!empty($_POST['remove_video']) && $settings['video']){hs_delete($settings['video']);$settings['video']='';}
   $v=hs_upload('hero_video'); if($v)$settings['video']=$v;
   if(!empty($_POST['remove_poster']) && $settings['video_poster']){hs_delete($settings['video_poster']);$settings['video_poster']='';}
   $p=hs_upload('video_poster'); if($p)$settings['video_poster']=$p;
   foreach($settings['slides'] as $i=>$s){$settings['slides'][$i]['alt']=trim((string)($_POST['alt_'.$i]??$s['alt']??''));$settings['slides'][$i]['url']=trim((string)($_POST['url_'.$i]??$s['url']??''));}
 }
 hs_save($settings,$settings_file); admin_flash('success','Hero settings saved.'); header('Location: hero-slider.php'); exit;
}
require __DIR__.'/includes/header.php';
function hs_e($v){return admin_e($v);}
?>
<style>
.hs-wrap{max-width:1100px}.hs-card{background:#fff;border:1px solid #e1e9e3;border-radius:12px;padding:22px;margin-bottom:16px}.hs-card h2{margin:0 0 15px;color:#15583e;font-size:17px}.hs-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.hs-field{display:flex;flex-direction:column;gap:7px}.hs-field label{font-size:11px;font-weight:700;color:#45584e}.hs-field input,.hs-field select{border:1px solid #d8e2db;border-radius:7px;padding:10px;font:inherit;font-size:12px}.hs-check{display:flex;align-items:center;gap:8px;font-size:12px;margin:9px 0}.hs-slide{display:grid;grid-template-columns:150px 1fr auto;gap:15px;align-items:center;border:1px solid #e5ece7;border-radius:9px;padding:12px;margin-bottom:10px}.hs-slide img{width:150px;height:90px;object-fit:cover;border-radius:7px}.hs-btn{border:0;border-radius:7px;padding:10px 14px;background:#126044;color:#fff;font-weight:700;cursor:pointer}.hs-btn.danger{background:#a53b3b}.hs-muted{font-size:11px;color:#718078;line-height:1.6}.hs-actions{display:flex;gap:8px;flex-wrap:wrap}.hs-preview{width:100%;max-height:300px;object-fit:cover;border-radius:8px;background:#132b21}@media(max-width:700px){.hs-grid{grid-template-columns:1fr}.hs-slide{grid-template-columns:1fr}.hs-slide img{width:100%;height:170px}}
</style>
<main class="admin-content"><div class="hs-wrap">
<h1>Hero Slider</h1><p class="hs-muted">Kelola background Hero Homepage. Gunakan Slider gambar atau Background Video. Pengaturan ini tidak mengubah halaman package atau halaman lain.</p>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=hs_e(admin_csrf())?>"><input type="hidden" name="action" value="save">
<section class="hs-card"><h2>General</h2><label class="hs-check"><input type="checkbox" name="enabled" <?=$settings['enabled']==='1'?'checked':''?>> Aktifkan Hero Media</label><div class="hs-grid"><div class="hs-field"><label>Mode</label><select name="mode"><option value="slider" <?=$settings['mode']==='slider'?'selected':''?>>Image Slider</option><option value="video" <?=$settings['mode']==='video'?'selected':''?>>Background Video</option></select></div><div class="hs-field"><label>Overlay Opacity (%)</label><input type="number" min="0" max="90" name="overlay" value="<?=hs_e($settings['overlay'])?>"></div><div class="hs-field"><label>Slide Interval (ms)</label><input type="number" min="2000" max="20000" step="500" name="interval" value="<?=hs_e($settings['interval'])?>"></div></div><label class="hs-check"><input type="checkbox" name="autoplay" <?=$settings['autoplay']==='1'?'checked':''?>> Auto Play</label><label class="hs-check"><input type="checkbox" name="dots" <?=$settings['dots']==='1'?'checked':''?>> Navigation Dots</label><label class="hs-check"><input type="checkbox" name="cover" <?=$settings['cover']==='1'?'checked':''?>> Image Size: Cover</label></section>
<section class="hs-card"><h2>Add Image Slide</h2><div class="hs-grid"><div class="hs-field"><label>Image</label><input type="file" name="slide_image" accept="image/jpeg,image/png,image/webp,image/gif"></div><div class="hs-field"><label>Alt Text</label><input type="text" name="alt" placeholder="Bali ATV Adventure"></div><div class="hs-field"><label>Optional URL</label><input type="text" name="url" placeholder="/packages.php"></div></div><button class="hs-btn" name="action" value="add_slide" type="submit">+ Add Slide</button></section>
<section class="hs-card"><h2>Current Slides</h2><?php if(!$settings['slides']):?><p class="hs-muted">Belum ada slide. Tambahkan gambar di atas.</p><?php endif;?><?php foreach($settings['slides'] as $i=>$s):?><div class="hs-slide"><img src="<?=hs_e(asset_url($s['image']))?>" alt=""><div class="hs-grid"><div class="hs-field"><label>Alt Text</label><input type="text" name="alt_<?=$i?>" value="<?=hs_e($s['alt']??'')?>"></div><div class="hs-field"><label>URL</label><input type="text" name="url_<?=$i?>" value="<?=hs_e($s['url']??'')?>"></div></div><button class="hs-btn danger" type="submit" name="action" value="delete_slide" onclick="this.form.insertAdjacentHTML('beforeend','<input type=hidden name=index value=<?=$i?>>')">Delete</button></div><?php endforeach;?></section>
<section class="hs-card"><h2>Background Video</h2><p class="hs-muted">Video akan autoplay, muted dan loop agar sesuai perilaku background hero. MP4 H.264 disarankan.</p><div class="hs-grid"><div class="hs-field"><label>Upload Video</label><input type="file" name="hero_video" accept="video/mp4,video/webm,video/ogg"></div><div class="hs-field"><label>Poster Image</label><input type="file" name="video_poster" accept="image/jpeg,image/png,image/webp"></div></div><?php if($settings['video']):?><p class="hs-muted">Video aktif: <?=hs_e($settings['video'])?> <label class="hs-check"><input type="checkbox" name="remove_video" value="1"> Hapus video</label></p><?php endif;?><?php if($settings['video_poster']):?><p class="hs-muted">Poster aktif: <?=hs_e($settings['video_poster'])?> <label class="hs-check"><input type="checkbox" name="remove_poster" value="1"> Hapus poster</label></p><?php endif;?></section>
<button class="hs-btn" type="submit">Save Hero Settings</button></form></div></main>
