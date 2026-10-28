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
        $font = $bold ? '/F2' : '/F1';
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
        $siteName = trim(eticket_text('eticket_ticket_brand', trim($siteName) ?: setting('site_name','Manuaba Crystalfall'))) ?: (trim($siteName) ?: setting('site_name','Manuaba Crystalfall'));
        $siteEmail = trim($siteEmail);
        $sitePhone = trim($sitePhone);
        $siteAddress = trim($siteAddress) ?: 'Bali, Indonesia';
        $bookingCode = (string)($b['booking_code'] ?? '-');
        $invoiceNumber = trim((string)($b['invoice_number'] ?? ''));
        $package = (string)($b['package_name'] ?? 'Bali Adventure');
        $short = (string)($b['short_description'] ?? 'An unforgettable experience in Bali.');
        $guestCount = max(1, (int)($b['adults'] ?? 0) + (int)($b['children'] ?? 0));
        $payment = (string)($b['payment_method'] ?? '-');
        $paymentStatus = strtoupper((string)($b['payment_status'] ?? 'pending'));
        $status = ucfirst((string)($b['booking_status'] ?? 'received'));
        $total = (float)($b['total'] ?? 0);
        $pricePerPerson = (float)($b['price_adult'] ?? $b['activity_price'] ?? 0);
        $discount = (float)($b['discount_amount'] ?? 0);
        $pickup = (float)($b['pickup_price'] ?? 0);

        $c = [];
        // Background + header.
        bta_pdf_rect($c, 0, 0, 595, 842, [1,1,1]);
        bta_pdf_text($c, 34, 802, strtoupper($siteName), 17, true, [0.09,0.39,0.30]);
        bta_pdf_text($c, 34, 787, eticket_text('eticket_print_title','E-TICKET / BOOKING VOUCHER'), 7.5, true, [0.34,0.45,0.41]);
        bta_pdf_text($c, 445, 805, eticket_text('eticket_qr_help','BOOKING ID'), 7.0, false, [0.42,0.50,0.47]);
        bta_pdf_text($c, 445, 792, $bookingCode, 8.2, true, [0.12,0.26,0.21]);
        bta_pdf_text($c, 445, 776, 'BOOKING STATUS', 6.5, true, [0.22,0.35,0.30]);
        bta_pdf_text($c, 510, 776, strtoupper($status === 'New' ? 'RECEIVED' : $status), 7.2, true, [0.09,0.39,0.30]);
        bta_pdf_text($c, 445, 763, 'PAYMENT STATUS', 6.5, true, [0.22,0.35,0.30]);
        bta_pdf_text($c, 510, 763, $paymentStatus, 7.2, true, [0.09,0.39,0.30]);
        bta_pdf_line($c, 34, 748, 561, 748, [0.82,0.87,0.84], 0.8);

        // Hero.
        bta_pdf_rect($c, 34, 657, 527, 102, [0.07,0.35,0.25]);
        bta_pdf_rect($c, 34, 657, 527, 26, [0.06,0.31,0.22]);
        bta_pdf_text($c, 54, 726, eticket_text('eticket_print_eyebrow','Explore the real Bali'), 7.5, true, [0.75,0.92,0.46]);
        bta_pdf_multiline($c, 54, 696, 470, $package, 23, true, [1,1,1], 25);
        bta_pdf_multiline($c, 54, 670, 465, $short, 8.2, false, [0.94,0.98,0.96], 11);

        $colY = 426; $colH = 212; $gap = 0; $colW = 175.6667;
        $xs = [34, 209.6667, 385.3334];
        // Three columns.
        for ($i=0;$i<3;$i++) bta_pdf_rect($c, $xs[$i], $colY, $colW, $colH, [0.84,0.88,0.86], false, 0.7);
        bta_pdf_line($c, 209.6667, $colY, 209.6667, $colY+$colH, [0.84,0.88,0.86], 0.7);
        bta_pdf_line($c, 385.3334, $colY, 385.3334, $colY+$colH, [0.84,0.88,0.86], 0.7);

        // Booking details column.
        bta_pdf_text($c, 47, 616, eticket_text('eticket_booking_details_title','BOOKING DETAILS'), 10, true, [0.09,0.39,0.30]);
        $y=588;
        $fields = [
            ['Activity',$package,true], ['Booking ID',$bookingCode,false], ['Invoice',$invoiceNumber !== '' ? $invoiceNumber : '-',false], ['Date',bta_pdf_date($b['booking_date'] ?? ''),false],
            ['Activity Time',bta_pdf_time($b['activity_time'] ?? ''),false], ['Pickup Time',bta_pdf_time($b['pickup_time'] ?? ''),false], ['Guests',$guestCount.' '.($guestCount===1?'Person':'People'),false],
            ['Payment',$payment,false]
        ];
        foreach($fields as [$label,$value,$bold]){
            bta_pdf_text($c, 47, $y, $label, 7.1, true, [0.21,0.36,0.30]);
            bta_pdf_multiline($c, 47, $y-11, 145, (string)$value, 7.8, $bold, [0.32,0.42,0.38], 9);
            $y -= 23;
        }

        // Booking code / code graphic.
        bta_pdf_text($c, 222, 616, 'BOOKING CODE', 10, true, [0.09,0.39,0.30]);
        $qrImage = null;
        if (!bta_pdf_local_qr($c, 245, 500, 105, $bookingCode, $qrImage)) {
            bta_pdf_rect($c, 245, 500, 105, 105, [0.97,0.98,0.97]);
            bta_pdf_center($c, 297, 548, 125, 'QR unavailable', 7.2, false, [0.40,0.45,0.42]);
            bta_pdf_center($c, 297, 536, 125, 'Install QR dependency', 7.2, false, [0.40,0.45,0.42]);
        }
        bta_pdf_center($c, 297, 470, 150, $bookingCode, 8.2, true, [0.14,0.28,0.23]);
        bta_pdf_center($c, 297, 455, 150, 'Keep this code for check-in.', 7.2, false, [0.43,0.51,0.48]);
        if ($invoiceNumber !== '') bta_pdf_center($c, 297, 444, 150, 'Invoice: '.$invoiceNumber, 6.5, false, [0.34,0.45,0.41]);
        bta_pdf_rect($c, 225, 438, 145, 1, [0.89,0.93,0.91]);
        bta_pdf_center($c, 297, 430, 140, eticket_text('eticket_info_2','Show this e-ticket at the entrance.'), 7.1, false, [0.32,0.42,0.38]);

        // Guest information.
        bta_pdf_text($c, 399, 616, 'GUEST INFORMATION', 10, true, [0.09,0.39,0.30]);
        $gy=588;
        $guestFields = [
            ['Full Name',$b['customer_name'] ?? '-'], ['Country',$b['customer_country'] ?? '-'], ['Phone / WhatsApp',$b['customer_whatsapp'] ?? '-'],
            ['Email',$b['customer_email'] ?? '-'], ['Special Request',$b['special_request'] ?? '-']
        ];
        foreach($guestFields as [$label,$value]){
            bta_pdf_text($c, 399, $gy, $label, 7.1, true, [0.21,0.36,0.30]);
            bta_pdf_multiline($c, 399, $gy-11, 140, (string)$value, 7.7, false, [0.32,0.42,0.38], 9);
            $gy -= 33;
        }

        // Total bar.
        bta_pdf_rect($c, 34, 382, 527, 31, [0.92,0.97,0.93]);
        bta_pdf_text($c, 47, 400, 'PRICE PER PERSON', 6.9, true, [0.22,0.35,0.30]);
        bta_pdf_text($c, 47, 388, bta_pdf_money($pricePerPerson), 8.0, false, [0.29,0.40,0.36]);
        bta_pdf_line($c, 210, 386, 210, 409, [0.82,0.89,0.84], 0.7);
        bta_pdf_text($c, 224, 400, 'NUMBER OF GUESTS', 6.9, true, [0.22,0.35,0.30]);
        bta_pdf_text($c, 224, 388, (string)$guestCount, 8.0, false, [0.29,0.40,0.36]);
        bta_pdf_line($c, 368, 386, 368, 409, [0.82,0.89,0.84], 0.7);
        bta_pdf_text($c, 382, 400, 'TOTAL PRICE', 6.9, true, [0.22,0.35,0.30]);
        bta_pdf_text($c, 382, 387, bta_pdf_money($total), 12.4, true, [0.09,0.39,0.30]);

        // Lower row.
        bta_pdf_rect($c, 34, 274, 527, 96, [0.84,0.88,0.86], false, 0.7);
        bta_pdf_line($c, 209.6667, 274, 209.6667, 370, [0.84,0.88,0.86], 0.7);
        bta_pdf_line($c, 385.3334, 274, 385.3334, 370, [0.84,0.88,0.86], 0.7);
        bta_pdf_text($c, 47, 349, eticket_text('eticket_print_activity_location_title','ACTIVITY LOCATION'), 9.5, true, [0.09,0.39,0.30]);
        bta_pdf_multiline($c, 47, 329, 145, $package, 7.8, true, [0.25,0.36,0.32], 10);
        bta_pdf_multiline($c, 47, 311, 145, $siteAddress, 7.5, false, [0.34,0.43,0.39], 10);

        bta_pdf_text($c, 222, 349, eticket_text('eticket_print_important_title','IMPORTANT INFORMATION'), 9.5, true, [0.09,0.39,0.30]);
        $info = [
            eticket_text('eticket_info_1','Arrive 15 minutes before your scheduled time.'),
            eticket_text('eticket_info_2','Show this e-ticket at the entrance.'),
            eticket_text('eticket_info_3','This ticket is non-transferable unless approved by our team.'),
            eticket_text('eticket_info_4','Bring a valid ID if required.')
        ];
        $iy=328;
        foreach($info as $item){
            bta_pdf_text($c, 222, $iy, '-', 8, true, [0.19,0.35,0.29]);
            bta_pdf_multiline($c, 234, $iy, 136, $item, 7.2, false, [0.32,0.42,0.38], 9);
            $iy -= 16;
        }

        bta_pdf_text($c, 399, 349, eticket_text('eticket_print_help_title','NEED HELP?'), 9.5, true, [0.09,0.39,0.30]);
        bta_pdf_text($c, 399, 326, 'WhatsApp / Phone', 7.1, true, [0.21,0.36,0.30]);
        bta_pdf_multiline($c, 399, 314, 140, $sitePhone ?: '-', 7.6, false, [0.32,0.42,0.38], 9);
        bta_pdf_text($c, 399, 291, 'Email', 7.1, true, [0.21,0.36,0.30]);
        bta_pdf_multiline($c, 399, 279, 140, $siteEmail ?: '-', 7.2, false, [0.32,0.42,0.38], 9);

        // Footer / thank you.
        bta_pdf_rect($c, 34, 231, 527, 29, [0.92,0.97,0.93]);
        bta_pdf_text($c, 47, 247, eticket_text('eticket_footer_title','THANK YOU FOR CHOOSING {{site_name_upper}}!'), 7.4, true, [0.20,0.34,0.29]);
        bta_pdf_text($c, 47, 237, eticket_text('eticket_footer_subtitle','We look forward to welcoming you!'), 7.0, false, [0.38,0.47,0.43]);
        bta_pdf_text($c, 437, 244, str_replace('•','-',eticket_text('eticket_footer_right','NATURE - ADVENTURE - HAPPINESS')), 6.8, true, [0.34,0.45,0.41]);
        bta_pdf_text($c, 437, 235, eticket_text('eticket_ticket_title','Your E-Ticket'), 6.1, false, [0.45,0.53,0.49]);

        // Tiny footer note.
        bta_pdf_text($c, 34, 211, 'Booking: '.strtoupper($status === 'New' ? 'RECEIVED' : $status).' - Payment: '.$paymentStatus, 6.5, false, [0.48,0.55,0.52]);
        if ($pickup > 0) bta_pdf_text($c, 220, 211, 'Hotel transport: '.bta_pdf_money($pickup), 6.5, false, [0.48,0.55,0.52]);
        if ($discount > 0) bta_pdf_text($c, 400, 211, 'Discount: -'.bta_pdf_money($discount), 6.5, false, [0.48,0.55,0.52]);

        $content = implode("\n", $c)."\n";
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $xobject = $qrImage ? ' /XObject << /Im1 7 0 R >>' : '';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >>'.$xobject.' >> /Contents 4 0 R >>';
        $objects[] = "<< /Length ".strlen($content)." >>\nstream\n".$content."endstream";
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        if ($qrImage) {
            $objects[] = '<< /Type /XObject /Subtype /Image /Width '.(int)$qrImage['width'].' /Height '.(int)$qrImage['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($qrImage['bytes'])." >>\nstream\n".$qrImage['bytes']."\nendstream";
        }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i+1)." 0 obj\n".$obj."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects)+1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i=1; $i<=count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $pdf .= "trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
        return $pdf;
    }
}

if (!function_exists('bta_make_eticket_pdf')) {
    function bta_make_eticket_pdf(array $booking): ?string {
        $siteName = trim((string)site_brand_name());
        $siteEmail = trim((string)setting('site_email',''));
        $sitePhone = trim((string)setting('site_whatsapp',setting('site_phone','')));
        $siteAddress = trim((string)setting('site_address','Bali, Indonesia'));
        $pdf = bta_pdf_build($booking, $siteName, $siteEmail, $sitePhone, $siteAddress);
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $code = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($booking['booking_code'] ?? uniqid('booking_', true)));
        $path = $dir.DIRECTORY_SEPARATOR.$code.'-E-Ticket.pdf';
        if (@file_put_contents($path, $pdf) === false) return null;
        return $path;
    }
}
