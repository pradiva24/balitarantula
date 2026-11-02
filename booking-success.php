<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/eticket-config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$db = db();
$code = trim((string)($_GET['code'] ?? $_GET['booking'] ?? ''));

if ($code === '') {
    http_response_code(404);
    $title = 'Booking Not Found | ' . site_brand_name();
    require __DIR__ . '/includes/header.php';
    echo '<main style="max-width:800px;margin:100px auto;padding:40px 20px;text-align:center;color:#fff;"><h1>Booking Not Found</h1><p>Please use the direct link provided in your booking confirmation.</p><a class="button button-orange" style="margin-top:20px;display:inline-block;" href="' . e(BASE_URL . '/packages') . '">BACK TO PACKAGES</a></main>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$st = $db->prepare("
    SELECT b.*, p.name AS package_name, p.short_description, p.cover_image, p.price_adult, p.price_child, p.booking_mode, pa.name AS pickup_area_name 
    FROM bookings b 
    LEFT JOIN packages p ON p.id = b.package_id 
    LEFT JOIN pickup_areas pa ON pa.id = b.pickup_area_id 
    WHERE b.booking_code = ? 
    LIMIT 1
");
$st->execute([$code]);
$booking = $st->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    http_response_code(404);
    $title = 'Booking Not Found | ' . site_brand_name();
    require __DIR__ . '/includes/header.php';
    echo '<main style="max-width:800px;margin:100px auto;padding:40px 20px;text-align:center;color:#fff;"><h1>Booking Not Found</h1><p>We could not find booking <strong>' . e($code) . '</strong>.</p><a class="button button-orange" style="margin-top:20px;display:inline-block;" href="' . e(BASE_URL . '/packages') . '">BACK TO PACKAGES</a></main>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Lightweight status endpoint for Midtrans webhook poller
if (isset($_GET['ticket_status']) && $_GET['ticket_status'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'payment_status' => (string)($booking['payment_status'] ?? 'pending'),
        'booking_status' => (string)($booking['booking_status'] ?? 'new'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Print View Handler — standalone E-ticket layout matching the PDF design.
if (isset($_GET['print']) && $_GET['print'] === '1') {
    $siteName = site_brand_name();
    $companyPhone = trim((string)setting('site_whatsapp', ''));
    $siteEmail = trim((string)setting('site_email', ''));
    $siteAddress = trim((string)setting('site_address', 'Bali, Indonesia'));
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=420x420&margin=12&format=png&data=' . rawurlencode((string)$booking['booking_code']);
    $isTandem = (($booking['booking_mode'] ?? 'person') === 'tandem');
    $participantLabel = $isTandem ? '2 Persons (1 Tandem Unit)' : ((int)$booking['adults'].' Adults'.((int)$booking['children'] > 0 ? ', '.(int)$booking['children'].' Children' : ''));
    $pickupLocation = trim((string)($booking['hotel_name'] ?? ''));
    if (!empty($booking['pickup_area_name']) && stripos($pickupLocation, (string)$booking['pickup_area_name']) === false) $pickupLocation = trim($pickupLocation.', '.$booking['pickup_area_name'], ', ');
    $bookingStatus = strtoupper((string)($booking['booking_status'] ?? 'confirmed'));
    if ($bookingStatus === 'NEW') $bookingStatus = 'CONFIRMED';
    $paymentStatus = strtolower((string)($booking['payment_status'] ?? 'pending'));
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
      <title><?=e($booking['booking_code'])?> - E-Ticket</title>
      <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800;900&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
      <style>
        @page{size:A4 portrait;margin:0}
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:#111713;color:#171a18;font-family:Inter,Arial,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .ticket-page{width:210mm;min-height:297mm;margin:0 auto;padding:10mm;background:#111713;position:relative;overflow:hidden}
        .ticket-page:before{content:"";position:absolute;right:-35mm;top:-40mm;width:100mm;height:80mm;background:#ff6500;opacity:.14;transform:rotate(-20deg);pointer-events:none}
        .ticket-hero{height:62mm;position:relative;padding:6mm 7mm;color:#fff;background:linear-gradient(90deg,rgba(8,13,10,.96),rgba(8,13,10,.78)),url('<?=e(asset_url($booking['cover_image'] ?? ''))?>') center/cover no-repeat;border-top:3mm solid #ff6500;overflow:hidden}
        .brand{font:400 21pt/.85 'Barlow Condensed',Impact,sans-serif;letter-spacing:-.5px;text-transform:uppercase;color:#fff}
        .brand small{display:block;color:#ff6500;font:400 9pt/1 Inter,Arial,sans-serif;letter-spacing:2px;margin-top:3mm}
        .ticket-hero h1{font:400 37pt/.9 'Barlow Condensed',Impact,sans-serif;letter-spacing:.4px;margin:7mm 0 2mm;color:#fff}
        .ticket-hero .ribbon{display:inline-block;background:#ff6500;color:#fff;font:400 13pt/1 'Barlow Condensed',Impact,sans-serif;padding:2mm 5mm;transform:skew(-5deg)}
        .ticket-hero p{font-size:9pt;color:#e6e9e6;margin:4mm 0 0}
        .ticket-main{height:151mm;background:#fff;border-radius:5mm;display:grid;grid-template-columns:1fr 62mm;overflow:hidden;position:relative;margin-top:-1mm}
        .ticket-left{padding:7mm 7mm 6mm}
        .ticket-right{border-left:1px dashed #454a46;padding:6mm 5mm;text-align:center;display:flex;flex-direction:column;align-items:center}
        .section-label{font:400 13pt 'Barlow Condensed',Impact,sans-serif;color:#171a18;margin:0 0 4mm}
        .section-label i{color:#ff6500;margin-right:2mm}
        .code-line{display:flex;align-items:center;gap:3mm;margin-bottom:5mm}
        .code-value{font:400 21pt/1 'Barlow Condensed',Impact,sans-serif;background:#fff0e4;padding:3mm 4mm;border-radius:3mm;overflow-wrap:anywhere;flex:1}
        .status{font-size:8pt;font-weight:400;padding:2.5mm 3mm;border-radius:3mm;background:#e0f8e5;color:#15803d;white-space:nowrap}
        .package-row{display:flex;gap:4mm;padding:4mm 0;border-top:1px solid #e3e6e3;border-bottom:1px solid #e3e6e3;margin-bottom:3mm;align-items:center}
        .package-row img{width:42mm;height:27mm;object-fit:cover;border-radius:3mm}
        .package-row h2{font:400 17pt/1 'Barlow Condensed',Impact,sans-serif;margin:0 0 2mm}
        .package-row p{font-size:8pt;line-height:1.4;color:#5d665f;margin:0}
        .info-row{display:grid;grid-template-columns:42mm 4mm 1fr;gap:2mm;align-items:start;padding:2.4mm 0;border-bottom:1px solid #e5e8e5;font-size:8.5pt}
        .info-row b{font-weight:400}
        .info-row .key{font-weight:400}
        .ticket-stamp{background:#ff6500;color:#fff;font:400 20pt 'Barlow Condensed',Impact,sans-serif;padding:2mm 0;border-radius:3mm;width:100%;margin-bottom:6mm}
        .qr{width:43mm;height:43mm;object-fit:contain;margin:0 auto 3mm}
        .qr-code{font-size:8.5pt;font-weight:400;overflow-wrap:anywhere;margin-bottom:5mm}
        .right-rule{width:100%;border-top:1px solid #cfd4cf;margin:2mm 0 5mm}
        .right-brand{font:400 19pt/.85 'Barlow Condensed',Impact,sans-serif;text-transform:uppercase}
        .right-brand span{display:block;color:#ff6500;font-size:10pt;margin-top:2mm}
        .right-address{font-size:8pt;line-height:1.45;text-align:left;margin-top:6mm;display:flex;gap:2mm}
        .right-address i{color:#ff6500}
        .notes{background:#fff5ec;border-radius:4mm;margin-top:3mm;padding:5mm 7mm;min-height:35mm}
        .notes h3{font:400 16pt 'Barlow Condensed',Impact,sans-serif;color:#f06400;margin:0 0 3mm}
        .notes ul{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:1fr 1fr;gap:2mm 5mm}
        .notes li{font-size:8pt;line-height:1.35}
        .notes li i{color:#ff6500;margin-right:2mm}
        .ticket-footer{margin-top:3mm;min-height:18mm;background:#090d0b;color:#fff;padding:4mm 7mm;display:flex;justify-content:space-between;align-items:center}
        .footer-brand{font:400 15pt/.85 'Barlow Condensed',Impact,sans-serif}
        .footer-brand span{display:block;color:#ff6500;font-size:8pt;margin-top:1.5mm}
        .footer-contact{text-align:right;font-size:7.5pt;line-height:1.6;color:#e5e8e5}
        .print-tools{width:210mm;margin:8px auto;display:flex;justify-content:flex-end;gap:8px}
        .print-tools button{border:0;border-radius:8px;background:#ff6500;color:#fff;font-weight:400;padding:11px 16px;cursor:pointer}
        @media screen{body{padding:18px 0}.ticket-page{box-shadow:0 15px 50px #0008}}
        @media print{.print-tools{display:none!important}.ticket-page{margin:0;box-shadow:none}}
      </style>
    </head>
    <body>
      <div class="print-tools"><button type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save as PDF</button></div>
      <main class="ticket-page">
        <header class="ticket-hero">
          <div class="brand">Bali Tarantula<small>Adventure</small></div>
          <h1>E-TICKET</h1><span class="ribbon">BOOKING CONFIRMATION</span>
          <p>Please present this e-ticket at the registration desk upon arrival.</p>
        </header>
        <section class="ticket-main">
          <div class="ticket-left">
            <h2 class="section-label"><i class="fa-regular fa-calendar-check"></i> Booking Code</h2>
            <div class="code-line"><div class="code-value"><?=e($booking['booking_code'])?></div><span class="status"><i class="fa-solid fa-circle-check"></i> <?=e($bookingStatus)?></span></div>
            <div class="package-row">
              <?php if(!empty($booking['cover_image'])): ?><img src="<?=e(asset_url($booking['cover_image']))?>" alt="<?=e($booking['package_name'])?>"><?php endif; ?>
              <div><h2><?=e($booking['package_name'])?></h2><p><?=e($booking['short_description'] ?: 'Explore Bali’s scenic off-road trails.')?></p></div>
            </div>
            <div class="info-row"><span class="key">Activity Date</span><span>:</span><b><?=date('D, d M Y',strtotime($booking['booking_date']))?></b></div>
            <div class="info-row"><span class="key">Activity Time</span><span>:</span><b><?=e(substr((string)($booking['activity_time'] ?: '09:00'),0,5))?></b></div>
            <div class="info-row"><span class="key">Participants</span><span>:</span><b><?=e($participantLabel)?></b></div>
            <div class="info-row"><span class="key">Pickup Service</span><span>:</span><b><?=$booking['pickup_required']?'Yes':'No'?></b></div>
            <div class="info-row"><span class="key">Pickup Location</span><span>:</span><b><?=$booking['pickup_required']?e($pickupLocation ?: 'Hotel / Villa'):'Self Arrival'?></b></div>
            <div class="info-row"><span class="key">Hotel or Villa Name &amp; Room Number</span><span>:</span><b><?=$booking['pickup_required']?e(trim((string)($booking['hotel_name']??'')).((trim((string)($booking['room_number']??''))!=='')?' / Room '.trim((string)$booking['room_number']):'')):'-'?></b></div>
            <div class="info-row"><span class="key">Pickup Time</span><span>:</span><b><?=$booking['pickup_required']?e(substr((string)($booking['pickup_time'] ?: '—'),0,5)):'-'?></b></div>
            <div class="info-row"><span class="key">Payment Status</span><span>:</span><b><?=e(strtoupper((string)$booking['payment_status']))?></b></div>
            <div class="info-row"><span class="key">Total Payment</span><span>:</span><b style="color:#f06400;font-size:11pt"><?=e(rupiah($booking['total']))?></b></div>
          </div>
          <aside class="ticket-right">
            <div class="ticket-stamp">E-TICKET</div>
            <img class="qr" src="<?=e($qrUrl)?>" alt="Booking QR Code">
            <div class="qr-code"><?=e($booking['booking_code'])?></div>
            <div class="right-rule"></div>
            <div class="right-brand">Bali Tarantula<span>Adventure</span></div>
            <div class="right-address"><i class="fa-solid fa-location-dot"></i><span><?=e($siteAddress)?></span></div>
          </aside>
        </section>
        <section class="notes">
          <h3><i class="fa-solid fa-circle-info"></i> Important Notes</h3>
          <ul>
            <li><i class="fa-solid fa-check"></i>Bring this e-ticket (digital or printed) to the registration counter.</li>
            <li><i class="fa-solid fa-check"></i>Arrive at least 15 minutes before the activity time.</li>
            <li><i class="fa-solid fa-check"></i>Wear comfortable clothes and closed shoes.</li>
            <li><i class="fa-solid fa-check"></i>Follow the safety instructions from your guide.</li>
          </ul>
        </section>
        <footer class="ticket-footer">
          <div class="footer-brand">BALI TARANTULA<span>ADVENTURE</span></div>
          <div class="footer-contact"><?=e($companyPhone ?: $siteEmail)?><br><?=e($siteEmail)?><br>www.balitarantula.com</div>
        </footer>
      </main>
      <script>window.addEventListener('load',()=>{const images=[...document.images];Promise.all(images.map(img=>img.complete?Promise.resolve():new Promise(resolve=>{img.onload=img.onerror=resolve}))).then(()=>setTimeout(()=>window.print(),350));});</script>
    </body></html>
    <?php
    exit;
}

$title = 'Booking Confirmation | ' . site_brand_name();
$total = (float)$booking['total'];
$guestCount = (int)$booking['adults'] + (int)$booking['children'];
$waCrew = trim((string)setting('site_whatsapp', '+628133913939'));
$cleanWa = preg_replace('/\D+/', '', $waCrew);
$waChatUrl = $cleanWa ? "https://wa.me/{$cleanWa}?text=" . rawurlencode("Hello Bali Tarantula, I want to confirm my booking: " . $booking['booking_code']) : '#';

require __DIR__ . '/includes/header.php';
?>

<!-- Google Fonts: Barlow Condensed & Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style id="bta-success-modern-theme">
.bta-success-page{--success-orange:#ff6500;--success-dark:#171a18;--success-muted:#68716c;--success-line:#e7e9e6;background:#f3f5f3;color:#171a18;font-family:'Inter',Arial,sans-serif;padding:0 0 76px}
.bta-success-page *{box-sizing:border-box}
.bta-success-page .success-wrap{width:min(1180px,calc(100% - 40px));margin:0 auto;padding:36px 0 0}
.bta-success-page .success-hero{position:relative;isolation:isolate;overflow:hidden;min-height:285px;display:flex;align-items:center;background:#151a17;border-radius:0 0 24px 24px;color:#fff}
.bta-success-page .success-hero:before{content:"";position:absolute;z-index:-2;inset:0;background:linear-gradient(90deg,rgba(8,13,10,.96) 0%,rgba(8,13,10,.84) 42%,rgba(8,13,10,.12) 100%),url('<?=e(asset_url($booking['cover_image'] ?? ''))?>') center 48%/cover no-repeat}
.bta-success-page .success-hero:after{content:"";position:absolute;z-index:-1;right:-80px;top:-160px;width:380px;height:260px;background:var(--success-orange);opacity:.14;filter:blur(70px);border-radius:50%}
.bta-success-page .hero-inner{width:min(1180px,calc(100% - 40px));margin:0 auto;padding:42px 0 48px}
.bta-success-page .success-check{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;background:#16a34a;color:#fff;font-size:27px;box-shadow:0 0 0 7px rgba(22,163,74,.16);margin-bottom:20px}
.bta-success-page .hero-kicker{font-size:12px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#ff9b5d;margin:0 0 8px}
.bta-success-page .success-hero h1{font-family:'Barlow Condensed',Impact,sans-serif;font-size:clamp(42px,6vw,70px);line-height:.95;text-transform:uppercase;font-weight:900;color:#fff;margin:0 0 12px}
.bta-success-page .success-hero h1 span{color:#ff741c}
.bta-success-page .success-hero p{font-size:15px;line-height:1.7;color:#e4e8e5;max-width:560px;margin:0}
.bta-success-page .success-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.95fr);gap:18px;align-items:start}
.bta-success-page .success-card{background:#fff;border:1px solid #e5e8e5;border-radius:17px;box-shadow:0 10px 32px rgba(20,30,24,.06);padding:25px}
.bta-success-page .card-heading{display:flex;align-items:center;gap:11px;font-family:'Barlow Condensed',Impact,sans-serif;font-size:25px;font-weight:800;color:#171a18;margin:0 0 18px}
.bta-success-page .card-heading i{color:var(--success-orange);font-size:21px}
.bta-success-page .booking-code-panel{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:17px 18px;border:1px dashed #ffad78;background:#fff8f2;border-radius:12px;margin-bottom:24px}
.bta-success-page .booking-code-label{display:block;font-size:10px;font-weight:800;letter-spacing:1.5px;color:#8a6b58;text-transform:uppercase;margin-bottom:5px}
.bta-success-page .booking-code-value{font-family:'Barlow Condensed',Impact,sans-serif;font-size:clamp(22px,3.2vw,34px);line-height:1.1;font-weight:900;letter-spacing:.4px;color:#171a18;overflow-wrap:anywhere}
.bta-success-page .copy-code{flex:0 0 auto;border:1px solid var(--success-orange);background:#fff;color:#e95b00;border-radius:9px;padding:10px 14px;font-weight:800;cursor:pointer}
.bta-success-page .copy-code:hover{background:#fff0e5}
.bta-success-page .package-preview{display:flex;align-items:center;gap:16px;padding-bottom:20px;border-bottom:1px solid var(--success-line);margin-bottom:12px}
.bta-success-page .package-preview img{width:150px;height:105px;object-fit:cover;border-radius:11px;background:#edf0ed}
.bta-success-page .package-preview h3{font-family:'Barlow Condensed',Impact,sans-serif;font-size:25px;line-height:1.05;font-weight:800;color:#171a18;margin:0 0 6px}
.bta-success-page .package-preview p{font-size:13px;line-height:1.55;color:var(--success-muted);margin:0}
.bta-success-page .detail-list{margin:0}
.bta-success-page .detail-row{display:grid;grid-template-columns:minmax(135px,.8fr) 16px minmax(0,1.2fr);gap:9px;align-items:start;padding:12px 0;border-bottom:1px solid #edf0ed;font-size:13px}
.bta-success-page .detail-row:last-child{border-bottom:0}
.bta-success-page .detail-row dt{font-weight:700;color:#555f59}
.bta-success-page .detail-row .colon{color:#a1aaa4}
.bta-success-page .detail-row dd{margin:0;font-weight:700;color:#171a18;overflow-wrap:anywhere}
.bta-success-page .detail-row dd.amount{font-family:'Barlow Condensed',Impact,sans-serif;font-size:25px;color:var(--success-orange)}
.bta-success-page .status-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:30px;font-size:11px;font-weight:800;text-transform:capitalize;background:#fff4df;color:#a16207}
.bta-success-page .status-pill.paid{background:#e5f8e9;color:#15803d}
.bta-success-page .side-stack{display:grid;gap:18px}
.bta-success-page .side-info{display:flex;gap:13px;padding:14px 0;border-bottom:1px solid var(--success-line)}
.bta-success-page .side-info:last-child{border:0;padding-bottom:0}
.bta-success-page .side-icon{flex:0 0 36px;width:36px;height:36px;display:grid;place-items:center;border-radius:10px;background:#fff0e6;color:var(--success-orange);font-size:17px}
.bta-success-page .side-info strong{display:block;font-size:13px;color:#171a18;margin-bottom:4px}
.bta-success-page .side-info span{display:block;font-size:12px;line-height:1.6;color:#657069}
.bta-success-page .notes-panel{background:#fff8f1;border:1px solid #ffead9;border-radius:12px;padding:17px}
.bta-success-page .notes-panel h3{font-family:'Barlow Condensed',Impact,sans-serif;font-size:22px;color:#e95b00;margin:0 0 10px}
.bta-success-page .notes-panel ul{list-style:none;margin:0;padding:0;display:grid;gap:9px}
.bta-success-page .notes-panel li{font-size:12px;line-height:1.5;color:#414a44;display:flex;gap:9px}
.bta-success-page .notes-panel li i{color:#16a34a;margin-top:2px}
.bta-success-page .success-actions{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:12px;margin-top:20px}
.bta-success-page .success-action{min-height:58px;display:flex;align-items:center;justify-content:center;gap:10px;padding:13px 16px;border-radius:11px;text-decoration:none;font-size:13px;font-weight:800;text-align:center;border:1px solid #e1e5e1;background:#fff;color:#171a18;transition:.2s}
.bta-success-page .success-action i{font-size:19px}
.bta-success-page .success-action.primary{background:var(--success-orange);border-color:var(--success-orange);color:#fff;box-shadow:0 6px 18px rgba(255,101,0,.2)}
.bta-success-page .success-action.whatsapp i{color:#16a34a}
.bta-success-page .success-action:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(20,30,24,.1)}
@media(max-width:850px){.bta-success-page .success-grid{grid-template-columns:1fr}.bta-success-page .side-stack{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.bta-success-page{padding-bottom:42px}.bta-success-page .success-hero{min-height:250px;border-radius:0}.bta-success-page .hero-inner{padding:34px 0}.bta-success-page .success-wrap{width:calc(100% - 26px);padding-top:18px}.bta-success-page .success-card{padding:18px;border-radius:14px}.bta-success-page .side-stack{grid-template-columns:1fr}.bta-success-page .booking-code-panel{align-items:flex-start;flex-direction:column}.bta-success-page .package-preview img{width:112px;height:90px}.bta-success-page .package-preview h3{font-size:22px}.bta-success-page .detail-row{grid-template-columns:112px 10px minmax(0,1fr);gap:5px;font-size:12px}.bta-success-page .success-actions{grid-template-columns:1fr}.bta-success-page .success-action{min-height:52px}.bta-success-page .card-heading{font-size:23px}}
</style>

<div class="bta-success-page">
  <section class="success-hero">
    <div class="hero-inner">
      <div class="success-check"><i class="fa-solid fa-check" aria-hidden="true"></i></div>
      <p class="hero-kicker">Bali Tarantula Adventure · Reservation</p>
      <h1>Booking <span>Confirmed!</span></h1>
      <p>Thank you, <?=e($booking['customer_name'])?>. Your reservation has been successfully received. Keep your booking code for check-in and any assistance from our crew.</p>
    </div>
  </section>

  <main class="success-wrap">
    <div class="success-grid">
      <section class="success-card">
        <h2 class="card-heading"><i class="fa-solid fa-ticket" aria-hidden="true"></i> Booking Details</h2>
        <div class="booking-code-panel">
          <div>
            <span class="booking-code-label">Your booking reference</span>
            <div class="booking-code-value" id="btaBookingCode"><?=e($booking['booking_code'])?></div>
          </div>
          <button class="copy-code" type="button" id="copyBookingCode"><i class="fa-regular fa-copy"></i> Copy</button>
        </div>

        <div class="package-preview">
          <?php if(!empty($booking['cover_image'])): ?>
            <img src="<?=e(asset_url($booking['cover_image']))?>" alt="<?=e($booking['package_name'])?>">
          <?php else: ?>
            <div class="side-icon" style="width:150px;height:105px"><i class="fa-solid fa-mountain-sun"></i></div>
          <?php endif; ?>
          <div>
            <h3><?=e($booking['package_name'])?></h3>
            <p><?=e($booking['short_description'] ?: 'Get ready for an unforgettable Bali adventure.')?></p>
          </div>
        </div>

        <dl class="detail-list">
          <div class="detail-row"><dt>Customer Name</dt><span class="colon">:</span><dd><?=e($booking['customer_name'])?></dd></div>
          <div class="detail-row"><dt>Activity Date</dt><span class="colon">:</span><dd><?=date('D, d M Y',strtotime($booking['booking_date']))?></dd></div>
          <div class="detail-row"><dt>Activity Time</dt><span class="colon">:</span><dd><?=e(substr((string)($booking['activity_time'] ?: '09:00'),0,5))?></dd></div>
          <div class="detail-row"><dt>Participants</dt><span class="colon">:</span><dd><?=($booking['booking_mode']??'person')==='tandem'?'2 Persons (1 Tandem Unit)':((int)$booking['adults'].' Adults'.((int)$booking['children']>0?', '.(int)$booking['children'].' Children':''))?></dd></div>
          <div class="detail-row"><dt>Payment Status</dt><span class="colon">:</span><dd><span class="status-pill <?=strcasecmp((string)$booking['payment_status'],'paid')===0?'paid':''?>"><i class="fa-solid <?=strcasecmp((string)$booking['payment_status'],'paid')===0?'fa-circle-check':'fa-clock'?>"></i><?=e($booking['payment_status'])?></span></dd></div>
          <div class="detail-row"><dt>Total Payment</dt><span class="colon">:</span><dd class="amount"><?=rupiah($total)?></dd></div>
        </dl>
      </section>

      <div class="side-stack">
        <section class="success-card">
          <h2 class="card-heading"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Activity Information</h2>
          <div class="side-info">
            <div class="side-icon"><i class="fa-solid fa-map-location-dot"></i></div>
            <div><strong>Location</strong><span><?=e(trim((string)setting('site_address','Bali Tarantula Adventure, Ubud, Bali, Indonesia')))?></span></div>
          </div>
          <div class="side-info">
            <div class="side-icon"><i class="fa-regular fa-clock"></i></div>
            <div><strong>Schedule</strong><span>Activity Time: <?=e(substr((string)($booking['activity_time'] ?: '09:00'),0,5))?><br>Please arrive 15 minutes before your activity.</span></div>
          </div>
          <div class="side-info">
            <div class="side-icon"><i class="fa-solid fa-car"></i></div>
            <div><strong>Pickup Service <span class="status-pill" style="margin-left:5px;background:<?=$booking['pickup_required']?'#e5f8e9;color:#15803d':'#eef1ef;color:#59645d'?>"><?=$booking['pickup_required']?'Included':'Self Arrival'?></span></strong>
              <span><?php if($booking['pickup_required']): ?>Pickup Time: <?=e(substr((string)($booking['pickup_time'] ?: '—'),0,5))?><br>Pickup Location: <?=e(trim((string)($booking['hotel_name'] ?: $booking['pickup_area_name'] ?: 'Hotel / Villa')))?></span><?php else: ?>No hotel pickup selected. Please arrive at the activity location directly.</span><?php endif; ?></div>
          </div>
        </section>

        <section class="success-card">
          <div class="notes-panel">
            <h3><i class="fa-solid fa-circle-info"></i> Important Notes</h3>
            <ul>
              <li><i class="fa-solid fa-check"></i>Please bring a change of clothes.</li>
              <li><i class="fa-solid fa-check"></i>Wear comfortable clothes and closed shoes.</li>
              <li><i class="fa-solid fa-check"></i>Follow the safety instructions from our guide.</li>
              <li><i class="fa-solid fa-check"></i>Please arrive 15 minutes before your scheduled time.</li>
            </ul>
          </div>
        </section>
      </div>
    </div>

    <div class="success-actions">
      <a class="success-action primary" href="<?=BASE_URL?>/booking-success?code=<?=urlencode($booking['booking_code'])?>&amp;print=1" target="_blank"><i class="fa-solid fa-file-arrow-down"></i> Download E-Ticket</a>
      <a class="success-action whatsapp" href="<?=$waChatUrl?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Contact via WhatsApp</a>
      <a class="success-action" href="<?=BASE_URL?>/"><i class="fa-solid fa-house"></i> Back to Homepage</a>
    </div>
  </main>
</div>

<script>
(function(){
  const button=document.getElementById('copyBookingCode');
  const code=document.getElementById('btaBookingCode');
  if(!button||!code)return;
  button.addEventListener('click',async function(){
    const value=code.textContent.trim();
    try{
      await navigator.clipboard.writeText(value);
      button.innerHTML='<i class="fa-solid fa-check"></i> Copied';
    }catch(error){
      const range=document.createRange();range.selectNodeContents(code);
      const selection=window.getSelection();selection.removeAllRanges();selection.addRange(range);
      button.innerHTML='<i class="fa-solid fa-copy"></i> Select & Copy';
    }
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
