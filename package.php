<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: ' . BASE_URL . '/packages', true, 301);
    exit;
}

$hasDiscount = false;
try {
    $hasDiscount = (bool)db()->query("SHOW COLUMNS FROM packages LIKE 'discount_percent'")->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$discountSelect = $hasDiscount ? 'p.discount_percent' : '0 AS discount_percent';

$st = db()->prepare("
    SELECT p.id, p.category_id, p.name, p.slug, p.short_description, p.description, p.duration, 
           p.price_adult, {$discountSelect}, p.cover_image, p.seo_title, p.meta_description, 
           p.canonical_url, p.og_image, c.name AS category_name 
    FROM packages p 
    LEFT JOIN categories c ON c.id = p.category_id 
    WHERE p.slug = ? AND p.status = 1 
    LIMIT 1
");
$st->execute([$slug]);
$p = $st->fetch();

if (!$p) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$title = $p['seo_title'] ?: ($p['name'] . ' | ' . site_brand_name());
$description = $p['meta_description'] ?: ($p['short_description'] ?: 'Book ' . $p['name'] . ' with ' . site_brand_name() . '.');
$canonical = $p['canonical_url'] ?: BASE_URL . '/package/' . rawurlencode($p['slug']);
$ogImage = $p['og_image'] ?: ($p['cover_image'] ?: '');

// Fetch Gallery Images
$st = db()->prepare("SELECT image_path, alt_text FROM package_images WHERE package_id = ? ORDER BY sort_order, id");
$st->execute([(int)$p['id']]);
$imgs = $st->fetchAll();
$gallery = [];
if (!empty($p['cover_image'])) {
    $gallery[] = ['image_path' => $p['cover_image'], 'alt_text' => $p['name']];
}
foreach ($imgs as $im) {
    $path = (string)$im['image_path'];
    $exists = false;
    foreach ($gallery as $g) {
        if ($g['image_path'] === $path) {
            $exists = true;
            break;
        }
    }
    if (!$exists) $gallery[] = $im;
}

// Fetch Related Packages
$related = [];
try {
    $rs = db()->prepare("
        SELECT p.id, p.name, p.slug, p.short_description, p.cover_image, p.duration, p.price_adult, {$discountSelect}, c.name AS category_name 
        FROM packages p 
        LEFT JOIN categories c ON c.id = p.category_id 
        WHERE p.status = 1 AND p.id <> ? 
          AND p.name NOT LIKE '%Manuaba Crystalfall Full Package%'
        ORDER BY p.homepage_order ASC, p.id ASC 
        LIMIT 8
    ");
    $rs->execute([(int)$p['id']]);
    $related = $rs->fetchAll();
} catch (Throwable $e) {
    $related = [];
}

$wa = trim((string)setting('site_whatsapp', ''));
$waUrl = $wa ? whatsapp_url($wa, 'Hello Bali Tarantula Adventure, I would like to inquire about booking: ' . $p['name'] . '.') : '';

// Pricing calculation
$currentPrice = (float)($p['price_adult'] ?? 0);
$discountPercent = max(0, min(90, (float)($p['discount_percent'] ?? 0)));
$originalPrice = null;
try {
    $ps = db()->prepare('SELECT original_price FROM package_price_settings WHERE package_id = ? LIMIT 1');
    $ps->execute([(int)$p['id']]);
    $op = $ps->fetchColumn();
    if ($op !== false && $op !== null && $op !== '' && (float)$op > 0) {
        $originalPrice = (float)$op;
    }
} catch (Throwable $e) {}

if (!$originalPrice && $discountPercent > 0 && $currentPrice > 0) {
    $originalPrice = $currentPrice / (1 - $discountPercent / 100);
}
// Keep the visible saving badge consistent with the editable original price.
if ($originalPrice && $originalPrice > $currentPrice && $originalPrice > 0) {
    $discountPercent = max(0, min(90, round((($originalPrice - $currentPrice) / $originalPrice) * 100)));
}

// Price-card benefits are editable in Admin → Packages → Edit → Price bullets.
$priceBullets = [];
try {
    $pb = db()->prepare('SELECT item_text FROM package_price_bullets WHERE package_id = ? ORDER BY sort_order, id LIMIT 10');
    $pb->execute([(int)$p['id']]);
    $priceBullets = $pb->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

// Package Highlights
$highlightRows = [];
try {
    $hq = db()->prepare('SELECT highlight_text FROM package_highlights WHERE package_id = ? ORDER BY sort_order, id LIMIT 10');
    $hq->execute([(int)$p['id']]);
    $highlightRows = $hq->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}
if (!$highlightRows) {
    $highlightRows = [
        'Pure off-road adrenaline across Bali’s most thrilling natural landscapes.',
        'High-powered quad bikes maintained to strict international safety standards.',
        'Expert local trail marshals and complete safety gear provided.',
        'Insta-worthy photo stops at hidden jungle rivers and scenic waterfalls.'
    ];
}

// Inclusions & Exclusions
$includeRows = [];
$excludeRows = [];
try {
    $iq = db()->prepare('SELECT item_text FROM package_includes WHERE package_id = ? ORDER BY sort_order, id LIMIT 30');
    $iq->execute([(int)$p['id']]);
    $includeRows = $iq->fetchAll(PDO::FETCH_COLUMN);
    $eq = db()->prepare('SELECT item_text FROM package_excludes WHERE package_id = ? ORDER BY sort_order, id LIMIT 30');
    $eq->execute([(int)$p['id']]);
    $excludeRows = $eq->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}
if (!$includeRows) {
    $includeRows = [
        ($p['category_name'] ?: 'Adventure ATV') . ' ride experience',
        'Certified English-speaking trail instructor',
        'Full safety equipment (helmet, boots, life vests where applicable)',
        'Welcome drink & towel refreshments',
        'Locker, hot shower facilities, & changing room',
        'Insurance coverage during activity'
    ];
}
if (!$excludeRows) {
    $excludeRows = [
        'Hotel return transfers (available as add-on during checkout)',
        'Personal expenditure & professional photo/video documentation',
        'Gratuities for trail guide (optional)'
    ];
}

// FAQs
$faqRows = [];
try {
    $fq = db()->prepare('SELECT question, answer FROM package_faqs WHERE package_id = ? ORDER BY sort_order, id LIMIT 20');
    $fq->execute([(int)$p['id']]);
    $faqRows = $fq->fetchAll();
} catch (Throwable $e) {}
if (!$faqRows) {
    $faqRows = [
        ['question' => 'What should I wear and bring?', 'answer' => 'Wear comfortable clothes you don’t mind getting muddy, a change of clothes, sunscreen, and waterproof camera/pouch. We provide rubber boots and helmets.'],
        ['question' => 'Is driving experience required?', 'answer' => 'No previous ATV experience is required. Our professional guides provide a comprehensive safety and test drive briefing before we enter the track.'],
        ['question' => 'Are there age restrictions?', 'answer' => 'Single drivers must be at least 12 years old. Tandem passengers can ride from age 6 accompanied by an adult. Participants up to 65 years old in good physical health are welcome.'],
        ['question' => 'What happens if it rains?', 'answer' => 'Bali Tarantula adventures run rain or shine! In fact, wet tracks make for an even more legendary, mud-slinging experience. In case of extreme weather, rescheduling is offered free of charge.']
    ];
}

require __DIR__ . '/includes/header.php';
?>

<!-- Google Fonts: Barlow Condensed & Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style id="bta-package-detail-theme">
:root {
  --bta-bg: #f5f4ef;
  --bta-panel: #ffffff;
  --bta-panel-hover: #faf8f5;
  --bta-heading: #191919;
  --bta-text: #5d5b56;
  --bta-muted: #77736b;
  --bta-border: #dedbd4;
  --bta-border-subtle: #e6e3dc;
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

.bta-pkg-detail * {
  box-sizing: border-box;
}

.bta-pkg-detail .wrap {
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
  overflow: hidden;
  padding: 130px 0 60px;
  border-bottom: 3px solid var(--bta-orange);
  color: #fff;
}

.page-banner::before {
  content: "";
  position: absolute;
  inset: 0;
  background: url('<?=!empty($p['cover_image']) ? e(asset_url($p['cover_image'])) : 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1500&q=90'?>') center/cover no-repeat;
  opacity: 0.28;
  filter: saturate(1.1);
}

.page-banner::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(12,12,12,0.65) 0%, rgba(18,18,18,0.92) 100%);
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
  color: #f0efec;
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
  font-size: clamp(38px, 5.5vw, 70px);
  font-weight: 900;
  line-height: 0.98;
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
  line-height: 1.7;
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

/* 2-Column Detail Layout */
.package-detail-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 40px;
  align-items: start;
  padding: 60px 0 100px;
  background: var(--bta-bg);
}

.package-detail-main {
  min-width: 0;
}

/* Gallery & Cover */
.package-gallery-container {
  margin-bottom: 30px;
}

.package-cover-wrap {
  position: relative;
  height: clamp(340px, 42vw, 500px);
  border-radius: 16px;
  overflow: hidden;
  background: #ffffff;
  border: 1px solid var(--bta-border);
  box-shadow: 0 10px 30px rgba(30, 25, 15, 0.05);
}

.package-cover-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: opacity 0.3s ease, transform 0.4s ease;
}

.package-cover-wrap:hover .package-cover-img {
  transform: scale(1.02);
}

.package-thumb-row {
  display: flex;
  gap: 12px;
  margin-top: 14px;
  overflow-x: auto;
  scrollbar-width: none;
  padding-bottom: 4px;
}

.package-thumb-row::-webkit-scrollbar {
  display: none;
}

.package-thumb-btn {
  width: 84px;
  height: 60px;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid transparent;
  padding: 0;
  cursor: pointer;
  background: #ffffff;
  border: 1px solid var(--bta-border);
  flex: 0 0 auto;
  transition: all 0.2s;
}

.package-thumb-btn img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.package-thumb-btn.is-active, .package-thumb-btn:hover {
  border-color: var(--bta-orange);
}

/* Tabs Navigation */
.package-tabs {
  display: flex;
  gap: 12px;
  border-bottom: 1px solid var(--bta-border);
  margin: 30px 0 35px;
  overflow-x: auto;
  scrollbar-width: none;
}

 .package-tabs a {
  font-family: var(--bta-font-display);
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--bta-muted);
  text-decoration: none;
  padding: 12px 18px;
  border-bottom: 3px solid transparent;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.package-tabs a:hover, .package-tabs a.is-active {
  color: var(--bta-heading);
  border-color: var(--bta-orange);
}

/* Content Blocks */
.detail-block {
  margin-bottom: 45px;
  scroll-margin-top: 100px;
}

.detail-block h2, .detail-block h3 {
  font-family: var(--bta-font-display);
  font-weight: 900;
  text-transform: uppercase;
  color: var(--bta-heading);
  letter-spacing: 0.5px;
}

.detail-block h2 {
  font-size: 36px;
  margin: 0 0 16px;
}

.detail-block h2 span {
  color: var(--bta-orange);
}

.detail-block h3 {
  font-size: 26px;
  margin: 0 0 16px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.detail-block p {
  color: var(--bta-text);
  font-size: 15px;
  line-height: 1.7;
  margin-bottom: 16px;
}

/* Highlights & Inclusions lists */
.highlight-list, .check-list {
  list-style: none;
  padding: 0;
  margin: 0 0 20px;
}

.highlight-list li, .check-list li {
  position: relative;
  padding-left: 28px;
  font-size: 15px;
  color: #393731;
  margin-bottom: 12px;
  line-height: 1.55;
}

.highlight-list li::before {
  content: "⚡";
  position: absolute;
  left: 0;
  top: 0;
  color: var(--bta-orange);
  font-size: 16px;
}

/* Orange feature block for editable package highlights */
 .experience-highlights {
  background: #fcf6f3;
  color: #171717;
  padding: 28px 30px;
  border-radius: 10px;
}
.experience-highlights h3,
.experience-highlights h3 span,
.experience-highlights .highlight-list li,
.experience-highlights .highlight-list li::before {
  color: #171717;
}
.experience-highlights h3 { margin-bottom: 18px; }
.experience-highlights .highlight-list { margin-bottom: 0; }
.experience-highlights .highlight-list li { color: #21160f; }
@media (max-width: 640px) {
  .experience-highlights { padding: 22px 18px; }
}

.check-list li::before {
  content: "✓";
  position: absolute;
  left: 0;
  top: 0;
  color: var(--bta-green);
  font-weight: 900;
  font-size: 16px;
}

.exclude-list li {
  color: var(--bta-muted);
}

.exclude-list li::before {
  content: "✕";
  color: #ef4444;
}

/* FAQ Accordion */
.faq-accordion {
  border-top: 1px solid var(--bta-border);
  margin-top: 20px;
}

.faq-item {
  border-bottom: 1px solid var(--bta-border);
}

.faq-item summary {
  list-style: none;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 0;
  font-family: var(--bta-font-display);
  font-size: 18px;
  font-weight: 800;
  letter-spacing: 0.5px;
  color: var(--bta-heading);
  transition: color 0.2s;
}

.faq-item summary::-webkit-details-marker {
  display: none;
}

.faq-item summary:hover {
  color: var(--bta-orange);
}

.faq-item summary span.icon {
  font-size: 20px;
  color: var(--bta-orange);
  transition: transform 0.25s;
}

.faq-item[open] summary span.icon {
  transform: rotate(45deg);
}

.faq-answer {
  padding: 0 0 18px;
  color: var(--bta-text);
  font-size: 14px;
  line-height: 1.65;
}

/* Aside Booking Panel */
.booking-panel {
  position: sticky;
  top: 100px;
  background: var(--bta-panel);
  border: 1px solid var(--bta-border-subtle);
  border-top: 3px solid var(--bta-orange);
  border-radius: 16px;
  padding: 28px 24px;
  box-shadow: 0 18px 48px rgba(30, 25, 15, 0.07);
}

.panel-label {
  display: none;
  font-family: var(--bta-font-display);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--bta-orange);
  margin-bottom: 8px;
}

.booking-panel h3 {
  font-family: var(--bta-font-display);
  font-size: 28px;
  font-weight: 900;
  color: var(--bta-heading);
  line-height: 1.1;
  text-transform: uppercase;
  margin: 0 0 14px;
}

/* Special Price Badge with Green Animated Dot */
 .special-price-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(22, 163, 74, 0.12);
  border: 1px solid rgba(22, 163, 74, 0.3);
  color: #15803d;
  font-family: var(--bta-font-display);
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 1px;
  text-transform: uppercase;
  padding: 5px 12px;
  border-radius: 999px;
  margin-bottom: 12px;
}

.special-price-badge::before {
  content: "";
  display: inline-block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--bta-green);
  box-shadow: 0 0 8px rgba(22, 163, 74, 0.8);
  animation: specialPriceDotPulse 1.2s ease-in-out infinite;
}

@keyframes specialPriceDotPulse {
  0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 8px rgba(22, 163, 74, 0.8); }
  50% { opacity: 0.25; transform: scale(0.65); box-shadow: 0 0 2px rgba(22, 163, 74, 0.2); }
}

.panel-price-row {
  margin-bottom: 20px;
}

.panel-price-old {
  font-size: 14px;
  color: var(--bta-muted);
  text-decoration: line-through;
  line-height: 1.2;
}

.panel-price-save {
  display: inline-flex;
  align-items: center;
  font-family: var(--bta-font-display);
  font-size: 12px;
  line-height: 1.3;
  font-weight: 900;
  letter-spacing: .5px;
  color: #c2410c;
  background: rgba(255, 90, 31, .14);
  border: 1px solid rgba(255, 90, 31, .24);
  padding: 4px 9px;
  border-radius: 3px;
  margin-left: 8px;
  vertical-align: middle;
}

.panel-price-current {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 0 6px;
  font-family: var(--bta-font-display);
  font-size: 34px;
  font-weight: 900;
  color: var(--bta-heading);
  line-height: 1;
  margin-top: 4px;
}

.panel-price-current small {
  display: inline;
  font-family: var(--bta-font-body);
  font-size: 13px;
  color: var(--bta-muted);
  font-weight: 500;
  margin-left: 0;
  white-space: nowrap;
}

/* Action Buttons (Book Now & WhatsApp) */
.panel-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 20px;
}

