<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$seo = db()->prepare("SELECT * FROM seo_meta WHERE page_key=? LIMIT 1");
$seo->execute(['packages']);
$seo = $seo->fetch() ?: [];

$title = $seo['seo_title'] ?? (site_brand_name() . ' | Adventure Packages');
$description = $seo['meta_description'] ?? setting('site_description', site_brand_name() . ' — Explore our thrilling ATV, UTV, and extreme adventure experiences in Bali.');

$hasBestSeller = false;
$hasDiscount = false;
try {
    $hasBestSeller = (bool)db()->query("SHOW COLUMNS FROM packages LIKE 'best_seller'")->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
try {
    $hasDiscount = (bool)db()->query("SHOW COLUMNS FROM packages LIKE 'discount_percent'")->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$bestSellerSelect = $hasBestSeller ? 'p.best_seller' : '0 AS best_seller';
$discountSelect = $hasDiscount ? 'p.discount_percent' : '0 AS discount_percent';

$packages = db()->query("
    SELECT p.id, p.category_id, p.name, p.slug, p.short_description, p.price_adult, {$discountSelect}, p.cover_image, {$bestSellerSelect}, c.name AS category_name 
    FROM packages p 
    LEFT JOIN categories c ON c.id = p.category_id 
    WHERE p.status = 1 
    ORDER BY p.homepage_order ASC, p.id ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$packageCategories = [];
foreach ($packages as $p) {
    $cid = (int)($p['category_id'] ?? 0);
    $cname = trim((string)($p['category_name'] ?? ''));
    if ($cid > 0 && $cname !== '' && !isset($packageCategories[$cid])) {
        $packageCategories[$cid] = $cname;
    }
}
asort($packageCategories, SORT_NATURAL | SORT_FLAG_CASE);

$heroImage = '';
foreach ($packages as $hp) {
    if (!empty($hp['cover_image'])) {
        $heroImage = asset_url($hp['cover_image']);
        break;
    }
}
if (!$heroImage) {
    $heroImage = 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1800&q=85';
}

require __DIR__ . '/includes/header.php';
?>

<!-- Google Fonts: Barlow Condensed & Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style id="bta-packages-modern-theme">
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

.bta-packages * {
  box-sizing: border-box;
}

.bta-packages .wrap {
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
  background: url('<?=e($heroImage)?>') center/cover no-repeat;
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

/* Main Section */
.packages-main-section {
  padding: 70px 0 110px;
  background: var(--bta-bg);
}

.section-heading {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 24px;
  margin-bottom: 35px;
  flex-wrap: wrap;
}

.section-heading .eyebrow {
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

.section-heading h2 {
  font-family: var(--bta-font-display);
  font-size: clamp(34px, 5vw, 62px);
  font-weight: 900;
  line-height: 0.98;
  letter-spacing: -0.5px;
  text-transform: uppercase;
  color: var(--bta-heading);
  margin: 0;
}

.section-heading h2 span {
  color: var(--bta-orange);
}

.section-description {
  font-family: var(--bta-font-body);
  font-size: 15px;
  color: var(--bta-text);
  max-width: 520px;
  margin: 0;
  line-height: 1.7;
}

/* Category Filter Tabs */
.packages-category-wrap {
  margin-bottom: 40px;
  position: relative;
}

.packages-category-tabs {
  display: flex;
  align-items: center;
  gap: 10px;
  overflow-x: auto;
  overflow-y: hidden;
  padding: 4px 2px 14px;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  white-space: nowrap;
}

.packages-category-tabs::-webkit-scrollbar {
  display: none;
}

.packages-category-tab {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--bta-panel);
  border: 1px solid var(--bta-border);
  color: var(--bta-text);
  font-family: var(--bta-font-display);
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  padding: 10px 22px;
  border-radius: 999px;
  cursor: pointer;
  transition: all 0.25s ease;
  flex: 0 0 auto;
  box-shadow: 0 2px 8px rgba(30, 25, 15, 0.04);
}

.packages-category-tab:hover {
  border-color: var(--bta-orange);
  color: var(--bta-heading);
  background: var(--bta-panel-hover);
}

.packages-category-tab.is-active {
  background: var(--bta-orange);
  border-color: var(--bta-orange);
  color: #ffffff;
  box-shadow: 0 4px 18px var(--bta-orange-glow);
}

.packages-category-tab.is-active::before {
  content: "";
  display: inline-block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #ffffff;
  animation: tabDotPulse 1.2s ease-in-out infinite;
}

@keyframes tabDotPulse {
  0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 0 0 rgba(255,255,255,0.7); }
  50% { opacity: 0.3; transform: scale(0.7); box-shadow: 0 0 0 4px rgba(255,255,255,0); }
}

/* Adventure Grid & Cards */
.adventure-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 28px;
}

.adventure-card {
  position: relative;
  min-height: 480px;
  height: 520px;
  background: #252525;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--bta-border);
  box-shadow: 0 12px 32px rgba(30, 25, 15, 0.08);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 32px 28px;
  transition: transform 0.35s ease, border-color 0.35s ease, box-shadow 0.35s ease;
  text-decoration: none;
}

.adventure-card:hover {
  transform: translateY(-8px);
  border-color: var(--bta-orange);
  box-shadow: 0 20px 48px rgba(30, 25, 15, 0.16), 0 0 24px var(--bta-orange-glow);
}

.adventure-card img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s ease;
  z-index: 1;
}

.adventure-card:hover img {
  transform: scale(1.06);
}

.card-shade {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(10,13,18,0.15) 0%, rgba(10,13,18,0.52) 45%, rgba(10,13,18,0.96) 100%);
  z-index: 2;
  transition: background 0.35s ease;
}

