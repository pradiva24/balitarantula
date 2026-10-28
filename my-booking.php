<?php
require __DIR__.'/config/database.php';
require __DIR__.'/includes/functions.php';

$code=trim((string)($_GET['code']??''));
// Accept pasted labels such as "Invoice: BTA-INV-2026-000056".
$code=preg_replace('/^\s*invoice\s*[:#-]?\s*/i','',$code) ?? $code;
$email=trim($_GET['email']??'');
$booking=null; $error='';

// POST -> Redirect -> GET: prevents browser "Confirm Form Resubmission / ERR_CACHE_MISS"
// when the visitor presses Back/Forward after checking a booking.
if($_SERVER['REQUEST_METHOD']==='POST'){
    $postCode=trim($_POST['code']??'');
    $postEmail=trim($_POST['email']??'');
    if($postCode!=='' && $postEmail!==''){
        $target=rtrim(BASE_URL,'/').'/my-booking.php?code='.rawurlencode($postCode).'&email='.rawurlencode($postEmail);
        header('Location: '.$target, true, 303);
        exit;
    }
    header('Location: '.rtrim(BASE_URL,'/').'/my-booking.php', true, 303);
    exit;
}

function my_booking_find(PDO $db, string $code, string $email): ?array {
    $st=$db->prepare("SELECT b.*,p.name package_name,p.slug package_slug,p.short_description,p.cover_image,a.name pickup_name
                     FROM bookings b
                     LEFT JOIN packages p ON p.id=b.package_id
                     LEFT JOIN pickup_areas a ON a.id=b.pickup_area_id
                     WHERE (
                         UPPER(REPLACE(REPLACE(TRIM(COALESCE(b.booking_code,'')),' ',''),'-','')) = UPPER(REPLACE(REPLACE(TRIM(?),' ',''),'-',''))
                         OR UPPER(REPLACE(REPLACE(TRIM(COALESCE(b.invoice_number,'')),' ',''),'-','')) = UPPER(REPLACE(REPLACE(TRIM(?),' ',''),'-',''))
                       )
                       AND LOWER(REPLACE(TRIM(COALESCE(b.customer_email,'')),' ','')) = LOWER(REPLACE(TRIM(?),' ',''))
                     LIMIT 1");
    $st->execute([$code,$code,$email]);
    $row=$st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

if(!$booking && $code!=='' && $email!==''){
    $booking=my_booking_find(db(),$code,$email);
    if(!$booking) $error='We could not find a booking with those details.';
}

$page_title='Check My Booking';
require __DIR__.'/includes/header.php';
?>
<style>
/* My Booking — isolated styles matching homepage palette */
.bta-myb{background:#f5f4ef;padding:0 0 70px;color:#5d5b56;font-family:'Inter',system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.bta-myb *{box-sizing:border-box}
.bta-myb-wrap{width:min(1160px,92%);margin:0 auto}
.bta-myb-hero{position:relative;min-height:300px;overflow:hidden;background:linear-gradient(112deg, #171717 0%, #242321 65%, #30271f 100%);color:#fff;border-bottom:3px solid #ff5a1f}
.bta-myb-hero-bg{position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.85) 0%,rgba(0,0,0,.68) 40%,rgba(0,0,0,.35) 100%)}
.bta-myb-hero-bg img{position:absolute;right:0;top:0;width:58%;height:100%;object-fit:cover;opacity:.25}
.bta-myb-hero-content{position:relative;z-index:2;padding:75px 0 60px}
.bta-myb-eyebrow{font-family:'Barlow Condensed',sans-serif;font-size:13px;font-weight:800;letter-spacing:2.5px;text-transform:uppercase;color:#ff5a1f;margin-bottom:10px}
.bta-myb-title{font-family:'Barlow Condensed',sans-serif;margin:0 0 10px;color:#fff!important;font-size:clamp(38px,5.5vw,58px)!important;line-height:0.98!important;font-weight:900;letter-spacing:-0.5px;text-transform:uppercase}
.bta-myb-sub{margin:0;color:#e0ded9;font-size:14px;line-height:1.65;max-width:680px}
.bta-myb-code{display:inline-flex;margin-top:20px;padding:9px 16px;border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.1);border-radius:4px;font-family:'Barlow Condensed',sans-serif;font-size:14px;font-weight:800;letter-spacing:1px;color:#fff}
.bta-myb-card{background:#fff;border:1px solid #dedbd4;border-radius:16px;box-shadow:0 12px 36px rgba(30,25,15,.06)}
.bta-myb-main{margin-top:-35px;position:relative;z-index:4;padding:28px}
.bta-myb-head{display:flex;justify-content:space-between;align-items:center;gap:18px;padding-bottom:20px;border-bottom:1px solid #dedbd4}
.bta-myb-head h2{font-family:'Barlow Condensed',sans-serif;margin:0;color:#191919;font-size:clamp(24px,3vw,32px);font-weight:900;letter-spacing:-0.5px;text-transform:uppercase}
.bta-myb-statuses{display:flex;gap:8px;flex-wrap:wrap}
.bta-myb-pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:7px 12px;background:#fff5eb;color:#ff5a1f;font-size:11px;font-weight:800;border:1px solid #ffe3cc}
.bta-myb-pill.payment{background:#fff8e6;color:#b37d00;border-color:#fcebc2}
.bta-myb-customer{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:20px 0;border-bottom:1px solid #dedbd4}
.bta-myb-mini{min-width:0}.bta-myb-mini span,.bta-myb-label{display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#77736b;margin-bottom:5px}.bta-myb-mini b{font-size:13px;color:#191919;word-break:break-word}.bta-myb-mini a{color:#ff5a1f;text-decoration:none}
.bta-myb-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,.8fr);gap:22px;padding-top:22px}
.bta-myb-activity{display:grid;grid-template-columns:190px minmax(0,1fr);gap:20px;align-items:start}
.bta-myb-cover{height:155px;border-radius:12px;overflow:hidden;background:#181818}.bta-myb-cover img{width:100%;height:100%;object-fit:cover;display:block}
.bta-myb-activity h3{font-family:'Barlow Condensed',sans-serif;margin:0 0 5px;color:#191919;font-size:24px;font-weight:800;letter-spacing:0.5px;text-transform:uppercase}.bta-myb-location{font-size:11px;color:#77736b;margin-bottom:13px}.bta-myb-desc{font-size:13px;color:#5d5b56;line-height:1.6;margin:0 0 15px}
.bta-myb-info{border-top:1px solid #dedbd4}.bta-myb-info-row{display:flex;justify-content:space-between;gap:15px;padding:10px 0;border-bottom:1px solid #dedbd4;font-size:13px}.bta-myb-info-row span{color:#77736b}.bta-myb-info-row b{color:#191919;text-align:right}
.bta-myb-price{border:1px solid #dedbd4;border-radius:13px;padding:18px;background:#faf8f5}.bta-myb-price-row{display:flex;justify-content:space-between;gap:15px;padding:9px 0;font-size:13px}.bta-myb-price-row span{color:#77736b}.bta-myb-price-row b{color:#191919;text-align:right}.bta-myb-total{margin:9px 0;padding:16px;background:rgba(255,90,31,0.08);border:1px solid rgba(255,90,31,0.25);border-radius:10px}.bta-myb-total small{display:block;font-family:'Barlow Condensed',sans-serif;font-size:12px;font-weight:800;color:#ff5a1f;text-transform:uppercase;letter-spacing:1px}.bta-myb-total strong{display:block;margin-top:4px;font-family:'Barlow Condensed',sans-serif;font-size:28px;font-weight:900;color:#ff5a1f;line-height:1.1}
.bta-myb-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:23px;padding-top:21px;border-top:1px solid #dedbd4}.bta-myb-btn{display:inline-flex!important;align-items:center;justify-content:center;min-height:46px;padding:11px 18px;border-radius:8px!important;text-decoration:none!important;font-family:'Barlow Condensed',sans-serif!important;font-size:15px!important;font-weight:900!important;letter-spacing:1px;text-transform:uppercase;border:1px solid #dedbd4!important;transition:.2s ease}.bta-myb-btn.primary{background:#ff5a1f!important;border-color:#ff5a1f!important;color:#111!important}.bta-myb-btn.primary:hover{background:#ff743f!important}.bta-myb-btn.whatsapp{background:#fff!important;border-color:#25d366!important;color:#25d366!important}.bta-myb-btn.ticket{background:#191919!important;color:#fff!important;border-color:#191919!important}.bta-myb-btn:hover{transform:translateY(-1px)}
.bta-myb-note{margin-top:16px;padding:13px 15px;border-radius:10px;background:#faf8f5;border:1px solid #dedbd4;color:#77736b;font-size:12px;line-height:1.55}
.bta-myb-bottom{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}.bta-myb-info-card{padding:22px}.bta-myb-info-card h3{font-family:'Barlow Condensed',sans-serif;margin:0 0 12px;font-size:20px;font-weight:800;letter-spacing:0.5px;text-transform:uppercase;color:#191919}.bta-myb-info-card ul{margin:0;padding-left:18px;color:#5d5b56;font-size:13px;line-height:1.8}.bta-myb-support{display:flex;align-items:center;justify-content:space-between;gap:15px}.bta-myb-support p{margin:3px 0 0;color:#5d5b56;font-size:13px}
.bta-myb-search{padding:55px 0}.bta-myb-search-card{max-width:760px;margin:auto;padding:30px}.bta-myb-search-head{text-align:center;margin-bottom:24px}.bta-myb-search-head h1{font-family:'Barlow Condensed',sans-serif;margin:7px 0;color:#191919;font-size:clamp(30px,4vw,42px);font-weight:900;letter-spacing:-0.5px;text-transform:uppercase}.bta-myb-search-head p{margin:0;color:#77736b;font-size:14px}.bta-myb-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.bta-myb-form-grid label{font-size:11px;font-weight:800;color:#191919}.bta-myb-form-grid input{display:block;width:100%;margin-top:7px;padding:12px 13px;border:1px solid #d8d4cc;border-radius:8px;background:#fff;color:#191919;font:inherit;outline:none}.bta-myb-submit{width:100%;margin-top:17px}.bta-myb-error{margin-bottom:18px;padding:13px 15px;border-radius:10px;background:#fff1f0;border:1px solid #f0d3d0;color:#a13b32;font-size:12px}
@media(max-width:800px){.bta-myb-grid{grid-template-columns:1fr}.bta-myb-customer{grid-template-columns:1fr 1fr}.bta-myb-activity{grid-template-columns:150px minmax(0,1fr)}.bta-myb-bottom{grid-template-columns:1fr}}
@media(max-width:600px){.bta-myb-hero{min-height:250px}.bta-myb-hero-bg img{width:100%;opacity:.18}.bta-myb-hero-content{padding:52px 0 55px}.bta-myb-title{font-size:31px!important}.bta-myb-sub{font-size:13px}.bta-myb-main{margin-top:-25px;padding:18px}.bta-myb-head{align-items:flex-start;flex-direction:column}.bta-myb-customer{grid-template-columns:1fr}.bta-myb-activity{grid-template-columns:1fr}.bta-myb-cover{height:190px}.bta-myb-actions{display:grid}.bta-myb-btn{width:100%}.bta-myb-search{padding:30px 0}.bta-myb-search-card{padding:20px 16px}.bta-myb-form-grid{grid-template-columns:1fr}.bta-myb-price{padding:15px}}
</style>
<main class="bta-myb">
<?php if(!$booking): ?>
<section class="bta-myb-search">
 <div class="bta-myb-wrap">
  <div class="bta-myb-card bta-myb-search-card">
   <div class="bta-myb-search-head"><div class="bta-myb-eyebrow">CUSTOMER PORTAL</div><h1>Check Your Booking</h1><p>Enter your booking code or invoice number and the email used during booking.</p></div>
   <?php if($error): ?><div class="bta-myb-error"><?=e($error)?></div><?php endif; ?>
   <form method="get" action="<?=e(BASE_URL.'/my-booking')?>" autocomplete="on">
    <div class="bta-myb-form-grid">
      <label>Booking Code / Invoice Number<input name="code" required placeholder="Example: BTA-20260920-001 or BTA-INV-2026-000056" value="<?=e($code)?>"></label>
      <label>Email Address<input type="email" name="email" required placeholder="you@example.com" value="<?=e($email)?>"></label>
    </div>
    <button class="bta-myb-btn primary bta-myb-submit" type="submit">Check Booking →</button>
   </form>
  </div>
 </div>
</section>
<?php else:
  $paid=strtolower((string)($booking['payment_status']??''))==='paid';
  $cancelled=strtolower((string)($booking['booking_status']??''))==='cancelled';
  $guestCount=(int)$booking['adults']+(int)$booking['children'];
  $total=(float)$booking['total'];
  $pricePerPerson=(float)$booking['price_adult'];
  $cover=trim((string)($booking['cover_image']??''));
  if($cover && !preg_match('~^https?://~i',$cover)) $cover=asset_url($cover);
  $status=strtolower((string)($booking['booking_status']??'new'));
  $statusLabel=ucfirst($status==='new'?'Received':$status);
  $paymentLabel=ucfirst((string)($booking['payment_status']??'pending'));
  $wa=trim((string)($booking['customer_whatsapp']??''));
  $waUrl=$wa?whatsapp_url($wa,'Hello, I would like to ask about my booking '.$booking['booking_code']):'';
?>
<section class="bta-myb-hero">
 <div class="bta-myb-hero-bg"><?php if($cover): ?><img src="<?=e($cover)?>" alt=""><?php endif; ?></div>
 <div class="bta-myb-wrap bta-myb-hero-content">
  <div class="bta-myb-eyebrow">YOUR BOOKING</div>
  <h1 class="bta-myb-title">Booking Details</h1>
  <p class="bta-myb-sub">Review your booking, payment information and confirmation details in one place.</p>
  <div class="bta-myb-code">Booking ID&nbsp; <?=e((string)$booking['booking_code'])?></div>
 </div>
</section>
<div class="bta-myb-wrap">
 <section class="bta-myb-card bta-myb-main">
  <div class="bta-myb-head"><h2><?=e((string)$booking['package_name'])?></h2><div class="bta-myb-statuses"><span class="bta-myb-pill"><?=fa_icon('success','fa-solid fa-circle')?> <?=e($statusLabel)?></span><span class="bta-myb-pill payment"><?=fa_icon('payment_gateway','fa-solid fa-circle-dollar-to-slot')?> Payment <?=e($paymentLabel)?></span></div></div>
  <div class="bta-myb-customer">
   <div class="bta-myb-mini"><span>Customer</span><b><?=e((string)($booking['customer_name']??'-'))?></b></div>
   <div class="bta-myb-mini"><span>Email</span><b><?=e((string)($booking['customer_email']??'-'))?></b></div>
   <div class="bta-myb-mini"><span>WhatsApp</span><b><?php if($waUrl): ?><a href="<?=e($waUrl)?>" target="_blank" rel="noopener"><?=e($wa?:'-')?></a><?php else: ?>-<?php endif; ?></b></div>
  </div>
  <div class="bta-myb-grid">
   <div class="bta-myb-activity">
    <div class="bta-myb-cover"><?php if($cover): ?><img src="<?=e($cover)?>" alt="<?=e((string)$booking['package_name'])?>" loading="lazy"><?php endif; ?></div>
    <div><h3><?=e((string)$booking['package_name'])?></h3><div class="bta-myb-location"><?=fa_icon('location','fa-solid fa-location-dot')?> <?=e(site_brand_name())?> – Bali</div><?php if(!empty($booking['short_description'])): ?><p class="bta-myb-desc"><?=e((string)$booking['short_description'])?></p><?php endif; ?>
     <div class="bta-myb-info"><div class="bta-myb-info-row"><span>Activity Date</span><b><?=e(date('D, M d, Y',strtotime((string)$booking['booking_date'])))?></b></div><div class="bta-myb-info-row"><span>Pickup Time</span><b><?=e(substr((string)$booking['pickup_time'],0,5))?></b></div><div class="bta-myb-info-row"><span>Number of Guests</span><b><?=e((string)$guestCount)?> <?=($guestCount===1?'Person':'People')?></b></div><?php if((int)$booking['pickup_required']===1): ?><div class="bta-myb-info-row"><span>Pickup Area</span><b><?=e((string)($booking['pickup_name']??'-'))?></b></div><?php endif; ?></div>
    </div>
   </div>
   <div class="bta-myb-price">
    <div class="bta-myb-price-row"><span>Price per Person</span><b>IDR <?=number_format($pricePerPerson,0,',','.')?></b></div>
    <div class="bta-myb-price-row"><span>Number of Guests</span><b><?=e((string)$guestCount)?></b></div>
    <?php if((float)$booking['pickup_price']>0): ?><div class="bta-myb-price-row"><span>Hotel Transport</span><b>IDR <?=number_format((float)$booking['pickup_price'],0,',','.')?></b></div><?php endif; ?>
    <?php if((float)($booking['discount_amount']??0)>0): ?><div class="bta-myb-price-row"><span>Promo Discount</span><b>-IDR <?=number_format((float)$booking['discount_amount'],0,',','.')?></b></div><?php endif; ?>
    <div class="bta-myb-total"><small>Total Price</small><strong>IDR <?=number_format($total,0,',','.')?></strong></div>
    <div class="bta-myb-price-row"><span>Payment Method</span><b><?=e((string)($booking['payment_method']??'Not selected'))?></b></div>
    <div class="bta-myb-price-row"><span>Payment Status</span><b><?=e($paymentLabel)?></b></div>
   </div>
  </div>
  <div class="bta-myb-actions">
   <?php if(!$cancelled && !$paid && file_exists(__DIR__.'/booking-confirmation.php')): ?><a class="bta-myb-btn primary" href="<?=e(BASE_URL.'/booking-confirmation?code='.rawurlencode((string)$booking['booking_code']).'&token='.rawurlencode((string)($booking['access_token']??'')))?>"><?=fa_icon('mybooking_payment','fa-solid fa-credit-card')?> Open Payment &amp; Confirmation</a><?php endif; ?>
   <?php if($waUrl): ?><a class="bta-myb-btn whatsapp" target="_blank" rel="noopener" href="<?=e($waUrl)?>"><?=fa_icon('whatsapp','fa-brands fa-whatsapp')?> WhatsApp Us</a><?php endif; ?>
   <a class="bta-myb-btn ticket" href="<?=e(BASE_URL.'/booking-success?code='.rawurlencode((string)$booking['booking_code']))?>"><?=fa_icon('ticket','fa-solid fa-ticket')?> View E-Ticket</a>
  </div>
  <div class="bta-myb-note"><?php if($paid): ?>Payment status is <strong>Paid</strong>. You can review your confirmation and e-ticket anytime.<?php elseif($cancelled): ?>This booking is currently marked as cancelled. Please contact our team if you need assistance.<?php else: ?>Your payment is still pending. Use <strong>Open Payment &amp; Confirmation</strong> to review or complete your payment.<?php endif; ?></div>
 </section>
 <div class="bta-myb-bottom">
  <section class="bta-myb-card bta-myb-info-card"><h3>Important Information</h3><ul><li>Please arrive 15 minutes before your scheduled activity.</li><li>Keep your booking code for reference.</li><li>Show your e-ticket at the entrance when requested.</li><li>For changes or assistance, contact our support team.</li></ul></section>
  <section class="bta-myb-card bta-myb-info-card bta-myb-support"><div><h3>Need Help?</h3><p>Our team is ready to assist you with your booking.</p></div><?php if($waUrl): ?><a class="bta-myb-btn whatsapp" target="_blank" rel="noopener" href="<?=e($waUrl)?>">WhatsApp Us</a><?php else: ?><a class="bta-myb-btn" href="<?=e(BASE_URL.'/contact-us')?>">Contact Support</a><?php endif; ?></section>
 </div>
</div>
<?php endif; ?>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>
