<?php
require __DIR__.'/config/database.php';
require __DIR__.'/includes/functions.php';
require __DIR__.'/config/midtrans.php';

$code  = trim((string)($_GET['code'] ?? ''));
$email = trim((string)($_GET['email'] ?? ''));
$accessToken = trim((string)($_GET['token'] ?? ''));

$booking = false;

// Prefer the private booking access token when available.
// Fall back to booking code + email for older payment links.
if ($code !== '' && $accessToken !== '') {
    $s = db()->prepare("SELECT
            b.*,
            p.name AS package_name,
            p.cover_image,
            p.short_description,
            p.duration
        FROM bookings b
        LEFT JOIN packages p ON p.id = b.package_id
        WHERE b.booking_code = ?
          AND b.access_token = ?
        LIMIT 1");
    $s->execute([$code, $accessToken]);
    $booking = $s->fetch();
}

if (!$booking && $code !== '' && $email !== '') {
    $s = db()->prepare("SELECT
            b.*,
            p.name AS package_name,
            p.cover_image,
            p.short_description,
            p.duration
        FROM bookings b
        LEFT JOIN packages p ON p.id = b.package_id
        WHERE b.booking_code = ?
          AND LOWER(TRIM(b.customer_email)) = LOWER(TRIM(?))
        LIMIT 1");
    $s->execute([$code, $email]);
    $booking = $s->fetch();
}

if (!$booking) {
    http_response_code(404);
    exit('Booking not found');
}

if (($booking['payment_status'] ?? '') === 'paid') {
    header('Location: '.BASE_URL.'/booking-confirmation?code='.rawurlencode($code).'&token='.rawurlencode((string)($booking['access_token']??$accessToken)));
    exit;
}

$paymentError = '';
$token = '';
try {
    $token = midtrans_snap_token($booking);
} catch (Throwable $e) {
    $paymentError = $e->getMessage();
}

$siteName = (string)site_brand_name();
$sitePhone = (string)setting('site_phone', '');
$siteWhatsapp = (string)setting('site_whatsapp', '');
$siteEmail = (string)setting('site_email', '');
$siteLogo = (string)setting('site_logo', '');
$currency = (string)setting('currency', 'IDR');

$packageName = (string)($booking['package_name'] ?? 'Adventure Experience');
$coverImage = (string)($booking['cover_image'] ?? '');
if ($coverImage === '') {
    $coverImage = (string)setting('og_image', '');
}
if ($coverImage === '') {
    $coverImage = 'uploads/homepage/98ba0997177102d4d72e.webp';
}
$coverUrl = asset_url($coverImage);

$guestCount = (int)($booking['adults'] ?? 0) + (int)($booking['children'] ?? 0);
$guestLabel = $guestCount === 1 ? '1 Guest' : $guestCount.' Guests';
$activityDate = !empty($booking['booking_date']) ? date('d M Y', strtotime($booking['booking_date'])) : '-';
$locationLabel = 'Bali, Indonesia';

$paymentMethodLabel = ucwords(str_replace(['_', '-'], ' ', (string)($booking['payment_method'] ?? 'Midtrans')));
$total = (float)($booking['total'] ?? 0);
$totalFormatted = number_format($total, 0, ',', '.');

$destinationSuccess = BASE_URL.'/booking-confirmation?code='.rawurlencode($code).'&token='.rawurlencode((string)($booking['access_token']??$accessToken));
$destinationFallback = BASE_URL.'/booking-success?code='.rawurlencode($code);

$pageTitle = 'Secure Payment | '.$siteName;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($pageTitle)?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700;1,800;1,900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
:root{
  --green:#ff5500;
  --green-dark:#e04800;
  --green-soft:#fff5eb;
  --ink:#18231d;
  --muted:#6e7972;
  --line:#dfe8e2;
  --card:#ffffff;
  --page:#f5f8f6;
}
*{box-sizing:border-box}
html,body{margin:0;padding:0;min-height:100%;font-family:'Inter',system-ui,-apple-system,sans-serif;color:var(--ink);background:var(--page)}
h1,h2,h3,h4,h5,h6,.mid-kicker,.mid-pay-button,.mid-label,.mid-booking-code{font-family:'Barlow Condensed',sans-serif;text-transform:uppercase}
.fa-solid, .fas, [class*="fa-solid"] { font-family: "Font Awesome 6 Free" !important; font-weight: 900 !important; font-style: normal !important; text-transform: none !important; }
.fa-regular, .far, [class*="fa-regular"] { font-family: "Font Awesome 6 Free" !important; font-weight: 400 !important; font-style: normal !important; text-transform: none !important; }
.fa-brands, .fab, [class*="fa-brands"] { font-family: "Font Awesome 6 Brands" !important; font-weight: 400 !important; font-style: normal !important; text-transform: none !important; }
i[class*="fa-"], span[class*="fa-"], [class^="fa-"], [class*=" fa-"], .fa, .fas, .far, .fab { font-style: normal !important; line-height: 1 !important; text-rendering: auto !important; text-transform: none !important; -webkit-font-smoothing: antialiased !important; }
body{overflow-x:hidden}
a{color:inherit}
.mid-page{min-height:100vh;display:grid;grid-template-columns:minmax(320px,42%) minmax(0,58%);position:relative;background:#eaf1ed}
.mid-visual{position:relative;min-height:100vh;overflow:hidden;background:#174d35 url('<?=e($coverUrl)?>') center/cover no-repeat;color:#fff}
.mid-visual:before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(8,38,27,.12) 0%,rgba(7,44,29,.36) 42%,rgba(6,38,25,.9) 100%)}
.mid-visual:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(8,39,28,.18),transparent 70%)}
.mid-visual-inner{position:relative;z-index:2;height:100%;min-height:100vh;padding:26px 48px 42px;display:flex;flex-direction:column}
.mid-logo{display:inline-flex;align-items:center;gap:13px;text-decoration:none;width:max-content;max-width:100%;color:#fff}
.mid-logo img{display:block;max-width:190px;max-height:58px;width:auto;height:auto;object-fit:contain;filter:brightness(0) invert(1)}
.mid-logo-mark{width:44px;height:44px;border:2px solid rgba(255,255,255,.8);border-radius:13px;display:grid;place-items:center;font-weight:800;letter-spacing:-1px}
.mid-brand-copy{display:flex;flex-direction:column;line-height:1.05}
.mid-brand-copy strong{font-size:16px;letter-spacing:.7px}
.mid-brand-copy span{font-size:8px;letter-spacing:3px;opacity:.82;margin-top:5px}
.mid-visual-copy{margin-top:auto;max-width:530px;padding-top:90px}
.mid-kicker{display:inline-flex;align-items:center;gap:9px;text-transform:uppercase;font-size:11px;letter-spacing:2.4px;font-weight:700;margin-bottom:16px;color:#eef8f3}
.mid-kicker:before{content:"";width:34px;height:1px;background:#bde3cf}
.mid-visual-copy h1{font-size:clamp(36px,4.2vw,66px);line-height:1.03;letter-spacing:-2px;margin:0 0 18px;font-weight:800;max-width:530px}
.mid-visual-copy p{font-size:15px;line-height:1.75;color:rgba(255,255,255,.88);max-width:470px;margin:0}
.mid-visual-features{display:flex;flex-wrap:wrap;gap:12px 26px;margin-top:28px}
.mid-feature{display:flex;align-items:center;gap:9px;font-size:12px;font-weight:600;color:#fff}
.mid-feature svg{width:22px;height:22px;stroke:#d6efdf;flex:none}
.mid-side-note{margin-top:30px;font-size:11px;color:rgba(255,255,255,.68);letter-spacing:.3px}

.mid-main{min-width:0;padding:26px 5vw 42px;display:flex;align-items:center;justify-content:center;position:relative}
.mid-main-shell{width:min(840px,100%)}
.mid-topbar{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:18px;color:#435149}
.mid-secure{display:inline-flex;align-items:center;gap:9px;font-size:12px;font-weight:700}
.mid-secure svg{width:18px;height:18px;stroke:var(--green);flex:none}
.mid-lang{display:flex;align-items:center;gap:10px;font-size:12px;color:#7a847e}.mid-lang strong{color:var(--green)}
.mid-card{background:rgba(255,255,255,.97);border:1px solid rgba(210,224,215,.85);border-radius:28px;box-shadow:0 22px 65px rgba(18,57,39,.13);padding:36px}
.mid-heading{text-align:center;margin-bottom:26px}.mid-heading .mini{font-size:11px;letter-spacing:3px;font-weight:700;color:#68756e;text-transform:uppercase;display:inline-flex;align-items:center;gap:12px}.mid-heading .mini:before,.mid-heading .mini:after{content:"";display:inline-block;width:30px;height:1px;background:#d4dfd9}.mid-heading h2{font-size:clamp(34px,4vw,48px);line-height:1.08;letter-spacing:-1.5px;margin:13px 0 10px;font-weight:800}.mid-heading p{margin:0 auto;color:var(--muted);font-size:13px;line-height:1.7;max-width:630px}
.mid-booking{display:grid;grid-template-columns:minmax(0,1fr) 210px;gap:24px;background:var(--green-soft);border:1px solid #ddece4;border-radius:22px;padding:22px;margin-bottom:24px}
.mid-booking-main{display:grid;grid-template-columns:118px minmax(0,1fr);gap:18px;min-width:0}
.mid-thumb{width:118px;height:118px;border-radius:16px;overflow:hidden;background:#d7e3dc}.mid-thumb img{display:block;width:100%;height:100%;object-fit:cover}
.mid-booking-details{min-width:0}.mid-label{font-size:10px;text-transform:uppercase;letter-spacing:1.8px;font-weight:700;color:#1e6a4b;margin-bottom:6px}.mid-booking-code{font-size:21px;line-height:1.2;font-weight:800;letter-spacing:-.5px;overflow-wrap:anywhere;margin-bottom:13px}.mid-activity{font-size:15px;font-weight:700;margin-bottom:10px}.mid-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px 14px}.mid-meta-item{display:flex;align-items:center;gap:7px;font-size:12px;color:#46554d}.mid-meta-item svg{width:16px;height:16px;stroke:#17221c;flex:none}.mid-total{border-left:1px solid #d3e2da;padding-left:24px;display:flex;flex-direction:column;justify-content:center}.mid-total .mid-label{margin-bottom:6px}.mid-total strong{font-size:34px;line-height:1.05;letter-spacing:-1.5px;color:var(--green);word-break:break-word}.mid-total .small{margin-top:6px;font-size:10px;color:#77837d}
.mid-info-row{display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid var(--line);padding:0 2px 17px;margin-bottom:20px}.mid-info-item{min-width:0}.mid-info-item span{display:block;font-size:10px;color:#8a958f;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:5px}.mid-info-item strong{font-size:12px;color:#35433b;overflow-wrap:anywhere}
.mid-pay-button{appearance:none;border:0;outline:0;width:100%;border-radius:17px;background:linear-gradient(180deg,#287653 0%,#1e6546 100%);color:#fff;min-height:68px;padding:0 25px;display:flex;align-items:center;justify-content:center;gap:15px;font-family:inherit;font-size:19px;font-weight:700;cursor:pointer;box-shadow:0 14px 30px rgba(29,103,72,.18);transition:transform .2s ease,box-shadow .2s ease,opacity .2s ease}.mid-pay-button:hover{transform:translateY(-1px);box-shadow:0 18px 34px rgba(29,103,72,.24)}.mid-pay-button:disabled{opacity:.55;cursor:not-allowed;transform:none;box-shadow:none}.mid-pay-button svg{width:23px;height:23px;stroke:currentColor;flex:none}.mid-pay-arrow{font-size:29px;font-weight:400;line-height:1;margin-left:2px}
.mid-secure-note{text-align:center;color:#68746e;font-size:12px;line-height:1.6;margin:14px 0 20px}.mid-secure-note span{display:inline-flex;align-items:center;gap:8px}.mid-secure-note svg{width:16px;height:16px;stroke:var(--green)}
.mid-methods{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin:0 auto 22px}.mid-method{min-width:66px;padding:9px 11px;border:1px solid #e0e7e3;border-radius:10px;background:#fff;text-align:center;font-size:12px;line-height:1;color:#334139;font-weight:800;box-shadow:0 3px 8px rgba(22,55,38,.04)}.mid-method.master{letter-spacing:-.4px}
.mid-trust{border-top:1px solid var(--line);padding-top:20px;display:grid;grid-template-columns:repeat(3,1fr);gap:0}.mid-trust-item{display:flex;align-items:center;gap:11px;padding:0 18px;border-right:1px solid var(--line)}.mid-trust-item:first-child{padding-left:0}.mid-trust-item:last-child{border-right:0;padding-right:0}.mid-trust-item svg{width:29px;height:29px;stroke:var(--green);flex:none}.mid-trust-item strong{display:block;font-size:12px;line-height:1.3}.mid-trust-item small{display:block;color:#7d8982;font-size:10px;line-height:1.45;margin-top:2px}
.mid-error{background:#fff5f4;color:#8c352c;border:1px solid #f0cfc9;border-radius:14px;padding:14px 15px;font-size:12px;line-height:1.6;margin-bottom:18px}.mid-error strong{display:block;color:#6e2c26;margin-bottom:2px}.mid-footnote{text-align:center;color:#8a948f;font-size:10px;line-height:1.6;margin-top:18px}.mid-footnote a{color:var(--green);font-weight:700;text-decoration:none}
@media (min-width:1081px){
  /* Desktop compact layout: keep the complete checkout visible in one viewport */
  .mid-page{height:100svh;min-height:100svh;overflow:hidden}
  .mid-visual{min-height:100svh}
  .mid-visual-inner{min-height:100svh;padding:18px 34px 24px}
  .mid-logo{gap:9px}
  .mid-logo img{max-width:165px;max-height:48px}
  .mid-logo-mark{width:38px;height:38px;border-radius:11px}
  .mid-brand-copy strong{font-size:14px}
  .mid-brand-copy span{font-size:7px;letter-spacing:2.5px;margin-top:4px}
  .mid-visual-copy{max-width:450px;padding-top:36px}
  .mid-kicker{font-size:9px;letter-spacing:2px;margin-bottom:10px}
  .mid-kicker:before{width:28px}
  .mid-visual-copy h1{font-size:clamp(34px,3.2vw,48px);letter-spacing:-1.5px;margin-bottom:12px;max-width:440px}
  .mid-visual-copy p{font-size:12px;line-height:1.55;max-width:410px}
  .mid-visual-features{gap:10px 18px;margin-top:20px}
  .mid-feature{font-size:10px;gap:7px}
  .mid-feature svg{width:19px;height:19px}

  .mid-main{height:100svh;padding:14px 3.4vw 16px;align-items:center}
  .mid-main-shell{width:min(760px,100%)}
  .mid-topbar{margin-bottom:9px;gap:14px}
  .mid-secure{font-size:10px;gap:7px}
  .mid-secure svg{width:15px;height:15px}
  .mid-lang{font-size:10px;gap:8px}
  .mid-card{border-radius:22px;padding:24px 28px;box-shadow:0 16px 46px rgba(18,57,39,.11)}
  .mid-heading{margin-bottom:16px}
  .mid-heading .mini{font-size:8px;letter-spacing:2.5px;gap:9px}
  .mid-heading .mini:before,.mid-heading .mini:after{width:24px}
  .mid-heading h2{font-size:clamp(27px,2.4vw,36px);letter-spacing:-1px;margin:8px 0 7px}
  .mid-heading p{font-size:11px;line-height:1.5;max-width:540px}
  .mid-booking{grid-template-columns:minmax(0,1fr) 165px;gap:16px;padding:16px;border-radius:17px;margin-bottom:16px}
  .mid-booking-main{grid-template-columns:92px minmax(0,1fr);gap:13px}
  .mid-thumb{width:92px;height:92px;border-radius:13px}
  .mid-label{font-size:8px;letter-spacing:1.4px;margin-bottom:4px}
  .mid-booking-code{font-size:17px;letter-spacing:-.4px;margin-bottom:8px}
  .mid-activity{font-size:12px;margin-bottom:7px}
  .mid-meta{gap:5px 10px}
  .mid-meta-item{font-size:10px;gap:5px}
  .mid-meta-item svg{width:14px;height:14px}
  .mid-total{padding-left:18px}
  .mid-total strong{font-size:27px;letter-spacing:-1.1px}
  .mid-total .small{font-size:8px}
  .mid-info-row{gap:16px;padding-bottom:12px;margin-bottom:15px}
  .mid-info-item span{font-size:8px;letter-spacing:.8px;margin-bottom:3px}
  .mid-info-item strong{font-size:10px}
  .mid-pay-button{min-height:54px;border-radius:13px;padding:0 18px;gap:11px;font-size:15px}
  .mid-pay-button svg{width:19px;height:19px}
  .mid-pay-arrow{font-size:24px}
  .mid-secure-note{font-size:10px;margin:9px 0 13px}
  .mid-secure-note svg{width:14px;height:14px}
  .mid-methods{gap:7px;margin-bottom:14px}
  .mid-method{min-width:60px;padding:7px 9px;border-radius:8px;font-size:9px}
  .mid-trust{padding-top:14px}
  .mid-trust-item{gap:8px;padding:0 12px}
  .mid-trust-item svg{width:24px;height:24px}
  .mid-trust-item strong{font-size:10px}
  .mid-trust-item small{font-size:8px}
  .mid-footnote{font-size:8px;margin-top:12px}
}

@media (min-width:1081px) and (max-height:800px){
  .mid-visual-copy{padding-top:18px}
  .mid-visual-copy h1{font-size:36px;margin-bottom:9px}
  .mid-visual-copy p{font-size:11px}
  .mid-visual-features{margin-top:15px}
  .mid-main{padding-top:8px;padding-bottom:8px}
  .mid-card{padding:20px 24px}
  .mid-heading{margin-bottom:12px}
  .mid-heading h2{font-size:30px;margin:6px 0 5px}
  .mid-heading p{font-size:10px}
  .mid-booking{padding:13px;margin-bottom:12px;grid-template-columns:minmax(0,1fr) 150px}
  .mid-booking-main{grid-template-columns:80px minmax(0,1fr);gap:11px}
  .mid-thumb{width:80px;height:80px}
  .mid-booking-code{font-size:15px;margin-bottom:6px}
  .mid-activity{font-size:11px;margin-bottom:5px}
  .mid-meta-item{font-size:9px}
  .mid-total strong{font-size:24px}
  .mid-info-row{padding-bottom:9px;margin-bottom:11px}
  .mid-pay-button{min-height:48px;font-size:14px;border-radius:11px}
  .mid-secure-note{margin:7px 0 9px;font-size:9px}
  .mid-methods{margin-bottom:10px;gap:5px}
  .mid-method{min-width:54px;padding:6px 7px;font-size:8px}
  .mid-trust{padding-top:10px}
  .mid-trust-item svg{width:21px;height:21px}
  .mid-trust-item strong{font-size:9px}
  .mid-trust-item small{font-size:7px}
  .mid-footnote{font-size:7px;margin-top:8px}
}

@media(max-width:1080px){
  .mid-page{grid-template-columns:1fr}
  .mid-visual{min-height:320px;height:42vh;max-height:430px}
  .mid-visual-inner{min-height:100%;padding:22px 34px 30px}
  .mid-visual-copy{padding-top:40px}
  .mid-visual-copy h1{font-size:clamp(34px,7vw,50px);max-width:640px}
  .mid-visual-copy p{max-width:650px}
  .mid-side-note{display:none}
  .mid-main{padding:28px 24px 40px;align-items:flex-start}
}
@media(max-width:720px){
  .mid-visual{height:300px;min-height:300px}
  .mid-visual-inner{padding:18px 18px 24px}
  .mid-logo img{max-width:160px;max-height:46px}
  .mid-logo-mark{width:40px;height:40px}
  .mid-brand-copy strong{font-size:14px}
  .mid-brand-copy span{font-size:7px;letter-spacing:2.4px}
  .mid-visual-copy{padding-top:26px}
  .mid-visual-copy .mid-kicker{font-size:9px;letter-spacing:2px;margin-bottom:9px}
  .mid-visual-copy h1{font-size:34px;letter-spacing:-1px;margin-bottom:9px;max-width:95%}
  .mid-visual-copy p{font-size:12px;line-height:1.6;max-width:94%}
  .mid-visual-features{display:none}
  .mid-main{padding:16px 13px 28px}
  .mid-topbar{padding:0 3px;margin-bottom:12px}.mid-secure{font-size:10px}.mid-lang{font-size:10px}
  .mid-card{border-radius:21px;padding:20px 15px;box-shadow:0 14px 38px rgba(18,57,39,.11)}
  .mid-heading{margin-bottom:18px}.mid-heading .mini{font-size:8px;letter-spacing:2.2px;gap:8px}.mid-heading .mini:before,.mid-heading .mini:after{width:20px}.mid-heading h2{font-size:29px;letter-spacing:-.8px;margin:10px 0 8px}.mid-heading p{font-size:11px;line-height:1.55}
  .mid-booking{display:block;padding:13px;border-radius:17px;margin-bottom:18px}
  .mid-booking-main{grid-template-columns:76px minmax(0,1fr);gap:12px}.mid-thumb{width:76px;height:76px;border-radius:12px}.mid-booking-code{font-size:14px;margin-bottom:8px}.mid-activity{font-size:12px;margin-bottom:7px}.mid-meta{grid-template-columns:1fr;gap:4px}.mid-meta-item{font-size:10px}.mid-meta-item svg{width:13px;height:13px}
  .mid-total{border-left:0;border-top:1px solid #d5e4dc;margin-top:13px;padding:13px 0 1px;display:block}.mid-total .mid-label{text-align:center;font-size:8px}.mid-total strong{display:block;text-align:center;font-size:26px;letter-spacing:-.8px}.mid-total .small{text-align:center;display:block;font-size:9px}
  .mid-info-row{gap:10px;padding-bottom:13px;margin-bottom:15px}.mid-info-item span{font-size:7px;letter-spacing:.8px}.mid-info-item strong{font-size:10px}
  .mid-pay-button{min-height:57px;border-radius:14px;padding:0 15px;font-size:15px;gap:9px}.mid-pay-button svg{width:19px;height:19px}.mid-pay-arrow{font-size:24px}
  .mid-secure-note{font-size:10px;margin:11px 0 15px}.mid-methods{gap:6px;margin-bottom:17px}.mid-method{min-width:58px;padding:8px 8px;font-size:9px;border-radius:8px}
  .mid-trust{gap:0;padding-top:16px}.mid-trust-item{padding:0 8px;gap:6px;flex-direction:column;text-align:center;border-right:1px solid var(--line)}.mid-trust-item:first-child{padding-left:0}.mid-trust-item:last-child{padding-right:0}.mid-trust-item svg{width:24px;height:24px}.mid-trust-item strong{font-size:9px}.mid-trust-item small{font-size:8px}.mid-footnote{font-size:9px}
}
@media(max-width:390px){
  .mid-visual{height:270px;min-height:270px}.mid-visual-copy h1{font-size:30px}.mid-card{padding:18px 12px}.mid-booking{padding:11px}.mid-booking-main{grid-template-columns:68px minmax(0,1fr)}.mid-thumb{width:68px;height:68px}.mid-booking-code{font-size:13px}.mid-total strong{font-size:24px}.mid-method{min-width:52px;font-size:8px;padding:7px 6px}
}
@media(prefers-reduced-motion:reduce){.mid-pay-button{transition:none}}
</style>
</head>
<body>
<div class="mid-page">
  <aside class="mid-visual" aria-label="Adventure payment introduction">
    <div class="mid-visual-inner">
      <a class="mid-logo" href="<?=e(BASE_URL)?>/" aria-label="<?=e($siteName)?>">
        <?php if ($siteLogo): ?>
          <img src="<?=e(asset_url($siteLogo))?>" alt="<?=e($siteName)?>" loading="eager">
        <?php else: ?>
          <span class="mid-logo-mark">AU</span>
          <span class="mid-brand-copy"><strong><?=e($siteName)?></strong><span>ADVENTURE AWAITS</span></span>
        <?php endif; ?>
      </a>

      <div class="mid-visual-copy">
        <div class="mid-kicker">Secure Payment by Midtrans</div>
        <h1>Good Experiences.<br>Brighter Journeys.</h1>
        <p>Thank you for choosing <?=e($siteName)?>. Complete your payment securely to confirm your booking and get ready for your Bali experience.</p>
        <div class="mid-visual-features">
          <div class="mid-feature"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M12 3l8 3v6c0 5-3.4 8.1-8 9-4.6-.9-8-4-8-9V6l8-3z"/><path d="M8 12l2.5 2.5L16 9"/></svg>Secure Payment</div>
          <div class="mid-feature"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/></svg>Instant Confirmation</div>
          <div class="mid-feature"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M7 13a5 5 0 1 1 10 0v6H7z"/><path d="M7 16H4v-3a8 8 0 0 1 16 0v3h-3"/><path d="M12 8v4"/></svg>24/7 Support</div>
        </div>
      </div>
    </div>
  </aside>

  <main class="mid-main">
    <div class="mid-main-shell">
      <div class="mid-topbar">
        <div class="mid-secure"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Secure Checkout</div>
        <div class="mid-lang"><strong>EN</strong><span>|</span><span>ID</span></div>
      </div>

      <section class="mid-card">
        <div class="mid-heading">
          <div class="mini">Secure Checkout</div>
          <h2>Complete Your Payment</h2>
          <p>Finish your payment now to confirm your booking and get ready for an unforgettable Bali adventure.</p>
        </div>

        <div class="mid-booking">
          <div class="mid-booking-main">
            <div class="mid-thumb"><img src="<?=e($coverUrl)?>" alt="<?=e($packageName)?>" loading="eager" decoding="async"></div>
            <div class="mid-booking-details">
              <div class="mid-label">Booking Number</div>
              <div class="mid-booking-code"><?=e($booking['booking_code'])?></div>
              <div class="mid-activity"><?=e($packageName)?></div>
              <div class="mid-meta">
                <div class="mid-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg><?=e($activityDate)?></div>
                <div class="mid-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20"/><circle cx="10" cy="8" r="3"/><path d="M20 20v-1.5a3.5 3.5 0 0 0-3-3.45M15 5.1a3 3 0 0 1 0 5.8"/></svg><?=e($guestLabel)?></div>
                <div class="mid-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.2"/></svg><?=e($locationLabel)?></div>
              </div>
            </div>
          </div>
          <div class="mid-total">
            <div class="mid-label">Total Payment</div>
            <strong>Rp <?=e($totalFormatted)?></strong>
            <div class="small"><?=e($currency)?> · <?=e($paymentMethodLabel)?></div>
          </div>
        </div>

        <div class="mid-info-row">
          <div class="mid-info-item"><span>Payment Method</span><strong><?=e($paymentMethodLabel)?></strong></div>
          <div class="mid-info-item" style="text-align:right"><span>Booking Status</span><strong><?=e(ucwords(str_replace('_',' ',(string)($booking['booking_status'] ?? 'new'))))?></strong></div>
        </div>

        <?php if ($paymentError): ?>
          <div class="mid-error">
            <strong>Payment Temporarily Unavailable</strong>
            Please try again in a moment or contact our support team.
          </div>
        <?php endif; ?>

        <button id="pay-button" class="mid-pay-button" type="button" <?=(!$token ? 'disabled' : '')?>>
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/></svg>
          <span>Pay Now with Midtrans</span>
          <span class="mid-pay-arrow"><?=site_icon_html("payment.arrow","fa-solid fa-arrow-right")?></span>
        </button>

        <div class="mid-secure-note"><span><svg viewBox="0 0 24 24" fill="none" stroke-width="1.9"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Your payment is processed securely by Midtrans.</span></div>

        <div class="mid-methods" aria-label="Payment methods">
          <div class="mid-method">VISA</div>
          <div class="mid-method master">MASTERCARD</div>
          <div class="mid-method">JCB</div>
          <div class="mid-method">BCA</div>
          <div class="mid-method">MANDIRI</div>
          <div class="mid-method">BNI</div>
          <div class="mid-method">GOPAY</div>
        </div>

        <div class="mid-trust">
          <div class="mid-trust-item"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.7"><path d="M12 3l8 3v6c0 5-3.4 8.1-8 9-4.6-.9-8-4-8-9V6l8-3z"/><path d="M8 12l2.5 2.5L16 9"/></svg><div><strong>100% Secure</strong><small>SSL Encrypted</small></div></div>
          <div class="mid-trust-item"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.7"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/></svg><div><strong>Fast &amp; Easy</strong><small>Payment Process</small></div></div>
          <div class="mid-trust-item"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.7"><path d="M7 13a5 5 0 1 1 10 0v6H7z"/><path d="M7 16H4v-3a8 8 0 0 1 16 0v3h-3"/><path d="M12 8v4"/></svg><div><strong>Need Help?</strong><small>Contact Our Support</small></div></div>
        </div>

        <div class="mid-footnote">
          Booking <strong><?=e($booking['booking_code'])?></strong> · Questions? 
          <?php if ($siteWhatsapp): ?><a href="<?=e(whatsapp_url($siteWhatsapp,'Hello '.$siteName.', I need help with my booking '.$booking['booking_code']))?>" target="_blank" rel="noopener">WhatsApp us</a>
          <?php elseif ($sitePhone): ?>Contact <?=e($sitePhone)?>
          <?php elseif ($siteEmail): ?><a href="mailto:<?=e($siteEmail)?>"><?=e($siteEmail)?></a>
          <?php else: ?>Our support team<?php endif; ?>
        </div>
      </section>
    </div>
  </main>
</div>

<?php if ($token): ?>
<script src="<?=e(midtrans_base_url())?>/snap/snap.js" data-client-key="<?=e(midtrans_config()['client_key'])?>"></script>
<script>
(function(){
  const button = document.getElementById('pay-button');
  if (!button) return;
  const token = <?=json_encode($token, JSON_UNESCAPED_SLASHES)?>;
  const successUrl = <?=json_encode($destinationSuccess, JSON_UNESCAPED_SLASHES)?>;
  const fallbackUrl = <?=json_encode($destinationFallback, JSON_UNESCAPED_SLASHES)?>;

  button.addEventListener('click', function(){
    if (!window.snap || !token) return;
    button.disabled = true;
    button.querySelector('span:nth-child(2)').textContent = 'Opening Secure Payment…';
    window.snap.pay(token, {
      onSuccess: function(){ window.location.href = successUrl; },
      onPending: function(){ window.location.href = successUrl; },
      onError: function(){
        button.disabled = false;
        button.querySelector('span:nth-child(2)').textContent = 'Pay Now with Midtrans';
        alert('Payment could not be completed. Please try again.');
      },
      onClose: function(){
        button.disabled = false;
        button.querySelector('span:nth-child(2)').textContent = 'Pay Now with Midtrans';
      }
    });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
