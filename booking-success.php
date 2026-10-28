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
    SELECT b.*, p.name AS package_name, p.short_description, p.cover_image, p.price_adult, p.price_child, pa.name AS pickup_area_name 
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

// Print View Handler
if (isset($_GET['print']) && $_GET['print'] === '1') {
    $siteName = site_brand_name();
    $companyPhone = trim((string)setting('site_whatsapp', ''));
    $siteEmail = trim((string)setting('site_email', ''));
    $siteAddress = trim((string)setting('site_address', 'Bali, Indonesia'));
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&format=png&data=' . rawurlencode((string)$booking['booking_code']);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title><?=e($booking['booking_code'])?> - E-Ticket</title>
      <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #0f172a; margin: 0; padding: 0; background: #fff; font-size: 13px; line-height: 1.5; }
        .ticket-box { border: 2px solid #0f172a; border-radius: 12px; padding: 24px; max-width: 720px; margin: 0 auto; }
        .ticket-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 16px; margin-bottom: 20px; }
        .ticket-header h1 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; color: #ff5500; }
        .ticket-header p { margin: 4px 0 0; color: #64748b; font-size: 12px; }
        .ticket-code { font-size: 20px; font-weight: 900; background: #f1f5f9; padding: 6px 14px; border-radius: 6px; letter-spacing: 1px; }
        .ticket-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px; }
        .info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #cbd5e1; }
        .info-row span { color: #64748b; }
        .info-row b { text-align: right; }
        .qr-box { text-align: center; }
        .qr-box img { width: 140px; height: 140px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 4px; }
        .qr-box p { font-size: 11px; color: #64748b; margin-top: 6px; }
        .notice-box { background: #fff7ed; border-left: 4px solid #ff5500; padding: 12px 16px; font-size: 12px; line-height: 1.5; color: #9a3412; margin-top: 20px; }
      </style>
    </head>
    <body>
      <div class="ticket-box">
        <div class="ticket-header">
          <div>
            <h1><?=e($siteName)?></h1>
            <p>Official Adventure Reservation Voucher</p>
          </div>
          <div class="ticket-code"><?=e($booking['booking_code'])?></div>
        </div>

        <div class="ticket-grid">
          <div>
            <div class="info-row"><span>Rider Name</span><b><?=e($booking['customer_name'])?></b></div>
            <div class="info-row"><span>Adventure Track</span><b><?=e($booking['package_name'])?></b></div>
            <div class="info-row"><span>Date</span><b><?=date('D, M d, Y', strtotime($booking['booking_date']))?></b></div>
            <div class="info-row"><span>Time Slot</span><b><?=e(substr((string)($booking['activity_time'] ?: '09:00'), 0, 5))?></b></div>
            <div class="info-row"><span>Participants</span><b><?=(int)$booking['adults']?> Adults <?=((int)$booking['children'] > 0 ? ', ' . (int)$booking['children'] . ' Children' : '')?></b></div>
            <div class="info-row"><span>Transport</span><b><?=$booking['pickup_required'] ? e($booking['pickup_area_name'] . ' — ' . $booking['hotel_name']) : 'Self Arrival'?></b></div>
            <div class="info-row"><span>Payment Status</span><b><?=strtoupper((string)$booking['payment_status'])?></b></div>
            <div class="info-row"><span>Total Paid/Due</span><b><?=rupiah($booking['total'])?></b></div>
          </div>
          <div class="qr-box">
            <img src="<?=e($qrUrl)?>" alt="QR Code">
            <p>Show voucher upon arrival</p>
          </div>
        </div>

        <div class="notice-box">
          Please arrive 15 minutes prior to your scheduled time. Show this digital or printed e-ticket to the registration reception. Safety briefing and gear fitment take place before entering the track.
        </div>
      </div>
      <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    </body>
    </html>
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
:root {
  --bta-bg: #f5f4ef;
  --bta-panel: #ffffff;
  --bta-heading: #191919;
  --bta-text: #5d5b56;
  --bta-muted: #77736b;
  --bta-border: #dedbd4;
  --bta-orange: #ff5a1f;
  --bta-orange-hover: #ff743f;
  --bta-orange-glow: rgba(255, 90, 31, 0.35);
  --bta-green: #16a34a;
  --bta-font-display: 'Barlow Condensed', sans-serif;
  --bta-font-body: 'Inter', sans-serif;
}

body {
  background: var(--bta-bg);
  color: var(--bta-text);
  font-family: var(--bta-font-body);
  line-height: 1.6;
  margin: 0;
  padding: 0;
  -webkit-font-smoothing: antialiased;
}

.bta-success-page * {
  box-sizing: border-box;
}

.bta-success-page .wrap {
  width: min(1280px, 92%);
  margin: 0 auto;
}

/* Page Banner */
.page-banner {
  position: relative;
  min-height: 380px;
  display: flex;
  align-items: center;
  background: linear-gradient(112deg, #171717 0%, #242321 65%, #30271f 100%);
  border-bottom: 3px solid var(--bta-orange);
  overflow: hidden;
  padding: 120px 0 60px;
}

.page-banner::before {
  content: "";
  position: absolute;
  inset: 0;
  background: url('https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1500&q=90') center/cover no-repeat;
  opacity: 0.22;
  filter: saturate(1.1);
}

.page-banner::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(23,23,23,0.7) 0%, rgba(23,23,23,0.92) 100%);
}

.page-banner-content {
  position: relative;
  z-index: 2;
}

.page-banner-content .eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-family: var(--bta-font-display);
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 2.5px;
  text-transform: uppercase;
  color: var(--bta-orange);
  margin: 0 0 10px;
}

.page-banner-content .eyebrow-line {
  display: inline-block;
  width: 24px;
  height: 2px;
  background: var(--bta-orange);
}

.page-banner-content h1 {
  font-family: var(--bta-font-display);
  font-size: clamp(40px, 6vw, 76px);
  font-weight: 900;
  line-height: 0.95;
  letter-spacing: -0.5px;
  text-transform: uppercase;
  color: #ffffff;
  margin: 0 0 16px;
}

.page-banner-content h1 span {
  color: var(--bta-orange);
}

.page-banner-content p {
  font-family: var(--bta-font-body);
  font-size: 16px;
  color: #e0ded9;
  max-width: 600px;
  margin: 0 0 20px;
}

/* Success Card */
.success-wrap {
  max-width: 780px;
  margin: 0 auto;
  padding: 60px 20px 120px;
}

.success-card {
  background: var(--bta-panel);
  border: 1px solid var(--bta-border);
  border-top: 4px solid var(--bta-orange);
  border-radius: 16px;
  padding: clamp(28px, 5vw, 48px);
  box-shadow: 0 16px 42px rgba(30, 25, 15, 0.06);
}

.check-circle {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: rgba(22, 163, 74, 0.12);
  border: 1px solid rgba(22, 163, 74, 0.35);
  color: var(--bta-green);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  font-weight: 900;
  margin-bottom: 20px;
}

.success-card h2 {
  font-family: var(--bta-font-display);
  font-size: clamp(34px, 5vw, 52px);
  font-weight: 900;
  text-transform: uppercase;
  color: var(--bta-heading);
  line-height: 1;
  margin: 0 0 10px;
}

.success-card > p {
  font-size: 16px;
  color: var(--bta-text);
  margin: 0 0 24px;
}

.booking-code-box {
  padding: 16px 20px;
  background: #faf8f5;
  border: 1px solid var(--bta-border);
  border-radius: 10px;
  font-family: var(--bta-font-display);
  font-size: 26px;
  font-weight: 900;
  letter-spacing: 2px;
  color: var(--bta-orange);
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.booking-code-box small {
  font-family: var(--bta-font-body);
  font-size: 12px;
  color: var(--bta-muted);
  letter-spacing: 0;
  font-weight: 500;
}

.details-rows {
  margin: 24px 0;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px dashed var(--bta-border);
  font-size: 15px;
}

.detail-row span {
  color: var(--bta-muted);
}

.detail-row strong {
  color: var(--bta-heading);
  text-align: right;
  max-width: 65%;
  overflow-wrap: anywhere;
}

.notice-box {
  background: rgba(255, 90, 31, 0.08);
  border-left: 3px solid var(--bta-orange);
  padding: 16px 18px;
  border-radius: 0 8px 8px 0;
  font-size: 14px;
  line-height: 1.6;
  color: var(--bta-text);
  margin: 30px 0;
}

.success-actions {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  margin-top: 30px;
}

.button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 26px;
  font-family: var(--bta-font-display);
  font-size: 16px;
  font-weight: 900;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  text-decoration: none;
  border-radius: 8px;
  border: 0;
  cursor: pointer;
  transition: all 0.25s ease;
  white-space: nowrap;
}

.button-orange {
  background: var(--bta-orange);
  color: #111111 !important;
  box-shadow: 0 4px 20px var(--bta-orange-glow);
}

.button-orange:hover {
  background: var(--bta-orange-hover);
  transform: translateY(-2px);
  color: #111111 !important;
}

.button-outline {
  background: #ffffff;
  color: var(--bta-heading);
  border: 1px solid var(--bta-border);
}

.button-outline:hover {
  background: #f0eee8;
  border-color: #c9c6c0;
  color: #111111;
}

@media (max-width: 600px) {
  .booking-code-box {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    font-size: 22px;
  }
  .success-actions {
    flex-direction: column;
  }
  .button {
    width: 100%;
  }
}
</style>

<div class="bta-success-page">
  <!-- Page Banner -->
  <section class="page-banner">
    <div class="wrap page-banner-content">
      <p class="eyebrow"><span class="eyebrow-line"></span>BOOKING REQUEST</p>
      <h1>YOUR NEXT <span>ADVENTURE.</span></h1>
      <p>Keep your reservation code handy for any questions or follow-up with our crew.</p>
    </div>
  </section>

  <!-- Success Card Section -->
  <main class="success-wrap">
    <article class="success-card">
      <div class="check-circle"><i class="fa-solid fa-check" aria-hidden="true"></i></div>
      <h2>BOOKING RECEIVED.</h2>
      <p>Thanks, <b><?=e($booking['customer_name'])?></b>! Your adventure request has been registered in our system.</p>

      <div class="booking-code-box">
        <span><?=e($booking['booking_code'])?></span>
        <small>Official Booking Code</small>
      </div>

      <div class="details-rows">
        <div class="detail-row">
          <span>Adventure Track</span>
          <strong><?=e($booking['package_name'])?></strong>
        </div>
        <div class="detail-row">
          <span>Activity Date &amp; Time</span>
          <strong><?=date('D, M d, Y', strtotime($booking['booking_date']))?> &middot; <?=e(substr((string)($booking['activity_time'] ?: '09:00'), 0, 5))?></strong>
        </div>
        <div class="detail-row">
          <span>Participants</span>
          <strong><?=(int)$booking['adults']?> Adults <?=((int)$booking['children'] > 0 ? ', ' . (int)$booking['children'] . ' Children' : '')?></strong>
        </div>
        <div class="detail-row">
          <span>Hotel Transport</span>
          <strong><?=$booking['pickup_required'] ? e($booking['pickup_area_name'] . ' — ' . $booking['hotel_name']) : 'Ticket Only (Self Arrival)'?></strong>
        </div>
        <div class="detail-row">
          <span>Total Amount</span>
          <strong style="color:var(--bta-orange); font-size:18px;"><?=rupiah($total)?></strong>
        </div>
        <div class="detail-row">
          <span>Payment Method</span>
          <strong><?=e($booking['payment_method'])?></strong>
        </div>
        <div class="detail-row">
          <span>Payment Status</span>
          <strong style="text-transform:uppercase; color:<?=strcasecmp((string)$booking['payment_status'], 'paid') === 0 ? '#16a34a' : '#d97706'?>;">
            <?=e($booking['payment_status'])?>
          </strong>
        </div>
      </div>

      <div class="notice-box">
        An instant confirmation email and your PDF e-ticket have been generated and sent to <b><?=e($booking['customer_email'])?></b>. If you require private transfers, custom schedules, or route upgrades, contact our team on WhatsApp anytime.
      </div>

      <div class="success-actions">
        <a class="button button-orange" href="<?=BASE_URL?>/booking-success?code=<?=urlencode($booking['booking_code'])?>&amp;print=1" target="_blank">
          PRINT / DOWNLOAD E-TICKET <span><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i></span>
        </a>
        <a class="button button-outline" href="<?=$waChatUrl?>" target="_blank" rel="noopener">
          CHAT WITH CREW <span><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span>
        </a>
        <a class="button button-outline" href="<?=BASE_URL?>/">
          BACK TO HOME <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
        </a>
      </div>
    </article>
  </main>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
