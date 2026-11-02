<?php
if (!function_exists('bta_booking_email_html')) {
    function bta_booking_email_html(array $b, string $confirmUrl, string $emailTitle = 'Booking Received', string $intro = '', string $audience = 'customer', string $ticketUrl = ''): string {
        $siteName = trim(eticket_text('eticket_ticket_brand', (string)site_brand_name())) ?: 'Bali Tarantula Adventure';
        $siteEmail = trim((string)setting('site_email',''));
        $sitePhone = trim((string)setting('site_whatsapp',setting('site_phone','')));
        $siteAddress = trim((string)setting('site_address','Bali, Indonesia'));
        $siteUrl = rtrim((string)setting('site_url',BASE_URL),'/');
        $code = (string)($b['booking_code'] ?? '-');
        $package = (string)($b['package_name'] ?? '-');
        $dateRaw = (string)($b['booking_date'] ?? '');
        $date = $dateRaw !== '' ? date('D, d M Y',strtotime($dateRaw)) : '-';
        $activityTime = trim((string)($b['activity_time'] ?? ''));
        $activityTime = $activityTime !== '' ? date('h:i A',strtotime($activityTime)) : '-';
        $pickupTime = trim((string)($b['pickup_time'] ?? ''));
        $pickupTime = $pickupTime !== '' ? date('h:i A',strtotime($pickupTime)) : '-';
        $isTandem = (($b['booking_mode'] ?? 'person') === 'tandem');
        $guestLabel = $isTandem ? '2 Persons (1 Tandem Unit)' : ((int)($b['adults'] ?? 0).' Adults, '.(int)($b['children'] ?? 0).' Children');
        $pickupYes = !empty($b['pickup_required']);
        $hotel = trim((string)($b['hotel_name'] ?? ''));
        $room = trim((string)($b['room_number'] ?? ''));
        $payment = (string)($b['payment_method'] ?? '-');
        $paymentStatus = ucfirst((string)($b['payment_status'] ?? 'pending'));
        $bookingStatus = strtoupper((string)($b['booking_status'] ?? 'confirmed'));
        if ($bookingStatus === 'NEW') $bookingStatus = 'RECEIVED';
        $total = (float)($b['total'] ?? 0);
        $short = trim((string)($b['short_description'] ?? ''));
        $cover = trim((string)($b['cover_image'] ?? ''));
        $coverUrl = '';
        if ($cover) {
            if (preg_match('~^https?://~i',$cover)) $coverUrl = $cover;
            elseif (function_exists('asset_url')) $coverUrl = asset_url($cover);
            else $coverUrl = $siteUrl.'/'.ltrim($cover,'/');
        }
        $isAdmin = strtolower(trim($audience)) === 'admin';
        $intro = trim($intro) ?: ($isAdmin ? 'A new adventure booking has been received. Please review the reservation details below.' : 'Thank you for choosing Bali Tarantula Adventure. Your reservation has been received.');
        if (!$isAdmin && function_exists('eticket_text')) $intro = eticket_text('eticket_email_message',$intro) ?: $intro;
        $greeting = $isAdmin ? 'Hello Reservation Team,' : 'Hello '.e((string)($b['customer_name'] ?? 'Guest')).',';
        $money = static function($n){ return 'IDR '.number_format((float)$n,0,',','.'); };
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data='.rawurlencode($code);
        $row = static function($label,$value,$highlight=false) {
            return '<tr><td style="padding:9px 8px;border-bottom:1px solid #ecefec;color:#5f6962;font-size:13px;width:42%">'.e($label).'</td><td style="padding:9px 8px;border-bottom:1px solid #ecefec;color:'.($highlight?'#f06400':'#202722').';font-size:13px;text-align:right">'.e((string)$value).'</td></tr>';
        };
        // The Website Logo setting is populated by the Branding media picker/upload.
        // Use that exact selected asset; do not substitute the Admin Logo or a text-made logo.
        $logoPath = trim((string)setting('site_logo',''));
        $logoUrl = '';
        if ($logoPath !== '') {
            $logoUrl = function_exists('asset_url') ? asset_url($logoPath) : $siteUrl.'/'.ltrim($logoPath,'/');
            if (!preg_match('~^https?://~i', $logoUrl)) $logoUrl = $siteUrl.'/'.ltrim($logoUrl,'/');
        }
        $logo = $logoUrl !== ''
            ? '<img src="'.e($logoUrl).'" width="240" alt="'.e($siteName).'" style="display:block;width:240px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;margin:0 auto;background:transparent">'
            : '<span style="font-family:Arial,Helvetica,sans-serif;font-size:20px;color:#202722">'.e($siteName).'</span>';
        $coverBlock = $coverUrl ? '<img src="'.e($coverUrl).'" width="170" alt="" style="display:block;width:170px;height:112px;object-fit:cover;border-radius:9px;border:0">' : '';
        $hotelRoom = trim($hotel.($room!==''?' / Room '.$room:''));
        $pickupRows = $pickupYes
            ? $row('Hotel / Villa',$hotel ?: 'Not provided').$row('Room Number',$room ?: 'Not provided').$row('Pickup Time',$pickupTime)
            : $row('Pickup Service','No — Self arrival');
        $customerRows = $isAdmin
            ? '<tr><td colspan="2" style="padding:16px 8px 5px;font-size:15px;color:#202722">Customer Information</td></tr>'
              .$row('Customer Name',$b['customer_name'] ?? '-')
              .$row('Email',$b['customer_email'] ?? '-')
              .$row('WhatsApp',$b['customer_whatsapp'] ?? '-')
              .$row('Country',$b['customer_country'] ?? '-')
              .$row('Special Request',$b['special_request'] ?? '-')
            : '';
        $ticketUrl = trim($ticketUrl) !== '' ? $ticketUrl : $confirmUrl;
        $ctaLabel = $isAdmin ? 'Open / Print E-Ticket' : 'View / Print E-Ticket';
        $cta = '<tr><td align="center" style="padding:18px 26px 8px"><a href="'.e($ticketUrl).'" style="display:inline-block;background:#ff6500;color:#fff;text-decoration:none;padding:14px 25px;border-radius:7px;font-size:14px">'.$ctaLabel.'</a></td></tr>';
        $ticketText = $isAdmin ? 'Open the guest e-ticket from the button above to print or save it as PDF.' : 'Open your e-ticket from the button above. You can print it or save it as PDF.';
        $qrBlock = '<img src="'.e($qrUrl).'" width="112" height="112" alt="Booking QR code" style="display:block;width:112px;height:112px;border:0">';
        return '<!doctype html><html><body style="margin:0;padding:0;background:#f2f3f1;font-family:Arial,Helvetica,sans-serif;color:#202722">'
        .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f2f3f1;padding:22px 8px"><tr><td align="center">'
        .'<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#fff;border:1px solid #e5e8e5;border-radius:10px;overflow:hidden">'
        .'<tr><td align="center" style="padding:22px 22px 18px">'.$logo.'<div style="margin-top:13px;font-size:20px;line-height:1.3;color:#202722">✓ &nbsp;'.e($emailTitle).'</div><div style="font-size:13px;line-height:1.6;color:#68716b;margin-top:7px">'.e($intro).'</div></td></tr>'
        .'<tr><td style="padding:0 25px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="background:#ff6500;color:#fff;padding:10px;font-size:18px;letter-spacing:5px">E-TICKET</td></tr></table></td></tr>'
        .'<tr><td style="padding:15px 22px 12px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td valign="middle"><div style="font-size:12px;color:#5f6962;margin-bottom:6px">Booking Code</div><div style="font-size:22px;line-height:1.3;color:#171a18;word-break:break-word">'.e($code).'</div></td><td width="125" align="right" valign="middle">'.$qrBlock.'</td></tr></table></td></tr>'
        .'<tr><td style="padding:12px 22px;background:#fff7f0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'.($coverBlock?'<td width="180" valign="middle">'.$coverBlock.'</td>':'').'<td valign="middle" style="padding:5px 10px"><div style="font-size:19px;color:#202722">'.e($package).'</div><div style="font-size:12px;line-height:1.55;color:#626c65;margin-top:5px">'.e($short).'</div></td></tr></table></td></tr>'
        .'<tr><td style="padding:12px 22px 3px"><div style="font-size:15px;color:#202722;padding:8px 0">Booking Details</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
        .$row('Activity Date',$date).$row('Activity Time',$activityTime).$row('Participants',$guestLabel).$row('Pickup Service',$pickupYes?'Yes':'No')
        .($pickupYes?$row('Hotel or Villa Name',$hotel ?: 'Not provided').$row('Room Number',$room ?: 'Not provided').$row('Pickup Time',$pickupTime):'')
        .$row('Payment Method',$payment).$row('Payment Status',$paymentStatus).$row('Total Payment',$money($total),true)
        .$customerRows
        .'</table></td></tr>'
        .$cta
        .'<tr><td style="padding:14px 22px 18px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="background:#fff5eb;border:1px solid #ffe1c9;border-radius:8px;padding:13px 15px"><div style="font-size:13px;color:#e85f00">Important Notes</div><div style="font-size:12px;line-height:1.7;color:#515b54;margin-top:6px">• Please present this e-ticket (digital or printed) at the registration desk.<br>• Arrive at least 15 minutes before the activity time.<br>• Wear comfortable clothes and closed shoes.<br>• Follow the safety instructions from your guide.</div><div style="font-size:12px;color:#5f6962;margin-top:10px">'.e($ticketText).'</div></td></tr></table></td></tr>'
        .'<tr><td style="padding:15px 22px;background:#151a17;color:#fff"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="font-size:12px;line-height:1.8;color:#f0f1ef">'.e($siteName).'<br>'.e($sitePhone).'<br>'.e($siteEmail).'<br>'.e($siteAddress).'<br>www.balitarantula.com</td></tr></table></td></tr>'
        .'</table></td></tr></table></body></html>';
    }
}
