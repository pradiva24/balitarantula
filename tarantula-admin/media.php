<?php
require __DIR__.'/includes/auth.php';

$db = db();
$is_picker = isset($_GET['picker']) && $_GET['picker'] === '1';
$picker_multiple = isset($_GET['multiple']) && $_GET['multiple'] === '1';

function media_e($v){ return e($v); }
function media_is_image(string $path): bool { return (bool)preg_match('~\.(?:jpe?g|png|webp|gif)$~i',$path); }
function media_root(): string { return __DIR__.'/../uploads'; }
function media_local_path(string $relative): string {
    $relative=ltrim(str_replace('\\','/',$relative),'/');
    if(!preg_match('~^uploads/(?!payment-proofs/)([A-Za-z0-9_./-]+)$~i',$relative)) return '';
    $root=realpath(media_root()); $full=realpath(__DIR__.'/../'.$relative);
    if(!$root||!$full||strncmp($full,$root.DIRECTORY_SEPARATOR,strlen($root)+1)!==0||!is_file($full)) return '';
    return $full;
}
function media_scan(): array {
    $root=media_root(); $items=[];
    if(!is_dir($root)) return $items;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    foreach($it as $file){
        if(!$file->isFile()) continue;
        $path=$file->getPathname();
        $rel=ltrim(str_replace(DIRECTORY_SEPARATOR,'/',substr($path,strlen($root))),'/');
        if(!media_is_image($rel)||preg_match('~^(?:payment-proofs|_variants)/~i',$rel)) continue;
        $wh=@getimagesize($path);
        $items[]=[
            'path'=>'uploads/'.$rel,'file'=>$path,'name'=>basename($path),
            'folder'=>dirname($rel)==='.'?'root':dirname($rel),
            'size'=>(int)$file->getSize(),'mtime'=>(int)$file->getMTime(),
            'mime'=>(string)(@mime_content_type($path)?:'image/*'),
            'width'=>(int)($wh[0]??0),'height'=>(int)($wh[1]??0)
        ];
    }
    usort($items,static fn($a,$b)=>$b['mtime']<=>$a['mtime']);
    return $items;
}
function media_existing_hashes(): array {
    static $cache=null; if($cache!==null) return $cache;
    $cache=[];
    foreach(media_scan() as $item){ $h=@hash_file('sha256',$item['file']); if($h) $cache[$h]=$item['path']; }
    return $cache;
}
function media_upload_one(array $f): array {
    // Keep a per-request cache of optimized hashes so the same image cannot be
    // saved twice when it appears more than once in a single multi-file upload.
    static $newOptimizedHashes=[];

    $err=(int)($f['error']??UPLOAD_ERR_NO_FILE);
    if($err===UPLOAD_ERR_NO_FILE) throw new RuntimeException('No image selected.');
    $map=[UPLOAD_ERR_INI_SIZE=>'Image is larger than the server upload limit.',UPLOAD_ERR_FORM_SIZE=>'Image is larger than the form upload limit.',UPLOAD_ERR_PARTIAL=>'Upload was interrupted before the image was completely received.',UPLOAD_ERR_NO_TMP_DIR=>'Server temporary upload folder is missing.',UPLOAD_ERR_CANT_WRITE=>'Server could not write the uploaded file.',UPLOAD_ERR_EXTENSION=>'A PHP extension stopped the upload.'];
    if($err!==UPLOAD_ERR_OK) throw new RuntimeException($map[$err]??('Upload failed (PHP error '.$err.').'));
    $size=(int)($f['size']??0); if($size<=0) throw new RuntimeException('The uploaded image is empty.');
    if($size>bt_image_max_upload_bytes()) throw new RuntimeException('Image exceeds the 20 MB application limit.');
    $tmp=(string)($f['tmp_name']??''); if($tmp===''||!is_uploaded_file($tmp)) throw new RuntimeException('The uploaded file is not available on the server.');
    $info=@getimagesize($tmp); if(!$info||empty($info['mime'])) throw new RuntimeException('The uploaded file is not a valid image.');
    $mime=(string)$info['mime']; if(!bt_image_allowed_mime($mime)) throw new RuntimeException('Only JPG, PNG, WebP or GIF images are allowed.');

    $sourceHash=@hash_file('sha256',$tmp);
    if($sourceHash){ $existing=media_existing_hashes(); if(isset($existing[$sourceHash])) return ['path'=>$existing[$sourceHash],'duplicate'=>true]; }

    $dir=media_root().'/media';
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir)) throw new RuntimeException('Cannot create uploads/media. Check folder permissions.');
    if(!is_writable($dir)) throw new RuntimeException('The uploads/media folder is not writable by PHP.');

    // First try the existing optimizer. If WebP support is unavailable, keep the valid original image instead of failing.
    $outputExt=strtolower((string)bt_image_output_extension($mime));
    if(!in_array($outputExt,['jpg','jpeg','png','webp','gif'],true)) $outputExt='webp';
    $name=bin2hex(random_bytes(16)).'.'.$outputExt;
    $dest=$dir.'/'.$name; $tmpDest=$dest.'.tmp'; $optimized=false;
    try{
        bt_image_optimize($tmp,$tmpDest,2400,2400,82);
        if(is_file($tmpDest)&&filesize($tmpDest)>0){
            $optimized=true;
            if(!@rename($tmpDest,$dest)){
                if(!@copy($tmpDest,$dest)) throw new RuntimeException('Server could not save the optimized image.');
                @unlink($tmpDest);
            }
        }
    }catch(Throwable $ignored){
        if(is_file($tmpDest)) @unlink($tmpDest);
        $optimized=false;
    }
    if(!$optimized){
        $originalExt=strtolower(pathinfo((string)($f['name']??''),PATHINFO_EXTENSION));
        if(!in_array($originalExt,['jpg','jpeg','png','webp','gif'],true)) $originalExt='jpg';
        $name=bin2hex(random_bytes(16)).'.'.$originalExt; $dest=$dir.'/'.$name;
        if(!@move_uploaded_file($tmp,$dest)){
            if(!@copy($tmp,$dest)) throw new RuntimeException('Server could not save the uploaded image.');
        }
    }
    if(!is_file($dest)||filesize($dest)<=0) throw new RuntimeException('Server saved an empty image.');

    // Check the final stored file too, so a JPG and its normalized WebP do not create repeated files.
    $finalHash=@hash_file('sha256',$dest);
    if($finalHash){
        $relative='uploads/media/'.$name;
        if(isset($newOptimizedHashes[$finalHash]) && $newOptimizedHashes[$finalHash]!==$relative){
            @unlink($dest);
            return ['path'=>$newOptimizedHashes[$finalHash],'duplicate'=>true];
        }
        $existing=media_existing_hashes();
        if(isset($existing[$finalHash]) && $existing[$finalHash]!==$relative){
            @unlink($dest);
            return ['path'=>$existing[$finalHash],'duplicate'=>true];
        }
        $newOptimizedHashes[$finalHash]=$relative;
    }
    return ['path'=>'uploads/media/'.$name,'duplicate'=>false];
}
function media_db_references(PDO $db,string $relative): array {
    $hits=[];
    $tables=[
      ['settings','setting_value'],['gallery','image_path'],['packages','cover_image','og_image'],['package_images','image_path'],
      ['reviews','image'],['blog_posts','cover_image','og_image'],['blog_page_settings','hero_image'],['homepage_settings','hero_image'],['homepage_media','image_path']
    ];
    foreach($tables as $spec){
      $table=array_shift($spec);
      try{
        $q=$db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$table]);
        $available=[];foreach($q->fetchAll(PDO::FETCH_COLUMN) as $c)$available[strtolower($c)]=$c;
        $cols=[];foreach($spec as $c)if(isset($available[strtolower($c)]))$cols[]=$available[strtolower($c)];if(!$cols)continue;
        $ors=[];$params=[];foreach($cols as $c){$ors[]='`'.str_replace('`','``',$c).'` LIKE ?';$params[]='%'.$relative.'%';}
        $st=$db->prepare('SELECT * FROM `'.str_replace('`','``',$table).'` WHERE '.implode(' OR ',$ors).' LIMIT 10');$st->execute($params);
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row){$label=$row['name']??$row['title']??$row['setting_key']??('ID '.($row['id']??''));$hits[]=ucfirst(str_replace('_',' ',$table)).' — '.$label;}
      }catch(Throwable $e){}
    }
    return array_values(array_unique($hits));
}
function media_flash_json(bool $ok,string $message,array $extra=[]): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode(array_merge(['ok'=>$ok,'message'=>$message],$extra),JSON_UNESCAPED_SLASHES); exit; }