.btn-book-now {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: var(--bta-orange);
  color: #111111;
  font-family: var(--bta-font-display);
  font-size: 17px;
  font-weight: 900;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  padding: 14px 20px;
  border-radius: 8px;
  text-decoration: none;
  transition: all 0.25s ease;
  box-shadow: 0 4px 20px var(--bta-orange-glow);
}

.btn-book-now:hover {
  background: var(--bta-orange-hover);
  transform: translateY(-2px);
  color: #111111;
}

.btn-whatsapp {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: #25D366;
  color: #fff;
  font-family: var(--bta-font-display);
  font-size: 17px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  padding: 14px 20px;
  border-radius: 8px;
  text-decoration: none;
  transition: all 0.25s ease;
}

.btn-whatsapp:hover {
  background: #20bd5a;
  transform: translateY(-2px);
  color: #fff;
}

/* Advance confirmation note (hidden on mobile) */
.panel-confirm-note {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: #fff8eb;
  border: none;
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 20px;
}

.panel-confirm-note .note-icon {
  flex: 0 0 18px;
  color: var(--bta-orange);
  font-weight: 900;
  font-size: 16px;
  line-height: 1.2;
}

.panel-confirm-note .note-text {
  font-size: 12px;
  line-height: 1.45;
  color: #9a3412;
}

