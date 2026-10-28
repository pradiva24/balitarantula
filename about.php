<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $about = db()->query("SELECT * FROM about_page WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $about = [];
}

$title = 'About Us | ' . site_brand_name();
$description = 'Learn more about ' . site_brand_name() . ', our story, our extreme off-road mindset, and passion for Bali wilderness experiences.';
$canonical = BASE_URL . '/about.php';
$ogImage = !empty($about['hero_image']) ? asset_url($about['hero_image']) : '';

require __DIR__ . '/includes/header.php';
?>

<!-- Google Fonts: Barlow Condensed & Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style id="bta-about-modern-theme">
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

.bta-about * {
  box-sizing: border-box;
}

.bta-about .wrap {
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

/* Section Split: Who We Are */
.inner-split {
  padding: 100px 0;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 60px;
  align-items: center;
}

.inner-split .eyebrow {
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
  margin: 0;
}

.inner-title span {
  color: var(--bta-orange);
}

.inner-copy {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.inner-copy p {
  font-family: var(--bta-font-body);
  font-size: 16px;
  color: var(--bta-text);
  line-height: 1.75;
  margin: 0;
}

/* Buttons */
.button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 30px;
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
  width: fit-content;
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
  background: transparent;
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.4);
}

.button-outline:hover {
  background: rgba(255, 255, 255, 0.12);
  border-color: #ffffff;
  color: #ffffff;
}

/* Manifesto Section */
.manifesto {
  position: relative;
  min-height: 480px;
  display: flex;
  align-items: center;
  overflow: hidden;
  background: linear-gradient(112deg, #171717 0%, #242321 65%, #30271f 100%);
  padding: 100px 0;
  border-top: 3px solid var(--bta-orange);
  border-bottom: 3px solid var(--bta-orange);
}

.manifesto-image {
  position: absolute;
  inset: 0;
  background: url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1800&q=85') center/cover no-repeat;
  opacity: 0.25;
}

.manifesto-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, rgba(23,23,23,0.95) 0%, rgba(23,23,23,0.85) 60%, rgba(23,23,23,0.6) 100%);
}

.manifesto-content {
  position: relative;
  z-index: 2;
  max-width: 680px;
}

.manifesto-content .eyebrow {
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

.manifesto-content h2 {
  font-family: var(--bta-font-display);
  font-size: clamp(38px, 5.5vw, 68px);
  font-weight: 900;
  line-height: 0.98;
  letter-spacing: -0.5px;
  text-transform: uppercase;
  color: #ffffff;
  margin: 0 0 20px;
}

.manifesto-content h2 span {
  color: var(--bta-orange);
}

.manifesto-content p {
  font-size: 16px;
  color: #e0ded9;
  line-height: 1.7;
  margin: 0 0 28px;
}

/* Pillars Grid */
.pillars-section {
  padding: 90px 0;
  background: var(--bta-bg);
}

.pillars-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
  margin-top: 40px;
}

.pillar-card {
  background: var(--bta-panel);
  border: 1px solid var(--bta-border);
  border-top: 3px solid var(--bta-orange);
  border-radius: 12px;
  padding: 30px 24px;
  box-shadow: 0 10px 30px rgba(30, 25, 15, 0.05);
  transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s;
}

.pillar-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 16px 36px rgba(30, 25, 15, 0.1);
  border-color: var(--bta-orange);
}

.pillar-num {
  font-family: var(--bta-font-display);
  font-size: 14px;
  font-weight: 900;
  letter-spacing: 2px;
  color: var(--bta-orange);
  display: block;
  margin-bottom: 12px;
}

.pillar-card h3 {
  font-family: var(--bta-font-display);
  font-size: 24px;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--bta-heading);
  margin: 0 0 10px;
}

.pillar-card p {
  font-size: 14.5px;
  color: var(--bta-text);
  line-height: 1.6;
  margin: 0;
}

