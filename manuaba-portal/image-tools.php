<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/includes/auth.php';
if(function_exists('admin_require')) admin_require();

$page_title = 'Image Tools';
require __DIR__.'/includes/header.php';
$root = realpath(__DIR__.'/..');
$uploads = $root . '/uploads';
$variantsDir = $uploads . '/_variants';
$quarantineDir = $uploads . '/_cleanup-quarantine';
$manifestFile = $quarantineDir . '/manifest.json';

function it_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function it_mb($n): string { return number_format(((float)$n)/1048576, 2); }
function it_path(string $p): string { return str_replace('\\','/', $p); }

function it_code_corpus(string $root): string {
    $s = '';
    if (!is_dir($root)) return $s;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || $f->getSize() > 12000000) continue;
        $ext = strtolower($f->getExtension());
        if (!in_array($ext, ['php','js','css','html','htm','json'], true)) continue;
        $p = it_path($f->getPathname());
        if (strpos($p, '/uploads/') !== false) continue;
        $s .= "\n" . (string)@file_get_contents($f->getPathname());
    }
    return $s;
}

function it_db_corpus(): array {
    $text = '';
    $warnings = [];
    try {
        $pdo = db();
        $cols = $pdo->query(
            "SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE()
             AND DATA_TYPE IN ('char','varchar','text','tinytext','mediumtext','longtext','json')"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            $t = str_replace('`','``',$c['TABLE_NAME']);
            $col = str_replace('`','``',$c['COLUMN_NAME']);
            try {
                $st = $pdo->query("SELECT `$col` FROM `$t` WHERE `$col` IS NOT NULL");
                while ($r = $st->fetch(PDO::FETCH_NUM)) $text .= "\n" . (string)$r[0];
            } catch (Throwable $e) {
                $warnings[] = $c['TABLE_NAME'].'.'.$c['COLUMN_NAME'];
            }
        }
    } catch (Throwable $e) {
        $warnings[] = 'DATABASE';
    }
    return [$text, $warnings];
}

function it_resolve_source(string $root, string $rel): string {
    foreach ([$rel,$rel.'.jpg',$rel.'.jpeg',$rel.'.png',$rel.'.webp',$rel.'.avif'] as $c) {
        if (is_file($root.'/'.$c)) return $c;
    }
    return '';
}

function it_source_from_variant(string $rel): string {
    $rel = it_path($rel);
    if (strpos($rel,'_variants/') !== 0) return '';
    $rest = substr($rel, strlen('_variants/'));
    if (!preg_match('/^(.*)\/([^\/]+)-(480|768|1024|1200|1440|1920)w\.webp$/i', $rest, $m)) return '';
    return 'uploads/'.($m[1] !== '' ? $m[1].'/' : '').$m[2];
}

function it_scan_images(string $uploads): array {
    $out = [];
    if (!is_dir($uploads)) return $out;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $p = it_path($f->getPathname());
        $rel = ltrim(substr($p, strlen(it_path($uploads))), '/');
        if (strpos($rel,'_cleanup-quarantine/') === 0) continue;
        $ext = strtolower($f->getExtension());
        if (!in_array($ext,['jpg','jpeg','png','webp','avif'],true)) continue;
        $out[] = ['path'=>'uploads/'.$rel,'full'=>$f->getPathname(),'bytes'=>(int)$f->getSize()];
    }
    return $out;
}

function it_scan_variants(string $root, string $uploads): array {
    $out = [];
    if (!is_dir($uploads)) return $out;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $p = it_path($f->getPathname());
        $rel = ltrim(substr($p, strlen(it_path($uploads))), '/');
        if (!preg_match('/^_variants\/.*-(480|768|1024|1200|1440|1920)w\.webp$/i', $rel)) continue;
        preg_match('/-(480|768|1024|1200|1440|1920)w\.webp$/i', $rel, $m);
        $src = it_source_from_variant($rel);
        $resolved = it_resolve_source($root, $src);
        if ($resolved !== '') $src = $resolved;
        $out[] = [
            'path'=>'uploads/'.$rel,
            'source'=>$src,
            'width'=>$m[1] ?? '',
            'bytes'=>(int)$f->getSize(),
            'full'=>$p,
            'hash'=>@hash_file('sha256',$p) ?: ''
        ];
    }
    return $out;
}

