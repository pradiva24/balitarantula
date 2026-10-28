<?php
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$slug = trim($slug, "/");

if ($slug === '') {
    header('Location: '.BASE_URL.'/');
    exit;
}

$q = db()->prepare("SELECT * FROM pages WHERE slug=? AND status=1 LIMIT 1");
$q->execute([$slug]);
$page = $q->fetch();

if (!$page) {
    http_response_code(404);
    require __DIR__.'/404.php';
    exit;
}

$title = $page['title'].' | '.site_brand_name();
$description = trim(strip_tags((string)($page['content'] ?? '')));
if (strlen($description) > 155) $description = substr($description,0,152).'...';

require __DIR__.'/includes/header.php';
?>
<section class="page-hero" style="position:relative;background:linear-gradient(112deg, #171717 0%, #242321 65%, #30271f 100%);padding:90px 0 65px;overflow:hidden;border-bottom:3px solid #ff5a1f">
  <div class="container" style="position:relative;z-index:2;max-width:900px;margin:0 auto;padding:0 20px">
    <span style="display:inline-block;color:#ff5a1f;font-family:'Barlow Condensed',sans-serif;font-size:13px;font-weight:800;letter-spacing:2.5px;text-transform:uppercase;margin-bottom:8px">Information</span>
    <h1 style="font-family:'Barlow Condensed',sans-serif;font-size:clamp(36px,5.5vw,64px);font-weight:900;letter-spacing:-0.5px;text-transform:uppercase;color:#fff;margin:0;line-height:0.98"><?=e($page['title'])?></h1>
  </div>
</section>
<section class="section" style="padding:60px 0 80px;background:#f5f4ef">
  <div class="container" style="max-width:900px;margin:0 auto;padding:0 20px">
    <article class="page-content" style="background:#ffffff;border:1px solid #dedbd4;border-radius:14px;padding:clamp(24px, 4vw, 44px);box-shadow:0 12px 36px rgba(30,25,15,0.05);font-family:'Inter',sans-serif;font-size:16px;line-height:1.8;color:#5d5b56">
      <?=nl2br(e($page['content'] ?? ''))?>
    </article>
  </div>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
