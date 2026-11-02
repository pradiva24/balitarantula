<?php
require_once __DIR__.'/eticket-config.php';
/**
 * Lightweight, dependency-free one-page A4 e-ticket PDF generator.
 * It intentionally uses only standard PDF fonts so it works on shared hosting
 * without Composer, Imagick or GD.
 */

if (!function_exists('bta_pdf_ascii')) {
    function bta_pdf_ascii(string $text): string {
        $text = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $text);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) $text = $converted;
        }
        $text = str_replace(['–','—','•','·','×','“','”','’','‘','…'], ['-','-','-','-','x','"','"',"'","'",'...'], $text);
        return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
    }
}

if (!function_exists('bta_pdf_escape')) {
    function bta_pdf_escape(string $text): string {
        $text = bta_pdf_ascii($text);
        return str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $text);
    }
}

if (!function_exists('bta_pdf_money')) {
    function bta_pdf_money($amount): string {
        return 'IDR '.number_format((float)$amount, 0, ',', '.');
    }
}

if (!function_exists('bta_pdf_date')) {
    function bta_pdf_date($date): string {
        $date = trim((string)$date);
        if ($date === '') return '-';
        $ts = strtotime($date);
        return $ts ? date('D, M d, Y', $ts) : $date;
    }
}

if (!function_exists('bta_pdf_time')) {
    function bta_pdf_time($time): string {
        $time = trim((string)$time);
        return $time === '' ? '-' : substr($time, 0, 5);
    }
}

if (!function_exists('bta_pdf_lines')) {
    function bta_pdf_lines(string $text, int $maxChars): array {
        $text = trim(bta_pdf_ascii($text));
        if ($text === '') return ['-'];
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if ($line === '') { $line = $word; continue; }
            $candidate = $line.' '.$word;
            if (strlen($candidate) <= $maxChars) $line = $candidate;
            else { $lines[] = $line; $line = $word; }
        }
        if ($line !== '') $lines[] = $line;
        return $lines ?: ['-'];
    }
}

if (!function_exists('bta_pdf_text')) {
    function bta_pdf_text(array &$c, float $x, float $y, string $text, float $size = 9, bool $bold = false, array $rgb = [0.15,0.29,0.24]): void {
        $font = '/F1'; // Keep all PDF ticket typography regular, never bold.
        [$r,$g,$b] = $rgb;
        $c[] = sprintf('q %.3f %.3f %.3f rg BT %s %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET Q', $r,$g,$b,$font,$size,$x,$y,bta_pdf_escape($text));
    }
}

if (!function_exists('bta_pdf_multiline')) {
    function bta_pdf_multiline(array &$c, float $x, float $y, float $width, string $text, float $size = 8, bool $bold = false, array $rgb = [0.31,0.40,0.36], float $leading = 11): void {
        $maxChars = max(8, (int)floor($width / ($size * 0.53)));
        foreach (bta_pdf_lines($text, $maxChars) as $i => $line) {
            bta_pdf_text($c, $x, $y - ($i * $leading), $line, $size, $bold, $rgb);
        }
    }
}

if (!function_exists('bta_pdf_rect')) {
    function bta_pdf_rect(array &$c, float $x, float $y, float $w, float $h, array $rgb, bool $fill = true, float $lineWidth = 0.7): void {
        [$r,$g,$b] = $rgb;
        if ($fill) $c[] = sprintf('q %.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f Q', $r,$g,$b,$x,$y,$w,$h);
        else $c[] = sprintf('q %.3f %.3f %.3f RG %.2f w %.2f %.2f %.2f %.2f re S Q', $r,$g,$b,$lineWidth,$x,$y,$w,$h);
    }
}

if (!function_exists('bta_pdf_line')) {
    function bta_pdf_line(array &$c, float $x1, float $y1, float $x2, float $y2, array $rgb, float $lineWidth = 0.7, array $dash = []): void {
        [$r,$g,$b] = $rgb;
        $dashCmd = $dash ? (' ['.implode(' ', array_map('floatval', $dash)).'] 0 d') : ' [] 0 d';
        $c[] = sprintf('q %.3f %.3f %.3f RG %.2f w%s %.2f %.2f m %.2f %.2f l S Q', $r,$g,$b,$lineWidth,$dashCmd,$x1,$y1,$x2,$y2);
    }
}

