<?php
$footerName = setting('footer_name', site_brand_name());
$footerDesc = setting('footer_description','Create unforgettable memories with exciting Bali adventures, nature experiences, and local hospitality.');
$footerTag = setting('footer_tagline','NATURE · ADVENTURE · HAPPINESS');
$footerBottom = setting('footer_bottom_text','All rights reserved.');
$footerLogo = setting('footer_logo', setting('site_logo',''));
$footerBg = setting('footer_background','');
$quickLinksRaw = setting('footer_quick_links', "Home|/\nAbout Us|/about.php\nActivities|/packages.php\nPackages|/packages.php\nGallery|/#gallery\nBlog|/blog.php\nContact|/contact.php");
$popularRaw = setting('footer_popular_links', "Bali ATV|/packages.php\nAyung Rafting|/packages.php\nBali Swing|/packages.php\nUbud Activities|/packages.php\nKintamani Tour|/packages.php\nBali Car Rental|/contact.php");
$socialLinks = [];
// Social Media source: Admin → Social Links (contact_page.data.social_links).
try {
    // Frontend uses the same database connection as the public pages.
    $socialDb = function_exists('db') ? db() : null;
    if ($socialDb instanceof PDO) {
        $socialStmt = $socialDb->query("SELECT data FROM contact_page WHERE id=1 LIMIT 1");
        $socialRow = $socialStmt ? $socialStmt->fetch(PDO::FETCH_ASSOC) : false;
        $socialData = $socialRow && !empty($socialRow['data']) ? json_decode((string)$socialRow['data'], true) : null;
        if (is_array($socialData) && !empty($socialData['social_links']) && is_array($socialData['social_links'])) {
            foreach ($socialData['social_links'] as $social) {
                if (!is_array($social) || empty($social['enabled']) || trim((string)($social['url'] ?? '')) === '') continue;
                $socialLinks[] = [
                    'name' => trim((string)($social['name'] ?? 'Social Link')),
                    'url' => trim((string)$social['url']),
                    'icon' => trim((string)($social['icon'] ?? 'fa-solid fa-link')) ?: 'fa-solid fa-link',
                    'logo' => trim((string)($social['logo'] ?? '')),
                ];
            }
        }
    }
} catch (Throwable $e) { $socialLinks = []; }

$payments = array_values(array_filter(array_map('trim', explode(',', setting('footer_payment_methods','VISA,MASTERCARD,BCA,MANDIRI,BNI,BRI,QRIS')))));
$paymentIcons=[]; for($pi=1;$pi<=7;$pi++){ $paymentIcons[$pi]=setting('footer_payment_'.$pi.'_icon',''); }
$wonderfulLogo=setting('footer_wonderful_logo','');
$footerAssetExists = static function($path): bool {
    if(!$path || preg_match('~^https?://~i',(string)$path)) return true;
    return is_file(__DIR__.'/../'.ltrim((string)$path,'/'));
};
$parseLinks = static function($raw){
    $out=[];
    foreach(preg_split('/\r\n|\r|\n/', (string)$raw) as $line){
        $line=trim($line); if($line==='') continue;
        $parts=explode('|',$line,2); $label=trim($parts[0]??''); $url=trim($parts[1]??'#');
        if($label!=='') $out[]=['label'=>$label,'url'=>$url?:'#'];
    }
    return $out;
};
$quickLinks=$parseLinks($quickLinksRaw); $popularLinks=$parseLinks($popularRaw);

