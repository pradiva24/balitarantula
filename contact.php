<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/smtp.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['contact_token'])) {
    $_SESSION['contact_token'] = bin2hex(random_bytes(32));
}

$siteName = trim((string)site_brand_name());
$title = 'Contact Us | ' . $siteName;
$description = 'Contact ' . $siteName . ' in Bali. Plan your thrilling ATV and outdoor adventure with our expedition crew.';
$canonical = rtrim(BASE_URL, '/') . '/contact';

$phone = trim((string)setting('contact_phone', setting('site_phone', '+62 81 339 13939')));
$whatsapp = trim((string)setting('contact_whatsapp', setting('site_whatsapp', '+62 81 339 13939')));
$email = trim((string)setting('contact_email', setting('site_email', 'info@balitarantula.com')));
$address = trim((string)setting('contact_address', 'Payangan, Ubud, Gianyar, Bali – Indonesia'));
$cleanWa = preg_replace('/\D+/', '', $whatsapp);
$waUrl = $cleanWa ? "https://wa.me/{$cleanWa}?text=" . rawurlencode("Hello {$siteName}, I would like to ask about planning an adventure ride.") : '#';

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    if (!isset($_POST['contact_token']) || !hash_equals($_SESSION['contact_token'] ?? '', (string)$_POST['contact_token'])) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $from = trim((string)($_POST['email'] ?? ''));
        $phoneForm = trim((string)($_POST['phone'] ?? ''));
        $activity = trim((string)($_POST['activity'] ?? 'ATV Adventure'));
        $message = trim((string)($_POST['message'] ?? ''));

        if ($name === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || $message === '') {
            $error = 'Please fill out your name, a valid email address, and your message.';
        } else {
            $mailSubject = "Website Contact Enquiry: {$activity} - {$name}";
            $safeName = e($name);
            $safeFrom = e($from);
            $safePhone = e($phoneForm);
            $safeActivity = e($activity);
            $safeMessage = nl2br(e($message));

            $htmlBody = '<!doctype html><html><body style="margin:0;background:#0e1218;font-family:Arial,sans-serif;color:#e2e8f0;padding:24px">'
                . '<div style="max-width:640px;margin:0 auto;background:#141b24;border:1px solid rgba(255,255,255,0.1);border-radius:12px;overflow:hidden">'
                . '<div style="background:#ff5500;color:#fff;padding:20px 24px"><div style="font-size:12px;letter-spacing:2px;font-weight:800;text-transform:uppercase">' . e($siteName) . '</div><h2 style="margin:6px 0 0;font-size:22px;font-weight:900">New Expedition Enquiry</h2></div>'
                . '<div style="padding:24px;color:#cbd5e1">'
                . '<p><strong>Name:</strong> ' . $safeName . '</p>'
                . '<p><strong>Email:</strong> ' . $safeFrom . '</p>'
                . '<p><strong>Phone / WhatsApp:</strong> ' . ($safePhone ?: '-') . '</p>'
                . '<p><strong>Activity Interest:</strong> ' . $safeActivity . '</p>'
                . '<div style="margin-top:16px;padding:14px;background:#0a0d12;border-radius:8px;border:1px solid rgba(255,255,255,0.06);color:#f1f5f9">' . $safeMessage . '</div>'
                . '</div></div></body></html>';

            $recipientsRaw = trim((string)setting('booking_notification_email', ''));
            if ($recipientsRaw === '') $recipientsRaw = trim((string)setting('site_email', $email));
            $recipients = [];
            foreach (preg_split('/[,;]+/', $recipientsRaw) as $c) {
                $c = trim($c);
                if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)) $recipients[$c] = true;
            }

            $successCount = 0;
            $lastErr = '';
            foreach (array_keys($recipients) as $recipient) {
                if (smtp_send($recipient, $mailSubject, $htmlBody, $from)) {
                    $successCount++;
                } else {
                    $lastErr = smtp_last_error();
                }
            }

            if ($successCount > 0) {
                $sent = true;
                $_POST = [];
            } else {
                $error = 'Your message could not be sent. ' . ($lastErr !== '' ? 'SMTP: ' . $lastErr : 'Please check SMTP settings in Admin.');
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<!-- Google Fonts: Barlow Condensed & Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style id="bta-contact-modern-theme">
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

.bta-contact * {
  box-sizing: border-box;
}

.bta-contact .wrap {
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
  background: url('https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1800&q=85') center/cover no-repeat;
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

.breadcrumbs {
  font-family: var(--bta-font-display);
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: #c9c6c0;
}

.breadcrumbs a {
  color: #c9c6c0;
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumbs a:hover {
  color: var(--bta-orange);
}

/* Contact 2-Column Layout */
.contact-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 60px;
  padding: 90px 0 120px;
  align-items: start;
}

.contact-intro .eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-family: var(--bta-font-display);
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 2.5px;
  text-transform: uppercase;
  color: var(--bta-orange);
  margin: 0 0 14px;
}

.inner-title {
  font-family: var(--bta-font-display);
  font-size: clamp(38px, 5vw, 64px);
  font-weight: 900;
  line-height: 0.98;
  letter-spacing: -0.5px;
  text-transform: uppercase;
  color: var(--bta-heading);
  margin: 0 0 20px;
}

.inner-title span {
  color: var(--bta-orange);
}

.section-description {
  font-size: 16px;
  color: var(--bta-text);
  line-height: 1.7;
  margin-bottom: 40px;
}

.contact-detail {
  display: flex;
  gap: 20px;
  margin-bottom: 30px;
  align-items: flex-start;
}

.contact-detail span {
  font-family: var(--bta-font-display);
  font-size: 20px;
  font-weight: 900;
  color: var(--bta-orange);
  background: var(--bta-panel);
  border: 1px solid var(--bta-border);
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 48px;
  box-shadow: 0 4px 12px rgba(30, 25, 15, 0.05);
}

.contact-detail b {
  display: block;
  font-family: var(--bta-font-display);
  font-size: 18px;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--bta-heading);
  margin-bottom: 6px;
}

