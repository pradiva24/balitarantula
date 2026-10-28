<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../menu-helper.php';
if (function_exists('brand_output_buffer')) ob_start('brand_output_buffer');

$seoPageKey = seo_current_page_key();
$seoRecord = $seoPageKey !== '' ? seo_get_record($seoPageKey) : [];
$siteName = trim((string)site_brand_name());

$title = isset($title) ? trim((string)$title) : $siteName;
$description = isset($description) ? trim((string)$description) : trim((string)setting('site_description', site_brand_name().' — Bali hidden waterfall and nature adventure experience.'));
$canonical = isset($canonical) ? trim((string)$canonical) : '';
$ogImage = isset($ogImage) ? trim((string)$ogImage) : '';
$ogTitle = $title;
$ogDescription = $description;
$robots = 'index,follow';
$schemaJson = '';

if ($seoRecord) {
    $title = seo_value($seoRecord,'seo_title',$title);
    $description = seo_value($seoRecord,'meta_description',$description);
    $canonical = seo_value($seoRecord,'canonical_url',$canonical);
    $ogTitle = seo_value($seoRecord,'og_title',$title);
    $ogDescription = seo_value($seoRecord,'og_description',$description);
    $ogImage = seo_value($seoRecord,'og_image',$ogImage);
    $robots = seo_value($seoRecord,'robots',$robots);
    $schemaJson = trim((string)($seoRecord['schema_json'] ?? ''));
}
if ($ogTitle==='') $ogTitle=$title;
if ($ogDescription==='') $ogDescription=$description;
if ($ogImage==='') $ogImage=trim((string)setting('og_image',''));
$ogImage=seo_absolute_asset($ogImage);

if($canonical==='') $canonical=rtrim((string)setting('site_url',BASE_URL),'/').'/';
$canonical=seo_normalize_canonical($canonical);

$schemaData=null;
if($schemaJson!=='' && seo_json_is_valid($schemaJson)) $schemaData=json_decode($schemaJson,true);