$footerSlides=[];
$footerSlidesRaw=(string)setting('footer_image_slides','');
if($footerSlidesRaw!==''){
    $decoded=json_decode($footerSlidesRaw,true);
    if(is_array($decoded)){
        foreach($decoded as $slide){
            if(!is_array($slide)) continue;
            $fimg=trim((string)($slide['image']??''));
            if($fimg==='' || !$footerAssetExists($fimg)) continue;
            $furl=trim((string)($slide['url']??''));
            $falt=trim((string)($slide['alt']??''));
            $fnew=!empty($slide['newtab']);
            $footerSlides[]=['image'=>$fimg,'url'=>$furl!==''?$furl:'#','alt'=>$falt!==''?$falt:'Footer promotion','newtab'=>$fnew];
        }
    }
}
$wa=setting('site_whatsapp','');
$waButtonText=trim((string)setting('site_whatsapp_button_text','WhatsApp'));
if($waButtonText==='') $waButtonText='WhatsApp';
$waMessage=trim((string)setting('site_whatsapp_message','Hello Manuaba Crystalfall, I would like to ask about your experiences.'));
if($waMessage==='') $waMessage='Hello Manuaba Crystalfall, I would like to ask about your experiences.';
$bgStyle=$footerBg ? ' style="--footer-bg-image:url(\''.e(asset_url($footerBg)).'\')"' : '';
?><link rel="stylesheet" href="<?=BASE_URL?>/assets/css/footer.css?v=22">
<style id="bt-footer-slider-critical">
/* Footer Background Transparent Black Overlay (Darker) */
.bt-footer{position:relative;background-color:#000000!important;overflow:hidden;}
.bt-footer:before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.88) 0%,rgba(0,0,0,.95) 100%),var(--footer-bg-image,none)!important;background-size:cover!important;background-position:center!important;background-repeat:no-repeat!important;opacity:1!important;pointer-events:none;}
.bt-footer-main,.bt-footer-trust,.bt-footer-bottom{position:relative;z-index:1;}
.bt-footer-trust{background:rgba(0,0,0,.50)!important;}
.bt-footer-bottom{background:rgba(0,0,0,.92)!important;}

