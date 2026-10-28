<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$seo = db()->prepare("SELECT * FROM seo_meta WHERE page_key=? LIMIT 1");
$seo->execute(['blog']);
$seo = $seo->fetch() ?: [];

$title = $seo['seo_title'] ?? ('Adventure Journal & Travel Tips | ' . site_brand_name());
$description = $seo['meta_description'] ?? ('Explore Bali off-road advice, quad biking tips, and wilderness stories from ' . site_brand_name() . '.');
$canonical = BASE_URL . '/blog.php';

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
  background: url('https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1800&q=85') center/cover no-repeat;
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
</style>

<div class="bta-blog">
  <!-- Page Banner -->
  <section class="page-banner">
    <div class="wrap page-banner-content">
      <p class="eyebrow"><span class="eyebrow-line"></span>ADVENTURE JOURNAL</p>
      <h1>STORIES FROM <span>THE WILD.</span></h1>
      <p>Tips, track guides, gear advice, and inspirational stories for your next Bali off-road expedition.</p>
      <div class="breadcrumbs">
        <a href="<?=BASE_URL?>/">HOME</a> / ADVENTURE JOURNAL
      </div>
    </div>
  </section>

  <!-- Blog Listing -->
  <main class="blog-section">
    <div class="wrap">
      
      <div class="section-heading">
        <div>
          <p class="eyebrow"><span class="eyebrow-line"></span>LATEST STORIES</p>
          <h2>READ. RIDE. <span>REPEAT.</span></h2>
        </div>
        <p class="section-description">Get fully prepared before conquering our trails with helpful field guides and genuine rider experiences.</p>
      </div>

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
