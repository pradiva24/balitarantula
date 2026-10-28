<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: ' . BASE_URL . '/blog', true, 301);
    exit;
}
$st = db()->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published' LIMIT 1");
$st->execute([$slug]);
$r = $st->fetch();

if (!$r) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$title = $r['seo_title'] ?: ($r['title'] . ' | ' . site_brand_name());
$description = $r['meta_description'] ?: ($r['excerpt'] ?: 'Adventure tips and guides from ' . site_brand_name() . '.');
$canonical = BASE_URL . '/blog/' . rawurlencode($r['slug']);
$ogImage = !empty($r['og_image']) ? asset_url($r['og_image']) : (!empty($r['cover_image']) ? asset_url($r['cover_image']) : '');

$dateStr = !empty($r['published_at']) ? date('M d, Y', strtotime($r['published_at'])) : date('M d, Y', strtotime($r['created_at']));
$categoryName = !empty($r['category_name']) ? $r['category_name'] : 'BALI ADVENTURE GUIDE';

// Recent posts
$recentPosts = [];
try {
    $rq = db()->prepare("SELECT id, title, slug, cover_image, published_at FROM blog_posts WHERE status = 'published' AND id <> ? ORDER BY published_at DESC, id DESC LIMIT 3");
    $rq->execute([(int)$r['id']]);
    $recentPosts = $rq->fetchAll();
} catch (Throwable $e) {}

// Featured packages
$featuredPkgs = [];
try {
    $pq = db()->query("SELECT id, name, slug, price_adult, cover_image FROM packages WHERE status = 1 ORDER BY homepage_order ASC, id ASC LIMIT 2");
    $featuredPkgs = $pq->fetchAll();
} catch (Throwable $e) {}

require __DIR__ . '/includes/header.php';
?>

<!-- Google Fonts: Barlow Condensed & Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,500;0,600;0,700;0,800;0,900;1,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style id="bta-article-modern-theme">
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

.bta-article * {
  box-sizing: border-box;
}

.bta-article .wrap {
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
  background: url('<?=!empty($r['cover_image']) ? e(asset_url($r['cover_image'])) : 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1500&q=90'?>') center/cover no-repeat;
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
  font-size: clamp(36px, 5vw, 68px);
  font-weight: 900;
  line-height: 0.98;
  letter-spacing: -0.5px;
  text-transform: uppercase;
  color: #ffffff;
  margin: 0 0 16px;
  max-width: 840px;
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

/* Article Layout */
.article-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 50px;
  padding: 60px 0 120px;
  align-items: start;
  background: var(--bta-bg);
}

.article-main {
  min-width: 0;
  background: var(--bta-panel);
  border: 1px solid var(--bta-border);
  border-radius: 16px;
  padding: 36px 32px;
  box-shadow: 0 10px 32px rgba(30, 25, 15, 0.05);
}

.article-cover {
  width: 100%;
  height: clamp(300px, 35vw, 480px);
  object-fit: cover;
  border-radius: 12px;
  border: 1px solid var(--bta-border);
  margin-bottom: 24px;
}

.blog-meta {
  font-family: var(--bta-font-display);
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--bta-orange);
  margin: 0 0 14px;
}

.article-main h2 {
  font-family: var(--bta-font-display);
  font-size: clamp(30px, 4vw, 44px);
  font-weight: 900;
  line-height: 1.05;
  text-transform: uppercase;
  color: var(--bta-heading);
  margin: 0 0 20px;
}

.article-prose {
  font-size: 16px;
  line-height: 1.8;
  color: var(--bta-text);
  margin-bottom: 40px;
}

.article-prose p {
  margin: 0 0 20px;
}

.article-prose h2, .article-prose h3 {
  font-family: var(--bta-font-display);
  text-transform: uppercase;
  color: var(--bta-heading);
  margin: 32px 0 14px;
  font-weight: 900;
}

.article-prose h3 {
  font-size: 24px;
}

.article-prose blockquote {
  border-left: 4px solid var(--bta-orange);
  padding: 16px 20px;
  margin: 30px 0;
  background: #f5f4ef;
  border-radius: 0 10px 10px 0;
  font-family: var(--bta-font-display);
  font-size: 20px;
  font-weight: 700;
  line-height: 1.4;
  color: var(--bta-heading);
}

.button-orange {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--bta-orange);
  color: #111111;
  font-family: var(--bta-font-display);
  font-size: 16px;
  font-weight: 900;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  padding: 14px 26px;
  border-radius: 8px;
  text-decoration: none;
  transition: all 0.25s ease;
  box-shadow: 0 4px 20px var(--bta-orange-glow);
}