if (!function_exists('bta_pdf_center')) {
    function bta_pdf_center(array &$c, float $cx, float $y, float $w, string $text, float $size = 9, bool $bold = false, array $rgb = [0.15,0.29,0.24]): void {
        $text = bta_pdf_ascii($text);
        $approx = strlen($text) * $size * 0.50;
        bta_pdf_text($c, $cx - min($w,$approx)/2, $y, $text, $size, $bold, $rgb);
    }
}

if (!function_exists('bta_pdf_fetch_qr_jpeg')) {
    /**
     * Fetch a real QR PNG and convert it to an RGB JPEG for direct PDF embedding.
     * This avoids SVG parsing and does not draw a fake barcode.
     */
    function bta_pdf_fetch_qr_jpeg(string $value, int $pixels = 420): ?array {
        $value = trim($value);
        if ($value === '') return null;
        // Canonical QR source shared with the web E-ticket and print view.
        // Keep provider, payload and quiet-zone settings identical everywhere.
        $urls = [
            'https://api.qrserver.com/v1/create-qr-code/?size=' . $pixels . 'x' . $pixels . '&margin=12&format=png&data=' . rawurlencode($value),
        ];
        $png = false;
        foreach ($urls as $url) {
            $body = false;
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_TIMEOUT => 18,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT => 'BaliBooking-QR/1.0',
                ]);
                $body = curl_exec($ch);
                $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($http < 200 || $http >= 300) $body = false;
            } elseif (ini_get('allow_url_fopen')) {
                $context = stream_context_create(['http' => ['timeout' => 18, 'user_agent' => 'BaliBooking-QR/1.0']]);
                $body = @file_get_contents($url, false, $context);
            }
            if (is_string($body) && strncmp($body, "\x89PNG\r\n\x1a\n", 8) === 0) {
                $png = $body;
                break;
            }
        }
        if (!is_string($png)) return null;
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) return null;
        $im = @imagecreatefromstring($png);
        if (!$im) return null;
        $w = imagesx($im); $h = imagesy($im);
        $rgb = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($rgb, 255, 255, 255);
        imagefill($rgb, 0, 0, $white);
        imagecopy($rgb, $im, 0, 0, 0, 0, $w, $h);
        ob_start();
        $ok = imagejpeg($rgb, null, 96);
        $jpeg = ob_get_clean();
        imagedestroy($im); imagedestroy($rgb);
        if (!$ok || !is_string($jpeg) || strncmp($jpeg, "\xFF\xD8\xFF", 3) !== 0) return null;
        return ['bytes' => $jpeg, 'width' => $w, 'height' => $h];
    }
}

if (!function_exists('bta_pdf_fetch_cover_jpeg')) {
    function bta_pdf_fetch_cover_jpeg(string $path): ?array {
        $path = trim($path);
        if ($path === '') return null;
        if (preg_match('~^https?://~i', $path)) {
            $url = $path;
        } elseif (defined('BASE_URL')) {
            $url = rtrim((string)BASE_URL, '/') . '/' . ltrim($path, '/');
        } else {
            return null;
        }
        $bytes = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'BaliTarantula-Eticket/1.0'
            ]);
            $bytes = curl_exec($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http < 200 || $http >= 300) $bytes = false;
        } elseif (ini_get('allow_url_fopen')) {
            $bytes = @file_get_contents($url);
        }
        if (!is_string($bytes) || !function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) return null;
        $im = @imagecreatefromstring($bytes);
        if (!$im) return null;
        $w = imagesx($im);
        $h = imagesy($im);
        $rgb = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($rgb, 255, 255, 255);
        imagefill($rgb, 0, 0, $white);
        imagecopy($rgb, $im, 0, 0, 0, 0, $w, $h);
        ob_start();
        $ok = imagejpeg($rgb, null, 88);
        $jpeg = ob_get_clean();
        imagedestroy($im);
        imagedestroy($rgb);
        if (!$ok || !is_string($jpeg) || strncmp($jpeg, "\xFF\xD8\xFF", 3) !== 0) return null;
        return ['bytes' => $jpeg, 'width' => $w, 'height' => $h];
    }
}

