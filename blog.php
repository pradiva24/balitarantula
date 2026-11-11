<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$seo = db()->prepare("SELECT * FROM seo_meta WHERE page_key=? LIMIT 1");
$seo->execute(['blog']);
$seo = $seo->fetch() ?: [];

$title = $seo['seo_title'] ?? ('Adventure Journal & Travel Tips | ' . site_brand_name());
$description = $seo['meta_description'] ?? ('Explore Bali off-road advice, quad biking tips, and wilderness stories from ' . site_brand_name() . '.');
$canonical = BASE_URL . '/blog.php';

$blogPage = [];
try {
    $blogPage = db()->query("SELECT * FROM blog_page_settings WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $blogPage = [];
}
$blogValue = static function (string $key, string $fallback = '') use ($blogPage): string {
    $value = trim((string)($blogPage[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
};
$blogHeroImage = trim((string)($blogPage['hero_image'] ?? ''));
$blogHeroImageUrl = $blogHeroImage !== '' ? (preg_match('~^https?://~i', $blogHeroImage) ? $blogHeroImage : asset_url($blogHeroImage)) : '';

$hasCat = false;
try {
    $hasCat = (bool)db()->query("SHOW COLUMNS FROM blog_posts LIKE 'category_name'")->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$catSelect = $hasCat ? 'category_name' : "'BALI GUIDE' AS category_name";

$rows = db()->query("
    SELECT id, title, slug, excerpt, cover_image, published_at, {$catSelect} 
    FROM blog_posts 
    WHERE status = 'published' 
    ORDER BY published_at DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

require __DIR__ . '/includes/header.php';
?>

<!-- Google Fonts: Barlow Condensed & Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style id="bta-blog-modern-theme">
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

.bta-blog * {
  box-sizing: border-box;
}

.bta-blog .wrap {
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
  background: <?= $blogHeroImageUrl !== '' ? 'url(\''.e($blogHeroImageUrl).'\')' : 'linear-gradient(112deg, #171717 0%, #242321 65%, #30271f 100%)' ?> center/cover no-repeat;
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

.page-banner-content .blog-hero-button {
  display:inline-flex;align-items:center;gap:10px;margin:4px 0 22px;padding:13px 22px;
  background:var(--bta-orange);color:#171717;text-decoration:none;
  font:900 15px var(--bta-font-display);letter-spacing:1.2px;text-transform:uppercase;
  transition:transform .2s,background .2s;
}
.page-banner-content .blog-hero-button:hover{background:var(--bta-orange-hover);transform:translateY(-2px)}
.page-banner-content .breadcrumbs{margin-top:4px}
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

/* Blog Grid Section */
.blog-section {
  padding: 80px 0 120px;
  background: var(--bta-bg);
}

.section-heading {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 24px;
  margin-bottom: 45px;
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

/* Blog Cards */
.blog-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 30px;
}

.blog-card {
  background: var(--bta-panel);
  border: 1px solid var(--bta-border);
  border-bottom: 3px solid var(--bta-orange);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(30, 25, 15, 0.05);
  display: flex;
  flex-direction: column;
  transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}

.blog-card:hover {
  transform: translateY(-8px);
  border-color: var(--bta-orange);
  box-shadow: 0 20px 45px rgba(30, 25, 15, 0.12), 0 0 20px var(--bta-orange-glow);
}

.blog-image {
  position: relative;
  height: 240px;
  background: #222;
  overflow: hidden;
  display: block;
}

.blog-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}

.blog-card:hover .blog-image img {
  transform: scale(1.06);
}

.blog-image span {
  position: absolute;
  top: 16px;
  left: 16px;
  background: var(--bta-orange);
  border-radius: 999px;
  padding: 5px 12px;
  font-family: var(--bta-font-display);
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: #ffffff;
}

.blog-copy {
  padding: 24px;
  display: flex;
  flex-direction: column;
  flex: 1;
  background: var(--bta-panel);
}

.blog-meta {
  font-family: var(--bta-font-display);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--bta-orange);
  margin: 0 0 10px;
}

.blog-copy h3 {
  font-family: var(--bta-font-display);
  font-size: 24px;
  font-weight: 900;
  line-height: 1.15;
  text-transform: uppercase;
  margin: 0 0 12px;
}

.blog-copy h3 a {
  color: var(--bta-heading);
  text-decoration: none;
  transition: color 0.2s;
}

.blog-copy h3 a:hover {
  color: var(--bta-orange);
}

.blog-copy p {
  font-size: 14px;
  color: var(--bta-text);
  line-height: 1.6;
  margin: 0 0 20px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-link {
  margin-top: auto;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: var(--bta-font-display);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--bta-orange);
  text-decoration: none;
  transition: color 0.2s, transform 0.2s;
}

.card-link:hover {
  color: var(--bta-orange-hover);
  transform: translateX(4px);
}

@media (max-width: 1024px) {
  .blog-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .blog-grid {
    grid-template-columns: 1fr;
  }
  .page-banner {
    min-height: 300px;
    padding: 100px 0 45px;
  }
}

/* BTA UI refinement: editorial cards and responsive listing */
.bta-blog .blog-section{min-width:0}
.bta-blog .blog-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;align-items:stretch}
.bta-blog .blog-card{display:flex;flex-direction:column;min-width:0;overflow:hidden;background:#fff;border:1px solid #e8e4dc;box-shadow:0 10px 28px rgba(25,20,12,.045);transition:transform .22s ease,box-shadow .22s ease}
.bta-blog .blog-card:hover{transform:translateY(-4px);box-shadow:0 16px 34px rgba(25,20,12,.1)}
.bta-blog .blog-image{display:block;aspect-ratio:16/10;height:auto;overflow:hidden;background:#eee}
.bta-blog .blog-image img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .55s ease}
.bta-blog .blog-card:hover .blog-image img{transform:scale(1.045)}
.bta-blog .blog-copy{display:flex;flex:1;flex-direction:column;min-width:0;padding:22px}
.bta-blog .blog-copy h2,.bta-blog .blog-copy h3{line-height:1.08;overflow-wrap:anywhere}
.bta-blog .blog-copy>p:not(.blog-meta){flex:1;line-height:1.75}
.bta-blog .card-link{display:inline-flex;align-items:center;gap:9px;margin-top:14px}
@media(max-width:900px){.bta-blog .blog-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}}
@media(max-width:640px){.bta-blog .page-banner{padding-top:130px;min-height:320px}.bta-blog .blog-grid{grid-template-columns:1fr;gap:16px}.bta-blog .blog-copy{padding:19px}.bta-blog .blog-image{aspect-ratio:16/9}}
@media(prefers-reduced-motion:reduce){.bta-blog .blog-card,.bta-blog .blog-image img{transition:none}.bta-blog .blog-card:hover,.bta-blog .blog-card:hover .blog-image img{transform:none}}


/* Mobile blog hero CTA override. */
@media screen and (max-width:767px){html body .bta-blog .page-banner-content a.blog-hero-button{font-size:12px!important;}}
</style>

<div class="bta-blog">
  <!-- Page Banner -->
  <section class="page-banner">
    <div class="wrap page-banner-content">
      <p class="eyebrow"><span class="eyebrow-line"></span><?=e($blogValue('hero_kicker', 'ADVENTURE JOURNAL'))?></p>
      <h1><?=e($blogValue('hero_title', 'STORIES FROM'))?> <span><?=e($blogValue('hero_accent', 'THE WILD.'))?></span></h1>
      <?php if ($blogValue('hero_description') !== ''): ?><p><?=nl2br(e($blogValue('hero_description')))?></p><?php endif; ?>
      <?php if ($blogValue('hero_button_text') !== ''): ?><a class="blog-hero-button" href="#blog-list"><?=e($blogValue('hero_button_text', 'Explore Articles'))?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
      <div class="breadcrumbs">
        <a href="<?=e(rtrim(BASE_URL, '/'))?>/">HOME</a> / <?=e($blogValue('hero_kicker', 'ADVENTURE JOURNAL'))?>
      </div>
    </div>
  </section>

  <!-- Blog Listing -->
  <main class="blog-section" id="blog-list">
    <div class="wrap">
      <div class="blog-grid">
        <?php if (!empty($rows)): ?>
          <?php foreach ($rows as $r): 
            $articleUrl = BASE_URL . '/blog/' . e($r['slug']);
            $imgUrl = !empty($r['cover_image']) ? asset_url($r['cover_image']) : 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=900&q=85';
            $dateStr = !empty($r['published_at']) ? date('M d, Y', strtotime($r['published_at'])) : 'BALI GUIDE';
            $categoryName = !empty($r['category_name']) ? $r['category_name'] : 'BALI GUIDE';
          ?>
            <article class="blog-card">
              <a href="<?=$articleUrl?>" class="blog-image">
                <img src="<?=e($imgUrl)?>" alt="<?=e($r['title'])?>" loading="lazy">
                <span><?=e($categoryName)?></span>
              </a>
              <div class="blog-copy">
                <p class="blog-meta"><?=e($categoryName)?> &middot; <?=e($dateStr)?></p>
                <h3><a href="<?=$articleUrl?>"><?=e($r['title'])?></a></h3>
                <p><?=e($r['excerpt'] ?: 'Click below to read full insights, gear guidelines, and advice for this Bali adventure topic.')?></p>
                <a class="card-link" href="<?=$articleUrl?>">
                  READ ARTICLE <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
                </a>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="grid-column: 1 / -1; text-align: center; color: var(--bta-gray-400); padding: 50px 0;">No articles published yet. Publish articles from Admin &rarr; Blog.</p>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
