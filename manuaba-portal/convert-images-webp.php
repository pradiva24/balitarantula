<?php
/**
 * One-time WebP migration tool for Bali Tarantula Adventure.
 * Requires an authenticated admin session.
 * Converts JPG/JPEG/PNG files under /uploads to WebP, excluding payment proofs,
 * and updates database text fields containing the old asset paths.
 * Originals are intentionally kept for rollback.
 */
require __DIR__ . '/includes/auth.php';

if (empty($_SESSION['admin_id'])) { http_response_code(403); exit('Forbidden'); }

$base = realpath(__DIR__ . '/..');
$uploads = realpath($base . '/uploads');
if ($uploads === false || !is_dir($uploads)) { http_response_code(500); exit('Uploads directory not found.'); }

function webp_rel_from_file(string $base, string $file): string {
    return str_replace(DIRECTORY_SEPARATOR, '/', ltrim(str_replace($base, '', $file), DIRECTORY_SEPARATOR));
}

function webp_convert_file(string $src, string $dest): void {
    $info = @getimagesize($src);
    if (!$info || empty($info['mime'])) throw new RuntimeException('Invalid image.');
    $mime = strtolower((string)$info['mime']);
    if (!in_array($mime, ['image/jpeg','image/png'], true)) {
        throw new RuntimeException('Only JPG/JPEG/PNG are converted by this tool.');
    }

    if (class_exists('Imagick')) {
        $im = new Imagick();
        try {
            $im->readImage($src);
            if ($im->getNumberImages() > 1) $im->setIteratorIndex(0);
            if (method_exists($im, 'autoOrient')) @$im->autoOrient();
            $im->setImagePage(0, 0, 0, 0);
            $im->setOption('webp:method', '6');
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality(82);
            $im->stripImage();
            if (!$im->writeImage($dest)) throw new RuntimeException('Imagick could not write WebP.');
            $im->clear();
            $im->destroy();
        } catch (Throwable $e) {
            try { $im->clear(); $im->destroy(); } catch (Throwable $ignored) {}
            throw $e;
        }
        if (!is_file($dest) || filesize($dest) === 0) throw new RuntimeException('WebP output is empty.');
        return;
    }

    if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) {
        throw new RuntimeException('WebP encoding is unavailable. Enable Imagick WebP or GD imagewebp on hosting.');
    }
    $raw = @file_get_contents($src);
    if ($raw === false) throw new RuntimeException('Unable to read source image.');
    $im = @imagecreatefromstring($raw);
    if (!$im) throw new RuntimeException('GD could not decode image.');

    imagealphablending($im, true);
    imagesavealpha($im, true);
    $ok = @imagewebp($im, $dest, 82);
    imagedestroy($im);
    if (!$ok || !is_file($dest) || filesize($dest) === 0) {
        if (is_file($dest)) @unlink($dest);
        throw new RuntimeException('GD could not write WebP.');
    }
}

function update_db_path(string $old, string $new): int {
    $db = db();
    $count = 0;
    $cols = $db->query("SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('char','varchar','text','tinytext','mediumtext','longtext') ORDER BY TABLE_NAME, ORDINAL_POSITION")->fetchAll();
    foreach ($cols as $c) {
        $table = (string)$c['TABLE_NAME'];
        $col = (string)$c['COLUMN_NAME'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $col)) continue;
        $sql = "UPDATE `{$table}` SET `{$col}` = REPLACE(`{$col}`, ?, ?) WHERE `{$col}` LIKE ?";
        $st = $db->prepare($sql);
        $like = '%' . $old . '%';
        $st->execute([$old, $new, $like]);
        $count += $st->rowCount();
    }
    return $count;
}

function candidates(string $uploads): array {
    $skip = realpath($uploads . '/payment-proofs');
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $path = $f->getPathname();
        if ($skip && str_starts_with(realpath($path), $skip . DIRECTORY_SEPARATOR)) continue;
        $ext = strtolower($f->getExtension());
        if (!in_array($ext, ['jpg','jpeg','png'], true)) continue;
        $out[] = $path;
    }
    sort($out, SORT_STRING);
    return $out;
}

