<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/includes/auth.php';
if(function_exists('admin_require')) admin_require();

$root=realpath(__DIR__.'/..');
$quarantine=$root.'/uploads/_cleanup-quarantine';
$manifestFile=$quarantine.'/manifest.json';
$action=$_GET['action'] ?? '';

function fe($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function fmb($n){return number_format(((float)$n)/1048576,2);}

$manifest=[];
if(is_file($manifestFile)){
    $x=json_decode(@file_get_contents($manifestFile),true);
    if(is_array($x)) $manifest=$x;
}

$message='';
$deleted=0;
$bytes=0;
$errors=[];

if($action==='delete' && $manifest){
    foreach($manifest as $entry){
        $q=(string)($entry['quarantine']??'');
        // Safety: only allow files inside the exact quarantine directory.
        $full=realpath($root.'/'.$q);
        $qRoot=realpath($quarantine);
        if(!$full || !$qRoot || strpos($full,$qRoot.DIRECTORY_SEPARATOR)!==0){
            $errors[]=$q;
            continue;
        }
        if(is_file($full)){
            $size=(int)filesize($full);
            if(@unlink($full)){
                $deleted++;
                $bytes+=$size;
            }else{
                $errors[]=$q;
            }
        }
    }
    // Keep an audit log; do not remove the manifest itself.
    $log=[
        'deleted_at'=>date('c'),
        'deleted_files'=>$deleted,
        'deleted_bytes'=>$bytes,
        'errors'=>$errors,
        'manifest_entries'=>count($manifest)
    ];
    @file_put_contents($quarantine.'/deletion-log.json',json_encode($log,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    $message="Permanent cleanup completed: {$deleted} files deleted (".fmb($bytes)." MB).";
    if($errors) $message.=" ".count($errors)." file(s) could not be deleted.";
    // Clear manifest only for successfully deleted entries, preserving failed ones for review.
    $remaining=[];
    foreach($manifest as $entry){
        $q=(string)($entry['quarantine']??'');
        if(in_array($q,$errors,true)) $remaining[]=$entry;
        elseif(is_file($root.'/'.$q)) $remaining[]=$entry;
    }
    @file_put_contents($manifestFile,json_encode($remaining,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    $manifest=$remaining;
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Finalize Image Cleanup</title>
<style>
body{font-family:Arial,sans-serif;background:#f5f7f6;color:#172033;margin:0;padding:28px}.wrap{max-width:900px;margin:auto}
.panel{background:#fff;border:1px solid #e1e8e4;border-radius:14px;padding:22px;margin-bottom:16px}
.warn{border-left:4px solid #c38a2a;background:#fff8e9;padding:15px}.ok{border-left:4px solid #2f7d4f;background:#eef9f1;padding:15px}
.btn{display:inline-block;padding:13px 18px;border-radius:9px;text-decoration:none;font-weight:700}.red{background:#a63d32;color:white}.gray{background:#e9efeb;color:#172033}
</style></head><body><div class="wrap">
<h1>Finalize Image Cleanup</h1>
<p>This page permanently deletes <b>only files already moved into the cleanup quarantine</b>.</p>
<?php if($message): ?><div class="panel ok"><?=fe($message)?></div><?php endif;?>
<?php if($manifest): 
$pendingBytes=0; foreach($manifest as $e) $pendingBytes+=(int)($e['bytes']??0);
?>
<div class="panel warn">
<b>Final confirmation:</b><br>
Pending quarantine files: <?=count($manifest)?><br>
Pending size: <?=fmb($pendingBytes)?> MB<br><br>
Original images and active variant folders are NOT targeted by this action.
</div>
<a class="btn red" href="?action=delete" onclick="return confirm('PERMANENTLY DELETE all files currently in quarantine? This cannot be undone by Restore All. Continue?')">Permanently Delete Quarantine</a>
<a class="btn gray" href="image-cleanup-quarantine.php">Back to Quarantine</a>
<?php else: ?>
<div class="panel ok"><b>No quarantined files remain.</b><br>The cleanup quarantine is empty.</div>
<?php endif;?>
</div></body></html>