/* Critical slider styles: keep the promo strip compact and horizontal even if footer.css is cached/blocked. */
.bt-footer-slider{grid-column:1 / span 3;grid-row:2;min-width:0;min-height:0;align-self:start;margin-top:2px;box-sizing:border-box;}
.bt-footer-slider-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 8px;}
.bt-footer-slider-head h3{margin:0;color:#fff;font-size:18px;line-height:1.2;}
.bt-footer-slider-nav{display:flex;gap:6px;}
.bt-footer-slider-nav button{width:30px;height:30px;border:1px solid rgba(255,255,255,.28);border-radius:50%;background:rgba(255,255,255,.10);color:#fff;font-size:18px;line-height:1;cursor:pointer;display:grid;place-items:center;padding:0;transition:background .2s,color .2s;}
.bt-footer-slider-nav button:hover{background:#ed302b;color:#fff;}
.bt-footer-slider-viewport{width:100%;height:106px;overflow:hidden;border-radius:8px;}
.bt-footer-slider-track{display:flex;gap:12px;width:max-content;transform:translateX(0);transition:transform .45s ease;will-change:transform;}
.bt-footer-slide{position:relative;display:block;flex:0 0 calc((100% - 36px)/4);width:calc((100% - 36px)/4);height:106px;overflow:hidden;border-radius:8px;background:rgba(255,255,255,.07);text-decoration:none;}
.bt-footer-slide:only-child{flex-basis:100%;width:100%;}
.bt-footer-slide img{display:block;width:100%;height:100%;object-fit:cover;}
.bt-footer-slide-overlay{position:absolute;left:0;right:0;bottom:0;padding:6px 9px;background:linear-gradient(transparent,rgba(0,0,0,.72));color:#fff;font-size:11px;font-weight:800;}
.bt-footer-slide-overlay b{color:#ed302b;}
.bt-footer-slider-empty{height:106px;display:grid;place-items:center;border:1px dashed rgba(255,255,255,.22);border-radius:8px;color:#c7ddd2;font-size:12px;text-align:center;padding:12px;box-sizing:border-box;}
@media(max-width:1000px){
 .bt-footer-slider{grid-column:1 / -1;grid-row:auto;order:4;}
 .bt-footer-slider-viewport{height:120px;}
 .bt-footer-slide{flex-basis:calc((100% - 24px)/3);width:calc((100% - 24px)/3);height:120px;}
}
@media(max-width:620px){
 .bt-footer-slider{grid-column:1 / -1;}
 .bt-footer-slider-viewport{height:112px;}
 .bt-footer-slider-track{gap:10px;}
 .bt-footer-slide{flex-basis:calc((100% - 10px)/2);width:calc((100% - 10px)/2);height:112px;}
}

.wa-float{display:inline-flex!important;align-items:center;justify-content:center;gap:8px;white-space:nowrap;}
.wa-float-icon{display:inline-flex;align-items:center;justify-content:center;font-size:20px;line-height:1;}
.wa-float-icon i{display:block;}
.wa-float-label{display:inline-block;}
@media(max-width:520px){.wa-float{right:14px!important;bottom:14px!important;padding:11px 15px!important;gap:7px!important}.wa-float-icon{font-size:19px!important;}}
</style>
<footer class="bt-footer"<?= $bgStyle ?>>
  <div class="bt-footer-main">
    <div class="container bt-footer-grid">
      <section class="bt-footer-brand">
        <a class="bt-footer-logo" href="<?=BASE_URL?>/" aria-label="<?=e($footerName)?>">
          <?php if($footerLogo && $footerAssetExists($footerLogo)): ?><img src="<?=e(asset_url($footerLogo))?>" alt="<?=e($footerName)?>" loading="lazy" decoding="async"><?php else: ?><span class="bt-footer-logo-fallback">BT</span><?php endif; ?>
        </a>
        <h2><?=e($footerName)?></h2>
        <p><?=nl2br(e($footerDesc))?></p>
        <div class="bt-socials" aria-label="Social media">
          <?php foreach($socialLinks as $social): ?>
            <a href="<?=e($social['url'])?>" target="_blank" rel="noopener noreferrer" aria-label="<?=e($social['name'])?>" class="bt-social">
              <?php if($social['logo'] !== ''): ?>
                <img src="<?=e(asset_url($social['logo']))?>" alt="<?=e($social['name'])?>" loading="lazy" decoding="async">
              <?php else: ?>
                <?=site_icon_html('footer.social', $social['icon'])?>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="bt-footer-hashtag">#<?=e(preg_replace('/[^A-Za-z0-9]+/','', $footerName))?></div>
        <div class="bt-footer-mini-tag"><?=e($footerTag)?></div>
      </section>

      <nav class="bt-footer-column" aria-label="Quick Links">
        <h3><?=e(setting('footer_quick_title','Quick Links'))?></h3>
        <?php foreach($quickLinks as $link): ?><a href="<?=e($link['url'])?>"><span><?=site_icon_html("footer.link","fa-solid fa-chevron-right")?></span><?=e($link['label'])?></a><?php endforeach; ?>
      </nav>

      <nav class="bt-footer-column" aria-label="Popular Activities">
        <h3><?=e(setting('footer_popular_title','Popular Activities'))?></h3>
        <?php foreach($popularLinks as $link): ?><a href="<?=e($link['url'])?>"><span><?=site_icon_html("footer.link","fa-solid fa-chevron-right")?></span><?=e($link['label'])?></a><?php endforeach; ?>
      </nav>

      <section class="bt-footer-slider" aria-label="Promotional banners">
        <div class="bt-footer-slider-head">
          <h3><?=e(setting('footer_slider_title','Featured Offers'))?></h3>
          <?php if(count($footerSlides)>1): ?><div class="bt-footer-slider-nav" aria-label="Slider controls"><button type="button" class="bt-footer-slider-prev" aria-label="Previous slide"><?=site_icon_html("footer.slider_prev","fa-solid fa-chevron-left")?></button><button type="button" class="bt-footer-slider-next" aria-label="Next slide"><?=site_icon_html("footer.slider_next","fa-solid fa-chevron-right")?></button></div><?php endif; ?>
        </div>
        <?php if($footerSlides): ?>
        <div class="bt-footer-slider-viewport">
          <div class="bt-footer-slider-track">
            <?php foreach($footerSlides as $i=>$slide): ?>
              <a class="bt-footer-slide" href="<?=e($slide['url'])?>"<?= $slide['newtab'] ? ' target="_blank" rel="noopener noreferrer"' : '' ?> aria-label="<?=e($slide['alt'])?>">
                <img src="<?=e(asset_url($slide['image']))?>" alt="<?=e($slide['alt'])?>" loading="lazy" decoding="async">
                <span class="bt-footer-slide-overlay">View Offer <b><?=site_icon_html("footer.slide_arrow","fa-solid fa-arrow-right")?></b></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php else: ?>
          <div class="bt-footer-slider-empty">Add promotional images from Admin → Footer Builder.</div>
        <?php endif; ?>
      </section>

      <section class="bt-footer-contact">
        <h3><?=e(setting('footer_contact_title','Contact Us'))?></h3>
        <div class="bt-contact-item"><span class="bt-contact-icon"><?=site_icon_html("footer.address","fa-solid fa-location-dot")?></span><div><?=nl2br(e(setting('site_address','Bali, Indonesia')))?></div></div>
        <div class="bt-contact-item"><span class="bt-contact-icon"><?=site_icon_html("footer.phone","fa-solid fa-phone")?></span><div><?=e(setting('site_phone',''))?></div></div>
        <div class="bt-contact-item"><span class="bt-contact-icon"><?=site_icon_html("footer.email","fa-regular fa-envelope")?></span><div><?=e(setting('site_email',''))?></div></div>
        <div class="bt-contact-item"><span class="bt-contact-icon"><?=site_icon_html("footer.hours","fa-regular fa-clock")?></span><div><?=e(setting('footer_hours','Mon – Sun : 08:00 – 18:00'))?></div></div>
        <h4><?=e(setting('footer_offer_title','Get Special Offers'))?></h4>
        <p><?=e(setting('footer_offer_text','Subscribe to get the latest updates and exclusive deals.'))?></p>
        <form class="bt-newsletter" action="<?=BASE_URL?>/contact-us" method="get">
          <input type="email" name="newsletter_email" placeholder="<?=e(setting('footer_newsletter_placeholder','Your email address'))?>" aria-label="Email address">
          <button type="submit"><?=e(setting('footer_newsletter_button','Subscribe'))?></button>
        </form>
      </section>
    </div>
  </div>

  <style id="bt-hide-trust-mobile-tablet">
@media screen and (max-width:1024px){
  .bt-footer .bt-footer-trust,
  footer .bt-footer-trust{
    display:none !important;
    visibility:hidden !important;
    height:0 !important;
    min-height:0 !important;
    max-height:0 !important;
    margin:0 !important;
    padding:0 !important;
    overflow:hidden !important;
  }
}
</style>
<div class="bt-footer-trust">
    <div class="container bt-trust-grid">
      <div class="bt-trust-items">
        <div class="bt-trust"><span><?=site_icon_html("footer.trust_1","fa-solid fa-circle-check")?></span><strong><?=e(setting('footer_trust_1_title','Authentic'))?><small><?=e(setting('footer_trust_1_text','Bali Experience'))?></small></strong></div>
        <div class="bt-trust"><span><?=site_icon_html("footer.trust_2","fa-solid fa-shield-heart")?></span><strong><?=e(setting('footer_trust_2_title','Safe & Secure'))?><small><?=e(setting('footer_trust_2_text','Booking'))?></small></strong></div>
        <div class="bt-trust"><span><?=site_icon_html("footer.trust_3","fa-solid fa-handshake")?></span><strong><?=e(setting('footer_trust_3_title','Friendly'))?><small><?=e(setting('footer_trust_3_text','Local Team'))?></small></strong></div>
      </div>
      <div class="bt-payment"><b><?=e(setting('footer_payment_title','We Accept'))?></b><div><?php foreach($payments as $idx=>$pay): $icon=$paymentIcons[$idx+1]??''; ?><span class="bt-payment-item"><?php if($icon && $footerAssetExists($icon)): ?><img src="<?=e(asset_url($icon))?>" alt="<?=e($pay)?>" loading="lazy" decoding="async"><?php else: ?><?=e($pay)?><?php endif; ?></span><?php endforeach; ?></div></div>
      <div class="bt-wonderful"><?php if($wonderfulLogo && $footerAssetExists($wonderfulLogo)): ?><img src="<?=e(asset_url($wonderfulLogo))?>" alt="Wonderful Indonesia" loading="lazy" decoding="async"><?php else: ?><span>wonderful<br><strong>indonesia</strong></span><?php endif; ?></div>
    </div>
  </div>

  <div class="bt-footer-bottom">
    <div class="container bt-footer-bottom-inner">
      <div>© <?=date('Y')?> <?=e($footerName)?>. <?=e($footerBottom)?></div>
      <div class="bt-legal"><a href="<?=BASE_URL?>/page/privacy-policy">Privacy Policy</a><i>|</i><a href="<?=BASE_URL?>/page/terms-conditions">Terms &amp; Conditions</a><i>|</i><a href="<?=BASE_URL?>/sitemap">Sitemap</a></div>
      <div class="bt-bottom-tag"><?=e($footerTag)?></div>
    </div>
  </div>

<?php if(count($footerSlides)>1): ?>
<script>
(function(){
  const root=document.querySelector('.bt-footer-slider');
  if(!root) return;
  const track=root.querySelector('.bt-footer-slider-track');
  const slides=Array.from(root.querySelectorAll('.bt-footer-slide'));
  if(!track || slides.length<2) return;
  let index=0;
  let timer;
  function visibleCount(){
    return window.matchMedia('(max-width:620px)').matches ? 2
      : (window.matchMedia('(max-width:1000px)').matches ? 3 : 4);
  }
  function syncSlideWidths(){
    const count=visibleCount();
    const viewportWidth=root.querySelector('.bt-footer-slider-viewport')?.clientWidth || root.clientWidth;
    const gap=count>1 ? (window.matchMedia('(max-width:620px)').matches ? 10 : 12) : 0;
    const slideWidth=Math.max(1,(viewportWidth-(gap*(count-1)))/count);
    slides.forEach(slide=>{
      slide.style.flex='0 0 '+slideWidth+'px';
      slide.style.width=slideWidth+'px';
    });
    track.style.gap=gap+'px';
    track.style.width=((slideWidth*slides.length)+(gap*Math.max(0,slides.length-1)))+'px';
  }
  function step(){
    syncSlideWidths();
    const count=visibleCount();
    const max=Math.max(0,slides.length-count);
    if(index>max) index=0;
    const gap=parseFloat(getComputedStyle(track).gap)||0;
    const w=slides[0].getBoundingClientRect().width;
    track.style.transform='translate3d(-'+((w+gap)*index)+'px,0,0)';
  }
  function next(){ index++; step(); }
  function prev(){ index=Math.max(0,index-1); step(); }
  root.querySelector('.bt-footer-slider-next')?.addEventListener('click',()=>{next();restart();});
  root.querySelector('.bt-footer-slider-prev')?.addEventListener('click',()=>{prev();restart();});
  function start(){ timer=setInterval(()=>{ next(); },5000); }
  function restart(){ clearInterval(timer); start(); }
  window.addEventListener('resize',step,{passive:true});
  step(); start();
})();
</script>
<?php endif; ?>
</footer>
<?php if($wa): ?><a class="wa-float" target="_blank" rel="noopener" aria-label="<?=e($waButtonText)?>" href="<?=e(whatsapp_url($wa,$waMessage))?>"><span class="wa-float-icon"><?=site_icon_html('footer.whatsapp','fa-brands fa-whatsapp')?></span><span class="wa-float-label"><?=e($waButtonText)?></span></a><?php endif; ?>
</body></html>

<?php if (function_exists('brand_output_buffer') && ob_get_level() > 0) { @ob_end_flush(); } ?>