.contact-detail p {
  margin: 0 0 6px;
  font-size: 15px;
  color: var(--bta-text);
}

.contact-detail a {
  color: var(--bta-orange);
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s;
}

.contact-detail a:hover {
  color: var(--bta-orange-hover);
  text-decoration: underline;
}

/* Contact Form */
.contact-form {
  background: var(--bta-panel);
  border: 1px solid var(--bta-border);
  border-top: 3px solid var(--bta-orange);
  border-radius: 16px;
  padding: 40px 36px;
  box-shadow: 0 16px 42px rgba(30, 25, 15, 0.06);
}

.contact-form label {
  display: block;
  font-family: var(--bta-font-display);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--bta-heading);
  margin-bottom: 8px;
}

.contact-form input, 
.contact-form select, 
.contact-form textarea {
  width: 100%;
  background: #ffffff;
  border: 1px solid #d8d4cc;
  border-radius: 8px;
  padding: 14px 16px;
  font-family: var(--bta-font-body);
  font-size: 15px;
  color: #191919;
  margin-bottom: 22px;
  outline: none;
  transition: border-color 0.25s, box-shadow 0.25s;
}

.contact-form input:focus, 
.contact-form select:focus, 
.contact-form textarea:focus {
  border-color: var(--bta-orange);
  box-shadow: 0 0 0 3px rgba(255, 90, 31, 0.2);
}

.contact-form select option {
  background: #ffffff;
  color: #191919;
}

.button-orange {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: var(--bta-orange);
  color: #111111 !important;
  font-family: var(--bta-font-display);
  font-size: 17px;
  font-weight: 900;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  padding: 16px 24px;
  border-radius: 8px;
  border: 0;
  cursor: pointer;
  transition: all 0.25s ease;
  box-shadow: 0 4px 20px var(--bta-orange-glow);
}

.button-orange:hover {
  background: var(--bta-orange-hover);
  transform: translateY(-2px);
  color: #111111 !important;
}

.alert-box {
  padding: 16px;
  border-radius: 8px;
  margin-bottom: 24px;
  font-size: 14px;
  line-height: 1.5;
}

.alert-success {
  background: rgba(34, 197, 94, 0.12);
  border: 1px solid rgba(34, 197, 94, 0.35);
  color: #15803d;
}

.alert-error {
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.35);
  color: #b91c1c;
}

@media (max-width: 900px) {
  .contact-layout {
    grid-template-columns: 1fr;
    gap: 40px;
    padding: 60px 0;
  }
  .contact-form {
    padding: 28px 20px;
  }
}
</style>

