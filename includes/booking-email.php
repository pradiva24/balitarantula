<?php
if (!function_exists('bta_booking_email_html')) {
    function bta_booking_email_html(array $b, string $confirmUrl, string $emailTitle = 'Booking Received', string $intro = '', string $audience = 'customer'): string {
        $siteName = trim(eticket_text('eticket_ticket_brand', (string)site_brand_name())) ?: site_brand_name();
        $siteEmail = trim((string)setting('site_email',''));
        $sitePhone = trim((string)setting('site_whatsapp',setting('site_phone','')));
        $siteAddress = trim((string)setting('site_address','Bali, Indonesia'));
        $code = (string)($b['booking_code'] ?? '-');
        $invoice = (string)($b['invoice_number'] ?? '-');
        $package = (string)($b['package_name'] ?? '-');
        $date = (string)($b['booking_date'] ?? '-');
        $time = trim((string)($b['pickup_time'] ?? ''));
        $time = $time !== '' ? substr($time,0,5) : '-';
        $guests = max(1,(int)($b['adults'] ?? 0)+(int)($b['children'] ?? 0));
        $guestLabel = $guests.' '.($guests===1?'Guest':'Guests');
        $payment = (string)($b['payment_method'] ?? '-');
        $paymentStatus = ucfirst((string)($b['payment_status'] ?? 'pending'));
        $subtotal = (float)($b['subtotal'] ?? 0);
        $discount = (float)($b['discount_amount'] ?? 0);
        $total = (float)($b['total'] ?? 0);
        $short = trim((string)($b['short_description'] ?? ''));
        $cover = trim((string)($b['cover_image'] ?? ''));
        $coverUrl = '';
        if ($cover) {
            if (preg_match('~^https?://~i',$cover)) $coverUrl = $cover;
            elseif (function_exists('asset_url')) $coverUrl = asset_url($cover);
            else $coverUrl = rtrim(BASE_URL,'/').'/'.ltrim($cover,'/');
        }
        $isAdmin = strtolower(trim($audience)) === 'admin';
        $intro = trim($intro) ?: ($isAdmin ? 'A new booking has just been received. Please review the booking details below and follow up with the guest as needed.' : 'Thank you for choosing us. Your booking has been received and your e-ticket is attached to this email.');
        if (!$isAdmin && function_exists('eticket_text')) { $intro = eticket_text('eticket_email_message', $intro) ?: $intro; }
        $greeting = $isAdmin ? 'Hello Reservation Team,' : 'Hello <strong>'.e((string)($b['customer_name'] ?? 'Guest')).'</strong>,';
        $money = static function($n){ return 'IDR '.number_format((float)$n,0,',','.'); };
        $statusColor = strtolower($paymentStatus)==='paid' ? '#17633f' : '#9a7a1d';

        $img = $coverUrl !== '' ? '<img src="'.e($coverUrl).'" alt="" width="100%" style="display:block;width:100%;height:160px;object-fit:cover;max-width:680px;border:0">' : '';
        $discountRow = $discount > 0 ? '<tr><td style="padding:7px 0;color:#6b7b73">Discount</td><td align="right" style="padding:7px 0;color:#31584a">-'.$money($discount).'</td></tr>' : '';
        return '<!doctype html><html><body style="margin:0;padding:0;background:#f3f7f4;font-family:Arial,Helvetica,sans-serif;color:#1e382e">'
        .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f7f4;padding:28px 12px"><tr><td align="center">'
        .'<table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:680px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(19,54,38,.08)">'
        .'<tr><td style="background:#17633f;padding:24px 28px">'
        .'<div style="font-size:12px;letter-spacing:1.4px;font-weight:700;color:#bce97d;text-transform:uppercase">'.e($siteName).'</div>'
        .'<div style="font-size:28px;line-height:1.15;font-weight:800;color:#fff;margin-top:6px">'.e($emailTitle).'</div>'
        .'<div style="font-size:12px;color:#dff0e8;margin-top:8px">Booking ID: <strong>'.e($code).'</strong></div>'
        .'</td></tr>'
        .($img ? '<tr><td>'.$img.'</td></tr>' : '')
        .'<tr><td style="padding:26px 28px 8px">'
        .'<p style="font-size:15px;line-height:1.65;margin:0 0 8px">'.$greeting.'</p>'
        .'<p style="font-size:13px;line-height:1.7;color:#587067;margin:0">'.e($intro).'</p>'
        .'</td></tr>'
        .'<tr><td style="padding:14px 28px 4px"><div style="font-size:17px;font-weight:800;color:#183e31">'.e($package).'</div>'
        .($short!==''?'<div style="font-size:12px;color:#72817b;line-height:1.6;margin-top:4px">'.e($short).'</div>':'')
        .'</td></tr>'
        .'<tr><td style="padding:12px 28px">'
        .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
        .'<td width="50%" valign="top" style="padding:14px;background:#f7faf8;border:1px solid #e4ece7;border-radius:10px"><div style="font-size:10px;color:#7a8b84;text-transform:uppercase;letter-spacing:.5px;font-weight:700">Activity Date</div><div style="font-size:14px;font-weight:800;margin-top:5px;color:#254a3c">'.e($date).'</div></td>'
        .'<td width="10"></td>'
        .'<td width="50%" valign="top" style="padding:14px;background:#f7faf8;border:1px solid #e4ece7;border-radius:10px"><div style="font-size:10px;color:#7a8b84;text-transform:uppercase;letter-spacing:.5px;font-weight:700">Guests</div><div style="font-size:14px;font-weight:800;margin-top:5px;color:#254a3c">'.e($guestLabel).'</div></td>'
        .'</tr></table>'
        .'</td></tr>'
        .($isAdmin ? '<tr><td style="padding:4px 28px 12px"><div style="font-size:17px;font-weight:800;color:#183e31">Customer Information</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:8px"><tr><td style="padding:7px 0;color:#6b7b73">Name</td><td align="right" style="padding:7px 0;font-weight:700;color:#26483c">'.e((string)($b['customer_name'] ?? '-')).'</td></tr><tr><td style="padding:7px 0;color:#6b7b73">Email</td><td align="right" style="padding:7px 0;color:#31584a">'.e((string)($b['customer_email'] ?? '-')).'</td></tr><tr><td style="padding:7px 0;color:#6b7b73">WhatsApp</td><td align="right" style="padding:7px 0;color:#31584a">'.e((string)($b['customer_whatsapp'] ?? '-')).'</td></tr><tr><td style="padding:7px 0;color:#6b7b73">Country</td><td align="right" style="padding:7px 0;color:#31584a">'.e((string)($b['customer_country'] ?? '-')).'</td></tr></table></td></tr>' : '')
        .'<tr><td style="padding:4px 28px 14px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
        .'<tr><td style="padding:7px 0;color:#6b7b73">Booking ID</td><td align="right" style="padding:7px 0;font-weight:700;color:#26483c">'.e($code).'</td></tr>'
        .'<tr><td style="padding:7px 0;color:#6b7b73">Invoice</td><td align="right" style="padding:7px 0;color:#31584a">'.e($invoice).'</td></tr>'
        .'<tr><td style="padding:7px 0;color:#6b7b73">Time</td><td align="right" style="padding:7px 0;color:#31584a">'.e($time).'</td></tr>'
        .'<tr><td style="padding:7px 0;color:#6b7b73">Payment method</td><td align="right" style="padding:7px 0;color:#31584a">'.e($payment).'</td></tr>'
        .'<tr><td style="padding:7px 0;color:#6b7b73">Payment status</td><td align="right" style="padding:7px 0;font-weight:800;color:'.$statusColor.'">'.e($paymentStatus).'</td></tr>'
        .'<tr><td style="padding:7px 0;color:#6b7b73">Subtotal</td><td align="right" style="padding:7px 0;color:#31584a">'.$money($subtotal).'</td></tr>'
        .$discountRow
        .'<tr><td style="border-top:1px solid #e0e8e3;padding:13px 0 7px;font-weight:800;color:#1e382e">Total</td><td align="right" style="border-top:1px solid #e0e8e3;padding:13px 0 7px;font-size:21px;font-weight:900;color:#17633f">'.$money($total).'</td></tr>'
        .'</table></td></tr>'
        .(!$isAdmin ? '<tr><td style="padding:0 28px 10px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="background:#17633f;border-radius:10px"><a href="'.e($confirmUrl).'" style="display:block;padding:15px 18px;color:#fff;text-decoration:none;font-size:14px;font-weight:800">View Booking &amp; Payment Instructions</a></td></tr></table></td></tr>' : '')
        .'<tr><td style="padding:12px 28px 24px"><div style="padding:14px 16px;background:#edf7f0;border:1px solid #d7e9dc;border-radius:10px"><div style="font-size:13px;font-weight:800;color:#17633f">'.($isAdmin ? 'Booking E-Ticket is attached' : 'Your E-Ticket is attached').'</div><div style="font-size:12px;line-height:1.6;color:#587067;margin-top:4px">'.($isAdmin ? 'The PDF e-ticket is attached for your records.' : 'Please keep the attached PDF e-ticket and show it at the entrance. You can also use the booking link above to review your booking.').'</div></div></td></tr>'
        .'<tr><td style="padding:17px 28px;background:#f7faf8;border-top:1px solid #e6ede9"><div style="font-size:11px;color:#71827b;line-height:1.65">Need help? '.($sitePhone!==''?'<strong>'.e($sitePhone).'</strong>':'').' '.($siteEmail!==''?'&nbsp; · &nbsp;'.e($siteEmail):'').'</div><div style="font-size:11px;color:#87948f;margin-top:4px">'.e($siteAddress).'</div></td></tr>'
        .'</table></td></tr></table></body></html>';
    }
}