.card-number {
  position: absolute;
  top: 24px;
  left: 24px;
  z-index: 3;
  font-family: var(--bta-font-display);
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 2px;
  color: #ffffff;
  background: rgba(10, 13, 18, 0.65);
  backdrop-filter: blur(8px);
  padding: 6px 14px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.card-badge-bestseller {
  position: absolute;
  top: 24px;
  right: 24px;
  z-index: 3;
  font-family: var(--bta-font-display);
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 1.5px;
  color: #fff;
  background: var(--bta-orange);
  padding: 6px 14px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 4px 15px var(--bta-orange-glow);
}

.card-badge-bestseller::before {
  content: "";
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ffffff;
  animation: tabDotPulse 1.2s ease-in-out infinite;
}

.card-content {
  position: relative;
  z-index: 3;
}

.card-tag {
  display: inline-block;
  font-family: var(--bta-font-display);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--bta-orange);
  margin-bottom: 8px;
}

.adventure-card h3 {
  font-family: var(--bta-font-display);
  font-size: 30px;
  font-weight: 900;
  line-height: 1.05;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: #ffffff;
  margin: 0 0 10px;
}

.adventure-card p {
  font-family: var(--bta-font-body);
  font-size: 14px;
  color: #e0ded9;
  line-height: 1.55;
  margin: 0 0 18px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-pricing-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  padding-top: 14px;
  border-top: 1px solid rgba(255, 255, 255, 0.18);
  margin-top: 10px;
}

.card-price-box {
  display: flex;
  flex-direction: column;
}

.card-price-old {
  font-size: 13px;
  color: #dedbd4;
  text-decoration: line-through;
  line-height: 1.2;
}

.card-price-save {
  display: inline-block;
  font-family: var(--bta-font-display);
  font-size: 11px;
  font-weight: 900;
  color: #fff;
  background: var(--bta-orange);
  padding: 2px 6px;
  border-radius: 4px;
  margin-left: 6px;
}

.card-price-current {
  font-family: var(--bta-font-display);
  font-size: 26px;
  font-weight: 900;
  color: #ffffff;
  line-height: 1.1;
  letter-spacing: 0.5px;
}

.card-price-current small {
  font-family: var(--bta-font-body);
  font-size: 11px;
  color: #dedbd4;
  font-weight: 500;
  margin-left: 4px;
}

.card-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: var(--bta-font-display);
  font-size: 15px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: #111111;
  background: var(--bta-orange);
  padding: 10px 18px;
  border-radius: 6px;
  text-decoration: none;
  transition: all 0.25s ease;
  white-space: nowrap;
}

.card-link:hover {
  background: var(--bta-orange-hover);
  transform: translateX(3px);
  color: #111111;
}

.adventure-card.is-hidden {
  display: none !important;
}

.packages-empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 60px 20px;
  background: var(--bta-panel);
  border-radius: 16px;
  border: 1px dashed var(--bta-border);
  color: var(--bta-muted);
}

@media (max-width: 1024px) {
  .adventure-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 22px;
  }
}

@media (max-width: 640px) {
  .adventure-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }
  .page-banner {
    min-height: 300px;
    padding: 100px 0 45px;
  }
  .adventure-card {
    height: 460px;
    min-height: 440px;
    padding: 24px 20px;
  }
  .adventure-card h3 {
    font-size: 26px;
  }
  .card-pricing-row {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
  .card-link {
    justify-content: center;
  }
}