function it_load_manifest(string $file): array {
    if (!is_file($file)) return [];
    $x = json_decode((string)@file_get_contents($file), true);
    return is_array($x) ? $x : [];
}

function it_save_manifest(string $dir, string $file, array $data): bool {
    if (!is_dir($dir) && !@mkdir($dir,0755,true)) return false;
    return (bool)@file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

$view = $_GET['view'] ?? 'overview';
$allowedViews = ['overview','storage','duplicates','quarantine'];
if (!in_array($view, $allowedViews, true)) $view = 'overview';
$notice = ''; $noticeType = 'ok'; $actionErrors = [];

$codeCorpus = it_code_corpus($root);
[$dbCorpus,$dbWarnings] = it_db_corpus();
$allImages = it_scan_images($uploads);
$variants = it_scan_variants($root,$uploads);

/* Storage/orphan audit */
$orphan = []; $widthStats = [];
foreach ($variants as $v) {
    $widthStats[$v['width']] = ($widthStats[$v['width']] ?? ['files'=>0,'bytes'=>0]);
    $widthStats[$v['width']]['files']++;
    $widthStats[$v['width']]['bytes'] += $v['bytes'];
    if (!is_file($root.'/'.$v['source'])) {
        $codeRef = strpos($codeCorpus,$v['path']) !== false || strpos($codeCorpus,$v['path']) !== false;
        $dbRef = strpos($dbCorpus,$v['path']) !== false;
        if (!$codeRef && !$dbRef) $orphan[] = $v;
    }
}

/* Exact duplicate source groups + candidate groups */
$sources = [];
foreach ($allImages as $x) {
    if (strpos($x['path'],'uploads/_variants/') === 0) continue;
    $sources[] = [
        'path'=>$x['path'],
        'bytes'=>$x['bytes'],
        'hash'=>@hash_file('sha256',$x['full']) ?: '',
        'code'=>strpos($codeCorpus,$x['path']) !== false || strpos($codeCorpus,substr($x['path'],8)) !== false,
        'db'=>strpos($dbCorpus,$x['path']) !== false || strpos($dbCorpus,substr($x['path'],8)) !== false
    ];
}
$byHash = [];
foreach ($sources as $x) if ($x['hash']) $byHash[$x['hash']][] = $x;
$sourceGroups = [];
foreach ($byHash as $hash=>$g) {
    if (count($g) < 2) continue;
    $sourceGroups[] = ['hash'=>$hash,'items'=>$g,'refs'=>array_reduce($g,fn($n,$x)=>$n+($x['code']||$x['db']?1:0),0)];
}
$sourceGroupByPath = [];
foreach ($sourceGroups as $i=>$g) foreach ($g['items'] as $x) $sourceGroupByPath[$x['path']] = $i;
$candidates = [];
foreach ($sourceGroups as $i=>&$g) {
    $g['variants'] = [];
    foreach ($variants as $v) if (isset($sourceGroupByPath[$v['source']]) && $sourceGroupByPath[$v['source']] === $i) $g['variants'][] = $v;
    $g['candidate'] = ($g['refs'] === 0 && count($g['variants']) > 0);
    if ($g['candidate']) $candidates[$i] = $g;
}
unset($g);

/* Exact duplicate active variant groups */
$variantByHash = [];
foreach ($variants as $v) if ($v['hash']) $variantByHash[$v['hash']][] = $v;
$variantDuplicateGroups = [];
foreach ($variantByHash as $hash=>$g) if (count($g)>1) $variantDuplicateGroups[] = ['hash'=>$hash,'items'=>$g];

/* Actions: reversible quarantine / restore / permanent finalize */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'quarantine_orphans') {
        $limit = 20; $moved = 0; $bytes = 0;
        $manifest = it_load_manifest($manifestFile);
        foreach (array_slice($orphan,0,$limit) as $v) {
            $src = $root.'/'.$v['path'];
            $destRel = 'uploads/_cleanup-quarantine/'.substr($v['path'],strlen('uploads/_variants/'));
            $dest = $root.'/'.$destRel;
            if (!is_file($src)) continue;
            if (!is_dir(dirname($dest))) @mkdir(dirname($dest),0755,true);
            if (@rename($src,$dest)) {
                $manifest[]=['original'=>$v['path'],'quarantine'=>$destRel,'bytes'=>$v['bytes'],'time'=>date('c')];
                $moved++; $bytes += $v['bytes'];
            }
        }
        it_save_manifest($quarantineDir,$manifestFile,$manifest);
        $notice="Moved $moved orphan variant(s) to quarantine (".it_mb($bytes)." MB).";
    } elseif ($action === 'quarantine_candidates') {
        $selected = array_map('intval', $_POST['groups'] ?? []);
        $selected = array_values(array_intersect($selected,array_keys($candidates)));
        $manifest = it_load_manifest($manifestFile); $moved=0; $bytes=0;
        foreach ($selected as $gi) {
            foreach ($candidates[$gi]['variants'] as $v) {
                $src=$root.'/'.$v['path']; $destRel='uploads/_cleanup-quarantine/'.substr($v['path'],strlen('uploads/_variants/')); $dest=$root.'/'.$destRel;
                if (!is_file($src)) continue;
                if (!is_dir(dirname($dest))) @mkdir(dirname($dest),0755,true);
                if (@rename($src,$dest)) {
                    $manifest[]=['original'=>$v['path'],'quarantine'=>$destRel,'bytes'=>$v['bytes'],'time'=>date('c')];
                    $moved++; $bytes += $v['bytes'];
                } else $actionErrors[]=$v['path'];
            }
        }
        it_save_manifest($quarantineDir,$manifestFile,$manifest);
        $notice="Moved $moved candidate variant(s) to quarantine (".it_mb($bytes)." MB).";
        if ($actionErrors) {$noticeType='warn'; $notice.=" Errors: ".count($actionErrors);}
    } elseif ($action === 'restore_quarantine') {
        $manifest=it_load_manifest($manifestFile); $restored=0; $bytes=0; $remain=[];
        foreach($manifest as $row){
            $src=$root.'/'.ltrim((string)($row['quarantine']??''),'/');
            $dest=$root.'/'.ltrim((string)($row['original']??''),'/');
            if(is_file($src)){
                if(!is_dir(dirname($dest))) @mkdir(dirname($dest),0755,true);
                if(@rename($src,$dest)){ $restored++; $bytes+=(int)($row['bytes']??0); continue; }
            }
            $remain[]=$row;
        }
        it_save_manifest($quarantineDir,$manifestFile,$remain);
        $notice="Restore complete: $restored file(s) (".it_mb($bytes)." MB).";
        if($remain){$noticeType='warn';$notice.=" Remaining: ".count($remain);}
    } elseif ($action === 'finalize_quarantine') {
        $manifest=it_load_manifest($manifestFile); $deleted=0; $bytes=0; $remain=[];
        $qReal=realpath($quarantineDir);
        foreach($manifest as $row){
            $src=$root.'/'.ltrim((string)($row['quarantine']??''),'/');
            $real=is_file($src)?realpath($src):false;
            if($real && $qReal && strpos(it_path($real),it_path($qReal).'/')===0){
                if(@unlink($real)){ $deleted++; $bytes+=(int)($row['bytes']??0); continue; }
            }
            if(is_file($src)) $remain[]=$row;
        }
        it_save_manifest($quarantineDir,$manifestFile,$remain);
        $notice="Permanent cleanup completed: $deleted file(s) deleted (".it_mb($bytes)." MB).";
        if($remain){$noticeType='warn';$notice.=" Remaining: ".count($remain);}
    }
    /* Refresh after any mutation. */
    header('Location: image-tools.php?view='.rawurlencode($view).'&msg='.rawurlencode($notice).'&type='.rawurlencode($noticeType));
    exit;
}