.panel-divider {
  height: 1px;
  background: var(--bta-border);
  margin: 20px 0;
}

.panel-details-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.panel-details-list li {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 16px;
  padding: 11px 0;
  margin: 0;
  border: 0;
  border-bottom: 1px dashed var(--bta-border);
  border-radius: 0;
  background: transparent;
  font-size: 13px;
  line-height: 1.5;
  color: var(--bta-text);
}

.panel-details-list li::before { content: none; }

.panel-details-list li span {
  flex: 0 1 42%;
  color: var(--bta-text);
  font-weight: 400;
}

.panel-details-list li b {
  flex: 1 1 58%;
  color: var(--bta-heading);
  font-weight: 700;
  text-align: right;
}

/* Admin-managed price bullets remain visible without changing their stored values. */
.panel-details-list li:not(:has(span)) {
  justify-content: flex-start;
  text-align: left;
}

/* Related Packages */
.related-section {
  padding: 80px 0;
  border-top: 1px solid var(--bta-border);
  background: var(--bta-bg);
}

.related-section .adventure-grid {
  display: flex;
  gap: 18px;
  overflow-x: auto;
  overflow-y: hidden;
  scroll-snap-type: x mandatory;
  scroll-behavior: smooth;
  scrollbar-width: thin;
  scrollbar-color: var(--bta-orange) transparent;
  padding: 4px 2px 18px;
}

