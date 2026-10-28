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
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap"></noscript>
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
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css?v=122">

<style id="bta-global-adventure-navigation-v1">
/* Global Bali Tarantula Adventure navigation — shared by every page using includes/header.php */
.site-header{position:sticky!important;top:0!important;z-index:9999!important;height:auto!important;min-height:78px!important;background:rgba(10,13,17,.94)!important;color:#fff!important;border-top:0!important;border-bottom:1px solid rgba(255,255,255,.12)!important;box-shadow:0 8px 28px rgba(0,0,0,.24)!important;backdrop-filter:blur(14px)!important;-webkit-backdrop-filter:blur(14px)!important}
.site-header .nav{width:min(1440px,94%)!important;min-height:78px!important;height:auto!important;margin:0 auto!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:24px!important;position:relative!important}
.site-header .logo{min-width:160px!important;flex:0 0 auto!important;color:#fff!important}
.site-header .site-logo-img{height:62px!important;max-width:220px!important;width:auto!important;object-fit:contain!important}
.site-header #mainNav{margin-left:auto!important;display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:clamp(12px,1.8vw,28px)!important;background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;position:static!important;flex-direction:row!important;white-space:nowrap!important}
.site-header #mainNav a{display:inline-flex!important;align-items:center!important;justify-content:center!important;position:relative!important;padding:10px 0!important;margin:0!important;border:0!important;border-radius:0!important;background:transparent!important;color:#f8fafc!important;text-decoration:none!important;font-family:inherit!important;font-size:14px!important;font-weight:600!important;line-height:1.2!important;transition:color .2s ease!important}
.site-header #mainNav a:hover,.site-header #mainNav a:focus-visible{color:#ff2638!important}
.site-header #mainNav a:not(.nav-book):not(.nav-ticket)::after{content:"";position:absolute;left:0;right:100%;bottom:2px;height:2px;background:#ed1c2e;transition:right .2s ease}
.site-header #mainNav a:not(.nav-book):not(.nav-ticket):hover::after,.site-header #mainNav a:not(.nav-book):not(.nav-ticket):focus-visible::after{right:0}
.site-header #mainNav .nav-book,.site-header #mainNav a.nav-book{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-width:145px!important;min-height:44px!important;padding:0 22px!important;margin-left:4px!important;border:1px solid #fff!important;border-radius:10px!important;background:#ed1c2e!important;color:#fff!important;font-size:14px!important;font-weight:600!important;box-shadow:0 5px 18px rgba(237,28,46,.24)!important}
.site-header #mainNav .nav-book:hover{background:#c81022!important;color:#fff!important;transform:translateY(-1px)}
.site-header #mainNav .nav-ticket{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:7px!important;min-height:40px!important;padding:8px 12px!important;border:1px solid rgba(255,255,255,.25)!important;border-radius:10px!important;background:rgba(255,255,255,.07)!important;color:#fff!important}
.site-header #mainNav .nav-ticket .ticket-icon{width:18px!important;height:18px!important;filter:brightness(0) invert(1)!important}
.site-header .mobile-nav-btn{display:none!important;background:transparent!important;border:1px solid rgba(255,255,255,.25)!important;border-radius:10px!important;color:#fff!important;width:44px!important;height:44px!important;align-items:center!important;justify-content:center!important;font-size:22px!important;cursor:pointer!important}
@media(max-width:1050px){
 .site-header .nav{width:94%!important;gap:14px!important}
 .site-header .site-logo-img{height:54px!important;max-width:175px!important}
 .site-header #mainNav{gap:13px!important}
 .site-header #mainNav a{font-size:14px!important;font-weight:600!important}
 .site-header #mainNav .nav-book{min-width:118px!important;padding:0 14px!important}
}
@media(max-width:900px){
 .site-header{min-height:68px!important}
 .site-header .nav{width:92%!important;min-height:68px!important;height:68px!important;gap:12px!important}
 .site-header .site-logo-img{height:48px!important;max-width:165px!important}
 .site-header .mobile-nav-btn{display:inline-flex!important;flex:0 0 44px!important}
 .site-header #mainNav{display:none!important;position:absolute!important;left:-4.35%!important;right:-4.35%!important;top:68px!important;margin:0!important;padding:14px 5% 18px!important;flex-direction:column!important;align-items:stretch!important;gap:2px!important;background:rgba(10,13,17,.98)!important;border-top:1px solid rgba(255,255,255,.12)!important;border-bottom:2px solid #ed1c2e!important;box-shadow:0 18px 28px rgba(0,0,0,.28)!important;white-space:normal!important;max-height:calc(100vh - 68px)!important;overflow-y:auto!important}
 .site-header #mainNav.open{display:flex!important}
 .site-header #mainNav a{display:flex!important;width:100%!important;justify-content:flex-start!important;padding:13px 10px!important;font-size:14px!important;font-weight:600!important;border-radius:10px!important}
 .site-header #mainNav a:hover{background:rgba(255,255,255,.07)!important}
 .site-header #mainNav a:not(.nav-book):not(.nav-ticket)::after{display:none!important}
 .site-header #mainNav .nav-book{justify-content:center!important;margin:8px 0 0!important;min-height:46px!important;width:100%!important}
 .site-header #mainNav .nav-ticket{justify-content:center!important;margin:5px 0!important;width:100%!important}
}
@media(prefers-reduced-motion:reduce){.site-header #mainNav a,.site-header #mainNav a::after{transition:none!important}}
</style>

<?php if(setting('favicon','')):?><link rel="icon" href="<?=e(asset_url(setting('favicon','')))?>"><?php endif;?>
<script type="application/ld+json"><?=json_encode($schemaData,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script>
<!-- GLOBAL TYPOGRAPHY: Inter body, Rajdhani titles -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
<style id="global-site-typography">
html, body, body p, body li, body label, body input, body textarea, body select, body option, body td, body th, body small, body blockquote {font-family:"Inter",Arial,sans-serif!important;}
body h1,body h2,body h3,body h4,body h5,body h6,body .title,body .page-title,body .section-title {font-family:"Rajdhani",sans-serif!important;}
i.fa,i.fas,i.far,i.fab,.fa,.fas,.far,.fab,[class^="icon-"],[class*=" icon-"],.material-icons {font-family:"Font Awesome 6 Free","Font Awesome 6 Brands",sans-serif!important;}
</style>
</head>
<body>
<header class="site-header">
<div class="container nav">
<?php $siteLogo=setting('site_logo',setting('admin_logo','')); ?>
<a class="logo logo-image-only" href="<?=BASE_URL?>/" aria-label="<?=e($siteName)?>"><?php if($siteLogo): ?><img class="site-logo-img" src="<?=e(asset_url($siteLogo))?>" alt="" loading="eager" decoding="async" onerror="this.style.display='none'"><?php else: ?><span class="logo-badge">BT</span><?php endif; ?></a>
<button class="mobile-nav-btn" id="navBtn" aria-label="Open menu"><?=site_icon_html("header.mobile_menu","fa-solid fa-bars")?></button>
<?php bt_render_navigation('mainNav'); ?></div></header><main>
<script>
(function(){
 const btn=document.getElementById('navBtn');
 const nav=document.getElementById('mainNav');
 if(!btn||!nav)return;
 btn.setAttribute('aria-controls','mainNav');
 btn.setAttribute('aria-expanded','false');
 btn.addEventListener('click',function(){
   const opened=nav.classList.toggle('open');
   btn.setAttribute('aria-expanded',opened?'true':'false');
   btn.setAttribute('aria-label',opened?'Close menu':'Open menu');
 });
 nav.addEventListener('click',function(event){
   if(event.target.closest('a') && window.matchMedia('(max-width: 900px)').matches){
     nav.classList.remove('open');btn.setAttribute('aria-expanded','false');btn.setAttribute('aria-label','Open menu');
   }
 });
 document.addEventListener('keydown',function(event){if(event.key==='Escape'){nav.classList.remove('open');btn.setAttribute('aria-expanded','false');btn.setAttribute('aria-label','Open menu');}});
})();
</script>


<style>
html body { color: #808080; font-size: 14px; }
html body p, html body li, html body label, html body small, html body .text-muted, html body .description, html body .content-text { color: #808080; font-size: 14px; }
</style>
