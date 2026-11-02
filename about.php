<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $about = db()->query("SELECT * FROM about_page WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $about = [];
}

$aboutText = static function (string $key, string $fallback = '') use ($about): string {
    $value = trim((string)($about[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
};
$aboutImage = static function (string $key) use ($about): string {
    $path = trim((string)($about[$key] ?? ''));
    if ($path === '') return '';
    return preg_match('~^https?://~i', $path) ? $path : asset_url($path);
};
$aboutUrl = static function (string $value, string $fallback = '#') : string {
    $value = trim($value);
    if ($value === '') $value = $fallback;
    if (preg_match('~^(https?://|mailto:|tel:|#)~i', $value)) return $value;
    if (str_starts_with($value, '/')) return rtrim(BASE_URL, '/') . $value;
    return rtrim(BASE_URL, '/') . '/' . ltrim($value, '/');
};
$aboutIcon = static function (string $key, string $fallback) use ($aboutText): string {
    $icon = trim($aboutText($key, $fallback));
    return preg_match('/^[a-z0-9 -]+$/i', $icon) ? $icon : $fallback;
};

$brand = site_brand_name();
$title = $aboutText('hero_title', 'About Us') . ' | ' . $brand;
$description = $aboutText('hero_text', $aboutText('story_text_1', 'Discover our story, values and adventure experiences in Bali.'));
$canonical = BASE_URL . '/about.php';
$ogImage = $aboutImage('hero_image');

$heroTitle = $aboutText('hero_title', 'More Than An Adventure');
$heroAccent = $aboutText('hero_title_accent');
$heroImage = $aboutImage('hero_image');
$storyImage = $aboutImage('story_image');
$valuesImage = $aboutImage('values_image');
$ctaImage = $aboutImage('cta_image');

require __DIR__ . '/includes/header.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style id="bta-about-modern-theme">
:root{--about-bg:#f5f4ef;--about-panel:#fff;--about-heading:#191919;--about-text:#5d5b56;--about-muted:#77736b;--about-border:#dedbd4;--about-orange:#ff5a1f;--about-font-display:'Barlow Condensed',sans-serif;--about-font-body:'Inter',sans-serif}
.bta-about{background:var(--about-bg);color:var(--about-text);font-family:var(--about-font-body);line-height:1.65;overflow:hidden}
.bta-about *{box-sizing:border-box}
.bta-about .wrap{width:min(1280px,92%);margin:0 auto}
.bta-about .eyebrow{display:inline-flex;align-items:center;gap:10px;margin:0 0 14px;color:var(--about-orange);font:800 13px var(--about-font-display);letter-spacing:2.5px;text-transform:uppercase}
.bta-about .eyebrow:before{content:"";width:24px;height:2px;background:currentColor}
.bta-about h1,.bta-about h2,.bta-about h3{font-family:var(--about-font-display);text-transform:uppercase}
.bta-about .accent{color:var(--about-orange)}
.about-hero{position:relative;min-height:430px;display:flex;align-items:center;padding:135px 0 70px;background:#171717;border-bottom:3px solid var(--about-orange);isolation:isolate}
.about-hero:before{content:"";position:absolute;inset:0;z-index:-2;background:linear-gradient(110deg,#171717,#30271f)}
.about-hero .hero-photo{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-1;opacity:.32}
.about-hero:after{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(90deg,rgba(15,15,15,.92),rgba(15,15,15,.48))}
.about-hero h1{max-width:900px;margin:0 0 16px;color:#fff;font-size:clamp(46px,7vw,88px);font-weight:900;line-height:.94}
.about-hero .hero-copy{max-width:650px;margin:0 0 24px;color:#e4e1dc;font-size:16px}
.about-crumb{color:#c9c6c0;font:700 13px var(--about-font-display);letter-spacing:1.5px;text-transform:uppercase}
.about-crumb a{color:inherit;text-decoration:none}
.about-crumb a:hover{color:var(--about-orange)}
.about-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:14px 26px;border-radius:7px;background:var(--about-orange);color:#151515!important;text-decoration:none;font:900 16px var(--about-font-display);letter-spacing:1.3px;text-transform:uppercase;transition:.2s}
.about-btn:hover{transform:translateY(-2px);filter:brightness(1.08)}
.about-btn.outline{background:transparent;color:#fff!important;border:1px solid rgba(255,255,255,.55)}
.about-story{padding:96px 0;display:grid;grid-template-columns:1fr 1fr;gap:58px;align-items:center}
.about-story h2,.about-section-title{margin:0;color:var(--about-heading);font-size:clamp(38px,5vw,64px);font-weight:900;line-height:.98}
.about-story .story-copy{display:flex;flex-direction:column;gap:18px}
.about-story p,.about-values p{margin:0;font-size:15.5px;line-height:1.8}
.about-photo{display:block;width:100%;height:100%;min-height:330px;max-height:500px;object-fit:cover;border-radius:13px;background:#e5e1d9}
.about-quote{margin:8px 0 0;padding:18px 22px;border-left:3px solid var(--about-orange);background:#fff;border-radius:0 9px 9px 0;color:#34312c;font-weight:600}
.about-quote cite{display:block;margin-top:8px;color:var(--about-muted);font-size:12px;font-style:normal;font-weight:700}
.about-mindset{position:relative;min-height:390px;display:flex;align-items:center;padding:90px 0;background:#171717;border-block:3px solid var(--about-orange);isolation:isolate}
.about-mindset:before{content:"";position:absolute;inset:0;z-index:-2;background:linear-gradient(110deg,#171717,#30271f)}
.about-mindset .mindset-photo{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-1;opacity:.28}
.about-mindset:after{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(90deg,rgba(20,20,20,.95),rgba(20,20,20,.55))}
.about-mindset .mindset-copy{max-width:720px}
.about-mindset h2{margin:0 0 16px;color:#fff;font-size:clamp(40px,5.5vw,70px);font-weight:900;line-height:.98}
.about-mindset p{margin:0 0 25px;color:#e0ded9;font-size:16px;line-height:1.75}
.about-section{padding:88px 0}
.about-section-head{max-width:760px}
.about-section-head>p:last-child{margin:14px 0 0}
.about-card-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px;margin-top:36px}.about-values .about-card-grid{grid-template-columns:repeat(2,minmax(0,1fr));margin-top:0}
.about-card{padding:27px 23px;background:#fff;border:1px solid var(--about-border);border-top:3px solid var(--about-orange);border-radius:11px;box-shadow:0 10px 28px rgba(30,25,15,.045);transition:transform .2s,box-shadow .2s}
.about-card:hover{transform:translateY(-4px);box-shadow:0 16px 32px rgba(30,25,15,.09)}
.about-card .card-icon{display:grid;place-items:center;width:44px;height:44px;margin-bottom:17px;border-radius:10px;background:#fff0e9;color:var(--about-orange);font-size:19px}
.about-card .card-index{display:block;margin-bottom:9px;color:var(--about-orange);font:800 12px var(--about-font-display);letter-spacing:1.5px}
.about-card h3{margin:0 0 9px;color:var(--about-heading);font-size:24px;font-weight:800}
.about-card p{margin:0;color:var(--about-text);font-size:13.5px;line-height:1.7}
.about-values{display:grid;grid-template-columns:.9fr 1.1fr;gap:48px;align-items:center;padding:76px 0}
.about-values .values-photo{width:100%;height:100%;min-height:360px;max-height:480px;object-fit:cover;border-radius:13px;background:#e5e1d9}
.about-stats{padding:68px 0;background:#191919;color:#fff;border-block:3px solid var(--about-orange)}
.about-stats .stats-title{margin:0 0 28px;color:#fff;font:800 17px var(--about-font-display);letter-spacing:2px;text-transform:uppercase}
.about-stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
.about-stat{padding:18px 20px;border-left:2px solid var(--about-orange)}
.about-stat strong{display:block;color:var(--about-orange);font:900 clamp(34px,4vw,52px)/1 var(--about-font-display)}
.about-stat span{display:block;margin-top:8px;color:#dedbd4;font-size:13px}
.about-cta{position:relative;display:flex;align-items:center;min-height:360px;padding:75px 0;background:#201d1a;isolation:isolate}
.about-cta:before{content:"";position:absolute;inset:0;z-index:-2;background:linear-gradient(110deg,#171717,#30271f)}
.about-cta .cta-photo{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-1;opacity:.28}
.about-cta:after{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(90deg,rgba(15,15,15,.95),rgba(15,15,15,.55))}
.about-cta .cta-content{max-width:760px}
.about-cta h2{margin:0 0 14px;color:#fff;font-size:clamp(38px,5vw,62px);font-weight:900;line-height:.98}
.about-cta p{margin:0 0 24px;color:#e0ded9}
@media(max-width:980px){.about-card-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.about-story{gap:34px}.about-stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:700px){.about-hero{min-height:390px;padding:115px 0 55px}.about-story,.about-values{grid-template-columns:1fr;padding:62px 0;gap:27px}.about-story .story-visual{order:2}.about-story .story-copy{order:1}.about-photo,.about-values .values-photo{min-height:240px;max-height:330px}.about-mindset{min-height:350px;padding:65px 0}.about-section{padding:65px 0}.about-card-grid{grid-template-columns:1fr;gap:14px}.about-values .about-card-grid{grid-template-columns:1fr}.about-stat-grid{grid-template-columns:repeat(2,1fr);gap:14px}.about-stat{padding:14px}.about-cta{min-height:330px;padding:60px 0}}
</style>

<div class="bta-about">
  <section class="about-hero">
    <?php if ($heroImage !== ''): ?><img class="hero-photo" src="<?=e($heroImage)?>" alt="<?=e($aboutText('hero_alt', $heroTitle))?>" fetchpriority="high"><?php endif; ?>
    <div class="wrap">
      <p class="eyebrow"><?=e($aboutText('hero_kicker', 'ABOUT US'))?></p>
      <h1><?=e($heroTitle)?><?php if ($heroAccent !== '' && strcasecmp($heroAccent, $heroTitle) !== 0): ?> <span class="accent"><?=e($heroAccent)?></span><?php endif; ?></h1>
      <?php if ($aboutText('hero_text') !== ''): ?><p class="hero-copy"><?=nl2br(e($aboutText('hero_text')))?></p><?php endif; ?>
      <?php if ($aboutText('hero_button_text') !== ''): ?><p><a class="about-btn" href="<?=e($aboutUrl($aboutText('hero_button_url', '#our-story')))?>"><?=e($aboutText('hero_button_text'))?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></p><?php endif; ?>
      <div class="about-crumb"><a href="<?=e(rtrim(BASE_URL, '/'))?>/">HOME</a> / <?=e($aboutText('hero_kicker', 'ABOUT US'))?></div>
    </div>
  </section>

  <section class="wrap about-story" id="our-story">
    <div class="story-visual">
      <?php if ($storyImage !== ''): ?><img class="about-photo" src="<?=e($storyImage)?>" alt="<?=e($aboutText('story_alt', $aboutText('story_title', 'Our Story')))?>" loading="lazy"><?php else: ?><div class="about-photo" aria-hidden="true"></div><?php endif; ?>
    </div>
    <div class="story-copy">
      <p class="eyebrow"><?=e($aboutText('story_kicker', 'OUR STORY'))?></p>
      <h2><?=e($aboutText('story_title', 'Discover Our Story'))?></h2>
      <?php if ($aboutText('story_text_1') !== ''): ?><p><?=nl2br(e($aboutText('story_text_1')))?></p><?php endif; ?>
      <?php if ($aboutText('story_text_2') !== ''): ?><p><?=nl2br(e($aboutText('story_text_2')))?></p><?php endif; ?>
      <?php if ($aboutText('story_quote') !== ''): ?><blockquote class="about-quote"><?=nl2br(e($aboutText('story_quote')))?><cite><?=e($aboutText('story_quote_author', '— ' . $brand))?></cite></blockquote><?php endif; ?>
      <?php if ($aboutText('story_button_text') !== ''): ?><p><a class="about-btn" href="<?=e($aboutUrl($aboutText('story_button_url', 'packages.php')))?>"><?=e($aboutText('story_button_text'))?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></p><?php endif; ?>
    </div>
  </section>

  <section class="about-section">
    <div class="wrap">
      <div class="about-section-head">
        <p class="eyebrow"><?=e($aboutText('why_kicker', 'WHY CHOOSE US'))?></p>
        <h2 class="about-section-title"><?=e($aboutText('why_title', 'A Unique Experience in Bali'))?></h2>
        <?php if ($aboutText('why_text') !== ''): ?><p><?=nl2br(e($aboutText('why_text')))?></p><?php endif; ?>
      </div>
      <div class="about-card-grid">
        <?php for ($i=1; $i<=4; $i++):
          $iconFallback = ['fa-solid fa-mountain-sun','fa-solid fa-shield-halved','fa-solid fa-route','fa-solid fa-people-group'][$i-1];
          $icon = $aboutIcon("why_{$i}_icon", $iconFallback);
        ?>
          <article class="about-card">
            <div class="card-icon"><i class="<?=e($icon)?>" aria-hidden="true"></i></div>
            <span class="card-index"><?=sprintf('%02d', $i)?></span>
            <h3><?=e($aboutText("why_{$i}_title", ['Stunning Natural Views','Safety First','Adventure Tracks','Warm Local Hospitality'][$i-1]))?></h3>
            <p><?=e($aboutText("why_{$i}_text"))?></p>
          </article>
        <?php endfor; ?>
      </div>
    </div>
  </section>

  <section class="wrap about-values">
    <div>
      <p class="eyebrow"><?=e($aboutText('values_kicker', 'OUR VALUES'))?></p>
      <h2 class="about-section-title"><?=e($aboutText('values_title', 'Nature, People, and Meaningful Travel'))?></h2>
      <?php if ($aboutText('values_text') !== ''): ?><p style="margin-top:16px"><?=nl2br(e($aboutText('values_text')))?></p><?php endif; ?>
      <?php if ($valuesImage !== ''): ?><img class="values-photo" src="<?=e($valuesImage)?>" alt="<?=e($aboutText('values_alt', 'Our values'))?>" loading="lazy"><?php endif; ?>
    </div>
    <div class="about-card-grid">
      <?php for ($i=1; $i<=4; $i++):
        $iconFallback = ['fa-solid fa-leaf','fa-solid fa-handshake','fa-solid fa-heart','fa-solid fa-star'][$i-1];
        $icon = $aboutIcon("value_{$i}_icon", $iconFallback);
      ?>
        <article class="about-card">
          <div class="card-icon"><i class="<?=e($icon)?>" aria-hidden="true"></i></div>
          <h3><?=e($aboutText("value_{$i}_title", ['Sustainability','Community Support','Authentic Experiences','Excellence'][$i-1]))?></h3>
          <p><?=e($aboutText("value_{$i}_text"))?></p>
        </article>
      <?php endfor; ?>
    </div>
  </section>

  <section class="about-stats">
    <div class="wrap">
      <p class="eyebrow"><?=e($aboutText('stats_kicker', 'OUR IMPACT IN NUMBERS'))?></p>
      <div class="about-stat-grid">
        <?php for ($i=1; $i<=4; $i++): ?>
          <div class="about-stat">
            <strong><?=e($aboutText("stat_{$i}_number", ['5+','50K+','10+','100%'][$i-1]))?></strong>
            <span><?=e($aboutText("stat_{$i}_label", ['Years of Experience','Happy Visitors','Adventure Experiences','Guest Satisfaction'][$i-1]))?></span>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </section>

  <section class="about-cta">
    <?php if ($ctaImage !== ''): ?><img class="cta-photo" src="<?=e($ctaImage)?>" alt="<?=e($aboutText('cta_alt', $aboutText('cta_title', 'Adventure in Bali')))?>" loading="lazy"><?php endif; ?>
    <div class="wrap cta-content">
      <p class="eyebrow"><?=e($aboutText('cta_kicker', 'BE PART OF OUR STORY'))?></p>
      <h2><?=e($aboutText('cta_title', 'Create Your Unforgettable Moments in Bali'))?></h2>
      <?php if ($aboutText('cta_text') !== ''): ?><p><?=nl2br(e($aboutText('cta_text')))?></p><?php endif; ?>
      <?php if ($aboutText('cta_button_text') !== ''): ?><a class="about-btn" href="<?=e($aboutUrl($aboutText('cta_button_url', 'packages.php')))?>"><?=e($aboutText('cta_button_text'))?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