.related-section .adventure-grid::-webkit-scrollbar { height: 6px; }
.related-section .adventure-grid::-webkit-scrollbar-thumb { background: var(--bta-orange); border-radius: 8px; }
.related-section .adventure-grid::-webkit-scrollbar-track { background: rgba(255,90,31,.10); }
.related-heading { align-items: end; }
.related-slider-controls { display: flex; gap: 8px; flex: 0 0 auto; }
.related-slider-btn {
  width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center;
  border: 1px solid var(--bta-border); background: #fff; color: var(--bta-heading);
  font-size: 20px; cursor: pointer; transition: background .2s, color .2s, border-color .2s;
}
.related-slider-btn:hover, .related-slider-btn:focus-visible { background: var(--bta-orange); border-color: var(--bta-orange); color: #171717; }
.related-section .adventure-grid > .adventure-card {
  flex: 0 0 calc((100% - 36px) / 3);
  scroll-snap-align: start;
}

.related-section .adventure-card {
  position: relative;
  min-height: 420px;
  height: 420px;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--bta-border);
  box-shadow: 0 10px 30px rgba(30, 25, 15, 0.08);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 28px 24px;
  text-decoration: none;
  background: #252525;
  transition: transform 0.35s ease, border-color 0.35s ease, box-shadow 0.35s ease;
}

