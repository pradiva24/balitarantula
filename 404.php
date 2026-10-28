<?php
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/functions.php';

// Always return a real HTTP 404, including direct access to /404.php or /404.
http_response_code(404);

$title = setting('404_title', 'Oops! Page Not Found');
$description = setting('404_description', "It looks like this page took a little adventure and got lost in the jungle. But don't worry, there are still many amazing experiences waiting for you at <?=e(site_brand_name())?>!");
$eyebrow = setting('404_eyebrow', '404 · OOPS!');
$buttonText = setting('404_button_text', 'Back to Homepage');
$buttonUrl = setting('404_button_url', BASE_URL.'/');
$background = setting('404_background', 'uploads/homepage/dd5c08b6cf28dcfb4147.jpg');

$canonical = rtrim(setting('site_url', BASE_URL), '/') . '/404';
$ogImage = $background;
require __DIR__.'/includes/header.php';
?>
<style>
.bt-404-page{background:#f5f4ef;color:#5d5b56;font-family:'Inter',system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.bt-404-hero{position:relative;min-height:650px;overflow:hidden;display:flex;align-items:center;background:linear-gradient(112deg, #171717 0%, #242321 65%, #30271f 100%);border-bottom:3px solid #ff5a1f}
.bt-404-bg{position:absolute;inset:0;background:#181818}
.bt-404-bg img{width:100%;height:100%;object-fit:cover;display:block;opacity:.25}
.bt-404-overlay{position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.92) 0%,rgba(0,0,0,.82) 40%,rgba(0,0,0,.45) 70%,rgba(0,0,0,.2) 100%)}
.bt-404-content{position:relative;z-index:2;width:min(1180px,92%);margin:0 auto;padding:85px 0;max-width:680px;color:#fff}
.bt-404-code{font-family:'Barlow Condensed',sans-serif;font-size:clamp(110px,15vw,190px);line-height:.75;font-weight:900;letter-spacing:-6px;color:#ff5a1f;margin:0 0 25px;position:relative}
.bt-404-code:after{content:"✦";font-size:.18em;position:absolute;left:49%;top:20%;color:#ff5a1f}
.bt-404-script{font-family:"Brush Script MT","Segoe Script",cursive;font-size:clamp(46px,6vw,72px);font-weight:400;color:#ff5a1f;line-height:.8;margin:-10px 0 28px 80px;transform:rotate(-6deg)}
.bt-404-content h1{font-family:'Barlow Condensed',sans-serif;font-size:clamp(38px,5vw,58px);line-height:0.98;margin:0 0 16px;font-weight:900;letter-spacing:-0.5px;text-transform:uppercase}
.bt-404-content p{font-size:16px;line-height:1.75;color:#e0ded9;max-width:580px;margin:0 0 30px}
.bt-404-btn{display:inline-flex;align-items:center;gap:14px;background:#ff5a1f;color:#111111;text-decoration:none;border-radius:8px;padding:15px 30px;font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;transition:.2s ease;box-shadow:0 4px 20px rgba(255, 90, 31, 0.35)}
.bt-404-btn:hover{transform:translateY(-2px);background:#ff743f;color:#111111}
.bt-404-btn span{font-size:20px;line-height:1}
.bt-404-explore{position:relative;margin-top:-2px;background:#f5f4ef;padding:70px 0 85px;overflow:hidden;border-top:1px solid #dedbd4}
.bt-404-wave{display:none}
.bt-404-explore-inner{width:min(1180px,92%);margin:auto;text-align:center}
.bt-404-kicker{font-family:'Barlow Condensed',sans-serif;font-size:13px;letter-spacing:2.5px;color:#ff5a1f;font-weight:800;text-transform:uppercase}
.bt-404-explore h2{font-family:'Barlow Condensed',sans-serif;font-size:clamp(32px,4.5vw,50px);font-weight:900;letter-spacing:-0.5px;line-height:0.98;margin:8px 0 4px;color:#191919;text-transform:uppercase}
.bt-404-explore-sub{font-size:12px;letter-spacing:2px;color:#77736b;margin-bottom:32px;text-transform:uppercase}
.bt-404-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px}
.bt-404-card{background:#ffffff;border:1px solid #dedbd4;border-top:3px solid #ff5a1f;border-radius:12px;padding:24px 14px 22px;text-decoration:none;color:#191919;box-shadow:0 8px 24px rgba(30,25,15,0.04);transition:.2s ease}
.bt-404-card:hover{transform:translateY(-4px);box-shadow:0 16px 36px rgba(30,25,15,.1);border-color:#ff5a1f}
.bt-404-card-icon{width:54px;height:54px;border:1.5px solid #ff5a1f;border-radius:50%;display:grid;place-items:center;margin:0 auto 14px;font-size:24px;color:#ff5a1f;background:rgba(255,90,31,0.08)}
.bt-404-card h3{font-family:'Barlow Condensed',sans-serif;font-size:20px;margin:0 0 6px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;color:#191919}
.bt-404-card p{font-size:13px;margin:0;color:#5d5b56}
@media(max-width:900px){
 .bt-404-hero{min-height:580px}
 .bt-404-content{padding:70px 0}
 .bt-404-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:560px){
 .bt-404-hero{min-height:600px;align-items:flex-start}
 .bt-404-bg img{object-position:center center}
 .bt-404-overlay{background:linear-gradient(180deg,rgba(0,0,0,.92) 0%,rgba(0,0,0,.85) 60%,rgba(0,0,0,.6) 100%)}
 .bt-404-content{padding:75px 0 35px;text-align:center}
 .bt-404-code{font-size:105px;letter-spacing:-4px;margin-bottom:20px}
 .bt-404-script{font-size:46px;margin:-5px 0 25px 25px}
 .bt-404-content p{font-size:14px}
 .bt-404-btn{width:100%;max-width:100%;padding:14px 18px;justify-content:center}
 .bt-404-grid{grid-template-columns:1fr 1fr;gap:10px}
 .bt-404-card{padding:18px 8px}
 .bt-404-card-icon{width:44px;height:44px;font-size:20px}
}
</style>

<div class="bt-404-page">
<section class="bt-404-hero" aria-labelledby="error-title">
  <div class="bt-404-bg">
    <?php if($background): ?><img src="<?=e(asset_url($background))?>" alt="" loading="eager" decoding="async"><?php endif; ?>
  </div>
  <div class="bt-404-overlay"></div>
  <div class="bt-404-content">
    <div class="bt-404-code" aria-hidden="true">404</div>
    <div class="bt-404-script"><?=e($eyebrow)?></div>
    <h1 id="error-title"><?=e($title)?></h1>
    <p><?=nl2br(e($description))?></p>
    <a class="bt-404-btn" href="<?=e($buttonUrl)?>"><?=e($buttonText)?> <?=site_icon_html('error.button','fa-solid fa-arrow-right')?></a>
  </div>
</section>

<section class="bt-404-explore">
  <div class="bt-404-wave" aria-hidden="true"></div>
  <div class="bt-404-explore-inner">
    <span class="bt-404-kicker">Explore Our Website</span>
    <h2>Where would you like to go?</h2>
    <div class="bt-404-explore-sub">DISCOVER MORE BALI EXPERIENCES</div>
    <div class="bt-404-grid">
      <a class="bt-404-card" href="<?=BASE_URL?>/packages">
        <div class="bt-404-card-icon"><?=site_icon_html("error.adventure","fa-solid fa-person-hiking")?></div><h3>Adventures</h3><p>Feel the freedom</p>
      </a>
      <a class="bt-404-card" href="<?=BASE_URL?>/gallery">
        <div class="bt-404-card-icon"><?=site_icon_html("error.photo","fa-solid fa-camera")?></div><h3>Photo Spots</h3><p>Capture the moment</p>
      </a>
      <a class="bt-404-card" href="<?=BASE_URL?>/packages">
        <div class="bt-404-card-icon"><?=site_icon_html("error.packages","fa-solid fa-box-open")?></div><h3>Tour Packages</h3><p>Best combinations</p>
      </a>
      <a class="bt-404-card" href="<?=BASE_URL?>/about">
        <div class="bt-404-card-icon"><?=site_icon_html("error.about","fa-solid fa-circle-info")?></div><h3>About Us</h3><p>Discover our story</p>
      </a>
      <a class="bt-404-card" href="<?=BASE_URL?>/booking">
        <div class="bt-404-card-icon"><?=site_icon_html("error.book","fa-solid fa-calendar-check")?></div><h3>Book Now</h3><p>Start your adventure</p>
      </a>
    </div>
  </div>
</section>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