if(isset($_GET['ajax'])&&$_GET['ajax']==='csrf'){media_flash_json(true,'CSRF refreshed',['csrf'=>admin_csrf()]);}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $ajax=((string)($_POST['ajax']??'')==='1');
    try{
        admin_verify_csrf();
        $act=(string)($_POST['act']??'');
        if($act==='upload'){
            if(empty($_FILES['images'])||!isset($_FILES['images']['name'])) throw new RuntimeException('Please choose at least one image.');
            $names=$_FILES['images']['name']; $results=[]; $errors=[];
            $count=is_array($names)?count($names):0;
            for($i=0;$i<$count;$i++){
                $single=['name'=>$names[$i]??'','type'=>$_FILES['images']['type'][$i]??'','tmp_name'=>$_FILES['images']['tmp_name'][$i]??'','error'=>$_FILES['images']['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$_FILES['images']['size'][$i]??0];
                try{$results[]=media_upload_one($single);}catch(Throwable $e){$errors[]=($single['name']?:'Image '.($i+1)).': '.$e->getMessage();}
            }
            if(!$results && $errors) throw new RuntimeException(implode(' | ',$errors));
            $added=0;$duplicates=0;foreach($results as $r){$r['duplicate']?$duplicates++:$added++;}
            $message=$added.' new image(s) added'; if($duplicates)$message.=', '.$duplicates.' duplicate image(s) reused'; if($errors)$message.='. '.count($errors).' file(s) failed.';
            if($ajax) media_flash_json(true,$message,['added'=>$added,'duplicates'=>$duplicates,'errors'=>$errors,'items'=>$results]);
            admin_flash($errors?'error':'',$message.($errors?' '.implode(' | ',$errors):''));
        }elseif($act==='delete'){
            $path=trim((string)($_POST['path']??''));$full=media_local_path($path);if(!$full)throw new RuntimeException('Image file was not found.');
            $refs=media_db_references($db,$path);if($refs)throw new RuntimeException('This image is still used by: '.implode('; ',array_slice($refs,0,4)).'.');
            if(!@unlink($full))throw new RuntimeException('Could not delete image.');admin_flash('','Image deleted.');
        }
    }catch(Throwable $e){
        if($ajax){http_response_code(400);media_flash_json(false,$e->getMessage());}
        admin_flash('error',$e->getMessage());
    }
    $redirect='media.php';if($is_picker)$redirect.='?picker=1&multiple='.($picker_multiple?'1':'0');header('Location: '.$redirect);exit;
}

$items=media_scan();$q=trim((string)($_GET['q']??''));$folder=trim((string)($_GET['folder']??''));
$filter=function($item)use($q,$folder){if($folder!==''&&$item['folder']!==$folder)return false;if($q==='')return true;return stripos($item['name'],$q)!==false||stripos($item['path'],$q)!==false;};
$items=array_values(array_filter($items,$filter));$folders=[];foreach(media_scan() as $item)$folders[$item['folder']]=true;ksort($folders);$page_title='Media Library';

if($is_picker){
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><title>Choose from Media Library</title><style>
*{box-sizing:border-box}body{margin:0;background:#f6f8f7;color:#172033;font-family:'Inter',Arial,sans-serif}.picker{padding:18px 18px 88px;max-width:1240px;margin:auto}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}.title{margin:0;color:#15583e;font-size:22px}.close{border:0;border-radius:99px;width:36px;height:36px;background:#e9efeb;font-size:20px;cursor:pointer}.upload{background:#fff;border:1px solid #dfe7e1;border-radius:14px;padding:14px;margin-bottom:14px}.upload-row{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end}.upload input[type=file]{width:100%;padding:10px;border:1px dashed #b9c8bf;border-radius:9px}.upload button{border:0;border-radius:9px;background:#176b43;color:#fff;padding:11px 16px;font-weight:700;cursor:pointer}.status{display:none;margin-top:10px;padding:9px 11px;border-radius:9px;background:#eef7f1;font-size:12px}.status[data-type=error]{background:#fff0ee;color:#b42318}.grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.card{background:#fff;border:1px solid #e1e8e3;border-radius:12px;overflow:hidden;transition:.18s ease}.card:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(16,24,40,.08)}.thumb{aspect-ratio:4/2.8;background:#eef2ef}.thumb img{width:100%;height:100%;object-fit:cover;display:block}.info{padding:9px}.name{font-size:11px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.meta{font-size:10px;color:#667085;margin-top:3px}.select{margin:0 9px 9px;width:calc(100% - 18px);border:1px solid #176b43;border-radius:8px;padding:9px;background:#176b43;color:#fff;font-weight:700;cursor:pointer}.select:hover{background:#125a38}.card.selected{outline:3px solid rgba(23,107,67,.18);border-color:#176b43}.card.selected .select{background:#eaf6ef;color:#176b43}.picker-action{position:fixed;left:0;right:0;bottom:0;z-index:20;background:rgba(255,255,255,.97);border-top:1px solid #dfe7e1;box-shadow:0 -8px 24px rgba(16,24,40,.08);padding:12px 18px;display:flex;align-items:center;justify-content:center;gap:14px;backdrop-filter:blur(8px)}.selected-count{font-size:13px;font-weight:700;color:#344054}.use-image{border:0;border-radius:10px;background:#176b43;color:#fff;padding:11px 22px;font-weight:800;cursor:pointer;min-width:130px;box-shadow:0 5px 14px rgba(23,107,67,.2)}.use-image:disabled{background:#c9d5ce;color:#718096;cursor:not-allowed;box-shadow:none}.use-image:not(:disabled):hover{background:#125a38;transform:translateY(-1px)}@media(max-width:1000px){.grid{grid-template-columns:repeat(4,1fr)}}@media(max-width:700px){.grid{grid-template-columns:repeat(3,1fr)}.upload-row{grid-template-columns:1fr}.picker-action{justify-content:space-between}}@media(max-width:500px){.grid{grid-template-columns:repeat(2,1fr)}.picker-action{padding:10px 12px}.use-image{min-width:115px}}
</style></head><body><main class="picker"><div class="top"><h1 class="title">Choose from Media Library</h1><button class="close" id="pickerClose" type="button">×</button></div><div class="upload"><form id="pickerUploadForm" data-media-upload="1" method="post" action="media.php?picker=1&multiple=<?= $picker_multiple?'1':'0' ?>" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=media_e(admin_csrf())?>"><input type="hidden" name="act" value="upload"><input type="file" id="pickerUploadInput" name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/gif"><button data-upload-button type="submit">Upload Images</button><div class="status" data-upload-status></div><div style="height:5px;background:#edf2ef;border-radius:99px;margin-top:9px;overflow:hidden"><div data-upload-bar style="height:100%;width:0;background:#176b43"></div></div></form></div><div class="grid">
<?php foreach($items as $item):$url=asset_url($item['path']);?><article class="card"><div class="thumb"><img src="<?=media_e($url)?>" alt="<?=media_e($item['name'])?>" loading="lazy" onerror="this.parentElement.innerHTML='<span style=&quot;color:#b42318;font-size:11px;padding:10px&quot;>Missing image</span>'"></div><div class="info"><div class="name" title="<?=media_e($item['name'])?>"><?=media_e($item['name'])?></div><div class="meta"><?=number_format($item['size']/1024,1)?> KB · <?=$item['width']?>×<?=$item['height']?></div></div><button class="select" type="button" data-select data-path="<?=media_e($item['path'])?>" data-url="<?=media_e($url)?>" data-name="<?=media_e($item['name'])?>">Select</button></article><?php endforeach;?></div></main><div class="picker-action"><span class="selected-count" id="selectedCount">0 selected</span><button class="use-image" id="useImage" type="button" disabled>Use Image</button></div><script>
(function(){
  const multiple=<?=$picker_multiple?'true':'false'?>;
  const selected=[];
  const countEl=document.getElementById('selectedCount');
  const useBtn=document.getElementById('useImage');
  function refresh(){const n=selected.length;countEl.textContent=n+' image'+(n===1?'':'s')+' selected';useBtn.disabled=n===0;useBtn.textContent=n>1?'Use Images':'Use Image';}
  function send(items){const msg={source:'aloha-media-library',type:'media-selected',multiple:multiple,items:items};try{parent.postMessage(msg,location.origin)}catch(e){parent.postMessage(msg,'*')}}
  document.querySelectorAll('[data-select]').forEach(function(b){b.addEventListener('click',function(){const x={path:this.dataset.path,url:this.dataset.url,name:this.dataset.name};const i=selected.findIndex(v=>v.path===x.path);if(!multiple){selected.length=0;selected.push(x);document.querySelectorAll('[data-select]').forEach(function(other){other.textContent='Select';other.parentElement.classList.remove('selected')});this.textContent='Selected ✓';this.parentElement.classList.add('selected');}else if(i>=0){selected.splice(i,1);this.textContent='Select';this.parentElement.classList.remove('selected');}else{selected.push(x);this.textContent='Selected ✓';this.parentElement.classList.add('selected');}refresh();});});
  useBtn.addEventListener('click',function(){if(selected.length)send(selected.slice());});
  document.getElementById('pickerClose').addEventListener('click',function(){try{parent.postMessage({source:'aloha-media-library',type:'media-cancelled'},location.origin)}catch(e){}});
  refresh();
})();
</script><script src="assets/js/media-upload.js?v=21" defer></script></body></html><?php exit; }
require __DIR__.'/includes/header.php';
?>
<style>.media-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px}.media-head h2{margin:0 0 5px}.media-count{font-size:13px;color:#667085}.media-upload{background:#fff;border:1px solid #dfe7e1;border-radius:16px;padding:18px;margin-bottom:18px}.media-upload-grid{display:grid;grid-template-columns:1fr auto;gap:14px;align-items:end}.media-upload input[type=file]{width:100%;padding:11px;border:1px dashed #b8c8be;border-radius:10px;background:#fafcfb}.media-upload button{white-space:nowrap}.upload-status{display:none;margin-top:10px;padding:10px 12px;border-radius:9px;background:#eef7f1;font-size:12px}.upload-status[data-type=error]{background:#fff0ee;color:#b42318}.upload-progress{height:5px;background:#edf2ef;border-radius:99px;overflow:hidden;margin-top:8px}.upload-progress>div{height:100%;width:0;background:#176b43;transition:width .25s}.media-search{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}.media-search input,.media-search select{min-width:180px;padding:10px 12px;border:1px solid #d9dee7;border-radius:9px;background:#fff}.media-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}.media-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;box-shadow:0 3px 12px rgba(16,24,40,.05)}.media-thumb{aspect-ratio:4/2.8;background:#f3f5f7;display:flex;align-items:center;justify-content:center;overflow:hidden}.media-thumb img{width:100%;height:100%;object-fit:cover;display:block}.media-thumb.missing{color:#b42318;font-size:12px}.media-info{padding:11px}.media-name{font-size:12px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.media-meta{font-size:11px;color:#667085;margin-top:4px;line-height:1.5}.media-url{margin-top:8px;font-size:10px;color:#475467;word-break:break-all;background:#f8fafc;border-radius:7px;padding:7px}.media-actions{display:flex;gap:7px;padding:0 11px 11px}.media-actions form{margin:0}.media-btn{border:1px solid #d0d5dd;background:#fff;border-radius:8px;padding:7px 9px;font-size:11px;text-decoration:none;cursor:pointer}.media-btn.copy{flex:1}.media-btn.delete{color:#b42318;border-color:#f0c2bd}.media-empty{padding:45px;text-align:center;color:#667085;background:#fff;border:1px dashed #d0d5dd;border-radius:14px}@media(max-width:1200px){.media-grid{grid-template-columns:repeat(4,1fr)}}@media(max-width:900px){.media-grid{grid-template-columns:repeat(3,1fr)}.media-upload-grid{grid-template-columns:1fr}}@media(max-width:600px){.media-grid{grid-template-columns:repeat(2,1fr)}.media-search input,.media-search select{width:100%;min-width:0}}
</style>
<div class="media-head"><div><h2>Media Library</h2><div class="media-count"><?=count($items)?> image(s) shown. One central library for the entire website.</div></div><a class="btn gray" href="index.php">Back to Dashboard</a></div>
<div class="media-upload"><form data-media-upload="1" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="act" value="upload"><div class="media-upload-grid"><div><label><strong>Add Images</strong></label><input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/gif"><div style="font-size:12px;color:#667085;margin-top:7px">JPG, PNG, WebP or GIF · max 20 MB each. Duplicate files are automatically reused.</div><div class="upload-status" data-upload-status></div><div class="upload-progress"><div data-upload-bar></div></div></div><button data-upload-button class="btn" type="submit">Upload Images</button></div></form></div>
<form class="media-search" method="get"><input type="search" name="q" value="<?=media_e($q)?>" placeholder="Search filename or path"><select name="folder"><option value="">All folders</option><?php foreach(array_keys($folders) as $f):?><option value="<?=media_e($f)?>" <?=$folder===$f?'selected':''?>><?=media_e($f)?></option><?php endforeach;?></select><button class="btn gray">Filter</button><?php if($q!==''||$folder!==''):?><a class="btn gray" href="media.php">Reset</a><?php endif;?></form>
<?php if(!$items):?><div class="media-empty">No images found.</div><?php else:?><div class="media-grid"><?php foreach($items as $item):$url=asset_url($item['path']);?><article class="media-card"><div class="media-thumb"><img src="<?=media_e($url)?>" alt="<?=media_e($item['name'])?>" loading="lazy" onerror="this.parentElement.classList.add('missing');this.style.display='none';this.parentElement.insertAdjacentHTML('beforeend','<span>Missing image</span>')"></div><div class="media-info"><div class="media-name" title="<?=media_e($item['name'])?>"><?=media_e($item['name'])?></div><div class="media-meta"><?=media_e($item['folder'])?> · <?=number_format($item['size']/1024,1)?> KB · <?=$item['width']?>×<?=$item['height']?></div><div class="media-url"><?=media_e($url)?></div></div><div class="media-actions"><button type="button" class="media-btn copy" onclick="mediaCopy('<?=media_e($url)?>',this)">Copy URL</button><a class="media-btn" target="_blank" rel="noopener" href="<?=media_e($url)?>">Open</a><form method="post" onsubmit="return confirm('Delete this image? If it is used, deletion will be blocked.');"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="path" value="<?=media_e($item['path'])?>"><button class="media-btn delete" type="submit">Delete</button></form></div></article><?php endforeach;?></div><?php endif;?><script>function mediaCopy(url,b){const old=b.textContent;const done=()=>{b.textContent='Copied';setTimeout(()=>b.textContent=old,1200)};if(navigator.clipboard&&window.isSecureContext)navigator.clipboard.writeText(url).then(done).catch(()=>fallbackCopy(url,done));else fallbackCopy(url,done)}function fallbackCopy(t,cb){const x=document.createElement('textarea');x.value=t;document.body.appendChild(x);x.select();document.execCommand('copy');x.remove();cb()}</script><script src="assets/js/media-upload.js?v=20" defer></script><?php require __DIR__.'/includes/footer.php';