.related-section .adventure-card:hover {
  transform: translateY(-6px);
  border-color: var(--bta-orange);
  box-shadow: 0 20px 48px rgba(30, 25, 15, 0.16), 0 0 24px var(--bta-orange-glow);
}

.related-section .adventure-card img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s ease;
  z-index: 1;
}

.related-section .adventure-card:hover img {
  transform: scale(1.06);
}

.related-section .card-shade {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(10,13,18,0.15) 0%, rgba(10,13,18,0.52) 45%, rgba(10,13,18,0.96) 100%);
  z-index: 2;
}

.related-section .card-content {
  position: relative;
  z-index: 3;
}

.related-section .card-tag {
  display: inline-block;
  font-family: var(--bta-font-display);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--bta-orange);
  margin-bottom: 6px;
}

.related-section .adventure-card h3 {
  font-family: var(--bta-font-display);
  font-size: 26px;
  font-weight: 900;
  line-height: 1.05;
  color: #ffffff;
  margin: 0 0 8px;
  text-transform: uppercase;
}

.related-section .adventure-card p {
  font-size: 13px;
  color: #e0ded9;
  line-height: 1.5;
  margin: 0 0 14px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.related-section .card-pricing-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid rgba(255, 255, 255, 0.18);
  padding-top: 10px;
}

