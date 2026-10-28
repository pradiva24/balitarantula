<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$title = 'Photo Gallery | ' . site_brand_name();
$description = 'Explore thrilling moments from Bali Tarantula Adventure: rugged trails, muddy quads, bamboo jungles, and extreme Bali landscapes.';
$canonical = BASE_URL . '/gallery.php';

$gallery = [];
$categories = [];

try {
    $rows = db()->query("
        SELECT id, title, alt_text, category, image_path, sort_order 
        FROM gallery 
        WHERE status = 1 
        ORDER BY sort_order ASC, id DESC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fallback if gallery table is empty: use package images
    if (empty($rows)) {
        $pkgImgs = db()->query("
            SELECT pi.id, pi.image_path, pi.alt_text, p.name AS title, c.name AS category 
            FROM package_images pi 
            JOIN packages p ON p.id = pi.package_id 
            LEFT JOIN categories c ON c.id = p.category_id 
            WHERE p.status = 1 
            ORDER BY pi.sort_order ASC, pi.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rows = $pkgImgs;
    }

    $gallery = $rows;
    foreach ($gallery as $g) {
        $cat = trim((string)($g['category'] ?? ''));
        if ($cat !== '' && !isset($categories[$cat])) {
            $categories[$cat] = true;
        }
    }
} catch (Throwable $e) {
    $gallery = [];
}

$categoryList = array_keys($categories);
sort($categoryList, SORT_NATURAL | SORT_FLAG_CASE);

require __DIR__ . '/includes/header.php';
?>

<!-- Google Fonts: Barlow Condensed & Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style id="bta-gallery-modern-theme">
:root {
  --bta-bg: #f5f4ef;
  --bta-panel: #ffffff;
  --bta-panel-hover: #faf8f5;
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

.bta-gallery * {
  box-sizing: border-box;
}

.bta-gallery .wrap {
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
  background: url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1800&q=85') center/cover no-repeat;
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

/* Gallery Section */
.gallery-section {
  padding: 80px 0 120px;
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
.gallery-tabs-wrap {
  margin-bottom: 35px;
  position: relative;
}

.gallery-tabs {
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

.gallery-tabs::-webkit-scrollbar {
  display: none;
}

.gallery-tab-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--bta-panel);
  border: 1px solid var(--bta-border);
  color: var(--bta-text);
  font-family: var(--bta-font-display);
  font-size: 15px;
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

.gallery-tab-btn:hover {
  border-color: var(--bta-orange);
  color: var(--bta-heading);
  background: var(--bta-panel-hover);
}

.gallery-tab-btn.is-active {
  background: var(--bta-orange);
  border-color: var(--bta-orange);
  color: #ffffff;
  box-shadow: 0 4px 18px var(--bta-orange-glow);
}

.gallery-tab-btn.is-active::before {
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

/* Gallery Grid */
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}

.gallery-card {
  position: relative;
  aspect-ratio: 4 / 3;
  border-radius: 14px;
  overflow: hidden;
  background: #252525;
  border: 1px solid var(--bta-border);
  cursor: pointer;
  box-shadow: 0 10px 30px rgba(30, 25, 15, 0.08);
  transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}

.gallery-card:hover {
  transform: translateY(-6px);
  border-color: var(--bta-orange);
  box-shadow: 0 18px 40px rgba(30, 25, 15, 0.15), 0 0 20px var(--bta-orange-glow);
}

.gallery-card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.55s ease;
}

.gallery-card:hover img {
  transform: scale(1.08);
}

.gallery-card-shade {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(10,13,18,0.1) 0%, rgba(10,13,18,0.4) 60%, rgba(10,13,18,0.92) 100%);
  transition: background 0.3s ease;
}

.gallery-card span.tag {
  position: absolute;
  bottom: 18px;
  left: 20px;
  z-index: 2;
  font-family: var(--bta-font-display);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: #ffffff;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: color 0.2s;
}

.gallery-card:hover span.tag {
  color: var(--bta-orange);
}

.gallery-card.is-hidden {
  display: none !important;
}

/* Lightbox Modal */
.gallery-modal {
  position: fixed;
  inset: 0;
  z-index: 999999;
  background: rgba(10, 13, 18, 0.95);
  backdrop-filter: blur(12px);
  display: none;
  align-items: center;
  justify-content: center;
  padding: 30px;
}

.gallery-modal.is-open {
  display: flex;
}

.gallery-modal-content {
  position: relative;
  max-width: 1100px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.gallery-modal img {
  max-width: 100%;
  max-height: 75vh;
  object-fit: contain;
  border-radius: 12px;
  border: 1px solid var(--bta-border);
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7);
}

.gallery-modal-caption {
  margin-top: 16px;
  font-family: var(--bta-font-display);
  font-size: 18px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: #ffffff;
}

.gallery-modal-close {
  position: absolute;
  top: 24px;
  right: 28px;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #fff;
  font-size: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.2s;
}

.gallery-modal-close:hover {
  background: var(--bta-orange);
}

@media (max-width: 1024px) {
  .gallery-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .gallery-grid {
    grid-template-columns: 1fr;
  }
  .page-banner {
    min-height: 300px;
    padding: 100px 0 45px;
  }
}
</style>

<div class="bta-gallery">
  <!-- Page Banner -->
  <section class="page-banner">
    <div class="wrap page-banner-content">
      <p class="eyebrow"><span class="eyebrow-line"></span>PHOTO GALLERY</p>
      <h1>FEEL THE <span>ADRENALINE.</span></h1>
      <p>A glimpse of the landscapes, tracks, and outdoor moments that inspire every Bali Tarantula expedition.</p>
      <div class="breadcrumbs">
        <a href="<?=BASE_URL?>/">HOME</a> / PHOTO GALLERY
      </div>
    </div>
  </section>

  <!-- Gallery Grid Section -->
  <main class="gallery-section">
    <div class="wrap">
      
      <div class="section-heading">
        <div>
          <p class="eyebrow"><span class="eyebrow-line"></span>VISUAL JOURNAL</p>
          <h2>THE WILD IN <span>FRAMES.</span></h2>
        </div>
        <p class="section-description">High-octane action shots straight from our jungle circuits, volcanic sands, and muddy rivers. Click any photo to view in high resolution.</p>
      </div>

      <!-- Categories Filter Tabs -->
      <?php if (!empty($categoryList)): ?>
        <div class="gallery-tabs-wrap">
          <div class="gallery-tabs" role="tablist">
            <button type="button" class="gallery-tab-btn is-active" data-cat="all">ALL PHOTOS</button>
            <?php foreach ($categoryList as $cat): ?>
              <button type="button" class="gallery-tab-btn" data-cat="<?=e($cat)?>"><?=e($cat)?></button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Gallery Grid -->
      <div class="gallery-grid" id="galleryGrid">
        <?php if (!empty($gallery)): ?>
          <?php 
          $idx = 1;
          foreach ($gallery as $g): 
            $catLabel = trim((string)($g['category'] ?? 'ADVENTURE'));
            if ($catLabel === '') $catLabel = 'ADVENTURE';
            $imgUrl = asset_url($g['image_path']);
            $tagText = sprintf('%02d / %s ↗', $idx++, strtoupper($catLabel));
            $altText = !empty($g['title']) ? $g['title'] : (!empty($g['alt_text']) ? $g['alt_text'] : 'Bali Tarantula Adventure');
          ?>
            <div class="gallery-card" data-cat="<?=e($catLabel)?>" onclick="openGalleryModal('<?=e($imgUrl)?>', '<?=e($altText)?>')">
              <img src="<?=e($imgUrl)?>" alt="<?=e($altText)?>" loading="lazy">
              <div class="gallery-card-shade"></div>
              <span class="tag"><?=e($tagText)?></span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="grid-column: 1 / -1; text-align: center; color: var(--bta-gray-400); padding: 50px 0;">No photos uploaded yet. Manage your gallery via Admin &rarr; Gallery.</p>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<!-- Modal Lightbox -->
<div class="gallery-modal" id="galleryModal" onclick="closeGalleryModal(event)">
  <button class="gallery-modal-close" onclick="closeGalleryModalDirect()" aria-label="Close photo preview"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  <div class="gallery-modal-content" onclick="event.stopPropagation()">
    <img id="galleryModalImg" src="" alt="Gallery Preview">
    <div class="gallery-modal-caption" id="galleryModalCaption"></div>
  </div>
</div>

<script>
function openGalleryModal(src, title) {
  const modal = document.getElementById('galleryModal');
  const img = document.getElementById('galleryModalImg');
  const caption = document.getElementById('galleryModalCaption');
  img.src = src;
  caption.textContent = title;
  modal.classList.add('is-open');
}

function closeGalleryModal(e) {
  if (e.target.id === 'galleryModal') {
    closeGalleryModalDirect();
  }
}

function closeGalleryModalDirect() {
  document.getElementById('galleryModal').classList.remove('is-open');
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeGalleryModalDirect();
});

// Category Tabs Filter
(function(){
  const tabBtns = Array.from(document.querySelectorAll('.gallery-tab-btn'));
  const cards = Array.from(document.querySelectorAll('.gallery-card'));

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      tabBtns.forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      const cat = btn.getAttribute('data-cat');

      cards.forEach(card => {
        if (cat === 'all' || card.getAttribute('data-cat') === cat) {
          card.classList.remove('is-hidden');
        } else {
          card.classList.add('is-hidden');
        }
      });
    });
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