if (isset($_GET['msg'])) $notice=(string)$_GET['msg'];
if (isset($_GET['type'])) $noticeType=(string)$_GET['type'];
$manifest=it_load_manifest($manifestFile);
$quarantineBytes=0; foreach($manifest as $r) $quarantineBytes+=(int)($r['bytes']??0);

$totalBytes=0; foreach($allImages as $x) $totalBytes += $x['bytes'];
$variantBytes=0; foreach($variants as $x) $variantBytes += $x['bytes'];
$candidateVariantBytes=0; $candidateVariantFiles=0;
foreach($candidates as $g){$candidateVariantFiles+=count($g['variants']);foreach($g['variants'] as $v)$candidateVariantBytes+=$v['bytes'];}
$orphanBytes=array_sum(array_column($orphan,'bytes'));
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Image Tools</title>
<style>
.it-wrap{max-width:1250px}.it-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:18px}
.it-title{margin:0 0 6px;font-size:25px}.it-muted{color:#66756d}.it-nav{display:flex;gap:7px;flex-wrap:wrap;margin:0 0 18px}
.it-nav a{padding:8px 11px;border-radius:8px;background:#eef2ef;color:#18372c;text-decoration:none;font-size:12px;font-weight:700}
.it-nav a.active{background:#176b43;color:#fff}.it-card{background:#fff;border:1px solid #e1e8e4;border-radius:14px;padding:18px;margin-bottom:16px}
.it-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.it-stat{background:#f5f8f6;border-radius:10px;padding:13px}.it-stat b{display:block;font-size:21px;margin-top:4px}
.it-ok{background:#edf8f0;color:#175b36;padding:12px 14px;border-radius:9px;margin-bottom:14px}.it-warn{background:#fff7e3;color:#765711;padding:12px 14px;border-radius:9px;margin-bottom:14px}
.it-btn{display:inline-block;border:0;border-radius:8px;padding:9px 12px;background:#176b43;color:#fff;text-decoration:none;cursor:pointer;font-weight:700;font-size:12px}.it-btn.gray{background:#eef2ef;color:#18372c}.it-btn.red{background:#9b3c34}
.it-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.it-table{width:100%;border-collapse:collapse;font-size:12px}.it-table th,.it-table td{padding:8px;border-bottom:1px solid #edf0ee;text-align:left;vertical-align:top}.mono{font-family:monospace;word-break:break-all}
.it-group{border:1px solid #e1e8e4;border-radius:11px;margin:10px 0;padding:13px}.it-group-head{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}.it-badge{display:inline-block;padding:4px 7px;border-radius:999px;background:#eef2ef;margin-right:4px}.it-green{background:#edf8f0}.it-yellow{background:#fff7e3}
.it-small{font-size:11px;color:#68736e}pre{white-space:pre-wrap}
@media(max-width:850px){.it-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.it-hero{display:block}}
</style></head><body>
<div class="it-wrap">
<div class="it-hero"><div><h2 class="it-title">Image Tools</h2><div class="it-muted">Media storage audit, duplicate review, reversible quarantine and final cleanup in one page.</div></div><div><a class="it-btn gray" href="media-variants.php">← Media Variants</a></div></div>
<div class="it-nav">
<a class="<?=($view==='overview'?'active':'')?>" href="image-tools.php?view=overview">Overview</a>
<a class="<?=($view==='storage'?'active':'')?>" href="image-tools.php?view=storage">Storage Audit</a>
<a class="<?=($view==='duplicates'?'active':'')?>" href="image-tools.php?view=duplicates">Duplicates</a>
<a class="<?=($view==='quarantine'?'active':'')?>" href="image-tools.php?view=quarantine">Quarantine & Finalize</a>
</div>
<?php if($notice): ?><div class="<?=($noticeType==='warn'?'it-warn':'it-ok')?>"><?=it_e($notice)?></div><?php endif; ?>

<?php if($view==='overview'): ?>
<div class="it-card"><div class="it-grid">
<div class="it-stat">All source/media files<b><?=number_format(count($allImages))?></b><span class="it-small"><?=it_mb($totalBytes)?> MB</span></div>
<div class="it-stat">Active variants<b><?=number_format(count($variants))?></b><span class="it-small"><?=it_mb($variantBytes)?> MB</span></div>
<div class="it-stat">Orphan variants<b><?=number_format(count($orphan))?></b><span class="it-small"><?=it_mb($orphanBytes)?> MB</span></div>
<div class="it-stat">Duplicate source groups<b><?=number_format(count($sourceGroups))?></b><span class="it-small"><?=count($candidates)?> review candidates</span></div>
</div></div>
<div class="it-card">
<h3>Current status</h3>
<div class="<?=count($orphan)===0?'it-ok':'it-warn'?>">
<b><?=count($orphan)===0?'Orphan variants: CLEAN':'Orphan variants need review'?></b>
· <?=number_format(count($orphan))?> file(s) · <?=it_mb($orphanBytes)?> MB
</div>
<div class="it-actions">
<a class="it-btn" href="image-tools.php?view=storage">Open Storage Audit</a>
<a class="it-btn" href="image-tools.php?view=duplicates">Review Duplicates</a>
<a class="it-btn gray" href="image-tools.php?view=quarantine">Quarantine / Finalize</a>
</div>
</div>
<div class="it-card"><h3>Variant inventory</h3><table class="it-table"><tr><th>Width</th><th>Files</th><th>Storage</th><th>Status</th></tr>
<?php foreach([480,768,1024,1200,1440,1920] as $w): $s=$widthStats[(string)$w]??['files'=>0,'bytes'=>0]; ?>
<tr><td><?=$w?>w</td><td><?=number_format($s['files'])?></td><td><?=it_mb($s['bytes'])?> MB</td><td><?=$w===1200?'Not requested by current responsive helper':'Active responsive width'?></td></tr>
<?php endforeach; ?></table></div>
<?php endif; ?>

<?php if($view==='storage'): ?>
<div class="it-card"><div class="it-grid">
<div class="it-stat">1200w files<b><?=number_format($widthStats['1200']['files']??0)?></b><span class="it-small"><?=it_mb($widthStats['1200']['bytes']??0)?> MB</span></div>
<div class="it-stat">1200w orphan candidates<b><?=number_format(count(array_filter($orphan,fn($v)=>$v['width']==='1200')))?></b></div>
<div class="it-stat">All orphan variants<b><?=number_format(count($orphan))?></b><span class="it-small"><?=it_mb($orphanBytes)?> MB</span></div>
<div class="it-stat">Quarantine<b><?=number_format(count($manifest))?></b><span class="it-small"><?=it_mb($quarantineBytes)?> MB</span></div>
</div></div>
<div class="it-card"><div class="it-ok"><b>Frontend rule:</b> 480w, 768w, 1024w, 1440w, 1920w. 1200w is not generated/requested by the current responsive helper.</div>
<h3>Orphan variants</h3>
<?php if(!$orphan): ?><p>None found under the conservative source/reference check.</p><?php else: ?>
<table class="it-table"><tr><th>Path</th><th>Source</th><th>Width</th><th>Size</th></tr>
<?php foreach($orphan as $v): ?><tr><td class="mono"><?=it_e($v['path'])?></td><td class="mono"><?=it_e($v['source'])?></td><td><?=$v['width']?>w</td><td><?=it_mb($v['bytes'])?> MB</td></tr><?php endforeach; ?></table>
<form method="post" class="it-actions" onsubmit="return confirm('Move up to 20 orphan variants to quarantine?');"><input type="hidden" name="csrf" value="<?=it_e(admin_csrf())?>"><input type="hidden" name="action" value="quarantine_orphans"><button class="it-btn" type="submit">Quarantine next 20 orphans</button></form>
<?php endif; ?></div>
<?php endif; ?>

<?php if($view==='duplicates'): ?>
<div class="it-card"><div class="it-grid">
<div class="it-stat">Duplicate source groups<b><?=number_format(count($sourceGroups))?></b></div>
<div class="it-stat">Duplicate source files<b><?=number_format(array_sum(array_map(fn($g)=>count($g['items']),$sourceGroups)))?></b></div>
<div class="it-stat">Variant duplicate groups<b><?=number_format(count($variantDuplicateGroups))?></b></div>
<div class="it-stat">Review candidates<b><?=number_format(count($candidates))?></b><span class="it-small"><?=number_format($candidateVariantFiles)?> variants · <?=it_mb($candidateVariantBytes)?> MB</span></div>
</div></div>
<div class="it-card"><div class="it-warn"><b>Review only:</b> dynamic responsive paths can be constructed by the frontend. A duplicate group is not automatically safe to delete.</div>
<form method="post" onsubmit="return confirm('Move the selected candidate groups to quarantine? Originals will NOT be moved.');">
<input type="hidden" name="csrf" value="<?=it_e(admin_csrf())?>"><input type="hidden" name="action" value="quarantine_candidates">
<?php $n=0; foreach($candidates as $gi=>$g): $n++; $vb=array_sum(array_column($g['variants'],'bytes')); ?>
<div class="it-group">
<div class="it-group-head"><label><input type="checkbox" name="groups[]" value="<?=$gi?>"> <b>Candidate #<?=$n?></b></label><b><?=count($g['variants'])?> variants · <?=it_mb($vb)?> MB</b></div>
<table class="it-table"><tr><th>Source</th><th>Size</th><th>Code</th><th>DB</th></tr>
<?php foreach($g['items'] as $x): ?><tr><td class="mono"><?=it_e($x['path'])?></td><td><?=it_mb($x['bytes'])?> MB</td><td><?=$x['code']?'YES':'NO'?></td><td><?=$x['db']?'YES':'NO'?></td></tr><?php endforeach; ?></table>
<details><summary>Show variants</summary><table class="it-table"><tr><th>Variant</th><th>Width</th><th>Size</th></tr>
<?php foreach($g['variants'] as $v): ?><tr><td class="mono"><?=it_e($v['path'])?></td><td><?=$v['width']?>w</td><td><?=it_mb($v['bytes'])?> MB</td></tr><?php endforeach; ?></table></details>
</div>
<?php endforeach; ?>
<?php if($candidates): ?><button class="it-btn" type="submit">Quarantine Selected Groups</button><?php else: ?><p>No duplicate source candidates currently qualify.</p><?php endif; ?>
</form></div>
<div class="it-card"><h3>Exact duplicate active variant groups</h3><p class="it-small">These are informational only; no automatic deletion rule is applied.</p>
<table class="it-table"><tr><th>#</th><th>Files</th><th>Size each</th><th>Paths</th></tr>
<?php foreach(array_slice($variantDuplicateGroups,0,100) as $i=>$g): ?><tr><td><?=$i+1?></td><td><?=count($g['items'])?></td><td><?=it_mb($g['items'][0]['bytes'])?> MB</td><td><?php foreach($g['items'] as $v): ?><div class="mono"><?=it_e($v['path'])?></div><?php endforeach; ?></td></tr><?php endforeach; ?></table></div>
<?php endif; ?>

<?php if($view==='quarantine'): ?>
<div class="it-card"><div class="it-grid">
<div class="it-stat">Quarantined files<b><?=number_format(count($manifest))?></b></div>
<div class="it-stat">Quarantined storage<b><?=it_mb($quarantineBytes)?></b> MB</div>
<div class="it-stat">Restore<b>Available</b><span class="it-small">Moves files back to original paths</span></div>
<div class="it-stat">Permanent delete<b>Manual</b><span class="it-small">Only quarantine files</span></div>
</div></div>
<div class="it-card">
<?php if(!$manifest): ?><div class="it-ok"><b>Quarantine is empty.</b> No files are waiting for restore or permanent deletion.</div>
<?php else: ?>
<div class="it-warn"><b>Quarantine contains <?=count($manifest)?> file(s).</b> Test the website before permanent deletion.</div>
<table class="it-table"><tr><th>Original</th><th>Quarantine</th><th>Size</th></tr>
<?php foreach($manifest as $r): ?><tr><td class="mono"><?=it_e($r['original']??'')?></td><td class="mono"><?=it_e($r['quarantine']??'')?></td><td><?=it_mb($r['bytes']??0)?> MB</td></tr><?php endforeach; ?></table>
<div class="it-actions">
<form method="post" onsubmit="return confirm('Restore ALL quarantined files to their original paths?');"><input type="hidden" name="csrf" value="<?=it_e(admin_csrf())?>"><input type="hidden" name="action" value="restore_quarantine"><button class="it-btn gray">Restore All</button></form>
<form method="post" onsubmit="return confirm('PERMANENTLY DELETE ONLY the quarantined files? This cannot be undone.');"><input type="hidden" name="csrf" value="<?=it_e(admin_csrf())?>"><input type="hidden" name="action" value="finalize_quarantine"><button class="it-btn red">Permanently Delete Quarantine</button></form>
</div>
<?php endif; ?></div>
<?php endif; ?>

<?php if($dbWarnings): ?><div class="it-card it-small">Database scan warnings: <?=it_e(implode(', ',$dbWarnings))?></div><?php endif; ?>
</div>
<?php require __DIR__."/includes/footer.php"; ?>