.related-section .card-price-current {
  font-family: var(--bta-font-display);
  font-size: 22px;
  font-weight: 900;
  color: #ffffff;
}

.related-section .card-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: var(--bta-font-display);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: #111111;
  background: var(--bta-orange);
  padding: 8px 16px;
  border-radius: 6px;
  text-decoration: none;
  transition: all 0.25s ease;
}

.related-section .card-link:hover {
  background: var(--bta-orange-hover);
}

@media (max-width: 992px) {
  .package-detail-layout {
    grid-template-columns: 1fr;
    gap: 30px;
  }
  .booking-panel {
    position: relative;
    top: auto;
    margin-bottom: 30px;
  }
}

@media (max-width: 768px) {
  .panel-confirm-note {
    display: none !important; /* Hidden on mobile as per user requirement */
  }
  .panel-actions {
    flex-direction: row;
    gap: 8px;
  }
  .btn-book-now, .btn-whatsapp {
    flex: 1 1 0;
    width: 0;
    padding: 12px 8px;
    font-size: 14px;
    white-space: nowrap;
  }
  .related-section .adventure-grid > .adventure-card {
    flex-basis: min(82vw, 340px);
  }
  /* Compact fixed booking/price card at the bottom of mobile screens. */
  .bta-pkg-detail { padding-bottom: 150px; }
  .booking-panel {
    position: fixed !important;
    z-index: 999;
    left: 0;
    right: 0;
    bottom: 0;
    top: auto !important;
    width: 100%;
    max-width: none;
    box-sizing: border-box;
    margin: 0 !important;
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
    border-radius: 14px 14px 0 0;
    border: 1px solid var(--bta-border);
    border-top: 3px solid var(--bta-orange);
    background: var(--bta-panel);
    box-shadow: 0 -8px 30px rgba(0,0,0,.14);
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr);
    align-items: center;
    column-gap: 12px;
    row-gap: 8px;
  }
  .booking-panel .special-price-badge {
    grid-column: 1 / -1;
    grid-row: 1;
    margin: 0;
    justify-self: start;
    padding: 3px 8px;
    font-size: 10px;
  }
  .booking-panel .panel-price-row {
    grid-column: 1 / -1;
    grid-row: 2;
    margin: 0;
  }
  .booking-panel .panel-price-old {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 14px;
    line-height: 1.3;
  }
  .booking-panel .panel-price-save {
    margin-left: 0;
    padding: 3px 7px;
    font-size: 11px;
  }
  .booking-panel .panel-price-current {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 0 6px;
    font-size: 25px;
    line-height: 1.15;
    margin: 6px 0 0;
  }
  .booking-panel .panel-price-current small {
    display: inline;
    font-size: 12px;
    margin: 0;
    white-space: nowrap;
  }
  .booking-panel .panel-actions {
    grid-column: 1 / -1;
    grid-row: 3;
    margin: 0;
    flex-direction: row;
    gap: 8px;
  }
  .booking-panel .btn-book-now,
  .booking-panel .btn-whatsapp {
    flex: 1 1 0;
    width: auto;
    min-width: 0;
    padding: 10px 8px;
    font-size: 12px;
    line-height: 1.2;
    justify-content: center;
  }
  .panel-details-list { display: none !important; }
  .panel-divider { display: none !important; }
  .panel-details-list li { gap: 10px; font-size: 12px; }
  .panel-details-list li span { flex-basis: 40%; }
  .panel-details-list li b { flex-basis: 60%; }
}
</style>

