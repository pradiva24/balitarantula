<?php
/**
 * Bali Tarantula Adventure - image optimizer
 * Source limit: 20 MB.
 * This MAMP build does not have a WebP encoder, so the optimizer uses
 * JPEG for photos and PNG for PNG images instead of requiring WebP.
 */
function bt_image_max_upload_bytes(): int { return 20 * 1024 * 1024; }

function bt_image_allowed_mime(string $mime): bool {
    return in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true);
}

function bt_image_output_extension(string $mime): string {
    return $mime === 'image/png' ? 'png' : 'jpg';
}

function bt_image_optimize(string $tmp, string $dest, int $maxW=2400, int $maxH=2400, int $quality=82): void {
    if (!is_file($tmp)) throw new RuntimeException('Uploaded image file is missing.');
    $info = @getimagesize($tmp);
    if (!$info || empty($info['mime']) || !bt_image_allowed_mime((string)$info['mime'])) {
        throw new RuntimeException('Only JPG, PNG, WebP or GIF images are allowed.');
    }
    $mime = (string)$info['mime'];

    // Resize with Imagick first. This avoids loading a huge camera image into GD.
    if (class_exists('Imagick')) {
        $im = null;
        $tmpResized = null;
        try {
            $tmpResized = tempnam(sys_get_temp_dir(), 'btimg_');
            if ($tmpResized === false) throw new RuntimeException('Cannot create temporary image file.');
            @unlink($tmpResized);
            $ext = bt_image_output_extension($mime);
            $tmpResized .= '.'.$ext;

            $im = new Imagick();
            if ($mime === 'image/jpeg') {
                @$im->setOption('jpeg:size', $maxW.'x'.$maxH);
            }
            $im->readImage($tmp);
            if ($im->getNumberImages() > 1) $im->setIteratorIndex(0);
            if (method_exists($im, 'autoOrient')) @$im->autoOrient();
            $im->setImagePage(0,0,0,0);
            $im->thumbnailImage($maxW, $maxH, true, true);
            $im->stripImage();

            if ($ext === 'png') {
                $im->setImageFormat('png');
                $im->setOption('png:compression-level', '9');
            } else {
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality($quality);
                // Flatten transparency for non-PNG output.
                if ($im->getImageAlphaChannel()) {
                    $bg = new Imagick();
                    $bg->newImage($im->getImageWidth(), $im->getImageHeight(), 'white');
                    $bg->setImageFormat('jpeg');
                    $bg->compositeImage($im, Imagick::COMPOSITE_OVER, 0, 0);
                    $im->clear(); $im->destroy();
                    $im = $bg;
                }
            }

            if (!$im->writeImage($tmpResized) || !is_file($tmpResized) || filesize($tmpResized) === 0) {
                throw new RuntimeException('Imagick could not create the optimized image.');
            }
            $im->clear(); $im->destroy(); $im = null;

            // Move the optimized temporary file to the requested destination.
            if (!@rename($tmpResized, $dest)) {
                if (!@copy($tmpResized, $dest)) throw new RuntimeException('Unable to save optimized image.');
                @unlink($tmpResized);
            }
            if (!is_file($dest) || filesize($dest) === 0) throw new RuntimeException('Optimized image is empty.');
            return;
        } catch (Throwable $e) {
            if ($im instanceof Imagick) { try { $im->clear(); $im->destroy(); } catch (Throwable $ignored) {} }
            if ($tmpResized && is_file($tmpResized)) @unlink($tmpResized);
            // Fall through to GD.
        }
    }

    // GD fallback. WebP is deliberately not required.
    if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('Image processing is unavailable: PHP GD is not available.');
    }
    $raw = @file_get_contents($tmp);
    if ($raw === false) throw new RuntimeException('Unable to read uploaded image.');
    $src = @imagecreatefromstring($raw);
    if (!$src) throw new RuntimeException('PHP GD could not decode this image.');

    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, $maxW / max(1,$w), $maxH / max(1,$h));
    $nw = max(1,(int)round($w*$scale));
    $nh = max(1,(int)round($h*$scale));
    $dst = imagecreatetruecolor($nw,$nh);

    if ($mime === 'image/png') {
        imagealphablending($dst,false);
        imagesavealpha($dst,true);
        $transparent=imagecolorallocatealpha($dst,0,0,0,127);
        imagefilledrectangle($dst,0,0,$nw,$nh,$transparent);
    } else {
        $white=imagecolorallocate($dst,255,255,255);
        imagefilledrectangle($dst,0,0,$nw,$nh,$white);
    }
    imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
    imagedestroy($src);

    $ok = ($mime === 'image/png')
        ? @imagepng($dst,$dest,9)
        : @imagejpeg($dst,$dest,$quality);
    imagedestroy($dst);

    if (!$ok || !is_file($dest) || filesize($dest) === 0) {
        if (is_file($dest)) @unlink($dest);
        throw new RuntimeException('PHP GD could not save the optimized image.');
    }
}
