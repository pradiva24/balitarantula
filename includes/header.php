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
// Ensure the configured Site Name occurs exactly once in browser and social titles,
// including when it appears inside a longer SEO title segment.
$normalizeBrandTitle = static function (string $value) use ($siteName): string {
    $value = trim($value);
    if ($siteName === '') return $value;

    $brandPattern = '~' . preg_quote($siteName, '~') . '~iu';
    $seen = 0;
    $value = preg_replace_callback($brandPattern, static function ($match) use (&$seen) {
        $seen++;
        return $seen === 1 ? $match[0] : '';
    }, $value) ?? $value;

    // Clean separators and whitespace left where duplicate brand occurrences were removed.
    $value = preg_replace('/\\s*(?:\\||–|—)\\s*(?=(?:\\||–|—|$))/u', '', $value) ?? $value;
    $value = preg_replace('/\\s{2,}/u', ' ', $value) ?? $value;
    $value = trim($value, " |–—");
    if ($value === '') return $siteName;

    // Add the Site Name only if it was not already present.
    if ($seen === 0) $value = rtrim($value, " |–—") . ' | ' . $siteName;
    return $value;
};
$title = $normalizeBrandTitle($title);
$ogTitle = $normalizeBrandTitle($ogTitle);
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
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css?v=<?=time()?>">\n<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/mobile-responsive.css?v=20261110-2">