<div class="bta-pkg-detail">
  <!-- Page Banner -->
  <section class="page-banner">
    <div class="wrap page-banner-content">
      <p class="eyebrow"><span class="eyebrow-line"></span><?=e($p['category_name'] ?: 'ADVENTURE EXPERIENCE')?></p>
      <h1><?=e($p['name'])?></h1>
      <p><?=e($p['short_description'] ?: 'Get ready to explore the wild side of Bali on an unforgettable outdoor ride.')?></p>
      <div class="breadcrumbs">
        <a href="<?=BASE_URL?>/">HOME</a> / <a href="<?=BASE_URL?>/packages">OUR PACKAGES</a> / <?=e($p['name'])?>
      </div>
    </div>
  </section>

  <!-- Main 2-Column Detail Layout -->
  <main class="wrap package-detail-layout">
    
    <!-- Left Column: Details -->
    <div class="package-detail-main">
      
      <!-- Photo Cover & Thumbnails -->
      <div class="package-gallery-container">
        <div class="package-cover-wrap">
          <img id="mainPackageCover" class="package-cover-img" 
               src="<?=!empty($gallery[0]['image_path']) ? e(asset_url($gallery[0]['image_path'])) : 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1500&q=90'?>" 
               alt="<?=e($p['name'])?>">
        </div>
        <?php if (count($gallery) > 1): ?>
          <div class="package-thumb-row">
            <?php foreach ($gallery as $idx => $g): ?>
              <button type="button" class="package-thumb-btn <?=$idx===0?'is-active':''?>" 
                      onclick="changePackageCover('<?=e(asset_url($g['image_path']))?>', this)">
                <img src="<?=e(asset_url($g['image_path']))?>" alt="<?=e($g['alt_text'] ?: $p['name'])?>">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Quick Nav Tabs -->
      <div class="package-tabs">
        <a href="#overview" class="is-active">OVERVIEW</a>
        <a href="#highlights">HIGHLIGHTS</a>
        <a href="#inclusions">INCLUSIONS</a>
        <a href="#good-to-know">GOOD TO KNOW</a>
      </div>

      <!-- Section: Overview -->
      <section id="overview" class="detail-block">
        <div>
          <?=!empty($p['description']) ? nl2br(e($p['description'])) : '<p>Experience the ultimate thrill of navigating through Bali’s rugged terrain, ancient bamboo forests, muddy rice paddies, and thrilling river beds. Fully guided by experienced local trail masters.</p>'?>
        </div>
      </section>

      <!-- Section: Highlights -->
      <section id="highlights" class="detail-block experience-highlights">
        <h3><span>⚡</span> EXPERIENCE HIGHLIGHTS</h3>
        <ul class="highlight-list">
          <?php foreach ($highlightRows as $hl): ?>
            <li><?=e($hl)?></li>
          <?php endforeach; ?>
        </ul>
      </section>

      <!-- Section: Inclusions & Exclusions -->
      <section id="inclusions" class="detail-block">
        <h3><span><i class="fa-solid fa-check" aria-hidden="true"></i></span> WHAT'S INCLUDED</h3>
        <ul class="check-list">
          <?php foreach ($includeRows as $inc): ?>
            <li><?=e($inc)?></li>
          <?php endforeach; ?>
        </ul>

        <?php if (!empty($excludeRows)): ?>
          <h3 style="margin-top: 30px;"><span><i class="fa-solid fa-xmark" aria-hidden="true"></i></span> WHAT'S EXCLUDED</h3>
          <ul class="check-list exclude-list">
            <?php foreach ($excludeRows as $exc): ?>
              <li><?=e($exc)?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <!-- Section: FAQs / Good To Know -->
      <section id="good-to-know" class="detail-block">
        <h3><span><i class="fa-solid fa-circle-info" aria-hidden="true"></i></span> GOOD TO KNOW</h3>
        <div class="faq-accordion">
          <?php foreach ($faqRows as $faq): ?>
            <details class="faq-item">
              <summary>
                <?=e($faq['question'])?>
                <span class="icon">+</span>
              </summary>
              <div class="faq-answer">
                <?=nl2br(e($faq['answer']))?>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      </section>

    </div>

    <!-- Right Column: Booking Panel -->
    <aside class="booking-panel">
      <!-- Special Price Badge with Green Animated Dot -->
      <div class="special-price-badge">
        SPECIAL ONLINE RATE
      </div>

      <!-- Pricing Row -->
      <div class="panel-price-row">
        <?php if ($originalPrice && $originalPrice > $currentPrice): ?>
          <div class="panel-price-old">
            <?=rupiah($originalPrice)?>
            <span class="panel-price-save">SAVE <?=(int)$discountPercent?>%</span>
          </div>
        <?php endif; ?>
        <div class="panel-price-current">
          <?=rupiah($currentPrice)?>
          <small>/ person</small>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="panel-actions">
        <a href="<?=BASE_URL?>/booking?package=<?=(int)$p['id']?>" class="btn-book-now">
          BOOK NOW <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
        </a>
        <?php if ($waUrl): ?>
          <a href="<?=$waUrl?>" target="_blank" rel="noopener" class="btn-whatsapp">
            WHATSAPP <span><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span>
          </a>
        <?php endif; ?>
      </div>

      <!-- Advance Confirmation Note (Desktop only, hidden on mobile) -->
      <div class="panel-confirm-note">
        <span class="note-icon"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></span>
        <div class="note-text">
          Instant confirmation voucher sent to your email immediately upon booking. Free date rescheduling up to 24 hours prior.
        </div>
      </div>

      <div class="panel-divider"></div>

      <!-- Package Quick Specs -->
      <ul class="panel-details-list">
        <?php if (!empty($priceBullets)): ?>
          <?php foreach ($priceBullets as $bullet): ?>
            <li><?=e($bullet)?></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li><span>Category:</span> <b><?=e($p['category_name'] ?: 'Extreme Adventure')?></b></li>
          <li><span>Duration:</span> <b><?=e($p['duration'] ?: 'Approx 1.5 - 2 Hours')?></b></li>
          <li><span>Location:</span> <b>Payangan, Ubud, Bali</b></li>
          <li><span>Guide:</span> <b>Certified Trail Instructor</b></li>
          <li><span>Instant Voucher:</span> <b>Yes (E-Ticket)</b></li>
        <?php endif; ?>
      </ul>
    </aside>

  </main>

  <!-- Related Packages Section -->
  <?php if (!empty($related)): ?>
    <section class="related-section">
      <div class="wrap">
        <div class="section-heading related-heading">
          <div>
            <p class="eyebrow"><span class="eyebrow-line"></span>MORE THRILLS</p>
            <h2 style="font-size: 38px;">OTHER <span>ADVENTURES.</span></h2>
          </div>
          <div class="related-slider-controls" aria-label="Other adventures slider controls">
            <button type="button" class="related-slider-btn" data-slide="prev" aria-label="Previous adventures">&#8592;</button>
            <button type="button" class="related-slider-btn" data-slide="next" aria-label="Next adventures">&#8594;</button>
          </div>
        </div>
        <div class="adventure-grid" id="relatedAdventureSlider" aria-label="Other adventure packages" tabindex="0">
          <?php foreach ($related as $rel): ?>
            <a href="<?=BASE_URL?>/package/<?=e($rel['slug'])?>" class="adventure-card" style="height: 420px; min-height: 420px;">
              <img src="<?=!empty($rel['cover_image']) ? e(asset_url($rel['cover_image'])) : 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1000&q=85'?>" alt="<?=e($rel['name'])?>" loading="lazy">
              <div class="card-shade"></div>
              <div class="card-content">
                <span class="card-tag"><?=e($rel['category_name'] ?: 'BALI TARANTULA')?></span>
                <h3 style="font-size: 24px;"><?=e($rel['name'])?></h3>
                <div class="card-price-current" style="font-size: 20px;">
                  <?=rupiah($rel['price_adult'])?> <small>/ person</small>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>
</div>

<script>
const relatedSlider = document.getElementById('relatedAdventureSlider');
if (relatedSlider) {
  document.querySelectorAll('.related-slider-btn').forEach(button => {
    button.addEventListener('click', () => {
      const card = relatedSlider.querySelector('.adventure-card');
      const gap = parseFloat(getComputedStyle(relatedSlider).columnGap || getComputedStyle(relatedSlider).gap) || 18;
      const step = card ? card.getBoundingClientRect().width + gap : relatedSlider.clientWidth * 0.8;
      relatedSlider.scrollBy({ left: button.dataset.slide === 'next' ? step : -step, behavior: 'smooth' });
    });
  });
}

function changePackageCover(src, btn) {
  const cover = document.getElementById('mainPackageCover');
  if (!cover) return;
  cover.style.opacity = '0.3';
  setTimeout(() => {
    cover.src = src;
    cover.style.opacity = '1';
  }, 150);
  document.querySelectorAll('.package-thumb-btn').forEach(b => b.classList.remove('is-active'));
  btn.classList.add('is-active');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