@media (max-width: 900px) {
  .inner-split {
    grid-template-columns: 1fr;
    gap: 35px;
    padding: 60px 0;
  }
  .pillars-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .pillars-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<div class="bta-about">
  <!-- Page Banner -->
  <section class="page-banner">
    <div class="wrap page-banner-content">
      <p class="eyebrow"><span class="eyebrow-line"></span>OUR STORY</p>
      <h1>BORN TO GO <span>WILD.</span></h1>
      <p>Adventure is more than a destination. It is the way you experience Bali.</p>
      <div class="breadcrumbs">
        <a href="<?=BASE_URL?>/">HOME</a> / OUR STORY
      </div>
    </div>
  </section>

  <!-- Who We Are -->
  <section class="wrap inner-split">
    <div>
      <p class="eyebrow"><span class="eyebrow-line"></span>WHO WE ARE</p>
      <h2 class="inner-title">BALI IS BETTER<br>OFF THE <span>BEATEN PATH.</span></h2>
    </div>
    <div class="inner-copy">
      <p><?=!empty($about['story_text_1']) ? e($about['story_text_1']) : 'Bali Tarantula Adventure is built around one simple idea: the best memories happen when you step outside your comfort zone. From muddy ATV trails to tropical landscapes, we invite you to experience another side of the island.'?></p>
      <p><?=!empty($about['story_text_2']) ? e($about['story_text_2']) : 'Our goal is to make every adventure exciting, welcoming, and memorable. Bring your friends, family, or partner and get ready to explore Bali’s deepest wilderness with top-tier safety gear and professional guides.'?></p>
      <a class="button button-orange" href="<?=BASE_URL?>/packages">EXPLORE PACKAGES <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span></a>
    </div>
  </section>

  <!-- Manifesto Banner -->
  <section class="manifesto">
    <div class="manifesto-image"></div>
    <div class="manifesto-overlay"></div>
    <div class="wrap manifesto-content">
      <p class="eyebrow"><span class="eyebrow-line"></span>OUR MINDSET</p>
      <h2>LESS ROUTINE.<br><span>MORE ADRENALINE.</span></h2>
      <p>Wild scenery, fresh air, muddy tracks, and stories you will bring home. That is what adventure means to us.</p>
      <a class="button button-outline" href="<?=BASE_URL?>/contact">PLAN YOUR ADVENTURE <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span></a>
    </div>
  </section>

  <!-- Pillars Section -->
  <section class="pillars-section">
    <div class="wrap">
      <div class="section-heading">
        <div>
          <p class="eyebrow"><span class="eyebrow-line"></span>CORE PRINCIPLES</p>
          <h2 style="font-family: var(--bta-font-display); font-size: clamp(34px, 4.5vw, 54px); font-weight: 900; color: var(--bta-heading); text-transform: uppercase; margin: 0;">WHY RIDE WITH <span>TARANTULA.</span></h2>
        </div>
      </div>
      <div class="pillars-grid">
        <div class="pillar-card">
          <span class="pillar-num">01 / SAFETY</span>
          <h3>WORLD-CLASS GEAR</h3>
          <p><?=!empty($about['why_2_text']) ? e($about['why_2_text']) : 'Full-face helmets, protective boots, and quad bikes serviced daily for maximum safety and performance.'?></p>
        </div>
        <div class="pillar-card">
          <span class="pillar-num">02 / TRAILS</span>
          <h3>CUSTOM RAW TRACKS</h3>
          <p><?=!empty($about['why_1_text']) ? e($about['why_1_text']) : 'Private, exclusive off-road courses through dense bamboo jungles, natural rivers, and steep mud pits.'?></p>
        </div>
        <div class="pillar-card">
          <span class="pillar-num">03 / CREW</span>
          <h3>LOCAL EXPERTS</h3>
          <p><?=!empty($about['why_4_text']) ? e($about['why_4_text']) : 'Passionate trail instructors born and raised in Bali, eager to guide you safely through every incline and turn.'?></p>
        </div>
        <div class="pillar-card">
          <span class="pillar-num">04 / SPIRIT</span>
          <h3>PURE FREEDOM</h3>
          <p><?=!empty($about['why_3_text']) ? e($about['why_3_text']) : 'No crowded tourist spots. Just unfiltered adrenaline, breathtaking views, and pure Balinese nature.'?></p>
        </div>
      </div>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