if (!function_exists('bta_pdf_image_command')) {
    function bta_pdf_image_command(array &$c, float $x, float $y, float $size): void {
        $c[] = sprintf('q %.2f 0 0 %.2f %.2f %.2f cm /Im1 Do Q', $size, $size, $x, $y);
    }
}

if (!function_exists('bta_pdf_local_qr')) {
    /**
     * Draw a real QR image directly into the PDF as an embedded JPEG.
     * Requires cURL (or allow_url_fopen) and GD on the hosting account.
     */
    function bta_pdf_local_qr(array &$c, float $x, float $y, float $size, string $value, ?array &$image = null): bool {
        $image = bta_pdf_fetch_qr_jpeg($value);
        if (!$image) return false;
        bta_pdf_rect($c, $x, $y, $size, $size, [1,1,1]);
        bta_pdf_image_command($c, $x, $y, $size);
        return true;
    }
}

if (!function_exists('bta_pdf_real_qr')) {
    function bta_pdf_real_qr(array &$c, float $x, float $y, float $size, string $value): bool {
        return false;
    }
}

if (!function_exists('bta_pdf_build')) {
    function bta_pdf_build(array $b, string $siteName = '', string $siteEmail = '', string $sitePhone = '', string $siteAddress = ''): string {
        $siteName = trim(eticket_text('eticket_ticket_brand', trim($siteName) ?: 'Bali Tarantula Adventure')) ?: 'Bali Tarantula Adventure';
        $siteEmail = trim($siteEmail);
        $sitePhone = trim($sitePhone);
        $siteAddress = trim($siteAddress) ?: 'Bali, Indonesia';
        $code = (string)($b['booking_code'] ?? '-');
        $package = (string)($b['package_name'] ?? 'Bali Adventure');
        $date = bta_pdf_date($b['booking_date'] ?? '');
        $activityTime = bta_pdf_time($b['activity_time'] ?? '');
        $pickupTime = bta_pdf_time($b['pickup_time'] ?? '');
        $isTandem = (($b['booking_mode'] ?? 'person') === 'tandem');
        $guests = $isTandem ? '2 Persons (1 Tandem Unit)' : ((int)($b['adults'] ?? 0).' Adults'.((int)($b['children'] ?? 0) > 0 ? ', '.(int)$b['children'].' Children' : ''));
        $pickupYes = !empty($b['pickup_required']);
        $pickupPlace = trim((string)($b['hotel_name'] ?? ''));
        $pickupArea = trim((string)($b['pickup_area_name'] ?? ''));
        $pickupLocation = trim($pickupPlace.($pickupArea && stripos($pickupPlace,$pickupArea)===false ? ', '.$pickupArea : ''));
        $status = strtoupper((string)($b['booking_status'] ?? 'confirmed'));
        if ($status === 'NEW') $status = 'CONFIRMED';
        $payment = strtoupper((string)($b['payment_status'] ?? 'PENDING'));
        $total = bta_pdf_money($b['total'] ?? 0);

        $c = [];
        $dark=[0.035,0.055,0.045]; $orange=[1.0,0.34,0.0]; $ink=[0.08,0.09,0.08]; $muted=[0.37,0.40,0.38]; $line=[0.88,0.89,0.87];
        // Full dark canvas and compact adventure header.
        bta_pdf_rect($c,0,0,595,842,$dark);
        bta_pdf_rect($c,28,814,539,9,$orange);
        bta_pdf_text($c,49,790,'BALI TARANTULA',15,false,[1,1,1]);
        bta_pdf_text($c,49,773,'A D V E N T U R E',7,false,$orange);
        bta_pdf_text($c,49,731,'E-TICKET',28,false,[1,1,1]);
        bta_pdf_rect($c,49,704,145,20,$orange);
        bta_pdf_text($c,58,711,'BOOKING CONFIRMATION',7.5,false,[1,1,1]);
        bta_pdf_text($c,49,686,'Please present this e-ticket at the registration desk upon arrival.',7.2,false,[0.91,0.93,0.91]);

        // Main white ticket with left booking information and right QR stub.
        bta_pdf_rect($c,28,205,539,465,[1,1,1]);
        bta_pdf_rect($c,28,205,539,465,[0.88,0.89,0.87],false,0.8);

        // Booking reference and confirmation status.
        bta_pdf_text($c,49,642,'BOOKING CODE',8,false,$orange);
        bta_pdf_rect($c,49,604,250,29,[1.0,0.94,0.89]);
        bta_pdf_text($c,59,614,$code,13,false,$ink);
        $statusColor = $payment === 'PAID' ? [0.86,0.97,0.88] : [0.90,0.97,0.91];
        $statusInk = [0.08,0.43,0.19];
        bta_pdf_rect($c,307,604,78,29,$statusColor);
        bta_pdf_center($c,346,615,74,$status,7.2,false,$statusInk);

        // Embed the actual package cover image in the left thumbnail.
        $cover = bta_pdf_fetch_cover_jpeg((string)($b['cover_image'] ?? ''));
        if ($cover) {
            $c[] = 'q 112 0 0 55 49 525 cm /Im2 Do Q';
        } else {
            bta_pdf_rect($c,49,525,112,55,[0.93,0.95,0.93]);
            bta_pdf_center($c,105,550,100,'IMAGE UNAVAILABLE',6.2,false,$muted);
        }
        $packageLines = array_slice(bta_pdf_lines($package,31),0,2);
        foreach($packageLines as $i=>$lineText) bta_pdf_text($c,174,568-($i*13),$lineText,11,false,$ink);
        $descLines = array_slice(bta_pdf_lines((string)($b['short_description'] ?? 'Experience the thrill of Bali adventure trails.'),58),0,3);
        foreach($descLines as $i=>$lineText) bta_pdf_text($c,174,548-($i*9),$lineText,6.6,false,$muted);

        // Details in the same order and wording as the supplied reference PDF.
        $rows = [
            ['Activity Date',$date],
            ['Activity Time',$activityTime],
            ['Participants',$guests],
            ['Pickup Service',$pickupYes?'Yes':'No'],
            ['Pickup Location',$pickupYes?($pickupLocation?:$pickupArea?:'Hotel / Villa'):'Self Arrival'],
            ['Hotel or Villa Name & Room Number',$pickupYes?trim($pickupPlace.((trim((string)($b['room_number'] ?? '')) !== '') ? ' / Room '.trim((string)$b['room_number']) : '')):'-'],
            ['Pickup Time',$pickupYes?$pickupTime:'-'],
            ['Payment Status',$payment],
            ['Total Payment',$total]
        ];
        $y=496;
        foreach($rows as [$label,$value]) {
            $labelLines = array_slice(bta_pdf_lines((string)$label,25),0,2);
            $valueLines = array_slice(bta_pdf_lines((string)$value,34),0,2);
            $lineCount=max(count($labelLines),count($valueLines));
            // Put every text baseline inside a fixed-height row, never on its separator.
            $textTop=$y-5;
            foreach($labelLines as $i=>$txt) bta_pdf_text($c,49,$textTop-($i*8.5),$txt,6.6,false,$ink);
            bta_pdf_text($c,190,$textTop,':',6.6,false,$muted);
            foreach($valueLines as $i=>$txt) bta_pdf_text($c,205,$textTop-($i*8.5),$txt,6.8,false,((string)$label==='Total Payment'?$orange:$ink));
            // Two-line labels (e.g. Hotel or Villa...) get a taller row.
            $rowHeight = $lineCount > 1 ? 31 : 27;
            // No horizontal separator through or between detail text rows.
            $y -= $rowHeight;
        }

        // QR ticket stub.
        bta_pdf_rect($c,414,615,137,29,$orange);
        bta_pdf_center($c,482,625,130,'E-TICKET',14,false,[1,1,1]);
        $qr=null;
        if(!bta_pdf_local_qr($c,431,475,104,$code,$qr)) {
            bta_pdf_rect($c,431,475,104,104,[0.96,0.96,0.96]);
            bta_pdf_center($c,483,527,96,'QR UNAVAILABLE',7,false,$muted);
        }
        bta_pdf_center($c,482,461,130,$code,7.2,false,$ink);
        bta_pdf_center($c,482,415,130,'BALI TARANTULA',10,false,$ink);
        bta_pdf_center($c,482,402,130,'ADVENTURE',7.2,false,$orange);
        bta_pdf_center($c,482,377,130,$siteAddress,6.5,false,$muted);

        // Important notes in a two-column panel.
        bta_pdf_rect($c,28,91,539,99,[1.0,0.96,0.92]);
        bta_pdf_text($c,48,168,'IMPORTANT NOTES',12,false,$orange);
        $notes = [
            [48,143,'Bring this e-ticket (digital or printed) to the registration counter.'],
            [310,143,'Arrive at least 15 minutes before the activity time.'],
            [48,119,'Wear comfortable clothes and closed shoes.'],
            [310,119,'Follow the safety instructions from your guide.']
        ];
        foreach($notes as [$x,$yNote,$note]) {
            bta_pdf_text($c,$x,$yNote,'+',8,false,$orange);
            bta_pdf_multiline($c,$x+12,$yNote,225,$note,6.2,false,$ink,8);
        }

        // Compact footer matching the sample.
        bta_pdf_rect($c,28,28,539,51,[0.025,0.035,0.03]);
        bta_pdf_text($c,49,59,'BALI TARANTULA',9,false,[1,1,1]);
        bta_pdf_text($c,49,45,'ADVENTURE',6.5,false,$orange);
        bta_pdf_text($c,421,62,$sitePhone?:$siteEmail?:'Bali, Indonesia',6.4,false,[0.9,0.92,0.9]);
        bta_pdf_text($c,421,49,$siteEmail,6.4,false,[0.9,0.92,0.9]);
        bta_pdf_text($c,421,36,'www.balitarantula.com',6.4,false,[0.9,0.92,0.9]);

        $content=implode("\n",$c)."\n";
        $objects=[];
        $objects[]='<< /Type /Catalog /Pages 2 0 R >>';
        $objects[]='<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $xobjects=[];
        if ($qr) $xobjects[]='/Im1 7 0 R';
        if ($cover) $xobjects[]='/Im2 '.($qr?8:7).' 0 R';
        $xobject=$xobjects?' /XObject << '.implode(' ',$xobjects).' >>':'';
        $objects[]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >>'.$xobject.' >> /Contents 4 0 R >>';
        $objects[]="<< /Length ".strlen($content)." >>\nstream\n".$content."endstream";
        $objects[]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        if($qr) $objects[]='<< /Type /XObject /Subtype /Image /Width '.(int)$qr['width'].' /Height '.(int)$qr['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($qr['bytes'])." >>\nstream\n".$qr['bytes']."\nendstream";
        if($cover) $objects[]='<< /Type /XObject /Subtype /Image /Width '.(int)$cover['width'].' /Height '.(int)$cover['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($cover['bytes'])." >>\nstream\n".$cover['bytes']."\nendstream";
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets=[0];
        foreach($objects as $i=>$obj){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$obj."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=count($objects);$i++) $pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
        $pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
        return $pdf;
    }
}
if (!function_exists('bta_make_eticket_pdf')) {
    /**
     * Build the e-ticket with the built-in lightweight PDF renderer.
     * No Composer PDF library is required; the generated file is temporary
     * and is removed by booking.php after email delivery.
     */
    function bta_make_eticket_pdf(array $booking): ?string {
        $siteName = trim((string)site_brand_name());
        $siteEmail = trim((string)setting('site_email',''));
        $sitePhone = trim((string)setting('site_whatsapp',setting('site_phone','')));
        $siteAddress = trim((string)setting('site_address','Bali, Indonesia'));
        $pdf = bta_pdf_build($booking, $siteName, $siteEmail, $sitePhone, $siteAddress);
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $code = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($booking['booking_code'] ?? uniqid('booking_', true)));
        $path = $dir . DIRECTORY_SEPARATOR . $code . '-E-Ticket.pdf';
        if (@file_put_contents($path, $pdf) === false) {
            error_log('BTA e-ticket PDF: Failed writing temporary PDF for ' . $code);
            return null;
        }
        return $path;
    }
}