/* Match the homepage's dark, sharp-edged adventure package cards. */
body{background:#101010;color:#f7f5f0}
.bta-packages .packages-main-section{background:#101010;padding-top:42px}
.bta-packages .packages-category-tab{background:#181818;border-color:#343434;color:#e0e0e0;box-shadow:none;border-radius:0}
.bta-packages .packages-category-tab:hover{background:#222;border-color:#ff5a1f;color:#fff}
.bta-packages .packages-category-tab.is-active{background:#ff5a1f;border-color:#ff5a1f;color:#111;box-shadow:none}
.bta-packages .adventure-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.bta-packages .adventure-card{height:auto;min-height:430px;border:0;border-radius:0;box-shadow:none;padding:0;background:#252525}
.bta-packages .adventure-card:hover{transform:translateY(-4px);border-color:transparent;box-shadow:none}
.bta-packages .adventure-card img{z-index:0}
.bta-packages .card-shade{z-index:1;background:linear-gradient(0deg,rgba(0,0,0,.94),rgba(0,0,0,.12) 75%)}
.bta-packages .package-card-badges{position:absolute;z-index:3;top:16px;left:16px;right:16px;display:flex;align-items:flex-start;justify-content:space-between;gap:8px;pointer-events:none}
.bta-packages .package-category-badge{display:inline-flex;align-items:center;max-width:70%;padding:8px 12px;border-radius:50px;background:rgba(0,0,0,.68);border:1px solid rgba(255,255,255,.2);color:#fff;font:800 9px/1 var(--bta-font-body);letter-spacing:1px;text-transform:uppercase;backdrop-filter:blur(5px);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bta-packages .package-best-seller{display:inline-flex;align-items:center;gap:7px;flex:0 0 auto;padding:8px 12px;border-radius:50px;background:#ff5a1f;color:#fff;font:900 9px/1 var(--bta-font-body);letter-spacing:.9px;text-transform:uppercase}
.bta-packages .package-best-seller-dot{width:7px;height:7px;flex:0 0 7px;border-radius:50%;background:#fff;animation:tabDotPulse 1.2s ease-in-out infinite}
.bta-packages .card-content{position:absolute;left:0;right:0;bottom:0;padding:24px 22px;z-index:3}
.bta-packages .adventure-card h3{font-size:clamp(30px,3.2vw,43px);line-height:.92;letter-spacing:.2px;margin:0 0 12px;color:#fff}
.bta-packages .adventure-card p{font-size:11px;color:#c7c7c7;line-height:1.8;margin:0 0 14px}
.bta-packages .card-pricing-row{align-items:flex-end;border-top:1px solid #ffffff55;padding-top:13px;margin-top:12px;gap:10px}
.bta-packages .card-price-current{font-size:24px;color:#fff}
.bta-packages .card-price-current small{font-size:10px;color:#c7c7c7}
.bta-packages .card-price-old{font-size:12px;color:#c7c7c7}
.bta-packages .card-price-save{background:#ff5a1f;color:#fff;border-radius:0}
.bta-packages .card-link{background:transparent;color:#ff5a1f;padding:0;border-radius:0;font-size:13px;letter-spacing:1px;white-space:normal}
.bta-packages .card-link:hover{background:transparent;color:#ff743f;transform:translateX(3px)}
.bta-packages .card-link span{color:#ff5a1f}
.bta-packages .packages-empty-state{background:#181818;border-color:#343434;border-radius:0;color:#aaa}
@media(max-width:1024px){.bta-packages .adventure-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.bta-packages .adventure-card{min-height:410px}}
@media(max-width:640px){.bta-packages .packages-main-section{padding-top:26px}.bta-packages .adventure-grid{grid-template-columns:minmax(0,1fr);gap:14px}.bta-packages .adventure-card{min-height:410px;height:430px}.bta-packages .card-content{padding:22px 20px}.bta-packages .adventure-card h3{font-size:clamp(34px,10vw,46px)}.bta-packages .card-pricing-row{flex-direction:row;align-items:flex-end}.bta-packages .card-link{justify-content:flex-end;font-size:12px}}


/* White-background package collection, matching the supplied card reference */
:root {
  --bta-bg: #ffffff;
  --bta-panel: #ffffff;
  --bta-panel-hover: #f7f7f7;
  --bta-heading: #171717;
  --bta-text: #4d4d4d;
  --bta-muted: #777777;
  --bta-border: #e4e4e4;
  --bta-border-subtle: #eeeeee;
}
html, body { background: #fff !important; }
body { color: #222; }
.bta-packages { background: #fff; color: #222; }
.bta-packages .packages-main-section {
  background:#fff;
  padding: 34px 0 72px;
}
.bta-packages .wrap { width:min(1280px, calc(100% - 48px)); }
.bta-packages .packages-category-wrap { margin-bottom:24px; }
.bta-packages .packages-category-tab {
  border-radius:0;
  box-shadow:none;
  background:#fff;
  border-color:#dedede;
  color:#292929;
}
.bta-packages .packages-category-tab.is-active {
  background:#ff5a1f;
  border-color:#ff5a1f;
  color:#fff;
  box-shadow:none;
}
.bta-packages .adventure-grid {
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:14px;
}
.bta-packages .adventure-card {
  height:430px;
  min-height:430px;
  border:0;
  border-radius:0;
  box-shadow:none;
  padding:22px;
  background:#171717;
}
.bta-packages .adventure-card:hover {
  transform:translateY(-3px);
  border-color:transparent;
  box-shadow:0 10px 24px rgba(0,0,0,.13);
}
.bta-packages .adventure-card img { z-index:1; }
.bta-packages .card-shade {
  background:linear-gradient(180deg,rgba(0,0,0,.08) 0%,rgba(0,0,0,.18) 35%,rgba(0,0,0,.9) 100%);
}
.bta-packages .card-number {
  top:16px; left:16px;
  background:rgba(10,10,10,.88);
  border:1px solid rgba(255,255,255,.15);
  border-radius:30px;
  font-family:var(--bta-font-body);
  font-size:9px;
  letter-spacing:1px;
  padding:8px 12px;
}
.bta-packages .card-badge-bestseller {
  top:16px; right:16px;
  border-radius:30px;
  font-family:var(--bta-font-body);
  font-size:9px;
  letter-spacing:.8px;
  padding:7px 11px;
}
.bta-packages .card-content { width:100%; }
.bta-packages .card-tag {
  display:none;
}
.bta-packages .adventure-card h3 {
  font-size:clamp(25px,2.2vw,32px);
  line-height:1.02;
  margin:0 0 13px;
  letter-spacing:0;
}
.bta-packages .adventure-card p {
  font-size:12px;
  line-height:1.55;
  margin-bottom:10px;
  color:#e3e3e3;
}
.bta-packages .card-pricing-row {
  display:block;
  border-top:0;
  margin-top:0;
  padding-top:0;
}
.bta-packages .card-price-box {
  display:block;
  margin-bottom:13px;
}
.bta-packages .card-price-old {
  display:inline-block;
  color:#d8d8d8;
  font-size:13px;
  margin-right:6px;
}
.bta-packages .card-price-save {
  font-family:var(--bta-font-body);
  font-size:9px;
  border-radius:0;
}
.bta-packages .card-price-current {
  color:#ff5a1f;
  font-family:var(--bta-font-body);
  font-size:21px;
  font-weight:800;
}
.bta-packages .card-price-current small {
  color:#e5e5e5;
  font-size:10px;
  font-weight:400;
}
.bta-packages .card-link {
  display:flex;
  justify-content:space-between;
  width:100%;
  border-top:1px solid rgba(255,255,255,.35);
  border-radius:0;
  background:transparent;
  color:#ff5a1f;
  padding:14px 0 0;
  font-family:var(--bta-font-body);
  font-size:9px;
  letter-spacing:1.2px;
}
.bta-packages .card-link:hover {
  background:transparent;
  color:#fff;
  transform:none;
}
.bta-packages .card-link span { font-size:15px; }
.bta-packages .packages-empty-state {
  background:#fff;
  border-radius:0;
  box-shadow:none;
}
@media(max-width:1024px) {
  .bta-packages .adventure-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media(max-width:640px) {
  .bta-packages .wrap { width:calc(100% - 28px); }
  .bta-packages .packages-main-section { padding:24px 0 50px; }
  .bta-packages .adventure-grid { grid-template-columns:1fr; gap:14px; }
  .bta-packages .adventure-card { height:430px; min-height:430px; padding:20px; }
  .bta-packages .adventure-card h3 { font-size:29px; }
  .bta-packages .card-pricing-row { display:block; }
  .bta-packages .card-price-box { margin-bottom:12px; }
  .bta-packages .card-link { justify-content:space-between; }
}

</style>

<div class="bta-packages">
  <!-- Page Banner -->
  <section class="page-banner">
    <div class="wrap page-banner-content">
      <p class="eyebrow"><span class="eyebrow-line"></span>OUR PACKAGES</p>
      <h1>CHOOSE YOUR <span>THRILL.</span></h1>
      <p>Find your route, pick your pace, and get ready to ride through the heart of Bali's rugged wilderness.</p>
      <div class="breadcrumbs">
        <a href="<?=BASE_URL?>/">HOME</a> / OUR PACKAGES
      </div>
    </div>
  </section>

  <!-- Packages List Section -->
  <main class="packages-main-section" id="adventure-packages">
    <div class="wrap">
      

      <!-- Category Filter Tabs -->
      <div class="packages-category-wrap">
        <div class="packages-category-tabs" role="tablist" aria-label="Package Categories">
          <button type="button" class="packages-category-tab is-active" data-category="all" role="tab" aria-selected="true">
            ALL PACKAGES
          </button>
          <?php foreach ($packageCategories as $cid => $cname): ?>
            <button type="button" class="packages-category-tab" data-category="<?=e((string)$cid)?>" role="tab" aria-selected="false">
              <?=e($cname)?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Packages Grid -->
      <div class="adventure-grid" data-packages-grid>
        <?php if (!empty($packages)): ?>
          <?php 
          $idx = 1;
          foreach ($packages as $p): 
            $discountPercent = max(0, min(90, (float)($p['discount_percent'] ?? 0)));
            $currentPrice    = (float)($p['price_adult'] ?? 0);
            $originalPrice   = ($discountPercent > 0 && $currentPrice > 0) ? ($currentPrice / (1 - $discountPercent / 100)) : 0;
            $cardNumber      = sprintf('%02d / %s', $idx++, strtoupper(substr($p['category_name'] ?: 'ATV', 0, 6)));
            $packageUrl      = BASE_URL . '/package/' . e($p['slug']);
          ?>
            <article class="adventure-card" data-category="<?=e((string)(int)($p['category_id'] ?? 0))?>">
              <?php if (!empty($p['cover_image'])): ?>
                <img src="<?=e(asset_url($p['cover_image']))?>" alt="<?=e($p['name'])?>" loading="lazy" decoding="async">
              <?php else: ?>
                <img src="https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1000&q=85" alt="<?=e($p['name'])?>" loading="lazy">
              <?php endif; ?>

              <div class="card-shade"></div>
              <div class="package-card-badges">
                <span class="package-category-badge"><?=e($p['category_name'] ?: 'BALI ADVENTURE')?></span>
                <?php if (!empty($p['best_seller'])): ?>
                  <span class="package-best-seller"><span class="package-best-seller-dot" aria-hidden="true"></span>BEST SELLER</span>
                <?php endif; ?>
              </div>

              <div class="card-content">
                <h3><?=e($p['name'])?></h3>
                <p><?=e($p['short_description'])?></p>
                
                <div class="card-pricing-row">
                  <div class="card-price-box">
                    <?php if ($originalPrice > 0): ?>
                      <div class="card-price-old">
                        <?=rupiah($originalPrice)?>
                        <span class="card-price-save">SAVE <?=(int)$discountPercent?>%</span>
                      </div>
                    <?php endif; ?>
                    <div class="card-price-current">
                      <?=rupiah($currentPrice)?>
                      <small>/ person</small>
                    </div>
                  </div>
                  <a href="<?=$packageUrl?>" class="card-link">
                    VIEW PACKAGE <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
                  </a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="packages-empty-state">
            <h3>NO PACKAGES FOUND</h3>
            <p>Please check back soon or add packages via the Admin Portal.</p>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<script>
(function(){
  'use strict';
  const tabs = Array.from(document.querySelectorAll('.packages-category-tab'));
  const grid = document.querySelector('[data-packages-grid]');
  if (!tabs.length || !grid) return;

  const cards = Array.from(grid.querySelectorAll('.adventure-card'));
  const empty = document.createElement('div');
  empty.className = 'packages-empty-state';
  empty.innerHTML = '<h3>NO PACKAGES IN THIS CATEGORY</h3><p>Try selecting another category or view all packages.</p>';

  function filter(category) {
    let visible = 0;
    cards.forEach(card => {
      const match = category === 'all' || card.getAttribute('data-category') === category;
      card.classList.toggle('is-hidden', !match);
      if (match) visible++;
    });

    if (visible === 0) {
      if (!grid.contains(empty)) grid.appendChild(empty);
    } else if (grid.contains(empty)) {
      empty.remove();
    }

    tabs.forEach(tab => {
      const active = tab.getAttribute('data-category') === category;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      filter(tab.getAttribute('data-category') || 'all');
    });
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>