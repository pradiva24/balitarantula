<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/includes/auth.php';
if(function_exists('admin_require')) admin_require();

$page_title = 'Media Variants';
$root = realpath(__DIR__.'/..');
$uploads = $root . '/uploads';
$out = $uploads . '/_variants';
$widths = [480, 768, 1024, 1440, 1920];
$batchSize = 5;
$quality = 62;

function mv_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function mv_upload_root(): string { return str_replace('\\','/', realpath(__DIR__.'/../uploads')); }
function mv_variant_rel(string $file, int $w): string {
    $base = mv_upload_root();
    $rel = ltrim(str_replace('\\','/',substr($file, strlen($base))), '/');
    $info = pathinfo($rel);
    $dir = isset($info['dirname']) && $info['dirname'] !== '.' ? trim((string)$info['dirname'], './') : '';
    return ($dir !== '' ? $dir.'/' : '') . $info['filename'] . '-' . $w . 'w.webp';
}
function mv_collect_files(string $uploads, string $out): array {
    $files=[]; if(!is_dir($uploads)) return $files;
    $outReal=realpath($out);
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
    foreach($it as $file){
        if(!$file->isFile()) continue;
        $path=str_replace('\\','/',$file->getPathname());
        if($outReal && strpos($path,str_replace('\\','/',$outReal).'/')===0) continue;
        $ext=strtolower($file->getExtension());
        if(in_array($ext,['jpg','jpeg','png','webp'],true)) $files[]=$path;
    }
    sort($files,SORT_STRING); return $files;
}
function mv_make_variant(string $src,string $dst,int $w,int $quality): array {
    if(!extension_loaded('gd')) return [false,'PHP GD extension is not enabled'];
    $info=@getimagesize($src); if(!$info) return [false,'Invalid image'];
    [$ow,$oh,$type]=$info;
    if($ow <= $w) return [null,'Source is already <= '.$w.'px'];
    switch($type){
        case IMAGETYPE_JPEG: $im=@imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG: $im=@imagecreatefrompng($src); break;
        case IMAGETYPE_WEBP: $im=function_exists('imagecreatefromwebp')?@imagecreatefromwebp($src):false; break;
        default: return [false,'Unsupported image format'];
    }
    if(!$im) return [false,'Could not read image'];
    $nh=max(1,(int)round($oh*($w/$ow)));
    $canvas=imagecreatetruecolor($w,$nh);
    imagealphablending($canvas,false); imagesavealpha($canvas,true);
    $transparent=imagecolorallocatealpha($canvas,0,0,0,127); imagefill($canvas,0,0,$transparent);
    imagecopyresampled($canvas,$im,0,0,0,0,$w,$nh,$ow,$oh);
    if(!is_dir(dirname($dst)) && !@mkdir(dirname($dst),0755,true)){ imagedestroy($im); imagedestroy($canvas); return [false,'Could not create variant directory']; }
    $ok=function_exists('imagewebp') && @imagewebp($canvas,$dst,$quality);
    imagedestroy($im); imagedestroy($canvas);
    return ($ok && is_file($dst) && filesize($dst)>0)?[true,'Created']:[false,'Could not write WebP'];
}