$all = candidates($uploads);
$total = count($all);
$offset = max(0, (int)($_POST['offset'] ?? 0));
$batch = 10;
$message = null;
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();
    for ($i=$offset; $i<min($offset+$batch, $total); $i++) {
        $src = $all[$i];
        $rel = webp_rel_from_file($base, $src);
        $dest = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
        if (!$dest || $dest === $src) { $results[] = ['skip',$rel,'Invalid destination']; continue; }
        if (is_file($dest) && filesize($dest) > 0) {
            $results[] = ['skip',$rel,'WebP already exists'];
            continue;
        }
        try {
            webp_convert_file($src, $dest);
            $oldRel = $rel;
            $newRel = preg_replace('/\.(jpe?g|png)$/i', '.webp', $rel);
            $updated = update_db_path($oldRel, $newRel);
            $results[] = ['ok',$oldRel,'Created '.$newRel.'; DB rows updated: '.$updated];
        } catch (Throwable $e) {
            if (is_file($dest)) @unlink($dest);
            $results[] = ['error',$rel,$e->getMessage()];
        }
    }
    $next = $offset + $batch;
    $done = min($next, $total);
    $message = $done < $total ? "Processed {$done} of {$total}. Continue with the next batch." : "Completed. Processed {$total} source images.";
}
?><!doctype html>
<html><head><meta charset="utf-8"><title>WebP Image Migration</title>
<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f7f6;color:#17352a;padding:28px}.card{max-width:980px;margin:0 auto;background:#fff;border:1px solid #dfe7e2;border-radius:14px;padding:24px;box-shadow:0 10px 30px rgba(20,58,40,.06)}h1{margin:0 0 8px;font-size:24px}.muted{color:#65756d}.notice{padding:12px 14px;border-radius:10px;background:#edf7ed;margin:16px 0}.danger{background:#fff4e5}.bar{height:10px;background:#e8eeeb;border-radius:99px;overflow:hidden;margin:14px 0}.bar>span{display:block;height:100%;width:<?= $total?round((min($offset,$total)/$total)*100,1):0 ?>%;background:#2d7b59}.btn{border:0;background:#1d6847;color:#fff;padding:11px 16px;border-radius:8px;font-weight:700;cursor:pointer}.row{padding:9px 0;border-bottom:1px solid #edf1ee;font-size:13px}.ok{color:#216b49}.skip{color:#64746c}.error{color:#a12626;font-weight:700}code{background:#f1f4f2;padding:2px 5px;border-radius:5px}</style></head><body>
<div class="card">
<h1>WebP Image Migration</h1>
<p class="muted">Converts JPG/JPEG/PNG under <code>/uploads</code> to WebP. Payment proofs are excluded. Originals are kept.</p>
<div class="notice danger"><b>Before running:</b> make a database backup and confirm the hosting has Imagick WebP or GD WebP encoding.</div>
<p><b>Total source images:</b> <?=e($total)?></p>
<div class="bar"><span></span></div>
<?php if($message): ?><div class="notice"><?=e($message)?></div><?php endif; ?>
<?php if(!$message || ($offset+10)<$total): $nextOffset = $message ? min($offset+10,$total) : 0; if($nextOffset < $total): ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(admin_csrf())?>"><input type="hidden" name="offset" value="<?=$nextOffset?>"><button class="btn" type="submit">Convert next <?=min(10,$total-$nextOffset)?> images</button></form>
<?php endif; endif; ?>
<?php if($results): ?><h2>Batch result</h2><?php foreach($results as $r): ?><div class="row <?=$r[0]==='ok'?'ok':($r[0]==='error'?'error':'skip')?>"><b><?=e(strtoupper($r[0]))?></b> — <?=e($r[1])?> — <?=e($r[2])?></div><?php endforeach; endif; ?>
</div></body></html>