if($schemaData===null){
    $siteUrl=rtrim((string)setting('site_url',BASE_URL),'/');
    $logo=seo_absolute_asset((string)setting('site_logo',setting('admin_logo','')));
    if(isset($p) && is_array($p) && !empty($p['name'])){
        $schemaData=[
            '@context'=>'https://schema.org',
            '@type'=>'Product',
            'name'=>(string)$p['name'],
            'description'=>trim(strip_tags((string)($p['short_description']??$description))),
            'url'=>$canonical,
            'brand'=>['@type'=>'Brand','name'=>$siteName],
        ];
        $image=seo_absolute_asset((string)($p['cover_image']??''));
        if($image!=='') $schemaData['image']=[$image];
        if(isset($p['price_adult']) && $p['price_adult']!==''){
            $schemaData['offers']=[
                '@type'=>'Offer','url'=>$canonical,
                'priceCurrency'=>(string)setting('currency','IDR'),
                'price'=>(string)$p['price_adult'],
                'availability'=>'https://schema.org/InStock'
            ];
        }
    }elseif(isset($r) && is_array($r) && !empty($r['title'])){
        $schemaData=[
            '@context'=>'https://schema.org',
            '@type'=>'Article',
            'headline'=>(string)$r['title'],
            'description'=>trim(strip_tags((string)($r['excerpt']??$description))),
            'url'=>$canonical,
            'author'=>['@type'=>'Person','name'=>trim((string)($r['author_name']??''))?:$siteName],
            'publisher'=>['@type'=>'Organization','name'=>$siteName],
        ];
        if(!empty($r['published_at'])) $schemaData['datePublished']=date('c',strtotime((string)$r['published_at']));
        if(!empty($r['updated_at'])) $schemaData['dateModified']=date('c',strtotime((string)$r['updated_at']));
        $image=seo_absolute_asset((string)($r['cover_image']??''));
        if($image!=='') $schemaData['image']=[$image];
    }else{
        $schemaData=[
            '@context'=>'https://schema.org',
            '@graph'=>[
                [
                    '@type'=>'WebSite',
                    '@id'=>$siteUrl.'/#website',
                    'url'=>$siteUrl.'/',
                    'name'=>$siteName,
                ],
                [
                    '@type'=>'TravelAgency',
                    '@id'=>$siteUrl.'/#organization',
                    'name'=>$siteName,
                    'url'=>$siteUrl.'/',
                ],
            ],
        ];
        if($logo!=='') $schemaData['@graph'][1]['logo']=['@type'=>'ImageObject','url'=>$logo];
        $phone=trim((string)setting('site_phone','')); $email=trim((string)setting('site_email',''));
        $address=trim((string)setting('site_address','Bali, Indonesia'));
        if($phone!=='') $schemaData['@graph'][1]['telephone']=$phone;
        if($email!=='') $schemaData['@graph'][1]['email']=$email;
        if($address!=='') $schemaData['@graph'][1]['address']=['@type'=>'PostalAddress','addressLocality'=>$address,'addressCountry'=>'ID'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?></title>
<meta name="description" content="<?=e($description)?>">
<meta name="robots" content="<?=e($robots)?>">
<link rel="canonical" href="<?=e($canonical)?>">
<meta property="og:type" content="<?=(!empty($r)&&is_array($r)&&!empty($r['title']))?'article':'website'?>">
<meta property="og:site_name" content="<?=e($siteName)?>">
<meta property="og:locale" content="<?=e((string)setting('seo_locale','en_US'))?>">
<meta property="og:title" content="<?=e($ogTitle)?>">
<meta property="og:description" content="<?=e($ogDescription)?>">
<meta property="og:url" content="<?=e($canonical)?>">
<?php if($ogImage):?><meta property="og:image" content="<?=e($ogImage)?>"><meta property="og:image:alt" content="<?=e($ogTitle)?>"><?php endif;?>
<meta name="twitter:card" content="<?= $ogImage?'summary_large_image':'summary'?>">
<meta name="twitter:title" content="<?=e($ogTitle)?>">
<meta name="twitter:description" content="<?=e($ogDescription)?>">
<?php if($ogImage):?><meta name="twitter:image" content="<?=e($ogImage)?>"><?php endif;?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap">
<?php if(!empty($heroSlides[0]['url'])): ?>
<?php
    $heroPreloadPath = (string)$heroSlides[0]['url'];
    $heroPreloadMobile = bt_media_variant_relative($heroPreloadPath, 768);
    $heroPreloadDesktop = bt_media_variant_relative($heroPreloadPath, 1440);
    $heroPreloadMobileSrcset = bt_responsive_srcset($heroPreloadPath, [480,768]);
    $heroPreloadDesktopSrcset = bt_responsive_srcset($heroPreloadPath, [1024,1440,1920]);
?>
<?php if($heroPreloadMobile !== ''): ?>
<link rel="preload" as="image" href="<?=e(asset_url($heroPreloadMobile))?>" media="(max-width: 767px)" fetchpriority="high"<?= $heroPreloadMobileSrcset!=='' ? ' imagesrcset="'.e($heroPreloadMobileSrcset).'" imagesizes="100vw"' : '' ?>>
<?php endif; ?>
<?php if($heroPreloadDesktop !== ''): ?>
<link rel="preload" as="image" href="<?=e(asset_url($heroPreloadDesktop))?>" media="(min-width: 768px)" fetchpriority="high"<?= $heroPreloadDesktopSrcset!=='' ? ' imagesrcset="'.e($heroPreloadDesktopSrcset).'" imagesizes="100vw"' : '' ?>>
<?php else: ?>
<link rel="preload" as="image" href="<?=e($heroPreloadPath)?>" fetchpriority="high"<?= ($heroPreloadSrcset=bt_responsive_srcset($heroPreloadPath))!=='' ? ' imagesrcset="'.e($heroPreloadSrcset).'" imagesizes="100vw"' : '' ?>>
<?php endif; ?>
<?php endif; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css?v=<?=time()?>">

<style id="bta-global-adventure-navigation-v2">
/* Shared Bali Tarantula header redesign. Scoped here so all pages using includes/header.php share it. */
body.menu-open{overflow:hidden!important}
.topline{height:30px!important;padding:0 max(24px,calc((100vw - 1180px)/2))!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:12px!important;background:#ff5a1f!important;color:#101010!important;font-size:9px!important;font-weight:800!important;letter-spacing:1.7px!important;line-height:1.2!important;position:relative!important;z-index:10000!important}
.topline .orange{color:#fff!important}
header.site-header{height:82px!important;min-height:82px!important;padding:0 max(24px,calc((100vw - 1180px)/2))!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:24px!important;position:absolute!important;top:30px!important;left:0!important;right:0!important;width:100%!important;z-index:9999!important;background:transparent!important;color:#fff!important;border:0!important;border-bottom:1px solid rgba(255,255,255,.17)!important;box-shadow:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important}
.site-header .brand{display:flex!important;align-items:center!important;gap:10px!important;min-width:0!important;flex:0 1 auto!important;color:#fff!important;text-decoration:none!important}
.site-header .site-brand-logo{display:block!important;width:auto!important;height:auto!important;max-width:105px!important;max-height:54px!important;object-fit:contain!important}
.site-header .brand-mark{display:block!important;color:#fff!important;font:900 34px/.9 "Barlow Condensed",Impact,sans-serif!important;letter-spacing:-2px!important;white-space:nowrap!important}
.site-header .brand-mark span{color:#ff5a1f!important}
.site-header .brand-copy{display:block!important;color:#fff!important;font:800 12px/1.2 "Inter",Arial,sans-serif!important;letter-spacing:1.2px!important;text-decoration:none!important;white-space:nowrap!important}
.site-header .brand-copy small{display:block!important;color:#ff5a1f!important;font-size:9px!important;letter-spacing:3.7px!important;margin-top:4px!important}
.site-header #mainNav.main-nav,.site-header #mainNav{margin-left:auto!important;display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:clamp(14px,2.3vw,34px)!important;position:static!important;inset:auto!important;width:auto!important;padding:0!important;background:transparent!important;background-color:transparent!important;border:0!important;box-shadow:none!important;flex-direction:row!important;white-space:nowrap!important}
.site-header #mainNav a{display:inline-flex!important;align-items:center!important;justify-content:center!important;position:relative!important;width:auto!important;margin:0!important;padding:10px 0!important;border:0!important;border-radius:0!important;background:transparent!important;color:#f3f3f3!important;text-decoration:none!important;font-family:"Inter",Arial,sans-serif!important;font-size:11px!important;font-weight:700!important;line-height:1.2!important;letter-spacing:.7px!important;transition:color .2s ease!important}
.site-header #mainNav a:hover,.site-header #mainNav a:focus-visible{color:#ff5a1f!important}
.site-header #mainNav a:not(.nav-book):not(.nav-ticket)::after{content:""!important;position:absolute!important;left:0!important;right:100%!important;bottom:2px!important;height:2px!important;background:#ff5a1f!important;transition:right .2s ease!important}
.site-header #mainNav a:not(.nav-book):not(.nav-ticket):hover::after,.site-header #mainNav a:not(.nav-book):not(.nav-ticket):focus-visible::after{right:0!important}
.site-header #mainNav .nav-book,.site-header #mainNav a.nav-book{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-width:0!important;min-height:44px!important;padding:0 17px!important;margin:0!important;background:#ff5a1f!important;color:#111!important;border:0!important;border-radius:0!important;font-size:10px!important;font-weight:900!important;letter-spacing:1px!important;white-space:nowrap!important}
.site-header #mainNav .nav-book:hover{background:#ff743f!important;color:#111!important;transform:translateY(-1px)!important}
.site-header #mainNav .nav-ticket{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:7px!important;min-height:40px!important;padding:8px 0!important;margin:0!important;border:0!important;border-radius:0!important;background:transparent!important;color:#f3f3f3!important;font-size:11px!important;font-weight:700!important}
.site-header #mainNav .nav-ticket:hover{background:transparent!important;color:#ff5a1f!important}
.site-header #mainNav .nav-ticket .ticket-icon{width:16px!important;height:16px!important;filter:brightness(0) invert(1)!important}
.site-header .menu-toggle{display:none!important;flex-direction:column!important;justify-content:center!important;gap:5px!important;position:relative!important;z-index:10002!important;background:transparent!important;border:0!important;border-radius:0!important;padding:10px!important;margin:0!important;min-width:44px!important;min-height:44px!important;cursor:pointer!important}
.site-header .menu-toggle span{display:block!important;width:25px!important;height:2px!important;background:#fff!important;transition:transform .2s,opacity .2s!important}
.site-header .menu-toggle[aria-expanded=true] span:first-child{transform:translateY(7px) rotate(45deg)!important}
.site-header .menu-toggle[aria-expanded=true] span:nth-child(2){opacity:0!important}
.site-header .menu-toggle[aria-expanded=true] span:last-child{transform:translateY(-7px) rotate(-45deg)!important}
.site-header.is-scrolled{position:fixed!important;top:0!important;left:0!important;right:0!important;height:72px!important;min-height:72px!important;background:rgba(12,12,12,.9)!important;backdrop-filter:blur(14px)!important;-webkit-backdrop-filter:blur(14px)!important;border-bottom:1px solid rgba(255,255,255,.13)!important;box-shadow:0 8px 28px rgba(0,0,0,.12)!important}
.site-header.is-scrolled .brand-mark,.site-header.is-scrolled .brand-copy{color:#fff!important}
.site-header.is-scrolled .main-nav>a:not(.nav-book):not(.nav-ticket){color:#f3f3f3!important}
@media(max-width:900px){
 .topline{padding:0 20px!important}
 header.site-header{padding:0 20px!important}
 .site-header .site-brand-logo{max-width:90px!important;max-height:48px!important}
 .site-header .brand-copy{font-size:10px!important}
 .site-header .brand-copy small{font-size:8px!important;letter-spacing:3px!important}
 .site-header .menu-toggle{display:flex!important}
 .site-header #mainNav.main-nav,.site-header #mainNav{display:none!important;position:fixed!important;inset:112px 0 0!important;top:112px!important;left:0!important;right:0!important;bottom:0!important;width:100%!important;height:auto!important;max-height:none!important;overflow-y:auto!important;z-index:10001!important;flex-direction:column!important;align-items:stretch!important;justify-content:flex-start!important;gap:0!important;margin:0!important;padding:28px 24px 36px!important;background:rgba(14,14,14,.98)!important;background-color:rgba(14,14,14,.98)!important;border:0!important;box-shadow:none!important;white-space:normal!important}
 .site-header #mainNav.main-nav.open,.site-header #mainNav.open{display:flex!important}
 .site-header #mainNav a{display:flex!important;width:100%!important;justify-content:flex-start!important;padding:17px 0!important;margin:0!important;border-bottom:1px solid rgba(255,255,255,.12)!important;font:800 27px/1 "Barlow Condensed",Impact,sans-serif!important;letter-spacing:.2px!important;white-space:normal!important}
 .site-header #mainNav a:not(.nav-book):not(.nav-ticket)::after{display:none!important}
 .site-header #mainNav .nav-book,.site-header #mainNav a.nav-book{justify-content:center!important;margin:22px 0 0!important;padding:16px!important;min-height:48px!important;width:100%!important;font:900 11px/1.2 "Inter",Arial,sans-serif!important;letter-spacing:1px!important}
 .site-header #mainNav .nav-ticket{justify-content:center!important;margin:8px 0 0!important;width:100%!important;border:1px solid rgba(255,255,255,.25)!important;padding:14px!important}
 .site-header.is-scrolled{height:66px!important;min-height:66px!important}
 .site-header.is-scrolled #mainNav.main-nav,.site-header.is-scrolled #mainNav{top:66px!important;inset:66px 0 0!important}
}
@media(max-width:640px){
 .topline{height:28px!important;padding:0 18px!important;font-size:8px!important;letter-spacing:1px!important}
 header.site-header{height:70px!important;min-height:70px!important;top:28px!important;padding:0 18px!important}
 .site-header .brand{gap:7px!important}
 .site-header .brand-mark{font-size:30px!important}
 .site-header .brand-copy{font-size:9px!important;letter-spacing:.8px!important}
 .site-header .brand-copy small{font-size:7px!important;letter-spacing:2.5px!important}
 .site-header #mainNav.main-nav,.site-header #mainNav{top:98px!important;inset:98px 0 0!important;padding:24px 22px 32px!important}
 .site-header.is-scrolled{height:64px!important;min-height:64px!important;padding:0 18px!important}
 .site-header.is-scrolled #mainNav.main-nav,.site-header.is-scrolled #mainNav{top:64px!important;inset:64px 0 0!important}
}
@media(prefers-reduced-motion:reduce){.site-header #mainNav a,.site-header #mainNav a::after,.site-header .menu-toggle span{transition:none!important}}
</style>

<?php if(setting('favicon','')):?><link rel="icon" href="<?=e(asset_url(setting('favicon','')))?>"><?php endif;?>
<script type="application/ld+json"><?=json_encode($schemaData,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script>
<!-- GLOBAL TYPOGRAPHY: Inter body text, Barlow Condensed titles across the public website -->
<style id="global-site-typography">
:root{--display:"Barlow Condensed",Impact,sans-serif;--body:"Inter",Arial,sans-serif;--font-title:"Barlow Condensed",Impact,sans-serif;--font-body:"Inter",Arial,sans-serif;}
html,body,body p,body li,body label,body input,body textarea,body select,body option,body td,body th,body small,body blockquote,body a,body button,body span,body div,body summary,body figcaption,body time,body address,body dd,body dt {font-family:"Inter",Arial,sans-serif!important;}
body h1,body h2,body h3,body h4,body h5,body h6,body .title,body .page-title,body .section-title,body .inner-title,body .page-banner-content h1,body .section-heading h2,body .manifesto h2,body .cta-inner h2,body .card-content h3,body .track-info h3,body .blog-copy h2,body .blog-copy h3,body .article-main h2,body .article-main h3,body .detail-block h3,body .article-sidebar h3,body .booking-panel h3,body [class*="title"],body [class*="heading"] {font-family:"Barlow Condensed",Impact,sans-serif!important;}
body h1 span,body h2 span,body h3 span,body h4 span,body h5 span,body h6 span,body .title span,body .page-title span,body .section-title span,body .inner-title span,body .page-banner-content h1 span,body .section-heading h2 span,body .manifesto h2 span,body .cta-inner h2 span,body .card-content h3 span,body .track-info h3 span,body .blog-copy h2 span,body .blog-copy h3 span,body .article-main h2 span,body .article-main h3 span,body [class*="title"] span,body [class*="heading"] span {font-family:"Barlow Condensed",Impact,sans-serif!important;}
/* Font Awesome 6 support and font isolation */
.fa-solid, .fas, [class*="fa-solid"] { font-family: "Font Awesome 6 Free" !important; font-weight: 900 !important; font-style: normal !important; text-transform: none !important; }
.fa-regular, .far, [class*="fa-regular"] { font-family: "Font Awesome 6 Free" !important; font-weight: 400 !important; font-style: normal !important; text-transform: none !important; }
.fa-brands, .fab, [class*="fa-brands"] { font-family: "Font Awesome 6 Brands" !important; font-weight: 400 !important; font-style: normal !important; text-transform: none !important; }
i[class*="fa-"], span[class*="fa-"], [class^="fa-"], [class*=" fa-"], .fa, .fas, .far, .fab { font-style: normal !important; line-height: 1 !important; text-rendering: auto !important; text-transform: none !important; -webkit-font-smoothing: antialiased !important; }
</style>
</head>
<body>
<div class="topline"><span>BUILT FOR THE WILD</span><span>BALI, INDONESIA <span class="orange">●</span></span></div>
<header class="site-header">
  <a class="brand" href="<?=e(rtrim(BASE_URL,'/').'/')?>" aria-label="<?=e($siteName)?> home">
    <?php $headerLogo = trim((string)setting('site_logo',setting('admin_logo',''))); ?>
    <?php if($headerLogo !== ''): ?>
      <img src="<?=e(asset_url($headerLogo))?>" alt="<?=e($siteName)?>" class="site-brand-logo" loading="eager" decoding="async" onerror="this.style.display='none'">
    <?php else: ?>
      <span class="brand-mark">BT<span>A</span></span>
    <?php endif; ?>
   
  </a>
  <button class="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="mainNav"><span></span><span></span><span></span></button>
  <?php bt_render_navigation('mainNav','main-nav'); ?>
</header><main>
<script>
(function(){
  const toggle=document.querySelector('.site-header .menu-toggle');
  const nav=document.querySelector('.site-header #mainNav');
  const header=document.querySelector('.site-header');
  if(toggle&&nav){
    const closeMenu=()=>{
      toggle.setAttribute('aria-expanded','false');
      toggle.setAttribute('aria-label','Open navigation');
      nav.classList.remove('open');
      document.body.classList.remove('menu-open');
    };
    toggle.addEventListener('click',()=>{
      const expanded=toggle.getAttribute('aria-expanded')==='true';
      toggle.setAttribute('aria-expanded',String(!expanded));
      toggle.setAttribute('aria-label',expanded?'Open navigation':'Close navigation');
      nav.classList.toggle('open',!expanded);
      document.body.classList.toggle('menu-open',!expanded);
    });
    nav.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{
      if(window.matchMedia('(max-width: 900px)').matches) closeMenu();
    }));
    document.addEventListener('keydown',event=>{if(event.key==='Escape')closeMenu();});
  }
  if(header){
    const updateHeader=()=>header.classList.toggle('is-scrolled',window.scrollY>40);
    updateHeader();
    window.addEventListener('scroll',updateHeader,{passive:true});
  }
})();
</script>


<style>
html body { font-family: 'Inter', sans-serif !important; color: #5d5b56; font-size: 15px; }
html body p, html body li, html body label, html body small, html body .description, html body .content-text { font-family: 'Inter', sans-serif !important; color: #5d5b56; }
html body .text-muted { color: #77736b !important; }
</style>