// Collect only source paths; image dimensions are inspected only for the current batch.
$files=mv_collect_files($uploads,$out); $total=count($files);
$offset=max(0,min($total,(int)($_GET['offset']??0)));
$ran=false; $created=0; $skipped=0; $errors=[]; $processed=0;
if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_verify_csrf();
    $offset=max(0,min($total,(int)($_POST['offset']??0)));
    $batch=array_slice($files,$offset,$batchSize);
    foreach($batch as $src){
        $processed++;
        foreach($widths as $w){
            $dst=$out.'/'.mv_variant_rel($src,$w);
            if(is_file($dst) && filesize($dst)>0){$skipped++;continue;}
            [$ok,$msg]=mv_make_variant($src,$dst,$w,$quality);
            if($ok===true)$created++; elseif($ok===false)$errors[]=basename($src).' @ '.$w.'px: '.$msg; else $skipped++;
        }
    }
    $next=min($total,$offset+$processed);
    if(($_POST['action']??'')==='ajax'){
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'=>true,
            'offset'=>$next,
            'total'=>$total,
            'processed'=>$processed,
            'created'=>$created,
            'skipped'=>$skipped,
            'errors'=>$errors,
            'done'=>$next >= $total,
            'progress'=>$total ? min(100,round(($next/$total)*100,1)) : 100
        ]);
        exit;
    }
    header('Location: media-variants.php?offset='.$next.'&done='.($next>=$total?'1':'0').'&created='.$created.'&skipped='.$skipped.'&errors='.count($errors)); exit;
}
$nextOffset=min($total,$offset+$batchSize);
$done=$offset >= $total;
$progress=$total?min(100,(int)round(($offset/$total)*100)):100;
$created=(int)($_GET['created']??0); $skipped=(int)($_GET['skipped']??0); $errorCount=(int)($_GET['errors']??0);
require __DIR__.'/includes/header.php';
?>
<style>
.mv-wrap{max-width:1050px}.mv-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:20px}.mv-card{background:#fff;border:1px solid #e7ece9;border-radius:14px;padding:22px;box-shadow:0 5px 18px rgba(20,50,35,.04);margin-bottom:18px}.mv-title{margin:0 0 7px;font-size:24px}.mv-muted{color:#66756d}.mv-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.mv-stat{background:#f5f8f6;border-radius:10px;padding:14px}.mv-stat b{font-size:20px}.mv-bar{height:12px;background:#e8eeeb;border-radius:99px;overflow:hidden;margin:12px 0}.mv-fill{height:100%;background:#176b43;width:<?=$progress?>%}.mv-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.mv-btn{border:0;border-radius:9px;padding:11px 17px;background:#176b43;color:#fff;text-decoration:none;cursor:pointer;font-weight:600}.mv-btn.gray{background:#eef2ef;color:#18372c}.mv-tools-mini{padding:8px 11px;font-size:12px;white-space:nowrap}.mv-note{background:#f4f8f5;border-left:4px solid #176b43;padding:14px 16px;border-radius:8px}.mv-auto{margin-top:16px;padding:16px;background:#f5f8f6;border:1px solid #e2ebe5;border-radius:10px}.mv-auto-head{display:flex;justify-content:space-between;gap:10px}.mv-ok{background:#edf8f0;color:#175b36;padding:12px 14px;border-radius:9px;margin-bottom:16px}.mv-warn{background:#fff7e3;color:#765711;padding:12px 14px;border-radius:9px;margin-bottom:16px}@media(max-width:700px){.mv-tools-mini{display:inline-block;margin-top:10px}.mv-grid{grid-template-columns:repeat(2,1fr)}.mv-hero{display:block}}
</style>
<div class="mv-wrap">
  <div class="mv-hero">
    <div>
      <h2 class="mv-title">Media Variants</h2>
      <div class="mv-muted">Generate optimized WebP variants without changing your original Media Library files.</div>
    </div>
    <div>
      <a class="mv-btn mv-tools-mini" href="image-tools.php" title="Image Tools">🧹 Image Tools</a>
    </div>
  </div>
  <?php if($created||$skipped||$errorCount): ?><div class="mv-ok">Last batch: <b><?=number_format($created)?></b> variants created, <b><?=number_format($skipped)?></b> skipped<?= $errorCount ? ', '.number_format($errorCount).' errors' : '' ?>.</div><?php endif; ?>
  <div class="mv-card">
    <div class="mv-grid">
      <div class="mv-stat"><span class="mv-muted">Source images</span><br><b><?=number_format($total)?></b></div>
      <div class="mv-stat"><span class="mv-muted">Progress</span><br><b><?=$progress?>%</b></div>
      <div class="mv-stat"><span class="mv-muted">Batch size</span><br><b><?=$batchSize?></b> images</div>
      <div class="mv-stat"><span class="mv-muted">Variants</span><br><b>480 / 768 / 1024 / 1440 / 1920w</b></div>
    </div>
    <div class="mv-bar"><div class="mv-fill"></div></div>
    <div class="mv-muted">Processed <?=number_format($offset)?> of <?=number_format($total)?> source images.</div>
    <div class="mv-actions">
      <?php if(!$done): ?><form method="post"><input type="hidden" name="csrf" value="<?=mv_e(admin_csrf())?>"><input type="hidden" name="offset" value="<?=$offset?>"><button class="mv-btn" type="submit">Generate next <?=$batchSize?> images</button></form><?php else: ?><span class="mv-btn">Optimization complete</span><?php endif; ?>
      <?php if(!$done): ?><button class="mv-btn" id="mvAutoBtn" type="button">🚀 Generate All Automatically</button><?php endif; ?>
      <a class="mv-btn gray" href="media-variants.php">Start / Reset</a>
      <a class="mv-btn gray" href="media.php">Back to Media</a>
    </div>
    <div id="mvAutoBox" class="mv-auto" hidden>
      <div class="mv-auto-head"><b id="mvAutoTitle">Automatic optimization</b><span id="mvAutoPct">0%</span></div>
      <div class="mv-bar"><div id="mvAutoFill" class="mv-fill" style="width:0%"></div></div>
      <div id="mvAutoText" class="mv-muted">Preparing…</div>
      <div id="mvAutoStats" class="mv-muted" style="margin-top:8px"></div>
    </div>
  </div>
  <div class="mv-card"><div class="mv-note"><b>Safe mode:</b> Original images are never overwritten. Existing variants are skipped. Files are generated only when you click the batch button, so this page will not start processing hundreds of images automatically.</div><p class="mv-muted" style="margin:14px 0 0">Output: <code>uploads/_variants/</code> · WebP quality: <?=$quality?> · Source widths: 480px, 768px, 1024px, 1440px and 1920px.</p></div>
</div>
<script>
(function(){
  const btn=document.getElementById('mvAutoBtn');
  if(!btn) return;
  const box=document.getElementById('mvAutoBox'), fill=document.getElementById('mvAutoFill'), pct=document.getElementById('mvAutoPct');
  const text=document.getElementById('mvAutoText'), stats=document.getElementById('mvAutoStats');
  const csrf=<?=json_encode(admin_csrf())?>;
  const total=<?=json_encode($total)?>;
  let offset=<?=json_encode($offset)?>, createdTotal=0, skippedTotal=0, errorsTotal=0, running=false;
  btn.addEventListener('click', function(){
    if(running) return;
    running=true; btn.disabled=true; btn.textContent='⏳ Processing…'; box.hidden=false; runBatch();
  });
  async function runBatch(){
    try{
      const body=new URLSearchParams({action:'ajax',offset:String(offset),csrf:csrf});
      const res=await fetch('media-variants.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body});
      const data=await res.json();
      if(!data.ok) throw new Error('Batch failed');
      offset=data.offset; createdTotal+=Number(data.created||0); skippedTotal+=Number(data.skipped||0); errorsTotal+=(data.errors||[]).length;
      const progress=Number(data.progress||0);
      fill.style.width=progress+'%'; pct.textContent=progress+'%';
      text.textContent='Processed '+offset.toLocaleString()+' of '+total.toLocaleString()+' source images.';
      stats.textContent=createdTotal.toLocaleString()+' variants created · '+skippedTotal.toLocaleString()+' skipped · '+errorsTotal.toLocaleString()+' errors';
      if(data.done){
        btn.textContent='✓ All variants generated';
        text.textContent='Optimization complete.';
        running=false;
        setTimeout(()=>location.href='media-variants.php?offset='+total+'&done=1&created='+createdTotal+'&skipped='+skippedTotal+'&errors='+errorsTotal,700);
        return;
      }
      // Yield briefly so the browser/UI stays responsive between server-side batches.
      setTimeout(runBatch,120);
    }catch(e){
      running=false; btn.disabled=false; btn.textContent='🚀 Resume Generate All';
      text.textContent='Stopped: '+(e.message||'request error')+'. Your completed batches are safe; click Resume to continue.';
    }
  }
})();
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
