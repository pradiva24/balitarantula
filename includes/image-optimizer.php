<?php
/**
 * Bali Tarantula Adventure - image optimizer
 * Source upload limit: 20 MB.
 * Raster uploads are normalized to WebP.
 */
function bt_image_max_upload_bytes(): int { return 20 * 1024 * 1024; }

function bt_image_allowed_mime(string $mime): bool {
    return in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true);
}

function bt_image_output_extension(string $mime): string {
    return 'webp';
}

function bt_image_webp_available(): bool {
    if (class_exists('Imagick')) {
        try {
            $formats = (new Imagick())->queryFormats('WEBP');
            if (is_array($formats) && !empty($formats)) return true;
        } catch (Throwable $e) {
            // Fall through to GD check.
        }
    }
    return function_exists('imagewebp')
        && function_exists('imagecreatefromstring')
        && function_exists('imagecreatetruecolor');
}

function bt_image_optimize(string $tmp, string $dest, int $maxW=2400, int $maxH=2400, int $quality=82): void {
    if (!is_file($tmp)) throw new RuntimeException('Uploaded image file is missing.');
    $info = @getimagesize($tmp);
    if (!$info || empty($info['mime']) || !bt_image_allowed_mime((string)$info['mime'])) {
        throw new RuntimeException('Only JPG, PNG, WebP or GIF images are allowed.');
    }
    if (!bt_image_webp_available()) {
        throw new RuntimeException('WebP encoding is not available on this server. Enable Imagick WebP or GD WebP support.');
    }

    $mime = (string)$info['mime'];

    // Prefer Imagick for camera-sized images and better color/alpha handling.
    if (class_exists('Imagick')) {
        $im = null;
        $tmpResized = null;
        try {
            $tmpResized = tempnam(sys_get_temp_dir(), 'btimg_');
            if ($tmpResized === false) throw new RuntimeException('Cannot create temporary image file.');
            @unlink($tmpResized);
            $tmpResized .= '.webp';

            $im = new Imagick();
            if ($mime === 'image/jpeg') {
                @$im->setOption('jpeg:size', $maxW.'x'.$maxH);
            }
            $im->readImage($tmp);
            if ($im->getNumberImages() > 1) $im->setIteratorIndex(0);

            // Apply camera/phone EXIF orientation before measuring/resizing.
            // Support both newer and older Imagick method names.
            if (method_exists($im, 'autoOrient')) {
                @$im->autoOrient();
            } elseif (method_exists($im, 'autoOrientImage')) {
                @$im->autoOrientImage();
            } else {
                $orientation = method_exists($im, 'getImageOrientation')
                    ? (int)$im->getImageOrientation()
                    : 1;

                switch ($orientation) {
                    case 2:
                        @$im->flopImage();
                        break;
                    case 3:
                        @$im->rotateImage(new ImagickPixel('transparent'), 180);
                        break;
                    case 4:
                        @$im->flipImage();
                        break;
                    case 5:
                        @$im->transposeImage();
                        break;
                    case 6:
                        @$im->rotateImage(new ImagickPixel('transparent'), 90);
                        break;
                    case 7:
                        @$im->transverseImage();
                        break;
                    case 8:
                        @$im->rotateImage(new ImagickPixel('transparent'), 270);
                        break;
                }
                if (method_exists($im, 'setImageOrientation')) {
                    @$im->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
                }
            }

            // Reset the virtual canvas after orientation. This prevents
            // EXIF/canvas metadata from affecting the final dimensions.
            $im->setImagePage(0, 0, 0, 0);

            // Keep the original aspect ratio. The two values are only
            // maximum bounds; no crop or forced landscape dimensions.
            $im->thumbnailImage($maxW, $maxH, true, false);
            $im->stripImage();
            $im->setImageFormat('webp');
            if (method_exists($im, 'setOption')) {
                @$im->setOption('webp:method', '6');
            }
            $im->setImageCompressionQuality($quality);

            if (!$im->writeImage($tmpResized) || !is_file($tmpResized) || filesize($tmpResized) === 0) {
                throw new RuntimeException('Imagick could not create the WebP image.');
            }
            $im->clear(); $im->destroy(); $im = null;

            if (!@rename($tmpResized, $dest)) {
                if (!@copy($tmpResized, $dest)) throw new RuntimeException('Unable to save WebP image.');
                @unlink($tmpResized);
            }
            if (!is_file($dest) || filesize($dest) === 0) throw new RuntimeException('WebP image is empty.');
            return;
        } catch (Throwable $e) {
            if ($im instanceof Imagick) { try { $im->clear(); $im->destroy(); } catch (Throwable $ignored) {} }
            if ($tmpResized && is_file($tmpResized)) @unlink($tmpResized);
            // Fall through to GD WebP if available.
        }
    }

    // GD fallback. Do not store the original JPEG/PNG as a fallback: all raster uploads must be WebP.
    if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
        throw new RuntimeException('WebP encoding failed: PHP GD WebP support is not available.');
    }

    $raw = @file_get_contents($tmp);
    if ($raw === false) throw new RuntimeException('Unable to read uploaded image.');
    $src = @imagecreatefromstring($raw);
    if (!$src) throw new RuntimeException('PHP GD could not decode this image.');

    // Apply EXIF orientation for JPEGs before resizing.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $orientation = (int)($exif['Orientation'] ?? 1);
        switch ($orientation) {
            case 2:
                imageflip($src, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $rotated = imagerotate($src, 180, 0);
                if ($rotated) { imagedestroy($src); $src = $rotated; }
                break;
            case 4:
                imageflip($src, IMG_FLIP_VERTICAL);
                break;
            case 5:
                imageflip($src, IMG_FLIP_HORIZONTAL);
                $rotated = imagerotate($src, 90, 0);
                if ($rotated) { imagedestroy($src); $src = $rotated; }
                break;
            case 6:
                $rotated = imagerotate($src, -90, 0);
                if ($rotated) { imagedestroy($src); $src = $rotated; }
                break;
            case 7:
                imageflip($src, IMG_FLIP_HORIZONTAL);
                $rotated = imagerotate($src, -90, 0);
                if ($rotated) { imagedestroy($src); $src = $rotated; }
                break;
            case 8:
                $rotated = imagerotate($src, 90, 0);
                if ($rotated) { imagedestroy($src); $src = $rotated; }
                break;
        }
    }

    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, $maxW / max(1,$w), $maxH / max(1,$h));
    $nw = max(1,(int)round($w*$scale));
    $nh = max(1,(int)round($h*$scale));
    $dst = imagecreatetruecolor($nw,$nh);

    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst,0,0,0,127);
    imagefilledrectangle($dst,0,0,$nw,$nh,$transparent);
    imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
    imagedestroy($src);

    $ok = @imagewebp($dst,$dest,$quality);
    imagedestroy($dst);

    if (!$ok || !is_file($dest) || filesize($dest) === 0) {
        if (is_file($dest)) @unlink($dest);
        throw new RuntimeException('PHP GD could not save the WebP image.');
    }
}