<style id="bta-global-adventure-navigation-v2">
/* Shared Bali Tarantula header redesign. Scoped here so all pages using includes/header.php share it. */
body.menu-open{overflow:hidden!important}
.topline{height:30px!important;padding:0 max(24px,calc((100vw - 1180px)/2))!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:12px!important;background:#ed3833!important;color:#101010!important;font-size:9px!important;font-weight:800!important;letter-spacing:1.7px!important;line-height:1.2!important;position:relative!important;z-index:10000!important;text-transform:uppercase!important}
.topline .orange{color:#fff!important}
header.site-header{height:82px!important;min-height:82px!important;padding:0 max(24px,calc((100vw - 1180px)/2))!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:24px!important;position:absolute!important;top:30px!important;left:0!important;right:0!important;width:100%!important;z-index:9999!important;background:transparent!important;color:#fff!important;border:0!important;border-bottom:1px solid rgba(255,255,255,.17)!important;box-shadow:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important}
.site-header .brand{display:flex!important;align-items:center!important;gap:10px!important;min-width:0!important;flex:0 1 auto!important;color:#fff!important;text-decoration:none!important}
.site-header .site-brand-logo{display:block!important;width:auto!important;height:auto!important;max-width:105px!important;max-height:54px!important;object-fit:contain!important}
.site-header .brand-mark{display:block!important;color:#fff!important;font:900 34px/.9 "Barlow Condensed",Impact,sans-serif!important;letter-spacing:-2px!important;white-space:nowrap!important}
.site-header .brand-mark span{color:#ed3833!important}
.site-header .brand-copy{display:block!important;color:#fff!important;font:800 12px/1.2 "Inter",Arial,sans-serif!important;letter-spacing:1.2px!important;text-decoration:none!important;white-space:nowrap!important}
.site-header .brand-copy small{display:block!important;color:#ed3833!important;font-size:9px!important;letter-spacing:3.7px!important;margin-top:4px!important}
.site-header #mainNav.main-nav,.site-header #mainNav{margin-left:auto!important;display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:clamp(14px,2.3vw,34px)!important;position:static!important;inset:auto!important;width:auto!important;padding:0!important;background:transparent!important;background-color:transparent!important;border:0!important;box-shadow:none!important;flex-direction:row!important;white-space:nowrap!important}
.site-header #mainNav a{display:inline-flex!important;align-items:center!important;justify-content:center!important;position:relative!important;width:auto!important;margin:0!important;padding:10px 0!important;border:0!important;border-radius:0!important;background:transparent!important;color:#f3f3f3!important;text-decoration:none!important;font-family:"Inter",Arial,sans-serif!important;font-size:14px!important;font-weight:700!important;line-height:1.2!important;letter-spacing:.7px!important;transition:color .2s ease!important}
.site-header #mainNav a:hover,.site-header #mainNav a:focus-visible{color:#ed3833!important}
.site-header #mainNav a:not(.nav-book):not(.nav-ticket)::after{content:""!important;position:absolute!important;left:0!important;right:100%!important;bottom:2px!important;height:2px!important;background:#ed3833!important;transition:right .2s ease!important}
.site-header #mainNav a:not(.nav-book):not(.nav-ticket):hover::after,.site-header #mainNav a:not(.nav-book):not(.nav-ticket):focus-visible::after{right:0!important}
.site-header #mainNav .nav-book,.site-header #mainNav a.nav-book{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-width:0!important;min-height:44px!important;padding:0 17px!important;margin:0!important;background:#ed3833!important;color:#111!important;border:0!important;border-radius:0!important;font-size:13px!important;font-weight:900!important;letter-spacing:1px!important;white-space:nowrap!important}
.site-header #mainNav .nav-book:hover{background:#ff743f!important;color:#111!important;transform:translateY(-1px)!important}
.site-header #mainNav .nav-ticket{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:7px!important;min-height:40px!important;padding:8px 0!important;margin:0!important;border:0!important;border-radius:0!important;background:transparent!important;color:#f3f3f3!important;font-size:13px!important;font-weight:700!important}
.site-header #mainNav .nav-ticket:hover{background:transparent!important;color:#ed3833!important}
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

/* Mobile navigation: hide utility strip and keep the menu above every page section. */
@media(max-width:900px){
  .topline{display:none!important;height:0!important;min-height:0!important;visibility:hidden!important}
  header.site-header,
  header.site-header.is-scrolled{
    position:fixed!important;top:0!important;left:0!important;right:0!important;
    z-index:100000!important;height:70px!important;min-height:70px!important;
    background:rgba(12,12,12,.96)!important;
    -webkit-backdrop-filter:blur(14px)!important;backdrop-filter:blur(14px)!important;
    isolation:isolate!important;
  }
  .site-header #mainNav.main-nav,
  .site-header #mainNav{
    position:fixed!important;top:70px!important;left:0!important;right:0!important;bottom:0!important;
    width:100%!important;height:auto!important;max-height:calc(100dvh - 70px)!important;
    overflow-y:auto!important;overscroll-behavior:contain!important;
    z-index:100001!important;background:rgba(12,12,12,.99)!important;
    -webkit-backdrop-filter:blur(16px)!important;backdrop-filter:blur(16px)!important;
  }
  .site-header #mainNav.main-nav.open,
  .site-header #mainNav.open{display:flex!important}
}
@media(max-width:640px){
  header.site-header,header.site-header.is-scrolled{height:64px!important;min-height:64px!important}
  .site-header #mainNav.main-nav,
  .site-header #mainNav{top:64px!important;max-height:calc(100dvh - 64px)!important;padding:24px 22px 32px!important}
}

/* Mobile menu presentation: compact brand bar and full opaque navigation panel. */
@media(max-width:900px){
  header.site-header,header.site-header.is-scrolled{
    height:64px!important;min-height:64px!important;
    padding:0 16px!important;z-index:100000!important;
  }
  .site-header .menu-toggle{
    position:relative!important;z-index:100003!important;
    width:44px!important;height:44px!important;padding:9px!important;
  }
  .site-header #mainNav.main-nav,
  .site-header #mainNav{
    position:fixed!important;top:64px!important;left:0!important;right:0!important;bottom:0!important;
    width:100%!important;height:calc(100dvh - 64px)!important;
    max-height:calc(100dvh - 64px)!important;overflow-y:auto!important;
    display:none!important;flex-direction:column!important;align-items:stretch!important;
    justify-content:flex-start!important;gap:0!important;
    padding:26px 24px 40px!important;margin:0!important;
    background:#0b0b0b!important;background-color:#0b0b0b!important;
    opacity:1!important;z-index:100002!important;
    border-top:1px solid rgba(255,255,255,.16)!important;
    box-shadow:none!important;isolation:isolate!important;
  }
  .site-header #mainNav.main-nav.open,
  .site-header #mainNav.open{display:flex!important}
  .site-header #mainNav a{
    position:relative!important;display:flex!important;align-items:center!important;
    justify-content:flex-start!important;width:100%!important;min-height:56px!important;
    padding:16px 0!important;margin:0!important;
    color:#f5f5f5!important;background:transparent!important;
    border:0!important;border-bottom:1px solid rgba(255,255,255,.10)!important;
    border-radius:0!important;text-align:left!important;text-transform:uppercase!important;
    font:800 27px/1.05 "Barlow Condensed",Impact,sans-serif!important;
    letter-spacing:.7px!important;white-space:normal!important;
  }
  .site-header #mainNav a:hover,
  .site-header #mainNav a:focus-visible{color:#ed3833!important}
  .site-header #mainNav a[aria-current="page"],
  .site-header #mainNav a.active,
  .site-header #mainNav a.current{
    color:#ed3833!important;padding-left:34px!important;
  }
  .site-header #mainNav a[aria-current="page"]::before,
  .site-header #mainNav a.active::before,
  .site-header #mainNav a.current::before{
    content:""!important;position:absolute!important;left:0!important;top:50%!important;
    width:24px!important;height:2px!important;background:#ed3833!important;
    transform:translateY(-50%)!important;
  }
  .site-header #mainNav a:not(.nav-book):not(.nav-ticket)::after{display:none!important}
  .site-header #mainNav .nav-book,
  .site-header #mainNav a.nav-book{
    justify-content:center!important;width:100%!important;min-height:50px!important;
    margin:22px 0 0!important;padding:15px!important;
    color:#111!important;background:#ed3833!important;border:0!important;
    font:900 12px/1.2 "Inter",Arial,sans-serif!important;letter-spacing:1px!important;
  }
  .site-header #mainNav .nav-ticket{
    justify-content:center!important;width:100%!important;margin:10px 0 0!important;
    padding:14px!important;border:1px solid rgba(255,255,255,.25)!important;
  }
}
@media(max-width:640px){
  header.site-header,header.site-header.is-scrolled{
    height:58px!important;min-height:58px!important;padding:0 14px!important;
  }
  .site-header .site-brand-logo{max-width:88px!important;max-height:42px!important}
  .site-header .menu-toggle{width:42px!important;height:42px!important}
  .site-header #mainNav.main-nav,
  .site-header #mainNav{
    top:58px!important;height:calc(100dvh - 58px)!important;
    max-height:calc(100dvh - 58px)!important;padding:24px 14px 36px!important;
  }
  .site-header #mainNav a{font-size:28px!important;min-height:58px!important}
}