.button-orange:hover {
  background: var(--bta-orange-hover);
  transform: translateY(-2px);
  color: #111111;
}

/* Sidebar */
.article-sidebar {
  position: sticky;
  top: 100px;
}

.sidebar-box {
  background: var(--bta-panel);
  border: 1px solid var(--bta-border);
  border-top: 3px solid var(--bta-orange);
  border-radius: 14px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 10px 24px rgba(30, 25, 15, 0.05);
}

.sidebar-box .eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-family: var(--bta-font-display);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--bta-orange);
  margin: 0 0 8px;
}

.sidebar-box h3 {
  font-family: var(--bta-font-display);
  font-size: 22px;
  font-weight: 900;
  text-transform: uppercase;
  color: var(--bta-heading);
  margin: 0 0 12px;
}

.sidebar-box p {
  font-size: 13px;
  color: var(--bta-text);
  line-height: 1.6;
  margin: 0 0 16px;
}

.side-pkg-item {
  display: flex;
  gap: 12px;
  margin-bottom: 14px;
  align-items: center;
  text-decoration: none;
}

.side-pkg-item img {
  width: 64px;
  height: 52px;
  border-radius: 6px;
  object-fit: cover;
  flex: 0 0 64px;
}

.side-pkg-info strong {
  display: block;
  font-family: var(--bta-font-display);
  font-size: 15px;
  font-weight: 800;
  color: var(--bta-heading);
  line-height: 1.2;
}

.side-pkg-info span {
  font-size: 12px;
  color: var(--bta-orange);
  font-weight: 700;
}

@media (max-width: 900px) {
  .article-layout {
    grid-template-columns: 1fr;
    gap: 40px;
  }
  .article-sidebar {
    position: relative;
    top: auto;
  }
}
</style>

<div class="bta-article">
  <!-- Page Banner -->
  <section class="page-banner">
    <div class="wrap page-banner-content">
      <p class="eyebrow"><span class="eyebrow-line"></span>ADVENTURE JOURNAL</p>
      <h1><?=e($r['title'])?></h1>
      <div class="breadcrumbs">
        <a href="<?=BASE_URL?>/">HOME</a> / <a href="<?=BASE_URL?>/blog">JOURNAL</a> / ARTICLE
      </div>
    </div>
  </section>

  <!-- Article Body & Sidebar -->
  <main class="wrap article-layout">
    
    <article class="article-main">
      <?php if (!empty($r['cover_image'])): ?>
        <img class="article-cover" src="<?=e(asset_url($r['cover_image']))?>" alt="<?=e($r['title'])?>">
      <?php endif; ?>

      <p class="blog-meta"><?=e($categoryName)?> &middot; <?=e($dateStr)?></p>
      <h2><?=e($r['title'])?></h2>

      <div class="article-prose">
        <?=!empty($r['content']) ? $r['content'] : nl2br(e($r['excerpt'] ?? ''))?>
      </div>

      <a class="button-orange" href="<?=BASE_URL?>/packages">
        EXPLORE ALL PACKAGES <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
      </a>
    </article>

    <!-- Sidebar -->
    <aside class="article-sidebar">
      
      <!-- Recommended Packages -->
      <?php if (!empty($featuredPkgs)): ?>
        <div class="sidebar-box">
          <p class="eyebrow">READY TO RIDE?</p>
          <h3>FEATURED TRACKS</h3>
          <?php foreach ($featuredPkgs as $fp): ?>
            <a href="<?=BASE_URL?>/package/<?=e($fp['slug'])?>" class="side-pkg-item">
              <img src="<?=!empty($fp['cover_image']) ? e(asset_url($fp['cover_image'])) : 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=200&q=80'?>" alt="<?=e($fp['name'])?>">
              <div class="side-pkg-info">
                <strong><?=e($fp['name'])?></strong>
                <span><?=rupiah($fp['price_adult'])?></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Other Stories -->
      <?php if (!empty($recentPosts)): ?>
        <div class="sidebar-box">
          <p class="eyebrow">EXPLORE MORE</p>
          <h3>RECENT ARTICLES</h3>
          <?php foreach ($recentPosts as $rp): ?>
            <a href="<?=BASE_URL?>/blog/<?=e($rp['slug'])?>" style="display:block; text-decoration:none; margin-bottom:12px; border-bottom:1px dashed var(--bta-border); padding-bottom:8px;">
              <span style="font-family:var(--bta-font-display); font-size:15px; font-weight:800; color:var(--bta-white); line-height:1.2; display:block;">
                <?=e($rp['title'])?>
              </span>
              <small style="color:var(--bta-gray-400); font-size:11px;"><?=date('M d, Y', strtotime($rp['published_at']))?></small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </aside>

  </main>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
