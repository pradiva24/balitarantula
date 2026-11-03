<?php
require __DIR__.'/config/database.php';
require __DIR__.'/includes/functions.php';

$code=trim((string)($_GET['code']??''));
$code=preg_replace('/^\\s*invoice\\s*[:#-]?\\s*/i','',$code) ?? $code;
$email=trim((string)($_GET['email']??''));
$booking=null; $error='';

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

if($code!=='' && $email!==''){
    $booking=my_booking_find(db(),$code,$email);
    if(!$booking) $error='We could not find a booking with those details. Please check your booking code and email address.';
}
$page_title='My Booking';
require __DIR__.'/includes/header.php';

$waSite=trim((string)setting('site_whatsapp',''));
$heroFallback='https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=2200&q=90';
?>
<style>
.bta-myb{--myb-orange:#ff6500;--myb-ink:#151719;--myb-muted:#647080;background:#f2f3f4;color:var(--myb-ink);padding:0 0 70px;font-family:'Inter',Arial,sans-serif}
.bta-myb *{box-sizing:border-box}
.bta-myb-wrap{width:min(1110px,calc(100% - 48px));margin:0 auto}
.bta-myb-hero{position:relative;isolation:isolate;min-height:410px;padding:78px 0 70px;color:#fff;background:#171a18 url('<?=e($heroFallback)?>') center 44%/cover no-repeat;overflow:hidden}
.bta-myb-hero:before{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(90deg,rgba(7,10,9,.94) 0%,rgba(7,10,9,.78) 49%,rgba(7,10,9,.25) 100%),linear-gradient(0deg,rgba(7,10,9,.3),transparent 55%)}
.bta-myb-hero:after{content:"";position:absolute;z-index:-1;left:0;right:0;bottom:0;height:90px;background:linear-gradient(0deg,#f2f3f4,transparent)}
.bta-myb-eyebrow{display:flex;align-items:center;gap:11px;color:var(--myb-orange);font-size:13px;font-weight:900;letter-spacing:1.4px;text-transform:uppercase;margin:0 0 12px}
.bta-myb-eyebrow:before{content:"";width:42px;height:3px;background:var(--myb-orange)}
.bta-myb-title{font-family:'Barlow Condensed',Impact,sans-serif;font-size:clamp(48px,6vw,72px);line-height:.9;font-weight:900;font-style:italic;letter-spacing:.2px;text-transform:uppercase;color:#fff!important;margin:0 0 15px;max-width:650px}
.bta-myb-intro{font-size:15px;line-height:1.7;color:#f1f2f1;max-width:580px;margin:0}
.bta-myb-search{position:relative;z-index:5;margin-top:-42px;padding:0}
.bta-myb-search-card{display:grid;grid-template-columns:1fr 1fr auto;gap:13px;align-items:center;padding:25px 28px;background:#fff;border:1px solid #e3e5e8;border-radius:20px;box-shadow:0 15px 38px rgba(22,28,35,.14)}
.bta-myb-field{position:relative;display:block;min-width:0}
.bta-myb-field label{display:block;position:absolute;top:9px;left:55px;font-size:12px;font-weight:900;color:#151719}
.bta-myb-field input{width:100%;height:70px;padding:29px 14px 9px 55px;border:1.5px solid #d5d9df;border-radius:10px;background:#fff;color:#202833;font:500 14px 'Inter',Arial,sans-serif;outline:none;box-shadow:0 2px 5px #00000008}
.bta-myb-field input:focus{border-color:var(--myb-orange);box-shadow:0 0 0 3px #ff65001c}
.bta-myb-field-icon{position:absolute;left:18px;top:24px;color:#58616c;font-size:23px}
.bta-myb-submit{height:62px;min-width:230px;border:0;border-radius:10px;background:var(--myb-orange);color:#fff;font:900 17px 'Barlow Condensed',sans-serif;letter-spacing:.2px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:13px;padding:0 24px;transition:.2s}
.bta-myb-submit:hover{background:#e95b00;transform:translateY(-1px)}
.bta-myb-error{margin:18px 0 0;padding:14px 17px;border-radius:10px;background:#fff0ef;border:1px solid #f2cbc7;color:#a33228;font-size:13px}
.bta-myb-result{position:relative;z-index:2;margin-top:30px;background:#fff;border:1px solid #e4e6e9;border-radius:20px;overflow:hidden;box-shadow:0 18px 48px rgba(22,28,35,.11)}
.bta-myb-result-head{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:22px 30px;background:#fbfbfa;border-bottom:1px solid #e4e6e9}
.bta-myb-result-heading{display:flex;align-items:center;gap:17px}
.bta-myb-ticket-icon{width:55px;height:55px;display:grid;place-items:center;color:var(--myb-orange);font-size:37px}
.bta-myb-result-head h2{font:900 29px/1 'Barlow Condensed',sans-serif;margin:0 0 5px;color:#141719}
.bta-myb-result-head p{margin:0;color:#687383;font-size:13px}
.bta-myb-status{display:inline-flex;align-items:center;gap:9px;padding:10px 18px;border-radius:12px;background:#d9f8d7;color:#087d24;font-weight:900;font-size:15px;white-space:nowrap}
.bta-myb-status i{font-size:21px}
.bta-myb-status.pending{background:#fff2d5;color:#9b6500}
.bta-myb-status.cancelled,.bta-myb-status.failed{background:#ffe1df;color:#a52b23}
.bta-myb-result-body{padding:22px 30px 28px}
.bta-myb-package{display:grid;grid-template-columns:320px minmax(0,1fr);gap:28px;align-items:center;padding-bottom:18px;border-bottom:1px solid #dfe2e6}
.bta-myb-cover{height:150px;border-radius:14px;overflow:hidden;background:#171a18}
.bta-myb-cover img{width:100%;height:100%;object-fit:cover;display:block}
.bta-myb-package h3{font:900 32px/1.05 'Barlow Condensed',sans-serif;margin:0 0 5px;color:#141719;overflow-wrap:anywhere}
.bta-myb-package-name{font-size:18px;font-weight:600;color:#38414c;margin:0 0 13px}
.bta-myb-facts{display:flex;gap:12px 24px;flex-wrap:wrap;color:#202833;font-size:14px;font-weight:600}
.bta-myb-facts span{display:inline-flex;align-items:center;gap:9px}
.bta-myb-facts i{font-size:20px;color:#58616c}
.bta-myb-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;padding:19px 0;border-bottom:1px solid #dfe2e6}
.bta-myb-info-block{min-width:0}
.bta-myb-info-block+ .bta-myb-info-block{border-left:1px solid #dfe2e6;padding-left:25px}
.bta-myb-info-title{display:flex;align-items:center;gap:12px;font:800 21px 'Barlow Condensed',sans-serif;color:#16191c;margin:0 0 10px}
.bta-myb-info-title i{font-size:23px;color:var(--myb-orange);width:24px}
.bta-myb-info-line{display:grid;grid-template-columns:minmax(78px,auto) 10px minmax(0,1fr);gap:8px;padding:3px 0;font-size:13px;line-height:1.5}
.bta-myb-info-line span:first-child{color:#303944}
.bta-myb-info-line b{font-weight:600;overflow-wrap:anywhere}
.bta-myb-timeline{display:grid;grid-template-columns:repeat(4,1fr);padding:26px 5px 24px;position:relative}
.bta-myb-timeline:before{content:"";position:absolute;left:12.5%;right:12.5%;top:39px;height:3px;background:#ffd0b3}
.bta-myb-step{position:relative;text-align:center;z-index:1}
.bta-myb-step-dot{width:34px;height:34px;margin:0 auto 9px;border-radius:50%;display:grid;place-items:center;background:#d9dde2;border:3px solid #fff;color:#7c8793;font-size:14px}
.bta-myb-step.done .bta-myb-step-dot{background:var(--myb-orange);color:#fff}
.bta-myb-step strong{display:block;font-size:13px;color:#171a1d}
.bta-myb-step small{display:block;margin-top:3px;font-size:11px;color:#697483}
.bta-myb-actions{display:grid;grid-template-columns:1.2fr 1.15fr 1.1fr;gap:16px;margin-top:14px}
.bta-myb-btn{min-height:57px;padding:10px 15px;display:flex;align-items:center;justify-content:center;gap:11px;border:1.5px solid #c9cfd6;border-radius:10px;text-decoration:none!important;text-align:center;font:900 17px 'Barlow Condensed',sans-serif;color:#171a1d!important;background:#fff;transition:.2s}
.bta-myb-btn i{font-size:24px}
.bta-myb-btn:hover{transform:translateY(-2px);box-shadow:0 6px 14px #00000010}
.bta-myb-btn.primary{background:var(--myb-orange);border-color:var(--myb-orange);color:#fff!important}
.bta-myb-btn.whatsapp{border-color:#18a83b;color:#078a28!important}
.bta-myb-btn.muted{background:#fff;color:#171a1d!important}
.bta-myb-price{display:flex;justify-content:flex-end;gap:22px;flex-wrap:wrap;padding-top:17px;margin-top:15px;border-top:1px solid #e5e7ea;font-size:13px;color:#66717e}
.bta-myb-price strong{color:#171a1d}
@media(max-width:760px){
 .bta-myb-wrap{width:calc(100% - 32px)}.bta-myb-hero{min-height:370px;padding:66px 0 75px}.bta-myb-title{font-size:clamp(43px,11vw,60px)}.bta-myb-intro{font-size:13px;max-width:440px}
 .bta-myb-search{margin-top:-48px}.bta-myb-search-card{grid-template-columns:1fr;padding:17px;gap:11px;border-radius:15px}.bta-myb-field input{height:62px}.bta-myb-field-icon{top:20px}.bta-myb-submit{width:100%;min-width:0;height:56px}
 .bta-myb-result{margin-top:22px;border-radius:16px}.bta-myb-result-head{padding:17px;align-items:flex-start}.bta-myb-result-heading{gap:8px}.bta-myb-ticket-icon{width:38px;font-size:29px}.bta-myb-result-head h2{font-size:24px}.bta-myb-result-head p{font-size:11px}.bta-myb-status{padding:8px 10px;font-size:11px;gap:5px}.bta-myb-status i{font-size:15px}
 .bta-myb-result-body{padding:16px}.bta-myb-package{grid-template-columns:1fr;gap:14px}.bta-myb-cover{height:190px}.bta-myb-package h3{font-size:27px}.bta-myb-package-name{font-size:16px}.bta-myb-facts{font-size:12px;gap:9px 16px}
 .bta-myb-info-grid{grid-template-columns:1fr;gap:14px}.bta-myb-info-block+.bta-myb-info-block{border-left:0;border-top:1px solid #e1e4e8;padding:14px 0 0}.bta-myb-timeline{grid-template-columns:1fr 1fr;row-gap:18px;padding:22px 0}.bta-myb-timeline:before{display:none}.bta-myb-actions{grid-template-columns:1fr;gap:10px}.bta-myb-btn{min-height:52px}.bta-myb-price{justify-content:flex-start}
}
@media(max-width:420px){.bta-myb-result-head{flex-direction:column}.bta-myb-status{align-self:flex-start}.bta-myb-info-line{grid-template-columns:78px 8px minmax(0,1fr);font-size:12px}}
</style>

<main class="bta-myb">
 <section class="bta-myb-hero">
  <div class="bta-myb-wrap">
   <div class="bta-myb-eyebrow">MY BOOKING</div>
   <h1 class="bta-myb-title">Check Your Booking</h1>
   <p class="bta-myb-intro">Enter your booking code and email to view your booking details, download e-ticket or check the current status.</p>
  </div>
 </section>

 <div class="bta-myb-wrap">
  <section class="bta-myb-search">
   <form class="bta-myb-search-card" method="get" action="<?=e(rtrim(BASE_URL,'/').'/my-booking')?>" autocomplete="on">
    <div class="bta-myb-field">
     <i class="fa-solid fa-ticket-simple bta-myb-field-icon" aria-hidden="true"></i>
     <label for="myb-code">Booking Code / Invoice Number</label>
     <input id="myb-code" name="code" required value="<?=e($code)?>" placeholder="e.g. BTA-20261102-3F0089">
    </div>
    <div class="bta-myb-field">
     <i class="fa-regular fa-envelope bta-myb-field-icon" aria-hidden="true"></i>
     <label for="myb-email">Email Address</label>
     <input id="myb-email" type="email" name="email" required value="<?=e($email)?>" placeholder="Enter your email address">
    </div>
    <button class="bta-myb-submit" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Check Booking</button>
   </form>
   <?php if($error): ?><div class="bta-myb-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?=e($error)?></div><?php endif; ?>
  </section>

 <?php if($booking):
    $bookingStatus=strtolower((string)($booking['booking_status']??'new'));
    $paymentStatus=strtolower((string)($booking['payment_status']??'pending'));
    $cancelled=$bookingStatus==='cancelled';
    $paid=$paymentStatus==='paid';
    $confirmed=in_array($bookingStatus,['confirmed','paid','completed'],true);
    $guestCount=(int)$booking['adults']+(int)$booking['children'];
    $cover=trim((string)($booking['cover_image']??''));
    if($cover && !preg_match('~^https?://~i',$cover)) $cover=asset_url($cover);
    $wa=trim((string)($booking['customer_whatsapp']??'')) ?: $waSite;
    $waUrl=$wa?whatsapp_url($wa,'Hello, I would like to ask about my booking '.$booking['booking_code']):'';
    $hotelPickup=(int)($booking['pickup_required']??0)===1;
    $activityTime=trim((string)($booking['activity_time']??''));
    $pickupTime=trim((string)($booking['pickup_time']??''));
    $paymentLabel=ucfirst($paymentStatus);
    $statusLabel=$cancelled?'Cancelled':($confirmed?'Confirmed':'Received');
    $activityDate=!empty($booking['booking_date'])?date('D, d M Y',strtotime((string)$booking['booking_date'])):'-';
    $createdLabel=!empty($booking['created_at'])?date('d M Y, H:i',strtotime((string)$booking['created_at'])):'Booking received';
    $total=(float)($booking['total']??0);
    $ticketUrl=BASE_URL.'/booking-success?code='.rawurlencode((string)$booking['booking_code']).'&print=1';
 ?>
  <section class="bta-myb-result" aria-labelledby="myb-booking-title">
   <header class="bta-myb-result-head">
    <div class="bta-myb-result-heading">
     <div class="bta-myb-ticket-icon"><i class="fa-solid fa-ticket"></i></div>
     <div><h2 id="myb-booking-title">Booking Details</h2><p>Here is your booking information.</p></div>
    </div>
    <div class="bta-myb-status <?=$cancelled?'cancelled':($confirmed?'':'pending')?>"><i class="fa-solid <?=$cancelled?'fa-circle-xmark':($confirmed?'fa-circle-check':'fa-clock')?>"></i> <?=e($statusLabel)?></div>
   </header>

   <div class="bta-myb-result-body">
    <div class="bta-myb-package">
     <div class="bta-myb-cover"><?php if($cover): ?><img src="<?=e($cover)?>" alt="<?=e((string)$booking['package_name'])?>" loading="lazy"><?php endif; ?></div>
     <div>
      <h3><?=e((string)$booking['booking_code'])?></h3>
      <p class="bta-myb-package-name"><?=e((string)$booking['package_name'])?></p>
      <div class="bta-myb-facts">
       <span><i class="fa-regular fa-calendar-days"></i> <?=e($activityDate)?></span>
       <?php if($activityTime!==''): ?><span><i class="fa-regular fa-clock"></i> <?=e(date('h:i A',strtotime($activityTime)))?></span><?php endif; ?>
       <span><i class="fa-solid fa-user"></i> <?=e((string)$guestCount)?> <?=($guestCount===1?'Person':'Persons')?> (Adult <?=e((string)$booking['adults'])?>, Child <?=e((string)$booking['children'])?>)</span>
      </div>
     </div>
    </div>

    <div class="bta-myb-info-grid">
     <section class="bta-myb-info-block">
      <h3 class="bta-myb-info-title"><i class="fa-solid fa-user"></i> Customer Information</h3>
      <div class="bta-myb-info-line"><span>Name</span><span>:</span><b><?=e((string)($booking['customer_name']??'-'))?></b></div>
      <div class="bta-myb-info-line"><span>Email</span><span>:</span><b><?=e((string)($booking['customer_email']??'-'))?></b></div>
      <div class="bta-myb-info-line"><span>Phone</span><span>:</span><b><?=e((string)($booking['customer_whatsapp']??'-'))?></b></div>
     </section>
     <section class="bta-myb-info-block">
      <h3 class="bta-myb-info-title"><i class="fa-solid fa-car-side"></i> Pickup Information</h3>
      <div class="bta-myb-info-line"><span>Pickup Service</span><span>:</span><b><?=$hotelPickup?'Yes':'No'?></b></div>
      <?php if($hotelPickup): ?>
       <div class="bta-myb-info-line"><span>Hotel / Villa</span><span>:</span><b><?=e(trim((string)($booking['hotel_name']??'')) ?: (string)($booking['pickup_name']??'-'))?><?=!empty($booking['room_number'])?' · Room '.e((string)$booking['room_number']):''?></b></div>
       <div class="bta-myb-info-line"><span>Pickup Time</span><span>:</span><b><?=$pickupTime!==''?e(date('h:i A',strtotime($pickupTime))):'To be confirmed'?></b></div>
      <?php else: ?>
       <div class="bta-myb-info-line"><span>Location</span><span>:</span><b>Meet at activity location</b></div>
      <?php endif; ?>
     </section>
    </div>

    <div class="bta-myb-timeline" aria-label="Booking progress">
     <div class="bta-myb-step done"><div class="bta-myb-step-dot"><i class="fa-solid fa-check"></i></div><strong>Booking Received</strong><small><?=e($createdLabel)?></small></div>
     <div class="bta-myb-step <?=$paid?'done':''?>"><div class="bta-myb-step-dot"><i class="fa-solid <?=$paid?'fa-check':'fa-circle'?>"></i></div><strong>Payment <?=$paid?'Confirmed':'Pending'?></strong><small><?=$paid?'Payment received':'Awaiting payment'?></small></div>
     <div class="bta-myb-step <?=$confirmed?'done':''?>"><div class="bta-myb-step-dot"><i class="fa-solid <?=$confirmed?'fa-check':'fa-circle'?>"></i></div><strong>Booking <?=$confirmed?'Confirmed':'Confirmation'?></strong><small><?=$confirmed?'Booking confirmed':'Awaiting confirmation'?></small></div>
     <div class="bta-myb-step"><div class="bta-myb-step-dot"><i class="fa-regular fa-clock"></i></div><strong>Activity Day</strong><small><?=e($activityDate)?></small></div>
    </div>

    <div class="bta-myb-actions">
     <a class="bta-myb-btn primary" href="<?=e($ticketUrl)?>" target="_blank" rel="noopener"><i class="fa-solid fa-ticket"></i> Download E-Ticket</a>
     <?php if($waUrl): ?><a class="bta-myb-btn whatsapp" href="<?=e($waUrl)?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Contact Us via WhatsApp</a><?php endif; ?>
     <a class="bta-myb-btn muted" href="<?=e(BASE_URL.'/my-booking')?>"><i class="fa-solid fa-arrow-left"></i> Check Another Booking</a>
    </div>
    <div class="bta-myb-price">
     <span>Payment Status: <strong><?=e($paymentLabel)?></strong></span>
     <span>Total Booking: <strong>IDR <?=number_format($total,0,',','.')?></strong></span>
    </div>
   </div>
  </section>
 <?php endif; ?>
 </div>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>