/* Check Ticket navigation button: white outline, red hover for border, label and icon. */
.site-header #mainNav .nav-ticket,
.site-header #mainNav a.nav-ticket{
  display:inline-flex!important;align-items:center!important;justify-content:center!important;
  gap:8px!important;min-height:42px!important;padding:9px 14px!important;
  border:2px solid #fff!important;border-radius:0!important;
  background:transparent!important;color:#fff!important;
  transition:color .2s ease,border-color .2s ease,background .2s ease!important;
}
.site-header #mainNav .nav-ticket .ticket-icon,
.site-header #mainNav a.nav-ticket .ticket-icon{
  filter:brightness(0) invert(1)!important;
  transition:filter .2s ease!important;
}
.site-header #mainNav .nav-ticket:hover,
.site-header #mainNav .nav-ticket:focus-visible,
.site-header #mainNav a.nav-ticket:hover,
.site-header #mainNav a.nav-ticket:focus-visible{
  border-color:#ed3833!important;color:#ed3833!important;
  background:transparent!important;outline:none!important;
}
.site-header #mainNav .nav-ticket:hover .ticket-icon,
.site-header #mainNav .nav-ticket:focus-visible .ticket-icon,
.site-header #mainNav a.nav-ticket:hover .ticket-icon,
.site-header #mainNav a.nav-ticket:focus-visible .ticket-icon{
  filter:brightness(0) saturate(100%) invert(29%) sepia(91%) saturate(3452%) hue-rotate(340deg) brightness(95%) contrast(91%)!important;
}
@media(max-width:900px){
  .site-header #mainNav .nav-ticket,
  .site-header #mainNav a.nav-ticket{
    width:100%!important;min-height:52px!important;margin:12px 0 0!important;
    padding:14px!important;border:2px solid #fff!important;
    justify-content:center!important;color:#fff!important;
  }
}
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
<style id="bta-ui-polish">
/* Bali Tarantula Adventure — global UI polish; presentation only */
:root{
  --bta-bg:#101010;--bta-surface:#181818;--bta-surface-2:#202020;
  --bta-accent:#ed3833;--bta-text:#f7f5f0;--bta-muted:#a7a7a7;
  --bta-border:rgba(255,255,255,.12);--bta-radius:3px;
}
html{scroll-behavior:smooth;scroll-padding-top:90px}
body{background:var(--bta-bg);color:var(--bta-text);-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
::selection{background:var(--bta-accent);color:#fff}
a,button,input,select,textarea{ -webkit-tap-highlight-color:transparent }
a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,[tabindex]:focus-visible{
  outline:2px solid var(--bta-accent)!important;outline-offset:3px!important
}
/* Header: cleaner separation and dependable sticky visual state */
.site-header{z-index:100000;transition:background .25s ease,box-shadow .25s ease,border-color .25s ease}
.site-header.is-scrolled{background:rgba(12,12,12,.96)!important;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:0 8px 30px rgba(0,0,0,.22);border-bottom:1px solid var(--bta-border)}
.site-header .main-nav a{transition:color .2s ease,background .2s ease,border-color .2s ease,transform .2s ease}
.site-header .main-nav a:hover{color:var(--bta-accent)}
.site-header .nav-book,.site-header .nav-cta{background:var(--bta-accent)!important;color:#111!important;border:1px solid var(--bta-accent)!important;transition:background .2s ease,transform .2s ease!important}
.site-header .nav-book:hover,.site-header .nav-cta:hover{background:#ff514b!important;transform:translateY(-2px)}
/* Consistent page rhythm and readable content width */
main{min-width:0;overflow:clip}
main section{scroll-margin-top:90px}
.wrap{width:min(1180px,calc(100% - 48px));margin-inline:auto}
.page-banner{background-color:#171717;background-blend-mode:normal}
.page-banner-content h1,.section-heading h2,.inner-title{letter-spacing:.01em;text-wrap:balance}
.page-banner-content>p:not(.eyebrow),.section-description,.inner-copy p,.article-main p{max-width:68ch}
.eyebrow{letter-spacing:.16em}
.section-heading{gap:28px}
.section-heading .section-description{line-height:1.85}
/* Cards: image-led, aligned, with subtle motion */
.adventure-card,.track-card,.blog-card,.review-card,.package-card,.gallery-grid a{
  border-radius:var(--bta-radius);isolation:isolate
}
.adventure-card,.track-card,.blog-card{box-shadow:0 12px 34px rgba(0,0,0,.16)}
.adventure-card img,.track-card img,.blog-image img,.gallery-grid img{
  backface-visibility:hidden;transition:transform .55s cubic-bezier(.2,.7,.2,1),filter .35s ease
}
.adventure-card:hover img,.track-card:hover img,.blog-image:hover img,.gallery-grid a:hover img{transform:scale(1.045)}
.card-content p,.track-desc,.blog-copy>p:not(.blog-meta){line-height:1.8}
.card-link,.track-meta,.blog-meta{letter-spacing:.08em}
.button,.hero-actions .button,.nav-cta,.nav-book{
  min-height:48px;font-weight:800;letter-spacing:.07em
}
.button-orange,.button-dark,.button-outline,.nav-cta,.nav-book{
  transition:transform .2s ease,background .2s ease,color .2s ease,border-color .2s ease
}
.button-orange:hover,.button-dark:hover,.button-outline:hover{transform:translateY(-2px)}
/* Form controls: stronger hierarchy and clear focus */
input,select,textarea{max-width:100%;border-radius:2px}
input:not([type=checkbox]):not([type=radio]),select,textarea{
  min-height:44px
}
label{line-height:1.5}
button:disabled,.button[aria-disabled=true]{opacity:.55;cursor:not-allowed}
/* Image and content blocks */
img{height:auto}
.article-cover,.package-cover{border-radius:2px}
.article-main{min-width:0}
.article-main p{line-height:1.9}
.detail-block{padding-top:20px}
.detail-block ul,.booking-panel ul{line-height:1.9}
.booking-panel,.article-sidebar,.contact-form{box-shadow:0 14px 36px rgba(0,0,0,.18)}
/* Reviews, stats and gallery should feel like one design system */
.reviews-section,.home-gallery-section,.stats-strip{position:relative}
.review-card{background:rgba(24,24,24,.94);border:1px solid var(--bta-border)}
.gallery-grid a{background:var(--bta-surface);overflow:hidden}
.gallery-grid a:focus-visible{outline-offset:4px}
.site-footer{border-top:1px solid var(--bta-border)}
/* Responsive spacing and typography */
@media(max-width:850px){
  .wrap{width:calc(100% - 40px)}
  .section-heading{gap:18px}
}
@media(max-width:640px){
  html{scroll-padding-top:70px}
  main section{scroll-margin-top:70px}
  .wrap{width:calc(100% - 36px)}
  .section-heading{margin-bottom:26px}
  .page-banner{padding-top:132px}
  .page-banner-content>p:not(.eyebrow){font-size:12px;line-height:1.8}
  .button,.hero-actions .button{min-height:48px}
  .article-main p{font-size:13px;line-height:1.85}
  .contact-form,.article-sidebar,.booking-panel{padding:20px}
}
@media(prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  *,*::before,*::after{scroll-behavior:auto!important;transition-duration:.01ms!important;animation-duration:.01ms!important;animation-iteration-count:1!important}
}
</style>
</head>
<body>
<div class="topline"><span>BALI TARANTULA ADVENTURE</span><span>BALI, INDONESIA <span class="orange">●</span></span></div>
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



<!-- Removed legacy global text-color overrides: page components now control contrast on light and dark surfaces. -->
