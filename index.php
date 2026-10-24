<?php
if (is_file(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/app/bootstrap.php';
}
require_once __DIR__ . '/includes/cms.php';
$heroSlides = [
    bta_content_image('hero_image_1', 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1800&q=85'),
    bta_content_image('hero_image_2', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1800&q=85'),
    bta_content_image('hero_image_3', 'https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=1800&q=85'),
];
$heroSlides = array_values(array_unique(array_filter($heroSlides)));
if (!$heroSlides) $heroSlides = ['https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1800&q=85'];
$cms = static fn(string $key, string $fallback): string => htmlspecialchars(bta_content($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$cmsImage = static fn(string $key, string $fallback): string => htmlspecialchars(bta_content_image($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#111111">
  <meta name="description" content="Explore Bali with Bali Tarantula Adventure. Ride rugged ATV trails, discover jungle routes, and experience unforgettable outdoor adventures.">
  <title>Bali Tarantula Adventure | Go Beyond the Ordinary</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=20261029">
  <script src="assets/js/main.js?v=20261029" defer></script>
</head>
<body>
  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main>
    <section class="hero" id="home">
      <div class="hero-image" role="img" aria-label="ATV adventure through a tropical jungle in Bali" data-hero-slides="<?= htmlspecialchars(json_encode($heroSlides, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>" style="background-image:url('<?= $cmsImage('hero_image_1', 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1800&q=85') ?>')"></div>
      <div class="hero-shade"></div>
      <div class="hero-content wrap">
        <p class="eyebrow"><span class="eyebrow-line"></span> <?= $cms('hero_eyebrow','YOUR NEXT ADVENTURE STARTS HERE') ?></p>
        <h1><?= $cms('hero_title_main','CHASE THE') ?><br><span><?= $cms('hero_title_accent','UNTAMED.') ?></span></h1>
        <p class="hero-text"><?= $cms('hero_description','Trade the ordinary for muddy trails, wild landscapes, and stories worth telling. Bali is calling. Are you ready?') ?></p>
        <div class="hero-actions"><a class="button button-orange" href="#adventures"><?= $cms('hero_cta_primary','EXPLORE ADVENTURES') ?> <span>↗</span></a><a class="text-link" href="#about"><span class="play-icon">▶</span> <?= $cms('hero_cta_secondary','DISCOVER OUR WORLD') ?></a></div>
        <div class="hero-bottom"><span><b>01</b> / OUTDOOR EXPERIENCES</span><span class="scroll-note">SCROLL TO EXPLORE <i>↓</i></span></div>
      </div>
      <div class="hero-stamp"><span>GO</span><span>WILD</span><small> BALI • EST. FOR ADVENTURE</small></div>
    </section>

    <section class="intro-strip">
      <div class="wrap strip-inner"><span class="strip-label"><?= $cms('intro_label','NOT JUST A RIDE.') ?></span><p><?= $cms('intro_title',"IT'S YOUR CALL TO ADVENTURE.") ?></p><span class="strip-symbol">✳</span></div>
    </section>

    <section class="section adventures wrap" id="adventures">
      <div class="section-heading">
        <div><p class="eyebrow"><span class="eyebrow-line"></span> <?= $cms('adventures_eyebrow','PICK YOUR THRILL') ?></p><h2><?= $cms('adventures_title_main','FIND YOUR') ?> <span><?= $cms('adventures_title_accent','WILD.') ?></span></h2></div>
        <p class="section-description"><?= $cms('adventures_description','From jungle trails to rushing rivers, choose the experience that gets your heart racing.') ?></p>
      </div>
      <div class="adventure-grid">
        <article class="adventure-card card-featured">
          <img src="<?= $cmsImage('adventure_1_image','https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1200&q=85') ?>" alt="<?= $cms('adventure_1_title','ATV Adventure') ?>" loading="lazy">
          <div class="card-shade"></div><span class="card-number">01 / LAND</span>
          <div class="card-content"><span class="card-tag"><?= $cms('adventure_1_tag','DIRT. POWER. FREEDOM.') ?></span><h3><?= $cms('adventure_1_title','ATV ADVENTURE') ?></h3><p><?= $cms('adventure_1_description','Push through rugged tracks, rice fields, and tropical terrain.') ?></p><a href="#book" class="card-link">EXPLORE ATV <span>↗</span></a></div>
        </article>
        <article class="adventure-card">
          <img src="<?= $cmsImage('adventure_2_image','https://images.unsplash.com/photo-1530866495561-507c9faab2ed?auto=format&fit=crop&w=1000&q=85') ?>" alt="<?= $cms('adventure_2_title','White Water Rafting') ?>" loading="lazy">
          <div class="card-shade"></div><span class="card-number">02 / WATER</span>
          <div class="card-content"><span class="card-tag"><?= $cms('adventure_2_tag','RIDE THE CURRENT.') ?></span><h3><?= $cms('adventure_2_title','WHITE WATER RAFTING') ?></h3><p><?= $cms('adventure_2_description',"Take on the river and feel the rush of Bali's wild side.") ?></p><a href="#book" class="card-link">EXPLORE RAFTING <span>↗</span></a></div>
        </article>
        <article class="adventure-card">
          <img src="<?= $cmsImage('adventure_3_image','https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=1000&q=85') ?>" alt="<?= $cms('adventure_3_title','Jungle Explorer') ?>" loading="lazy">
          <div class="card-shade"></div><span class="card-number">03 / JUNGLE</span>
          <div class="card-content"><span class="card-tag"><?= $cms('adventure_3_tag','OFF THE BEATEN PATH.') ?></span><h3><?= $cms('adventure_3_title','JUNGLE EXPLORER') ?></h3><p><?= $cms('adventure_3_description','Discover hidden corners and unforgettable natural scenery.') ?></p><a href="#book" class="card-link">EXPLORE TRACKS <span>↗</span></a></div>
        </article>
      </div>
      <div class="section-foot"><span>01 — 03</span><span class="foot-line"></span><span>YOUR NEXT STORY IS OUT THERE.</span></div>
    </section>


    <section class="section tracks-section" id="tracks">
      <div class="wrap">
        <div class="section-heading">
          <div><p class="eyebrow"><span class="eyebrow-line"></span> <?= $cms('tracks_eyebrow','PICK YOUR TERRAIN') ?></p><h2><?= $cms('tracks_title_main','FOUR TRACKS.') ?><br><span><?= $cms('tracks_title_accent','ONE WILD RIDE.') ?></span></h2></div>
          <p class="section-description"><?= $cms('tracks_description','Choose your route and get ready for mud, jungle, river crossings, and unforgettable Bali landscapes.') ?></p>
        </div>
        <div class="tracks-grid">
          <article class="track-card track-orange">
            <img src="<?= $cmsImage('track_1_image','https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&w=1000&q=85') ?>" alt="<?= $cms('track_1_title','Tarantula Jungle Track') ?>" loading="lazy">
            <div class="track-overlay"></div><span class="track-index">TRACK / 01</span><span class="track-badge">MOST THRILLING</span>
            <div class="track-info"><p>DIRT • SPLASH • ADRENALINE</p><h3><?= $cms('track_1_title','TARANTULA JUNGLE TRACK') ?></h3><span class="track-desc"><?= $cms('track_1_description','Ride through wild greenery and rugged off-road terrain.') ?></span><a href="#book" class="track-link">EXPLORE THIS TRACK <b>↗</b></a></div>
          </article>
          <article class="track-card track-red">
            <img src="<?= $cmsImage('track_2_image','https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1000&q=85') ?>" alt="<?= $cms('track_2_title','Mud Track') ?>" loading="lazy">
            <div class="track-overlay"></div><span class="track-index">TRACK / 02</span>
            <div class="track-info"><p>GET MESSY</p><h3><?= $cms('track_2_title','MUD TRACK') ?></h3><span class="track-desc"><?= $cms('track_2_description','Take on muddy paths for a raw, muddy ATV experience.') ?></span><a href="#book" class="track-link">EXPLORE THIS TRACK <b>↗</b></a></div>
          </article>
          <article class="track-card track-dark">
            <img src="<?= $cmsImage('track_3_image','https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=1000&q=85') ?>" alt="<?= $cms('track_3_title','Jungle Trail') ?>" loading="lazy">
            <div class="track-overlay"></div><span class="track-index">TRACK / 03</span>
            <div class="track-info"><p>DEEP IN THE GREEN</p><h3><?= $cms('track_3_title','JUNGLE TRAIL') ?></h3><span class="track-desc"><?= $cms('track_3_description','Explore shaded forest trails surrounded by tropical nature.') ?></span><a href="#book" class="track-link">EXPLORE THIS TRACK <b>↗</b></a></div>
          </article>
          <article class="track-card track-orange">
            <img src="<?= $cmsImage('track_4_image','https://images.unsplash.com/photo-1470770841072-f978cf4d019e?auto=format&fit=crop&w=1000&q=85') ?>" alt="<?= $cms('track_4_title','River Adventure') ?>" loading="lazy">
            <div class="track-overlay"></div><span class="track-index">TRACK / 04</span>
            <div class="track-info"><p>WATER • NATURE • ACTION</p><h3><?= $cms('track_4_title','RIVER ADVENTURE') ?></h3><span class="track-desc"><?= $cms('track_4_description','Add a splash of excitement as the route meets the water.') ?></span><a href="#book" class="track-link">EXPLORE THIS TRACK <b>↗</b></a></div>
          </article>
        </div>
        <div class="tracks-note"><span class="tracks-note-mark">!</span><p>Adventure level and route conditions can vary. Confirm the final track details with the operator before booking.</p></div>
      </div>
    </section>

    <section class="manifesto" id="about">
      <div class="manifesto-image" style="background-image:url('<?= $cmsImage('manifesto_image','https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=1800&q=85') ?>')"></div><div class="manifesto-overlay"></div>
      <div class="manifesto-content wrap"><p class="eyebrow"><span class="eyebrow-line"></span> <?= $cms('manifesto_eyebrow','MORE THAN AN ACTIVITY') ?></p><h2><?= $cms('manifesto_title_main','LEAVE THE COMFORT ZONE.') ?><br><span><?= $cms('manifesto_title_accent','FIND YOUR EDGE.') ?></span></h2><p><?= $cms('manifesto_description','We believe the best memories happen when you get out there. Get muddy, get moving, and experience Bali beyond the usual path—with a team that lives for adventure.') ?></p><a class="button button-outline" href="#book"><?= $cms('manifesto_cta','MEET YOUR NEXT ADVENTURE') ?> <span>↗</span></a></div>
      <div class="manifesto-side">BALI TARANTULA / ADVENTURE IS OUT THERE</div>
    </section>

    <section class="section why-us wrap" id="why-us">
      <div class="section-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span> <?= $cms('why_eyebrow','THE TARANTULA DIFFERENCE') ?></p><h2><?= $cms('why_title_main','BUILT FOR') ?> <span><?= $cms('why_title_accent','THE THRILL.') ?></span></h2></div><p class="section-description"><?= $cms('why_description','Good adventures are remembered. Great ones become part of your story.') ?></p></div>
      <div class="reasons">
        <article><span class="reason-no">01</span><div class="reason-icon">✳</div><h3><?= $cms('why_1_title','REAL ADVENTURE') ?></h3><p><?= $cms('why_1_description','Immersive outdoor experiences that take you beyond the ordinary.') ?></p></article>
        <article><span class="reason-no">02</span><div class="reason-icon">↗</div><h3><?= $cms('why_2_title','LOCAL KNOW-HOW') ?></h3><p><?= $cms('why_2_description','Explore Bali with a team that knows the terrain and loves the island.') ?></p></article>
        <article><span class="reason-no">03</span><div class="reason-icon">＋</div><h3><?= $cms('why_3_title','YOUR KIND OF WILD') ?></h3><p><?= $cms('why_3_description','Choose an experience that matches your crew, your pace, and your spirit.') ?></p></article>
      </div>
    </section>


    <section class="reviews-section" id="guest-reviews">
      <div class="wrap">
        <div class="section-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span> <?= $cms('reviews_eyebrow','GUEST REVIEWS') ?></p><h2><?= $cms('reviews_title_main','THE ADVENTURE') ?><br><span><?= $cms('reviews_title_accent','IN THEIR WORDS.') ?></span></h2></div><p class="section-description"><?= $cms('reviews_description','Real feedback helps future adventurers choose the right experience.') ?></p></div>
        <div class="review-grid">
          <article class="review-card"><div class="review-stars" aria-label="Review rating placeholder">★★★★★</div><blockquote>“<?= $cms('review_1_quote','Add a verified guest review here.') ?>”</blockquote><footer><?= $cms('review_1_author','Guest review') ?><span>Guest review</span></footer></article>
          <article class="review-card"><div class="review-stars" aria-label="Review rating placeholder">★★★★★</div><blockquote>“<?= $cms('review_2_quote','Add another verified guest review here.') ?>”</blockquote><footer><?= $cms('review_2_author','Guest review') ?><span>Guest review</span></footer></article>
          <article class="review-card"><div class="review-stars" aria-label="Review rating placeholder">★★★★★</div><blockquote>“<?= $cms('review_3_quote','Add another verified guest review here.') ?>”</blockquote><footer><?= $cms('review_3_author','Guest review') ?><span>Guest review</span></footer></article>
        </div>
        <p class="review-note">Review section preview: these are layout placeholders, not published customer testimonials. Add only authentic reviews and accurate ratings before launch.</p>
      </div>
    </section>

    <section class="booking-cta" id="book"><div class="cta-pattern"></div><div class="wrap cta-inner"><p class="eyebrow"><span class="eyebrow-line"></span> <?= $cms('cta_eyebrow','YOUR STORY STARTS HERE') ?></p><h2><?= $cms('cta_title_main','READY TO GET') ?><br><span><?= $cms('cta_title_accent','DIRTY?') ?></span></h2><p><?= $cms('cta_description',"Bring your crew. Bring your courage. We'll bring the adventure.") ?></p><a class="button button-dark" href="<?= $cms('cta_whatsapp_url','https://wa.me/') ?>" target="_blank" rel="noopener"><?= $cms('cta_button','PLAN YOUR ADVENTURE') ?> <span>↗</span></a><small>UPDATE THE WHATSAPP LINK BEFORE LAUNCH</small></div></section>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
(()=>{const el=document.querySelector('[data-hero-slides]');if(!el)return;let slides=[];try{slides=JSON.parse(el.dataset.heroSlides||'[]')}catch(e){}slides=slides.filter(Boolean);if(slides.length<2)return;let i=0;window.setInterval(()=>{i=(i+1)%slides.length;el.style.backgroundImage='url("'+slides[i].replace(/["\\\\]/g,'')+'")'},6500)})();
</script>
</body>
</html>