<div class="bta-contact">
  <!-- Page Banner -->
  <section class="page-banner">
    <div class="wrap page-banner-content">
      <p class="eyebrow"><span class="eyebrow-line"></span>GET IN TOUCH</p>
      <h1>LET'S PLAN YOUR <span>RIDE.</span></h1>
      <p>Questions about our tracks, schedules, or package details? Send us a direct message or connect with our crew.</p>
      <div class="breadcrumbs">
        <a href="<?=BASE_URL?>/">HOME</a> / GET IN TOUCH
      </div>
    </div>
  </section>

  <!-- Contact Layout -->
  <main class="wrap contact-layout">
    
    <!-- Intro Column -->
    <div class="contact-intro">
      <p class="eyebrow"><span class="eyebrow-line"></span>CONTACT THE CREW</p>
      <h2 class="inner-title">YOUR NEXT<br><span>STORY STARTS HERE.</span></h2>
      <p class="section-description">Tell us what adventure you are looking for. Share your preferred date, group size, and activity interest. We reply swiftly during operating hours.</p>

      <div class="contact-detail">
        <span>01</span>
        <div>
          <b>BASECAMP LOCATION</b>
          <p><?=e($address)?></p>
          <p><small style="color:var(--bta-gray-400)">Open Daily: 08:00 AM – 06:00 PM (WITA)</small></p>
        </div>
      </div>

      <div class="contact-detail">
        <span>02</span>
        <div>
          <b>DIRECT ENQUIRIES</b>
          <p>Instant booking support, private tracks, & custom group rates:</p>
          <?php if ($email): ?>
            <p><a href="mailto:<?=e($email)?>"><?=e($email)?></a></p>
          <?php endif; ?>
          <?php if ($phone): ?>
            <p><a href="tel:<?=e($phone)?>"><?=e($phone)?></a></p>
          <?php endif; ?>
          <?php if ($waUrl !== '#'): ?>
            <p><a href="<?=$waUrl?>" target="_blank" rel="noopener">⚡ Chat with us on WhatsApp</a></p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Form Column -->
    <form class="contact-form" method="POST" action="">
      <input type="hidden" name="contact_token" value="<?=e($_SESSION['contact_token'])?>">

      <?php if ($sent): ?>
        <div class="alert-box alert-success">
          <b>✓ Message Sent Successfully!</b><br>
          Thank you for reaching out. Our adventure team will review your enquiry and get back to you shortly.
        </div>
      <?php elseif (!empty($error)): ?>
        <div class="alert-box alert-error">
          <b>✕ Unable to Send Message:</b><br>
          <?=e($error)?>
        </div>
      <?php endif; ?>

      <label for="name">YOUR NAME</label>
      <input id="name" name="name" placeholder="Enter your full name" value="<?=e($_POST['name'] ?? '')?>" required>

      <label for="email">EMAIL ADDRESS</label>
      <input id="email" type="email" name="email" placeholder="you@example.com" value="<?=e($_POST['email'] ?? '')?>" required>

      <label for="phone">WHATSAPP / PHONE (OPTIONAL)</label>
      <input id="phone" type="tel" name="phone" placeholder="+62 812..." value="<?=e($_POST['phone'] ?? '')?>">

      <label for="activity">ACTIVITY INTEREST</label>
      <select id="activity" name="activity">
        <option value="Single ATV Adventure" <?=(($_POST['activity']??'')==='Single ATV Adventure'?'selected':'')?>>Single ATV Adventure</option>
        <option value="Tandem ATV Adventure" <?=(($_POST['activity']??'')==='Tandem ATV Adventure'?'selected':'')?>>Tandem ATV Adventure</option>
        <option value="Jungle & River Track" <?=(($_POST['activity']??'')==='Jungle & River Track'?'selected':'')?>>Jungle & River Track</option>
        <option value="Mud Challenge Track" <?=(($_POST['activity']??'')==='Mud Challenge Track'?'selected':'')?>>Mud Challenge Track</option>
        <option value="Group / Corporate Outing" <?=(($_POST['activity']??'')==='Group / Corporate Outing'?'selected':'')?>>Group / Corporate Outing</option>
        <option value="Other / General Question" <?=(($_POST['activity']??'')==='Other / General Question'?'selected':'')?>>Other / General Question</option>
      </select>

      <label for="message">YOUR MESSAGE</label>
      <textarea id="message" name="message" rows="5" placeholder="Share your preferred date, number of riders, and any questions..." required><?=e($_POST['message'] ?? '')?></textarea>

      <button class="button-orange" type="submit" name="contact_submit" value="1">
        SEND ENQUIRY <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
      </button>
    </form>

  </main>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
