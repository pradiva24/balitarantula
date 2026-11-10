<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$galleryPage = [];
try {
    $galleryPage = db()->query("SELECT * FROM gallery_page_settings WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}
$galleryValue = static function (string $key, string $fallback = '') use ($galleryPage): string {
    $value = trim((string)($galleryPage[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
};
$title = 'Photo Gallery | ' . site_brand_name();
$description = $galleryValue('hero_description', 'Explore thrilling moments from Bali Tarantula Adventure: rugged trails, muddy quads, bamboo jungles, and extreme Bali landscapes.');
$canonical = BASE_URL . '/gallery.php';
$galleryHeroImage = trim((string)($galleryPage['hero_image'] ?? ''));
$galleryHeroImageUrl = $galleryHeroImage !== '' ? (preg_match('~^https?://~i', $galleryHeroImage) ? $galleryHeroImage : asset_url($galleryHeroImage)) : '';

$gallery = [];
$categories = [];

try {
    // Package images are the primary source for the public gallery.
    // Keep showing images attached to every active package, even when
    // additional images have also been uploaded through Admin > Gallery.
    $pkgImgs = db()->query("
        SELECT pi.id, pi.image_path, pi.alt_text, p.name AS title, c.name AS category,
               pi.sort_order, 'package' AS image_source
        FROM package_images pi
        JOIN packages p ON p.id = pi.package_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 1
        ORDER BY pi.sort_order ASC, pi.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Add manually managed gallery images after package images.
    $customImgs = db()->query("
        SELECT id, title, alt_text, category, image_path, sort_order,
               'gallery' AS image_source
        FROM gallery
        WHERE status = 1
        ORDER BY sort_order ASC, id DESC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Merge both sources and avoid showing the same file twice.
    $seenPaths = [];
    foreach (array_merge($pkgImgs, $customImgs) as $item) {
        $imagePath = trim((string)($item['image_path'] ?? ''));
        if ($imagePath === '' || isset($seenPaths[$imagePath])) continue;
        $seenPaths[$imagePath] = true;
        $gallery[] = $item;

        $cat = trim((string)($item['category'] ?? ''));
        if ($cat !== '') $categories[$cat] = true;
    }
} catch (Throwable $e) {
    error_log('BTA public gallery load error: '.$e->getMessage());
    // If one source is unavailable, still try to show the other source.
    if (empty($gallery)) {
        try {
            $gallery = db()->query("
                SELECT pi.id, pi.image_path, pi.alt_text, p.name AS title, c.name AS category,
                       pi.sort_order, 'package' AS image_source
                FROM package_images pi
                JOIN packages p ON p.id = pi.package_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.status = 1
                ORDER BY pi.sort_order ASC, pi.id DESC
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($gallery as $item) {
                $cat = trim((string)($item['category'] ?? ''));
                if ($cat !== '') $categories[$cat] = true;
            }
        } catch (Throwable $fallbackError) {
            error_log('BTA package gallery fallback error: '.$fallbackError->getMessage());
            $gallery = [];
        }
    }
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
  --bta-orange: #ed3833;
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
  background: <?= $galleryHeroImageUrl !== '' ? 'url(\''.e($galleryHeroImageUrl).'\')' : 'linear-gradient(112deg, #171717 0%, #242321 65%, #30271f 100%)' ?> center/cover no-repeat;
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
  border: 0;
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
  border-radius: 0;
  border: 0;
  outline: 0;
  box-shadow: none;
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

.gallery-modal-nav {
  position:absolute;top:50%;transform:translateY(-50%);z-index:3;width:50px;height:50px;
  display:flex;align-items:center;justify-content:center;border:0;border-radius:50%;
  background:rgba(255,255,255,.12);color:#fff;font-size:20px;cursor:pointer;
  transition:background .2s,transform .2s;
}
.gallery-modal-nav:hover{background:var(--bta-orange);transform:translateY(-50%) scale(1.06)}
.gallery-modal-prev{left:28px}.gallery-modal-next{right:28px}
.gallery-modal-counter{margin-top:8px;color:rgba(255,255,255,.65);font:600 12px var(--bta-font-body);letter-spacing:1px}
@media(max-width:600px){
 .gallery-modal{padding:18px}
 .gallery-modal-nav{width:42px;height:42px;top:auto;bottom:22px;transform:none}
 .gallery-modal-nav:hover{transform:scale(1.04)}
 .gallery-modal-prev{left:calc(50% - 52px)}.gallery-modal-next{right:calc(50% - 52px)}
 .gallery-modal-close{top:14px;right:14px}
 .gallery-modal-content{max-width:100%;max-height:80vh}
 .gallery-modal img{max-height:68vh}
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

/* BTA UI refinement: gallery grid and accessible lightbox controls */
.bta-gallery .gallery-section{min-width:0}
.bta-gallery .gallery-tabs{display:flex;gap:9px;overflow-x:auto;white-space:nowrap;scrollbar-width:none;padding:4px 2px 13px;overscroll-behavior-x:contain}
.bta-gallery .gallery-tabs::-webkit-scrollbar{display:none}
.bta-gallery .gallery-tab-btn{flex:0 0 auto;min-height:42px;padding:10px 17px}
.bta-gallery .gallery-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.bta-gallery .gallery-card{position:relative;min-width:0;overflow:hidden;aspect-ratio:4/3;background:#eee;cursor:pointer}
.bta-gallery .gallery-card img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .55s ease}
.bta-gallery .gallery-card:hover img{transform:scale(1.045)}
.bta-gallery .gallery-modal{padding:clamp(12px,3vw,30px)}
.bta-gallery .gallery-modal-content{max-width:min(1100px,92vw);max-height:82vh}
.bta-gallery .gallery-modal-content img{display:block;max-width:100%;max-height:72vh;object-fit:contain;margin:auto}
.bta-gallery .gallery-modal-close,.bta-gallery .gallery-modal-nav{min-width:42px;min-height:42px}
.bta-gallery .gallery-tab-btn:focus-visible,.bta-gallery .gallery-card:focus-visible,.bta-gallery .gallery-modal button:focus-visible{outline:2px solid #ed3833;outline-offset:3px}
@media(max-width:900px){.bta-gallery .gallery-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){.bta-gallery .page-banner{padding-top:130px;min-height:320px}.bta-gallery .gallery-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.bta-gallery .gallery-card{aspect-ratio:1/1}.bta-gallery .gallery-modal-content{max-width:100%;max-height:78vh}.bta-gallery .gallery-modal-content img{max-height:68vh}}
@media(max-width:380px){.bta-gallery .gallery-grid{grid-template-columns:1fr}.bta-gallery .gallery-card{aspect-ratio:4/3}}
@media(prefers-reduced-motion:reduce){.bta-gallery .gallery-card img{transition:none}.bta-gallery .gallery-card:hover img{transform:none}}


/* Match Packages category filter: three visible pills with horizontal swipe. */
@media screen and (max-width:640px){
  .bta-gallery .gallery-tabs{
    display:flex!important;
    flex-wrap:nowrap!important;
    align-items:center!important;
    gap:8px!important;
    overflow-x:auto!important;
    overflow-y:hidden!important;
    white-space:nowrap!important;
    scroll-snap-type:x mandatory!important;
    overscroll-behavior-x:contain!important;
    -webkit-overflow-scrolling:touch!important;
    scrollbar-width:none!important;
    padding:4px 2px 12px!important;
  }
  .bta-gallery .gallery-tabs::-webkit-scrollbar{display:none!important}
  .bta-gallery .gallery-tab-btn{
    display:inline-flex!important;
    flex:0 0 calc((100% - 16px)/3)!important;
    width:calc((100% - 16px)/3)!important;
    max-width:calc((100% - 16px)/3)!important;
    min-width:0!important;
    min-height:42px!important;
    padding:8px 5px!important;
    gap:5px!important;
    border-radius:20px!important;
    font-size:10px!important;
    line-height:1.15!important;
    letter-spacing:.35px!important;
    text-align:center!important;
    white-space:normal!important;
    overflow-wrap:anywhere!important;
    scroll-snap-align:start!important;
  }
  .bta-gallery .gallery-tab-btn.is-active::before{
    width:6px!important;height:6px!important;flex:0 0 6px!important;
  }
}
</style>

<div class="bta-gallery">
  <!-- Page Banner -->
  <section class="page-banner">
    <div class="wrap page-banner-content">
      <p class="eyebrow"><span class="eyebrow-line"></span><?=e($galleryValue('hero_kicker', 'PHOTO GALLERY'))?></p>
      <h1><?=e($galleryValue('hero_title', 'FEEL THE'))?> <span><?=e($galleryValue('hero_accent', 'ADRENALINE.'))?></span></h1>
      <?php if ($galleryValue('hero_description') !== ''): ?><p><?=nl2br(e($galleryValue('hero_description')))?></p><?php endif; ?>
      <div class="breadcrumbs">
        <a href="<?=e(rtrim(BASE_URL, '/'))?>/">HOME</a> / <?=e($galleryValue('hero_kicker', 'PHOTO GALLERY'))?>
      </div>
    </div>
  </section>

  <!-- Gallery Grid Section -->
  <main class="gallery-section">
    <div class="wrap">
      
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
            <div class="gallery-card" data-cat="<?=e($catLabel)?>" data-full-src="<?=e($imgUrl)?>" data-caption="<?=e($altText)?>" onclick="openGalleryModal(this)">
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
<div class="gallery-modal" id="galleryModal" onclick="closeGalleryModal(event)" aria-hidden="true">
  <button class="gallery-modal-close" onclick="closeGalleryModalDirect()" aria-label="Close photo preview"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
  <button class="gallery-modal-nav gallery-modal-prev" type="button" onclick="navigateGalleryModal(-1,event)" aria-label="Previous photo"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
  <div class="gallery-modal-content" onclick="event.stopPropagation()">
    <img id="galleryModalImg" src="" alt="Gallery Preview">
    <div class="gallery-modal-caption" id="galleryModalCaption"></div>
    <div class="gallery-modal-counter" id="galleryModalCounter" aria-live="polite"></div>
  </div>
  <button class="gallery-modal-nav gallery-modal-next" type="button" onclick="navigateGalleryModal(1,event)" aria-label="Next photo"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
</div>

<script>
let galleryModalItems = [];
let galleryModalIndex = 0;

function openGalleryModal(card) {
  galleryModalItems = Array.from(document.querySelectorAll('.gallery-card:not(.is-hidden)'));
  galleryModalIndex = galleryModalItems.indexOf(card);
  if (galleryModalIndex < 0) return;
  renderGalleryModal();
  const modal = document.getElementById('galleryModal');
  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
}

function renderGalleryModal() {
  const item = galleryModalItems[galleryModalIndex];
  if (!item) return;
  document.getElementById('galleryModalImg').src = item.dataset.fullSrc || item.querySelector('img').src;
  document.getElementById('galleryModalImg').alt = item.dataset.caption || '';
  document.getElementById('galleryModalCaption').textContent = item.dataset.caption || '';
  document.getElementById('galleryModalCounter').textContent = (galleryModalIndex + 1) + ' / ' + galleryModalItems.length;
}

function navigateGalleryModal(direction, event) {
  if (event) event.stopPropagation();
  if (!galleryModalItems.length) return;
  galleryModalIndex = (galleryModalIndex + direction + galleryModalItems.length) % galleryModalItems.length;
  renderGalleryModal();
}

function closeGalleryModal(e) {
  if (e.target.id === 'galleryModal') {
    closeGalleryModalDirect();
  }
}

function closeGalleryModalDirect() {
  const modal = document.getElementById('galleryModal');
  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeGalleryModalDirect();
  if (document.getElementById('galleryModal').classList.contains('is-open') && e.key === 'ArrowLeft') navigateGalleryModal(-1);
  if (document.getElementById('galleryModal').classList.contains('is-open') && e.key === 'ArrowRight') navigateGalleryModal(1);
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
