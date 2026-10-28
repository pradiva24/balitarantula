<?php
require __DIR__.'/config/database.php';
require __DIR__.'/includes/functions.php';
require __DIR__.'/includes/payment-methods.php';
$code=trim((string)($_GET['code']??''));
$email=trim((string)($_GET['email']??''));
$accessToken=trim((string)($_GET['token']??''));
$booking=false;

if($code!=='' && $accessToken!==''){
    $st=db()->prepare("SELECT b.*,p.name package_name FROM bookings b LEFT JOIN packages p ON p.id=b.package_id WHERE b.booking_code=? AND b.access_token=? LIMIT 1");
    $st->execute([$code,$accessToken]);
    $booking=$st->fetch();
}
if(!$booking && $code!=='' && $email!==''){
    $st=db()->prepare("SELECT b.*,p.name package_name FROM bookings b LEFT JOIN packages p ON p.id=b.package_id WHERE b.booking_code=? AND LOWER(TRIM(b.customer_email))=LOWER(TRIM(?)) LIMIT 1");
    $st->execute([$code,$email]);
    $booking=$st->fetch();
}
if(!$booking){ http_response_code(404); $page_title='Payment'; require __DIR__.'/includes/header.php'; echo '<section class="container" style="padding:70px 0"><div class="booking-box" style="max-width:800px;margin:auto;text-align:center"><h1 style="font-family:\'Barlow Condensed\',sans-serif;text-transform:uppercase;font-weight:900;letter-spacing:-0.5px;color:#111">Booking Not Found</h1><p style="color:#64748b">Please check your booking code and email.</p></div></section>'; require __DIR__.'/includes/footer.php'; exit; }
$page_title='Payment - '.$booking['booking_code']; require __DIR__.'/includes/header.php';
?>
<section class="container" style="padding:55px 0"><div class="booking-box" style="max-width:800px;margin:auto;background:#fff;border:1px solid #ebebeb;border-radius:14px;padding:32px;box-shadow:0 8px 30px rgba(0,0,0,.04)">
<span class="eyebrow" style="display:inline-block;color:#ff5500;font-family:'Barlow Condensed',sans-serif;font-weight:800;letter-spacing:2.5px;font-size:13px;text-transform:uppercase;margin-bottom:8px">SECURE PAYMENT</span><h1 style="font-family:'Barlow Condensed',sans-serif;text-transform:uppercase;font-weight:900;letter-spacing:-0.5px;font-size:38px;color:#111;margin:0 0 8px">Payment for <?=e($booking['booking_code'])?></h1>
<p style="color:#64748b;font-size:14px;margin:0 0 20px"><?=e($booking['package_name'])?> · <?=e($booking['booking_date'])?></p>
<div class="detail-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;background:#f9f9f9;padding:18px;border-radius:10px;border:1px solid #f0f0f0">
<div><span style="display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:1px">Customer</span><strong style="color:#111;font-size:15px"><?=e($booking['customer_name'])?></strong></div>
<div><span style="display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:1px">Total</span><strong style="color:#ff5500;font-size:22px;font-weight:900;font-family:'Barlow Condensed',sans-serif">Rp <?=number_format((float)$booking['total'],0,',','.')?></strong></div>
<div><span style="display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:1px">Payment Status</span><strong style="color:#111;font-size:15px;text-transform:uppercase"><?=e($booking['payment_status'])?></strong></div>
<div><span style="display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:1px">Payment Method</span><strong style="color:#111;font-size:15px"><?=e($booking['payment_method']??'Not selected')?></strong></div>
</div>
<div style="margin-top:24px;padding:20px;border:1px solid #ebebeb;border-radius:12px;background:#fff">
<h3 style="font-family:'Barlow Condensed',sans-serif;text-transform:uppercase;font-weight:800;letter-spacing:0.5px;font-size:22px;margin:0 0 10px;color:#111">Available payment methods</h3>
<p style="color:#475569;font-size:14px;line-height:1.7;margin:0 0 12px"><?=nl2br(e(implode("\n", active_payment_methods()))) ?></p>
<?php if(setting('payment_bank_name')): ?><p style="color:#111;font-size:14px;line-height:1.7;background:#fafafa;padding:12px 14px;border-radius:8px;border:1px solid #eee"><b><?=e(setting('payment_bank_name'))?></b><br>Account: <?=e(setting('payment_bank_account'))?><br>Holder: <?=e(setting('payment_bank_holder'))?></p><?php endif; ?>
</div>
<p style="margin-top:20px;color:#64748b;font-size:13px;line-height:1.7"><?=nl2br(e(setting('payment_instructions','Please complete payment and upload your proof from the booking confirmation page.'))) ?></p>
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px">
<?php if(($booking['payment_method']??'')==='Midtrans' && ($booking['payment_status']??'pending')!=='paid'): ?>
<a class="btn primary" style="background:#ff5500;color:#fff;padding:12px 24px;border-radius:4px;text-decoration:none;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:15px;text-transform:uppercase;letter-spacing:1px" href="midtrans-checkout.php?code=<?=urlencode($booking['booking_code'])?>&amp;email=<?=urlencode($booking['customer_email'])?>&amp;token=<?=urlencode((string)($booking['access_token']??''))?>">Pay with Midtrans</a>
<?php endif; ?>
<a class="btn" style="background:<?= (($booking['payment_method']??'')==='Midtrans' && ($booking['payment_status']??'pending')!=='paid') ? '#f1f1f1' : '#ff5500' ?>;color:<?= (($booking['payment_method']??'')==='Midtrans' && ($booking['payment_status']??'pending')!=='paid') ? '#111' : '#fff' ?>;padding:12px 24px;border-radius:4px;text-decoration:none;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:15px;text-transform:uppercase;letter-spacing:1px" href="booking-confirmation?code=<?=urlencode($booking['booking_code'])?>&amp;token=<?=urlencode((string)($booking['access_token']??''))?>">Back to Booking Confirmation</a>
</div>
</div></section>
<?php require __DIR__.'/includes/footer.php'; ?>