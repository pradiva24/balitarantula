<?php
declare(strict_types=1);

require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/functions.php';

$seo = db()->prepare("SELECT * FROM seo_meta WHERE page_key=? LIMIT 1");
$seo->execute(['home']);
$seo = $seo->fetch() ?: [];

$title = $seo['seo_title'] ?? 'Bali Tarantula Adventure | ATV, Rafting, Swing & Bali Experiences';
$description = $seo['meta_description'] ?? 'Discover Bali Tarantula Adventure with ATV, Ayung River Rafting, Bali Swing and exciting Bali adventure packages.';
$canonical = BASE_URL.'/';
$reviewsBg = 'assets/images/reviews-bg.jpg';
try {
    $reviewsBgSetting = trim((string)setting('homepage_reviews_bg', 'assets/images/reviews-bg.jpg'));
    if ($reviewsBgSetting !== '') $reviewsBg = $reviewsBgSetting;
    else $reviewsBg = '';
} catch (Throwable $e) { /* keep the default background */ }

$home = [];
try {
    $home = db()->query("SELECT * FROM homepage_content WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $home = [];
}


$packages = db()->query("
    SELECT p.*, c.name category_name
    FROM packages p
    LEFT JOIN categories c ON c.id=p.category_id
    WHERE p.status=1
    ORDER BY p.homepage_order ASC, p.id ASC
    LIMIT 6
")->fetchAll();

// Optional original prices are managed in Admin > Packages. Keep the homepage
// compatible with installations where the optional pricing table is absent.
$packageOriginalPrices = [];
try {
    $priceRows = db()->query("SELECT package_id, original_price FROM package_price_settings")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($priceRows as $priceRow) {
        if (isset($priceRow['package_id']) && $priceRow['original_price'] !== null && $priceRow['original_price'] !== '') {
            $packageOriginalPrices[(int)$priceRow['package_id']] = (float)$priceRow['original_price'];
        }
    }
} catch (Throwable $e) {
    $packageOriginalPrices = [];
}

// Homepage adventure tracks are managed separately from booking packages.
$tracks = [];
try {
    $tracks = db()->query("SELECT * FROM adventure_tracks WHERE status=1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $tracks = [];
}

$reviews = db()->query("
    SELECT * FROM reviews
    WHERE status=1
    ORDER BY featured DESC, review_date DESC, id DESC
    LIMIT 6
")->fetchAll();

$gallery = [];
try {
    $gallery = db()->query("
        SELECT * FROM gallery
        ORDER BY sort_order ASC, id DESC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $gallery = [];
}

$heroImage = '';
foreach ($gallery as $g) {
    if (!empty($g['image_path'])) {
        $heroImage = $g['image_path'];
        break;
    }
}
if (!$heroImage) {
    foreach ($packages as $p) {
        if (!empty($p['cover_image'])) {
            $heroImage = $p['cover_image'];
            break;
        }
    }
}

$heroPath = !empty($home['hero_image']) ? $home['hero_image'] : $heroImage;
$heroUrl = $heroPath ? asset_url($heroPath) : '';

/*
 * Hero media:
 * - Admin → Homepage → Hero Media is the primary source.
 * - Image Slider: uses up to 5 images selected in the dashboard.
 * - Background Video: uses the uploaded video + optional poster.
 * - Legacy settings keys remain supported for compatibility.
 */
$heroMediaConfig = json_decode((string)setting('homepage_hero_media',''), true);
if (!is_array($heroMediaConfig)) $heroMediaConfig = [];

$heroMediaMode = in_array(($heroMediaConfig['mode'] ?? ''), ['slider','video'], true)
    ? $heroMediaConfig['mode']
    : strtolower((string)setting('homepage_hero_media_mode', 'slider'));

if (!in_array($heroMediaMode, ['slider','video'], true)) $heroMediaMode = 'slider';

$heroVideoPath = trim((string)($heroMediaConfig['video'] ?? setting('homepage_hero_video', '')));
$heroVideo = $heroVideoPath ? asset_url($heroVideoPath) : '';
$heroPosterPath = trim((string)($heroMediaConfig['poster'] ?? ''));
$heroPosterUrl = $heroPosterPath ? asset_url($heroPosterPath) : $heroUrl;
$heroSlides = [];

$configuredSlides = $heroMediaConfig['slides'] ?? [];
if (is_array($configuredSlides)) {
    foreach ($configuredSlides as $path) {
        $path = trim((string)$path);
        if (!$path) continue;
        $heroSlides[] = [
            'url' => asset_url($path),
            'alt' => !empty($home['hero_alt']) ? $home['hero_alt'] : 'Bali Tarantula Adventure'
        ];
        if (count($heroSlides) >= 5) break;
    }
}

/* Preserve the existing fallback when the dashboard has no dedicated slide images. */
if (!$heroSlides && $heroPath) {
    $heroSlides[] = [
        'url' => asset_url($heroPath),
        'alt' => !empty($home['hero_alt']) ? $home['hero_alt'] : 'Bali Tarantula Adventure'
    ];
}
if (!$heroMediaConfig && !$heroSlides) {
    foreach ($gallery as $g) {
        $path = trim((string)($g['image_path'] ?? ''));
        if (!$path) continue;
        $url = asset_url($path);
        $duplicate = false;
        foreach ($heroSlides as $slide) {
            if ($slide['url'] === $url) { $duplicate = true; break; }
        }
        if (!$duplicate) {
            $heroSlides[] = [
                'url' => $url,
                'alt' => !empty($g['alt_text']) ? $g['alt_text'] : 'Bali adventure experience'
            ];
        }
        if (count($heroSlides) >= 5) break;
    }
}
?>
<?php
/* Render the existing shared header unchanged, adding only the new design's utility strip and fonts. */
ob_start();
require __DIR__.'/includes/header.php';
$btaHeaderMarkup = (string)ob_get_clean();
$btaFontLinks = '<link rel="preconnect" href="https://fonts.googleapis.com">'.
    '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'.
    '<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
$btaHeaderMarkup = str_replace('</head>', $btaFontLinks.'</head>', $btaHeaderMarkup);
/* Use the shared header as-is. The header component owns its topline and navigation. */
echo $btaHeaderMarkup;
?>
<style id="bta-new-design-css">
:root{--black:#101010;--panel:#181818;--orange:#ff5a1f;--red:#e52232;--white:#f7f5f0;--muted:#a7a7a7;--line:#343434;--display:"Barlow Condensed",Impact,sans-serif;--body:"Inter",Arial,sans-serif}*{box-sizing:border-box}html{scroll-behavior:smooth;scroll-padding-top:90px}body{margin:0;background:var(--black);color:var(--white);font-family:var(--body);line-height:1.6}body.menu-open{overflow:hidden}a{color:inherit;text-decoration:none}img{display:block;max-width:100%}.wrap{width:min(1180px,calc(100% - 48px));margin-inline:auto}.orange{color:var(--orange)}.topline{height:30px;padding:0 max(24px,calc((100vw - 1180px)/2));display:flex;align-items:center;justify-content:space-between;background:var(--orange);color:#101010;font-size:9px;font-weight:800;letter-spacing:1.7px}.brand{display:flex;align-items:center;gap:10px}.brand-mark{font:900 34px/.9 var(--display);letter-spacing:-2px;color:var(--white)}.brand-mark span{color:var(--orange)}.brand-copy{font:800 12px/1.2 var(--body);letter-spacing:1.2px}.brand-copy small{display:block;color:var(--orange);font-size:9px;letter-spacing:3.7px;margin-top:4px}.main-nav{display:flex;align-items:center;gap:34px;font-size:11px;font-weight:700;letter-spacing:.7px}.main-nav>a:not(.nav-cta){color:#e0e0e0;transition:color .2s}.main-nav>a:not(.nav-cta):hover{color:var(--orange)}.nav-cta{padding:13px 17px;background:var(--orange);color:#111}.nav-cta span,.button span{margin-left:14px}.menu-toggle{display:none}.hero{position:relative;min-height:690px;height:min(850px,100vh);display:flex;align-items:center;overflow:hidden;background:#222}.hero-image,.hero-shade{position:absolute;inset:0}.hero-image{background-image:url("https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=2200&q=90");background-size:cover;background-position:center 48%;transform:scale(1.02)}.hero-shade{background:linear-gradient(90deg,rgba(8,8,8,.88) 0%,rgba(8,8,8,.58) 46%,rgba(8,8,8,.1) 100%),linear-gradient(0deg,rgba(8,8,8,.68),transparent 35%)}.hero-content{position:relative;z-index:1;padding-top:90px}.eyebrow{display:flex;align-items:center;gap:11px;margin:0 0 18px;font-size:13px; text-transform:uppercase;font-weight:800;letter-spacing:2px;color:#e4e4e4}.eyebrow-line{width:27px;height:2px;background:var(--orange);display:inline-block}.hero h1,.section-heading h2,.manifesto h2,.cta-inner h2{font:900 clamp(64px,9.5vw,130px)/.82 var(--display);letter-spacing:-1px;margin:0;text-transform:uppercase}.hero h1 span,.section-heading h2 span,.manifesto h2 span,.cta-inner h2 span{color:var(--orange)}.hero-text{max-width:445px;margin:27px 0 28px;color:#d0d0d0;font-size:14px;line-height:1.9}.hero-actions{display:flex;align-items:center;gap:20px;flex-wrap:wrap}.hero-actions .button{min-height:50px;padding:0 24px;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:900;letter-spacing:1px;transition:transform .2s,background .2s,border-color .2s,color .2s;box-sizing:border-box}.hero-actions .button:hover{transform:translateY(-3px)}.hero-actions .button-orange{background:var(--orange);color:#111;border:none!important}.hero-actions .button-orange:hover{background:#ff743f;border-color:#ffffff !important;color:#111}.hero-actions .button-outline,.hero-actions .text-link{min-height:50px;padding:0 22px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #ffffff !important;color:#ffffff !important;background:rgba(0,0,0,.35);font-size:13px;font-weight:900;letter-spacing:1px;box-sizing:border-box;transition:transform .2s,background .2s,border-color .2s,color .2s}.hero-actions .button-outline:hover,.hero-actions .text-link:hover{background:#ffffff !important;color:#111111 !important;border-color:#ffffff !important;transform:translateY(-3px)}.hero-actions .button span{margin-left:12px;display:inline-flex;align-items:center}.button{min-height:50px;padding:0 21px;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:900;letter-spacing:1px;transition:transform .2s,background .2s,border-color .2s}.button:hover{transform:translateY(-3px)}.button-orange{background:var(--orange);color:#111}.button-orange:hover{background:#ff743f}.text-link{font-size:9px;font-weight:800;letter-spacing:1px;display:flex;align-items:center;gap:10px}.play-icon{width:27px;height:27px;border-radius:50%;display:grid;place-items:center;font-size:8px;padding-left:2px}.hero-bottom{position:absolute;top:calc(100vh - 160px);left:0;right:0;display:flex;justify-content:space-between;align-items:center;font-size:9px;font-weight:700;letter-spacing:1.5px;color:#dedede}.hero-bottom b{color:var(--orange)}.scroll-note i{font-style:normal;font-size:17px;color:var(--orange);margin-left:10px}.hero-stamp{position:absolute;right:max(28px,calc((100vw - 1180px)/2));bottom:110px;width:124px;height:124px;border:1px solid #ffffff70;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;transform:rotate(9deg);font:900 28px/.83 var(--display)}.hero-stamp span:nth-child(2){color:var(--orange)}.hero-stamp small{font:700 5px var(--body);letter-spacing:1px;margin-top:9px}.intro-strip{background:var(--orange);color:#111}.strip-inner{min-height:85px;display:flex;align-items:center;justify-content:space-between;gap:20px}.strip-label{font-size:10px;font-weight:900;letter-spacing:2px}.strip-inner p{font:800 clamp(19px,3vw,31px)/1 var(--display);letter-spacing:.6px;margin:0}.strip-inner strong{color:#fff}.strip-symbol{font-size:30px}.section{padding-top:110px;padding-bottom:100px}.section-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:32px;margin-bottom:39px}.section-heading h2{font-size:clamp(50px,7vw,82px);line-height:.9}.section-heading .eyebrow{margin-bottom:15px}.section-description{max-width:330px;color:var(--muted);font-size:12px;line-height:1.9;margin:0 0 3px}.adventure-grid{display:grid;grid-template-columns:1.15fr 1fr 1fr;gap:14px}.adventure-card{position:relative;min-height:440px;overflow:hidden;background:#252525}.adventure-card img{position:absolute;width:100%;height:100%;object-fit:cover;transition:transform .7s}.adventure-card:hover img{transform:scale(1.07)}.card-shade{position:absolute;inset:0;background:linear-gradient(0deg,rgba(0,0,0,.93),rgba(0,0,0,.12) 75%)}.card-number{position:absolute;top:20px;left:20px;font-size:9px;font-weight:800;letter-spacing:1.6px}.card-content{position:absolute;bottom:0;left:0;right:0;padding:26px 23px}.card-tag{font-size:8px;font-weight:800;letter-spacing:1.8px;color:var(--orange)}.card-content h3{font:900 clamp(34px,4vw,49px)/.88 var(--display);margin:10px 0 13px;letter-spacing:.2px}.card-content p{max-width:260px;color:#c7c7c7;font-size:11px;line-height:1.8;margin:0 0 18px}.card-link{display:flex;justify-content:space-between;align-items:center;padding-top:13px;border-top:1px solid #ffffff55;font-size:9px;font-weight:900;letter-spacing:1.2px}.card-link span{color:var(--orange);font-size:16px}.section-foot{display:flex;align-items:center;gap:16px;margin-top:22px;font-size:8px;font-weight:800;letter-spacing:1.4px;color:#888}.foot-line{height:1px;background:#3b3b3b;flex:1}.manifesto{position:relative;min-height:600px;display:flex;align-items:center;overflow:hidden;background:#1a1a1a}.manifesto-image,.manifesto-overlay{position:absolute;inset:0}.manifesto-image{background:url("https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=2000&q=85") center/cover}.manifesto-overlay{background:linear-gradient(90deg,rgba(10,10,10,.94),rgba(10,10,10,.7) 55%,rgba(10,10,10,.18))}.manifesto-content{position:relative;z-index:1;padding-top:90px;padding-bottom:90px}.manifesto h2{font-size:clamp(57px,8vw,98px);line-height:.87}.manifesto-content>p:not(.eyebrow){max-width:470px;color:#c4c4c4;font-size:12px;line-height:1.9;margin:24px 0}.button-outline{border:1px solid #ffffff80}.button-outline:hover{background:#fff;color:#111}.manifesto-side{position:absolute;right:24px;top:50%;writing-mode:vertical-rl;transform:translateY(-50%);font-size:8px;letter-spacing:2px;color:#ddd}.why-us{padding-bottom:115px}.why-us .section-heading .eyebrow{color:#111!important}.reasons{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-top:1px solid var(--line)}.reasons article{position:relative;padding:30px 27px 0 0;min-height:205px}.reasons article+article{padding-left:27px;border-left:1px solid var(--line)}.reason-no{position:absolute;top:29px;right:20px;font-size:9px;color:#777;letter-spacing:1px}.reason-icon{color:var(--orange);font-size:26px;margin-bottom:16px}.reasons h3{font:800 24px/1 var(--display);margin:0 0 12px}.reasons p{max-width:270px;font-size:11px;line-height:1.8;color:var(--muted);margin:0}.booking-cta{position:relative;isolation:isolate;overflow:hidden;background-color:var(--orange);color:#111;text-align:center;padding:88px 24px 95px}.booking-cta::before{content:"";position:absolute;inset:0;z-index:-1;background:rgba(255,90,31,.58);pointer-events:none}.booking-cta[style*="background-image"]{background-color:#ff5a1f}.booking-cta[style*="background-image"]::before{background:rgba(255,90,31,.62)}.cta-inner{position:relative}.cta-inner .eyebrow{justify-content:center;color:#222}.cta-inner .eyebrow-line{background:#111}.cta-inner h2{font-size:clamp(66px,10vw,115px);line-height:.82}.cta-inner h2 span{color:#fff}.cta-inner>p:not(.eyebrow){font-size:12px;margin:23px 0}.button-dark{background:#111;color:#fff}.button-dark:hover{background:#303030}.cta-inner>small{display:block;margin-top:12px;font-size:8px;letter-spacing:1px;opacity:.7}.site-footer{background:#101010}.footer-main{display:flex;align-items:center;justify-content:space-between;gap:25px;padding-top:35px;padding-bottom:35px}.footer-main>p{font:800 16px/1.5 var(--display);letter-spacing:.7px;margin:0}.footer-main>p span{font-size:10px;color:#888;letter-spacing:1px}.back-top{font-size:9px;font-weight:800;letter-spacing:1px}.back-top:hover{color:var(--orange)}.footer-bottom{border-top:1px solid var(--line);padding-top:15px;padding-bottom:15px;display:flex;justify-content:space-between;color:#777;font-size:8px;font-weight:700;letter-spacing:1.2px}
@media(min-width:1500px){.hero{height:850px}}@media(max-width:850px){.main-nav{gap:18px}.adventure-grid{grid-template-columns:1fr 1fr}.adventure-card:first-child{grid-column:span 2;min-height:420px}.adventure-card{min-height:390px}.section-heading{align-items:flex-start;flex-direction:column}.section-description{max-width:500px}.hero-stamp{width:100px;height:100px;right:30px;bottom:95px}.hero-stamp{font-size:23px}}@media(max-width:640px){.wrap{width:calc(100% - 36px)}.topline{padding:0 18px;font-size:8px}.site-header{height:70px;padding:0 18px}.brand-mark{font-size:30px}.brand-copy{font-size:10px}.brand-copy small{font-size:8px}.menu-toggle{display:flex;flex-direction:column;gap:5px;position:relative;z-index:12;background:transparent;border:0;padding:10px}.menu-toggle span{display:block;width:25px;height:2px;background:#fff;transition:transform .2s}.menu-toggle[aria-expanded=true] span:first-child{transform:translateY(7px) rotate(45deg)}.menu-toggle[aria-expanded=true] span:nth-child(2){opacity:0}.menu-toggle[aria-expanded=true] span:last-child{transform:translateY(-7px) rotate(-45deg)}.main-nav{display:none;position:fixed;inset:100px 0 0;background:#111;z-index:11;flex-direction:column;align-items:stretch;justify-content:flex-start;padding:35px 25px;gap:0}.main-nav.open{display:flex}.main-nav>a:not(.nav-cta){font:800 29px/1 var(--display);padding:18px 0;border-bottom:1px solid #333}.nav-cta{margin-top:25px;text-align:center;padding:17px}.hero{height:760px;min-height:680px;max-height:100svh;align-items:center}.hero-image{background-position:57% center}.hero-shade{background:linear-gradient(90deg,rgba(8,8,8,.83),rgba(8,8,8,.35)),linear-gradient(0deg,rgba(8,8,8,.65),transparent 45%)}.hero-content{padding-top:85px}.hero h1{font-size:clamp(67px,17vw,105px)}.hero-text{font-size:12px;max-width:340px}.hero-actions{align-items:flex-start;flex-direction:column;gap:18px}.hero-bottom{top:auto;bottom:24px}.scroll-note{display:none}.hero-stamp{right:20px;bottom:84px;width:77px;height:77px;font-size:18px}.hero-stamp small{font-size:4px}.strip-inner{min-height:72px;gap:10px}.strip-label{font-size:8px;letter-spacing:1px}.strip-inner p{font-size:20px;max-width:230px;line-height:1.15}.strip-symbol{font-size:24px}.section{padding-top:75px;padding-bottom:75px}.section-heading h2{font-size:clamp(49px,12vw,68px)}.section-heading{gap:18px;margin-bottom:27px}.adventure-grid{grid-template-columns:1fr;gap:12px}.adventure-card:first-child{grid-column:auto;min-height:430px}.adventure-card{min-height:400px}.card-content{padding:25px 20px}.card-content h3{font-size:47px}.manifesto{min-height:600px}.manifesto-content{padding-top:75px;padding-bottom:75px}.manifesto h2{font-size:clamp(55px,13vw,78px)}.manifesto-content>p:not(.eyebrow){font-size:11px}.manifesto-side{display:none}.reasons{grid-template-columns:1fr}.reasons article,.reasons article+article{min-height:auto;padding:25px 35px 25px 0;border-left:0;border-bottom:1px solid var(--line)}.reason-no{top:28px;right:5px}.reason-icon{margin-bottom:10px}.reasons h3{font-size:25px}.reasons p{max-width:350px}.booking-cta{padding:72px 18px 78px}.cta-inner h2{font-size:clamp(65px,17vw,94px)}.footer-main{align-items:flex-start;flex-wrap:wrap}.footer-main>p{order:3;width:100%;font-size:14px}.back-top{margin-left:auto;align-self:center}.footer-bottom{gap:15px;font-size:7px;letter-spacing:.6px}}.booking-cta .cta-inner, .booking-cta .cta-inner .eyebrow, .booking-cta .cta-inner h2, .booking-cta .cta-inner h2 span, .booking-cta .cta-inner>p:not(.eyebrow), .booking-cta .cta-inner>small, .booking-cta .cta-inner .button, .booking-cta .cta-inner .button span, .booking-cta .cta-inner .button i{color:#fff!important}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*::before,*::after{transition:none!important;animation:none!important}}

/* ATV track collection — horizontal slider with difficulty indicators */
.tracks-section{background:linear-gradient(180deg,#141414 0%,#0c0c0c 100%);position:relative}
.tracks-section:before{content:"";position:absolute;left:0;top:0;width:100%;height:4px;background:linear-gradient(90deg,var(--orange) 0 45%,var(--red) 45% 70%,#111 70%)}
.tracks-section .section-heading h2{color:#fff}.tracks-section .section-heading h2 span.orange{color:var(--orange)!important}
.tracks-section .section-heading .section-description{color:#fff}
.tracks-grid{display:flex;gap:13px;overflow-x:auto;overflow-y:hidden;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding:0 0 5px;scrollbar-width:none;-ms-overflow-style:none}
.tracks-grid::-webkit-scrollbar{display:none}
.track-card{position:relative;flex:0 0 calc((100% - 39px)/4);min-width:0;min-height:430px;overflow:hidden;background:#191919;border-bottom:4px solid var(--orange);isolation:isolate;scroll-snap-align:start}
.track-card img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2;filter:saturate(.75);transition:transform .65s,filter .4s}
.track-card:hover img{transform:scale(1.07);filter:saturate(1.1)}
.track-overlay{position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,rgba(0,0,0,.35),rgba(0,0,0,.22) 25%,rgba(0,0,0,.94) 100%)}
.track-index{position:absolute;left:18px;top:18px;font-size:9px;font-weight:900;letter-spacing:1.8px}
.track-badge{position:absolute;right:12px;top:15px;display:inline-flex;align-items:center;gap:7px;border-radius:50px;color:#fff;padding:7px 11px;font-size:8px;font-weight:900;letter-spacing:.8px;white-space:nowrap}
.track-badge:before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;flex:0 0 6px;animation:track-dot-pulse 1.25s ease-in-out infinite}
.track-difficulty-easy{background:#21854b}
.track-difficulty-moderate{background:#b58a14}
.track-difficulty-challenging{background:#d95b20}
.track-difficulty-extreme{background:#c92535}
@keyframes track-dot-pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.25;transform:scale(.72)}}
.track-info{position:absolute;left:19px;right:19px;bottom:18px}
.track-info>p{font-size:8px;letter-spacing:1.7px;font-weight:900;color:var(--orange);margin:0 0 9px}
.track-info h3{font:900 clamp(29px,3vw,41px)/.9 var(--display);margin:0 0 13px;letter-spacing:.2px}
.track-desc{display:block;min-height:54px;color:#d0d0d0;font-size:10px;line-height:1.7}
.track-meta{display:flex;align-items:center;gap:18px;margin-top:14px;padding-top:12px;border-top:1px solid #ffffff55;color:#fff;font-size:10px;font-weight:800;letter-spacing:.4px}
.track-meta-item{display:inline-flex;align-items:center;gap:7px;min-width:0}
.track-meta-item i{font-size:13px;color:var(--orange)}
.track-meta-distance{gap:6px}
.tracks-slider-progress{height:3px;width:100%;margin-top:17px;background:#ffffff26;overflow:hidden}
.tracks-slider-progress-bar{height:100%;width:0;background:#e52232;transition:width .12s linear}
.tracks-note{display:flex;align-items:center;gap:12px;margin-top:20px;padding:14px 16px;background:#191919;border-left:3px solid var(--red)}
.tracks-note-mark{display:grid;place-items:center;flex:0 0 22px;height:22px;background:var(--red);color:white;font-weight:900;font-size:12px}
.tracks-note p{font-size:10px;color:#aaa;margin:0;line-height:1.7}
@media(max-width:1000px){.track-card{flex-basis:calc((100% - 13px)/2);min-height:410px}.track-info h3{font-size:42px}}
@media(max-width:640px){.track-card{flex-basis:86%;min-height:420px}.track-info{left:22px;right:22px;bottom:22px}.track-info h3{font-size:47px}.track-desc{min-height:0}.tracks-note{align-items:flex-start}}
@media(prefers-reduced-motion:reduce){.track-badge:before{animation:none}.tracks-grid{scroll-behavior:auto}}

/* Interior pages shared layout */
.page-banner{position:relative;min-height:390px;padding:170px 0 45px;background:linear-gradient(100deg,rgba(8,8,8,.96),rgba(8,8,8,.64)),url("https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=1800&q=85") center 45%/cover;border-bottom:3px solid var(--red)}
.page-banner-content h1{font:900 clamp(54px,8vw,96px)/.88 var(--display);margin:0;text-transform:uppercase;max-width:900px}
.page-banner-content h1 span{color:var(--orange)}
.page-banner-content>p:not(.eyebrow){max-width:570px;color:#ccc;font-size:13px;line-height:1.8;margin:20px 0}
.breadcrumbs{margin-top:27px;font-size:9px;letter-spacing:1.5px;font-weight:800;color:#aaa}.breadcrumbs a{color:var(--orange)}
.inner-split{display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:start}
.inner-title{font:900 clamp(45px,6vw,72px)/.9 var(--display);margin:0 0 22px}.inner-title span{color:var(--orange)}
.inner-copy p,.package-detail-main p,.article-main p{font-size:13px;color:#c3c3c3;line-height:1.95}
.inner-copy .button{margin-top:15px}
.blog-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.blog-card{background:#181818;border-bottom:3px solid var(--orange)}
.blog-image{display:block;position:relative;height:230px;overflow:hidden}.blog-image img{width:100%;height:100%;object-fit:cover;transition:transform .5s}.blog-image:hover img{transform:scale(1.06)}
.blog-image>span{position:absolute;left:14px;top:14px;padding:6px 8px;background:var(--red);font-size:8px;font-weight:900;letter-spacing:1px}
.blog-copy{padding:20px}.blog-meta{font-size:9px!important;font-weight:800;letter-spacing:1.2px;color:var(--orange)!important}.blog-copy h3{font:800 27px/1 var(--display);margin:12px 0}.blog-copy h3 a:hover{color:var(--orange)}.blog-copy>p:not(.blog-meta){font-size:11px;color:#aaa;line-height:1.8}
.gallery-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}.gallery-grid a{height:270px;position:relative;overflow:hidden;background:#222}.gallery-grid img{width:100%;height:100%;object-fit:cover;transition:transform .5s,filter .4s}.gallery-grid a:hover img{transform:scale(1.06);filter:saturate(1.15)}.gallery-grid a:after{content:"";position:absolute;inset:35% 0 0;background:linear-gradient(transparent,rgba(0,0,0,.85))}.gallery-grid a span{position:absolute;z-index:1;bottom:15px;left:16px;font-size:9px;font-weight:900;letter-spacing:1px}
.contact-layout{display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:start}.contact-detail{display:flex;gap:17px;border-top:1px solid var(--line);padding:18px 0}.contact-detail>span{color:var(--red);font:800 25px var(--display)}.contact-detail b{font-size:10px;letter-spacing:1.3px}.contact-detail p{margin:4px 0 0;color:#aaa;font-size:12px}
.contact-form{padding:28px;background:#191919;border-top:3px solid var(--orange);display:flex;flex-direction:column;gap:9px}.contact-form label{font-size:9px;font-weight:900;letter-spacing:1.3px;margin-top:8px}.contact-form input,.contact-form select,.contact-form textarea{width:100%;padding:13px 14px;background:#101010;color:#f7f5f0;border:1px solid #393939;border-radius:0;font:12px var(--body)}.contact-form input:focus,.contact-form select:focus,.contact-form textarea:focus{outline:1px solid var(--orange);border-color:var(--orange)}.contact-form textarea{resize:vertical}.contact-form small,.booking-panel small{font-size:9px;color:#888;line-height:1.7;margin-top:8px}
.article-layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(240px, .8fr);gap:45px;align-items:start}.article-cover,.package-cover{width:100%;max-height:490px;object-fit:cover;margin-bottom:22px}.article-main h2{font:900 clamp(36px,5vw,58px)/.95 var(--display);margin:10px 0 20px}.article-main h3,.detail-block h3{font:800 29px/1 var(--display);margin:30px 0 10px}.article-main blockquote{border-left:3px solid var(--red);margin:25px 0;padding:15px 20px;background:#191919;font:700 23px/1.2 var(--display)}.article-sidebar,.booking-panel{padding:23px;background:#191919;border-top:3px solid var(--red)}.article-sidebar h3,.booking-panel h3{font:800 29px/1 var(--display);margin:10px 0}.article-sidebar p,.booking-panel p{font-size:11px;color:#aaa;line-height:1.8}.sidebar-link{font-size:11px;color:var(--orange)}.package-detail-layout{display:grid;grid-template-columns:minmax(0,1.8fr) minmax(260px,.8fr);gap:36px;align-items:start}.package-tabs{display:flex;gap:20px;flex-wrap:wrap;border-bottom:1px solid var(--line);padding:15px 0;margin-bottom:28px}.package-tabs a{font-size:9px;font-weight:900;letter-spacing:1px}.package-tabs a:hover{color:var(--orange)}.detail-block{border-top:1px solid var(--line);padding-top:15px;margin-top:25px}.detail-block ul,.booking-panel ul{padding-left:18px;color:#bbb;font-size:12px;line-height:2}.panel-label{color:var(--orange);font-size:9px;font-weight:900;letter-spacing:1.3px}.booking-panel .button{width:100%;margin:10px 0}.panel-divider{height:1px;background:var(--line);margin:20px 0}.booking-panel>b{font-size:10px;letter-spacing:1px}
@media(max-width:850px){.blog-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.gallery-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.inner-split,.contact-layout{gap:30px}.article-layout,.package-detail-layout{grid-template-columns:1fr}.article-sidebar,.booking-panel{max-width:none}}
@media(max-width:640px){.page-banner{min-height:350px;padding:145px 0 35px}.page-banner-content h1{font-size:clamp(49px,13vw,70px)}.inner-split,.contact-layout{grid-template-columns:1fr;gap:24px}.blog-grid,.gallery-grid{grid-template-columns:1fr}.blog-image{height:240px}.gallery-grid a{height:260px}.contact-form{padding:20px}.article-layout{gap:25px}.package-tabs{gap:13px}.package-detail-layout{gap:25px}}

/* Integration refinements: explicit page utilities and consistent card sizing */
.adventures{scroll-margin-top:90px}
.card-featured{min-height:460px}
.package-grid .adventure-card{min-height:430px}
.contact-intro{max-width:520px;margin-bottom:30px}
.contact-intro p{color:var(--muted);font-size:13px;line-height:1.9}
@media(max-width:850px){.card-featured{min-height:420px}.package-grid .adventure-card{min-height:390px}}
@media(max-width:640px){.card-featured,.package-grid .adventure-card{min-height:400px}.contact-intro{margin-bottom:22px}}

/* Global typography system: consistent display titles and readable body copy */
:root{
  --font-title:"Barlow Condensed",Impact,sans-serif;
  --font-body:"Inter",Arial,sans-serif;
  --text-xs:clamp(.7rem,.68rem + .08vw,.76rem);
  --text-sm:clamp(.78rem,.74rem + .12vw,.86rem);
  --text-base:clamp(.9rem,.86rem + .16vw,1rem);
  --text-lg:clamp(1.02rem,.96rem + .25vw,1.18rem);
  --title-xl:clamp(3.25rem,7.2vw,6.25rem);
  --title-lg:clamp(2.7rem,5.5vw,4.75rem);
  --title-md:clamp(2rem,3.8vw,3.25rem);
}
html{font-size:16px;text-size-adjust:100%;-webkit-text-size-adjust:100%}
body{font-family:var(--font-body);font-size:var(--text-base);line-height:1.7;font-weight:400;font-synthesis:none;text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased}
h1,h2,h3,h4,h5,h6,.inner-title,.page-banner-content h1,.hero h1,.section-heading h2,.manifesto h2,.cta-inner h2,.card-content h3,.track-info h3,.blog-copy h3,.article-main h2,.article-main h3,.detail-block h3,.article-sidebar h3,.booking-panel h3,.reasons h3{
  font-family:var(--font-title);font-weight:800;line-height:.98;letter-spacing:.005em;text-wrap:balance
}
h1,.hero h1,.page-banner-content h1{font-size:var(--title-xl)}
h2,.inner-title,.section-heading h2{font-size:var(--title-lg)}
h3,.article-main h3,.detail-block h3{font-size:var(--title-md)}
p,li,label,input,select,textarea,button{font-family:var(--font-body)}
p{line-height:1.8}
img,video,iframe{max-width:100%}
button,input,select,textarea{font-size:16px}
a,button{-webkit-tap-highlight-color:transparent}
:focus-visible{outline:2px solid var(--orange);outline-offset:3px}
.hero h1{font-size:clamp(4rem,9.5vw,8.1rem)}
.section-heading h2{font-size:clamp(2.8rem,6.5vw,5.1rem)}
.manifesto h2{font-size:clamp(3.4rem,7.5vw,6.1rem)}
.cta-inner h2{font-size:clamp(4rem,9vw,7.2rem)}
.card-content h3{font-size:25px; text-transform:uppercase;}
.track-info h3{font-size:clamp(1.9rem,2.8vw,2.7rem)}
.blog-copy h3,.article-sidebar h3,.booking-panel h3{font-size:clamp(1.55rem,2.3vw,2rem)}
.article-main h2{font-size:clamp(2.4rem,4.8vw,3.8rem)}
.article-main h3,.detail-block h3{font-size:clamp(1.6rem,2.4vw,2rem)}
.eyebrow,.card-tag,.card-number,.track-index,.track-badge,.track-info>p,.track-link,.nav-cta,.breadcrumbs,.blog-meta,.contact-form label,.panel-label,.footer-bottom,.back-top{
  line-height:1.4;text-wrap:pretty
}
.hero-text,.section-description,.inner-copy p,.package-detail-main p,.article-main p,.contact-detail p,.blog-copy>p:not(.blog-meta){
  font-size:var(--text-base);line-height:1.85
}

/* Responsive layout hardening across all pages */
.wrap{width:min(1180px,calc(100% - 48px))}
.adventure-grid,.tracks-grid,.blog-grid,.gallery-grid,.reasons{min-width:0}
.adventure-card,.track-card,.blog-card,.gallery-grid a,.contact-form,.article-main,.article-sidebar,.package-detail-main,.booking-panel{min-width:0}
.adventure-card img,.track-card img,.blog-image img,.gallery-grid img{display:block}
.contact-form input,.contact-form select,.contact-form textarea{min-width:0;max-width:100%}
.contact-form .button,.booking-panel .button{min-height:48px}
.page-banner-content,.inner-split>*,.contact-layout>*,.article-layout>*,.package-detail-layout>*{min-width:0}
@media(max-width:1024px){
  .wrap{width:calc(100% - 40px)}
  .main-nav{gap:clamp(12px,2vw,22px)}
  .section{padding-top:clamp(65px,8vw,95px);padding-bottom:clamp(65px,8vw,90px)}
  .inner-split,.contact-layout{gap:32px}
  .package-detail-layout{grid-template-columns:minmax(0,1.5fr) minmax(240px,.8fr);gap:24px}
}
@media(max-width:850px){
  .main-nav{gap:14px;font-size:10px}
  .main-nav .nav-cta{padding:11px 12px}
  .adventure-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .adventure-card:first-child{grid-column:span 2}
  .tracks-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .blog-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .gallery-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .inner-split,.contact-layout{grid-template-columns:1fr}
  .article-layout,.package-detail-layout{grid-template-columns:minmax(0,1fr)}
  .article-sidebar,.booking-panel{width:100%}
  .hero-stamp{transform:scale(.9) rotate(9deg);transform-origin:bottom right}
}
@media(max-width:640px){
  html{scroll-padding-top:76px}
  .wrap{width:calc(100% - 36px)}
  .topline{height:28px;padding:0 18px;font-size:8px;letter-spacing:1px}
 
  .main-nav{inset:98px 0 0;padding:24px 22px;overflow-y:auto}
  .main-nav>a:not(.nav-cta){font-size:clamp(1.6rem,7vw,2rem);padding:15px 0}
  .nav-cta{font-size:11px}
  .hero{min-height:660px;height:min(760px,100svh)}
  .hero-content{padding-top:65px}
  .hero h1{font-size:clamp(3.6rem,15vw,6rem);line-height:.86}
  .hero-text{max-width:34rem;font-size:var(--text-sm);line-height:1.8}
  .hero-stamp{right:14px;bottom:76px}
  .hero-bottom{bottom:18px;font-size:8px}
  .strip-inner{flex-wrap:wrap;padding-top:15px;padding-bottom:15px}
  .strip-inner p{max-width:75%;font-size:clamp(1.15rem,5.5vw,1.5rem)}
  .section-heading{gap:16px}
  .section-heading h2{font-size:clamp(2.65rem,11.5vw,4rem);line-height:.92}
  .section-description{font-size:var(--text-sm)}
  .adventure-grid,.tracks-grid,.blog-grid,.gallery-grid,.reasons{grid-template-columns:minmax(0,1fr)}
  .adventure-card,.adventure-card:first-child{grid-column:auto;min-height:390px}
  .track-card{min-height:390px}
  .track-info h3{font-size:clamp(2.2rem,10vw,3rem)}
  .blog-image{height:clamp(210px,62vw,280px)}
  .gallery-grid a{height:clamp(230px,70vw,320px)}
  .page-banner{min-height:320px;padding:135px 0 32px}
  .page-banner-content h1{font-size:clamp(2.8rem,12vw,4.4rem);line-height:.92}
  .page-banner-content>p:not(.eyebrow){font-size:var(--text-sm)}
  .inner-title{font-size:clamp(2.5rem,10vw,3.7rem)}
  .manifesto{min-height:540px}
  .manifesto-content{padding-top:65px;padding-bottom:65px}
  .manifesto h2{font-size:clamp(3rem,12vw,4.6rem)}
  .manifesto-content>p:not(.eyebrow){font-size:var(--text-sm)}
  .cta-inner h2{font-size:clamp(3.6rem,15vw,5.8rem)}
  .contact-form{padding:20px}
  .contact-form input,.contact-form select,.contact-form textarea{padding:13px 12px}
  .article-main h2{font-size:clamp(2.2rem,9vw,3.2rem)}
  .article-main blockquote{font-size:1.4rem}
  .package-tabs{gap:12px}
  .package-tabs a{font-size:9px}
  .footer-main{gap:18px}
  .footer-bottom{flex-wrap:wrap;line-height:1.6}
}
@media(max-width:380px){
  .wrap{width:calc(100% - 28px)}
  .brand{gap:7px}
  .brand-mark{font-size:27px}
  .brand-copy{font-size:9px;letter-spacing:.8px}
  .brand-copy small{font-size:7px;letter-spacing:2.5px}
  .hero h1{font-size:clamp(3.2rem,14vw,4rem)}
  .hero-stamp{width:65px;height:65px;font-size:16px}
  .hero-stamp small{font-size:3.5px}
  .strip-label{font-size:7px}
  .strip-inner p{font-size:1.05rem}
  .button{max-width:100%;padding:0 15px;font-size:9px}
}


/* Brand refresh: warm light surfaces with Bali Tarantula orange accents */
:root{
  --black:#f5f4ef;
  --panel:#ffffff;
  --white:#191919;
  --muted:#66645f;
  --line:#dedbd4;
  --orange:#ff5a1f;
  --red:#e52232;
}
body{background:#f5f4ef;color:#191919}
.topline{background:#ff5a1f;color:#171717}


.inner-title,.page-banner-content h1,.package-detail-main h2,.article-main h2,.article-main h3,.detail-block h3{color:#191919}
.inner-copy p,.package-detail-main p,.article-main p{color:#5e5d58}
.page-banner{background-color:#e8e5dd}
.page-banner-content>p:not(.eyebrow){color:#5e5d58}
.breadcrumbs{color:#6a6862}
.package-tabs{border-color:#dedbd4}
.package-tabs a{color:#34332f}
.detail-block{border-color:#dedbd4}
.detail-block ul{color:#5e5d58}
.article-sidebar,.booking-panel{background:#fff;color:#191919;border:1px solid #e6e3dc;border-top:3px solid #ff5a1f;box-shadow:0 18px 48px rgba(30,25,15,.07)}
.article-sidebar p,.booking-panel p{color:#66645f}
.article-sidebar h3,.booking-panel h3,.booking-panel>b{color:#191919}
.booking-panel ul{color:#5e5d58}
.panel-divider{background:#e7e3db}
.booking-panel small{color:#777;line-height:1.6;display:block}
.button-orange{background:#ff5a1f;color:#151515}
.site-footer{background:#171717;color:#f7f5f0}
.footer-bottom{border-color:#343434}
@media(min-width:851px){
  .package-detail-layout{align-items:start}
  .booking-panel{position:sticky;top:94px;align-self:start}
}
@media(max-width:850px){
 
  .package-detail-layout .booking-panel{position:sticky;top:76px;z-index:5;order:-1}
}
@media(max-width:640px){
 
  .main-nav.open{background:rgba(14,14,14,.97)}
  .package-detail-layout .booking-panel{top:70px;padding:18px;box-shadow:0 10px 28px rgba(0,0,0,.12)}
}


/* Contrast audit: enforce readable text on light surfaces and image overlays */
:root{--white:#f7f5f0;--muted:#66645f;--line:#dedbd4;--panel:#fff}
body{background:#f5f4ef;color:#191919}
main{color:#191919}
.section-heading h2,.inner-title,.article-main h2,.article-main h3,.detail-block h3,
.contact-intro h1,.contact-intro h2,.inner-copy h2{color:#191919}
.section-description,.inner-copy p,.package-detail-main p,.article-main p,.contact-detail p,
.blog-copy>p:not(.blog-meta){color:#5d5b56}
.page-banner{background:#e8e5dd;color:#191919}
.page-banner-content h1{color:#191919}
.page-banner-content .eyebrow{color:#494741}
.page-banner-content>p:not(.eyebrow){color:#55534d}
.breadcrumbs{color:#66635c}
.breadcrumbs a{color:#292824}
.package-tabs{border-color:#dedbd4}
.package-tabs a{color:#34322e}
.detail-block{border-color:#dedbd4}
.detail-block ul{color:#55534d}
.adventure-card,.track-card,.blog-card,.gallery-grid a{background:#fff}
.blog-card,.contact-detail,.inner-copy,.package-detail-main{color:#191919}
.blog-copy{background:#fff;color:#191919;padding:20px}
.blog-copy h2,.blog-copy h3,.blog-copy a{color:#191919}
.blog-copy>p:not(.blog-meta){color:#5d5b56}
.blog-meta{color:#77736b}
.contact-form{background:#fff;color:#191919;border:1px solid #e4e0d8;border-top:3px solid #ff5a1f;box-shadow:0 16px 42px rgba(30,25,15,.06)}
.contact-form label{color:#393731}
.contact-form input,.contact-form select,.contact-form textarea{background:#fff;color:#191919;border:1px solid #d8d4cc}
.contact-form input::placeholder,.contact-form textarea::placeholder{color:#77736b;opacity:1}
.contact-form select option{background:#fff;color:#191919}
.contact-form small{color:#68655f}
.article-sidebar,.booking-panel{color:#191919;background:#fff}
.article-sidebar h3,.booking-panel h3,.booking-panel>b{color:#191919}
.article-sidebar p,.booking-panel p{color:#5d5b56}
.booking-panel ul{color:#55534d}
.booking-panel small{color:#66635c}
.panel-divider{background:#e2ded6}
.reason-no{color:#69665f}
.reasons h3{color:#191919}
.reasons p{color:#5d5b56}
.adventure-card .card-content,.track-card .track-info,.gallery-grid a{color:#fff}
.adventure-card .card-content h3,.adventure-card .card-content p,.adventure-card .card-number,
.track-card .track-info h3,.track-card .track-info p,.track-card .track-index{color:#fff}
.adventure-card .card-content p,.track-card .track-info p{color:#f0efec}
.card-link{color:#fff}

.hero h1,.hero .hero-text,.hero .eyebrow,.hero-bottom,.hero-stamp{color:#fff}
.manifesto h2,.manifesto .eyebrow,.manifesto-content>p:not(.eyebrow),.manifesto-side{color:#fff}
.site-footer{background:#171717;color:#f7f5f0}
.site-footer .brand-copy,.site-footer .footer-main>p{color:#f7f5f0}
.site-footer .footer-bottom,.site-footer .footer-main>p span{color:#c0bdb7}
.button-orange{color:#151515}
.button-dark{color:#fff}
@media(max-width:640px){
 .page-banner-content h1{color:#191919}
 .main-nav.open{background:rgba(14,14,14,.98)}
 .blog-copy{padding:18px}
}


/* Banner heading system: consistent white + adventure orange across every page */
.page-banner{
  position:relative;isolation:isolate;overflow:hidden;
  background:linear-gradient(112deg,#171717 0%,#242321 65%,#30271f 100%);
  color:#fff;
}
.page-banner::before{
  content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;opacity:.22;
  background:linear-gradient(115deg,transparent 0 58%,rgba(255,90,31,.22) 58% 59%,transparent 59% 100%);
}
.page-banner-content h1{
  max-width:100%;margin:0;color:#fff;font-family:var(--display);
  font-size:clamp(3rem,6.2vw,5.6rem);font-weight:900;line-height:.98;
  letter-spacing:-.015em;text-wrap:balance;overflow-wrap:break-word;
}
.page-banner-content h1 span{color:#ff5a1f}
.page-banner-content>p:not(.eyebrow){color:#e0ded9;max-width:600px;line-height:1.75}
.page-banner-content .eyebrow{color:#f0efec}
.page-banner-content .breadcrumbs,.page-banner-content .breadcrumbs a{color:#c9c6c0}
.page-banner-content .breadcrumbs a:hover{color:#ff5a1f}
.page-banner-content{min-width:0}
h1,h2,h3,h4,p,a,button,label,li,small{overflow-wrap:break-word}
h1,h2,h3{ text-wrap:balance }
p,li{ text-wrap:pretty }
img{height:auto}
.adventure-card .card-content,.track-info,.blog-copy{min-width:0}
.adventure-card .card-content h3,.track-info h3,.blog-copy h2,.blog-copy h3{overflow-wrap:anywhere;line-height:1.02}
.card-content p,.track-desc,.blog-copy>p:not(.blog-meta){line-height:1.7}
.section-heading h2,.manifesto h2,.cta-inner h2,.inner-title,.article-main h2,.package-detail-main h2{
  overflow-wrap:break-word;text-wrap:balance;line-height:1.02;
}
.booking-page{padding-top:74px;padding-bottom:90px}
.booking-layout{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(270px,.65fr);gap:28px;align-items:start}
.booking-form{display:grid;gap:22px;min-width:0}
.booking-step,.booking-summary{background:#fff;border:1px solid #e3dfd7;border-radius:4px;padding:clamp(18px,3vw,28px);box-shadow:0 12px 34px rgba(32,25,14,.045);min-width:0}
.booking-step h2,.booking-summary h2{font:800 clamp(1.7rem,3vw,2.25rem)/1 var(--display);color:#191919;margin:0 0 8px}
.booking-step>p,.booking-summary>p{color:#625f58;font-size:13px;line-height:1.7;margin:0 0 18px}
.booking-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.booking-field{display:flex;flex-direction:column;gap:7px;min-width:0}
.booking-field.full{grid-column:1/-1}
.booking-field label{font-size:10px;font-weight:800;letter-spacing:.8px;color:#393731}
.booking-field input,.booking-field select,.booking-field textarea{width:100%;min-width:0;min-height:47px;padding:12px 13px;background:#fff;color:#191919;border:1px solid #d7d2c9;border-radius:2px;font:14px var(--body)}
.booking-field textarea{min-height:100px;resize:vertical}
.booking-field input:focus,.booking-field select:focus,.booking-field textarea:focus{outline:2px solid #ff5a1f;outline-offset:1px;border-color:#ff5a1f}
.booking-field input::placeholder,.booking-field textarea::placeholder{color:#77736b;opacity:1}
.booking-options{display:flex;flex-wrap:wrap;gap:10px}
.booking-option{display:flex;align-items:center;gap:9px;padding:12px 14px;border:1px solid #ded9d0;background:#faf9f6;color:#25231f;font-size:12px;cursor:pointer}
.booking-option input{accent-color:#ff5a1f}
.booking-summary{position:sticky;top:92px}
.booking-summary .summary-label{font-size:10px;font-weight:900;letter-spacing:1.4px;color:#ff5a1f}
.booking-summary .summary-row{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:13px 0;border-bottom:1px solid #e9e5de;font-size:12px;color:#5d5a54}
.booking-summary .summary-row strong{color:#191919;text-align:right}
.booking-summary .summary-note{font-size:11px;color:#68655e;line-height:1.7;margin-top:15px}
.booking-submit{width:100%;border:0;cursor:pointer;margin-top:18px}
.booking-notice{display:none;padding:14px;background:#fff2e9;border-left:3px solid #ff5a1f;color:#42220f;font-size:12px;line-height:1.7;margin-top:16px}
.booking-notice.show{display:block}
.booking-form small{color:#6b675f;font-size:11px;line-height:1.6}
@media(max-width:850px){
 .booking-layout{grid-template-columns:1fr}
 .booking-summary{position:static;order:-1}
}
@media(max-width:640px){
 .page-banner-content h1{font-size:clamp(2.7rem,12vw,4rem);line-height:1.02;letter-spacing:0}
 .page-banner-content>p:not(.eyebrow){font-size:12px}
 .booking-page{padding-top:48px;padding-bottom:60px}
 .booking-fields{grid-template-columns:minmax(0,1fr)}
 .booking-field.full{grid-column:auto}
 .booking-step,.booking-summary{padding:18px}
 .booking-options{flex-direction:column}
 .booking-option{width:100%}
}

.booking-fields[hidden]{display:none!important}


/* Refined title scale: slightly smaller and more balanced across viewports */
.hero h1{font-size:clamp(3.6rem,8.1vw,7.1rem);line-height:.9}
.page-banner-content h1{font-size:clamp(2.8rem,5.4vw,4.9rem);line-height:1.02}
.section-heading h2{font-size:clamp(2.9rem,5.8vw,4.6rem);line-height:.98}
.inner-title{font-size:clamp(2.7rem,5.4vw,4.2rem);line-height:1}
.manifesto h2{font-size:clamp(3.3rem,6.7vw,5.4rem);line-height:.94}
.cta-inner h2{font-size:clamp(3.8rem,7.8vw,6.3rem);line-height:.9}
.article-main h2,.package-detail-main h2{font-size:clamp(2.2rem,4.2vw,3.4rem);line-height:1}
.article-main h3,.detail-block h3{font-size:clamp(1.55rem,2.6vw,2rem);line-height:1.08}
/* Reviews styles are deliberately scoped to this section so they cannot restyle other homepage sections. */
.bta-home-main .reviews-section{position:relative;isolation:isolate;overflow:hidden;background-color:#07100d;background-position:center;background-size:cover;background-repeat:no-repeat;padding:84px 0 90px;color:#fff}
.bta-home-main .reviews-section:before{content:"";position:absolute;inset:0;z-index:0;pointer-events:none;background:linear-gradient(90deg,rgba(0,0,0,.48),rgba(0,0,0,.34)),linear-gradient(0deg,rgba(0,0,0,.42),rgba(0,0,0,.28))}
.bta-home-main .reviews-section>.wrap{position:relative;z-index:1}
.bta-home-main .reviews-section .section-heading{margin-bottom:28px}
.bta-home-main .reviews-section .section-heading h2{color:#fff}
.bta-home-main .reviews-section .section-description{color:rgba(255,255,255,.78)}
.bta-home-main .review-grid{display:flex;gap:24px;overflow-x:auto;overflow-y:hidden;scroll-snap-type:x mandatory;scroll-behavior:smooth;scrollbar-width:none;padding:4px 1px 14px;overscroll-behavior-x:contain;touch-action:pan-x;cursor:grab;-webkit-overflow-scrolling:touch;user-select:none}
.bta-home-main .review-grid:active{cursor:grabbing}
.bta-home-main .review-grid::-webkit-scrollbar{display:none}
.bta-home-main .review-card{position:relative;isolation:isolate;flex:0 0 calc((100% - 48px)/3);min-width:0;scroll-snap-align:start;background:#fff;border:1px solid rgba(20,35,29,.08);padding:26px 28px;color:#24332e;box-shadow:0 12px 32px rgba(0,0,0,.18);border-radius:20px;overflow:hidden;min-height:300px;display:flex;flex-direction:column;transition:background-color .28s ease,color .28s ease,border-color .28s ease,transform .28s ease}
.bta-home-main .review-card:before{content:none}
.bta-home-main .review-card-top{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:40px;margin-bottom:18px}
.bta-home-main .review-quote{display:inline-flex;align-items:center;height:38px;color:#d9932d;font-family:Georgia,serif;font-size:58px;font-weight:700;line-height:1;transition:color .28s ease}
.bta-home-main .review-stars{display:flex;align-items:center;gap:2px;color:#d9932d;letter-spacing:0;font-size:13px;margin:0;white-space:nowrap;transition:color .28s ease}
.bta-home-main .review-card blockquote{margin:0;color:#53615c;font-size:13px;line-height:1.75;flex:1;transition:color .28s ease}
.bta-home-main .review-card footer{display:flex;align-items:center;gap:12px;margin-top:20px;padding-top:16px;border-top:1px solid #e7e9e6;color:#263a33;font-size:11px;font-weight:800;letter-spacing:.4px;transition:color .28s ease,border-color .28s ease}
.bta-home-main .review-avatar{display:block;flex:0 0 38px;width:38px;height:38px;object-fit:cover;border-radius:50%;border:1px solid rgba(30,45,38,.14);background:#f4f4f0}
.bta-home-main .review-avatar-fallback{display:grid;place-items:center;color:#66736d;font-size:15px}
.bta-home-main .review-author{min-width:0;color:inherit}
.bta-home-main .review-card footer .review-author>span{display:block;margin-top:5px;color:#718079;font-size:10px;font-weight:500;letter-spacing:0;transition:color .28s ease}
.bta-home-main .review-card:hover,.bta-home-main .review-card:focus-within{background:#ed791d;color:#fff;border-color:#ed791d;transform:translateY(-3px)}
.bta-home-main .review-card:hover .review-quote,.bta-home-main .review-card:focus-within .review-quote,.bta-home-main .review-card:hover .review-stars,.bta-home-main .review-card:focus-within .review-stars,.bta-home-main .review-card:hover blockquote,.bta-home-main .review-card:focus-within blockquote,.bta-home-main .review-card:hover footer,.bta-home-main .review-card:focus-within footer,.bta-home-main .review-card:hover footer .review-author>span,.bta-home-main .review-card:focus-within footer .review-author>span{color:#fff}
.bta-home-main .review-card:hover footer,.bta-home-main .review-card:focus-within footer{border-top-color:rgba(255,255,255,.38)}
.bta-home-main .review-slider-progress{position:relative;height:4px;margin:14px 0 0;background:rgba(255,255,255,.28);overflow:hidden;border-radius:20px;display:block;width:100%}
.bta-home-main .review-slider-progress-bar{position:absolute;left:0;top:0;display:block;height:100%;width:33.333%;min-width:28px;background:#f0442d;border-radius:20px;transition:left .12s linear,width .12s linear;will-change:left}
.bta-home-main .review-note{margin:18px 0 0;color:rgba(255,255,255,.65);font-size:10px;line-height:1.6}
@media(max-width:850px){
 .hero h1{font-size:clamp(3.5rem,10vw,6rem)}
 .bta-home-main .review-card{flex-basis:calc((100% - 24px)/2)}
}
@media(max-width:640px){
 .hero h1{font-size:clamp(3.3rem,13.5vw,5.2rem);line-height:.92}
 .page-banner-content h1{font-size:clamp(2.6rem,10.5vw,3.7rem);line-height:1.03}
 .section-heading h2{font-size:clamp(2.65rem,10.5vw,3.6rem);line-height:1}
 .inner-title{font-size:clamp(2.45rem,10vw,3.4rem)}
 .manifesto h2{font-size:clamp(3rem,11vw,4.3rem);line-height:.96}
 .cta-inner h2{font-size:clamp(3.3rem,13.5vw,5rem);line-height:.92}
 .article-main h2,.package-detail-main h2{font-size:clamp(2rem,8.5vw,2.8rem)}
 .bta-home-main .reviews-section{padding:62px 0}
 .bta-home-main .review-card{flex-basis:86%;padding:20px}
}
@media(max-width:380px){
 .hero h1{font-size:clamp(3rem,12.5vw,3.8rem)}
 .page-banner-content h1{font-size:clamp(2.35rem,10vw,3rem)}
 .cta-inner h2{font-size:clamp(3rem,12.5vw,4rem)}
}

/* Global website settings */
.site-brand-logo{width:auto;height:auto;max-width:170px;max-height:54px;object-fit:contain}.footer-settings-links,.footer-socials{display:flex;flex-wrap:wrap;gap:8px 14px;margin-top:12px;font-size:12px}.footer-settings-links a,.footer-socials a{color:inherit;opacity:.85}.footer-settings-links a:hover,.footer-socials a:hover{color:var(--orange);opacity:1}.footer-settings-links span{opacity:.8}


.bta-new-hero-video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0}
.bta-hero-dots{position:absolute;z-index:3;left:50%;bottom:28px;transform:translateX(-50%);display:flex;gap:8px}
.bta-hero-dots button{width:8px;height:8px;padding:0;border:1px solid #fff;background:#ffffff70;border-radius:50%;cursor:pointer}
.bta-hero-dots button.active{background:var(--orange);border-color:var(--orange);transform:scale(1.25)}
/* Homepage integration: adapt existing shared header/footer to the supplied design without editing shared files. */
body{background:#f5f4ef!important;color:#191919!important}
body> .topline{position:relative;z-index:10001}

body>main{margin:0!important;padding:0!important;max-width:none!important;width:100%!important}
.bta-home-main{margin:0!important;padding:0!important;max-width:none!important;width:100%!important;color:#191919}
.bta-home-main .hero{margin:0!important}
.bta-home-main .hero-image img{width:100%;height:100%;object-fit:cover}
.bta-home-main .hero h1{max-width:780px}
.bta-home-main .hero .hero-text{color:#e0ded9}
.bta-home-main .hero .hero-actions .button{border-radius:0}
.bta-home-main .section-heading{min-width:0}
.bta-home-main .section-heading>div{min-width:0}
.bta-home-main .manifesto{background:#1a1a1a}
.bta-home-main .manifesto-image{background-position:center;background-size:cover}
.bta-home-main .reviews-section{margin:0}
.bta-home-main .reviews-section .section-heading h2{color:#fff}
.bta-home-main .review-card{border-radius:16px}
.bta-home-main .stats-strip{background:#171717;color:#fff;padding:28px 0}
.bta-home-main .stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px;text-align:center}
.bta-home-main .stat-item strong{display:block;font:900 clamp(28px,4vw,42px)/1 var(--display);color:var(--orange)}
.bta-home-main .stat-item span{display:block;margin-top:8px;color:#ddd;font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase}
.bta-home-main .home-gallery-section{padding:88px 0 95px;background:#f5f4ef}
.bta-home-main .home-gallery-section .section-heading .eyebrow{color:#191919!important}
/* Justified gallery: each row fills the available width while preserving image proportions. */
.bta-home-main .home-gallery-section .gallery-grid{display:flex;flex-direction:column;gap:12px;width:100%;}
.bta-home-main .home-gallery-section .gallery-row{display:flex;align-items:stretch;gap:12px;width:100%;}
.bta-home-main .home-gallery-section .gallery-grid a{height:auto;min-height:0;position:relative;display:block;overflow:hidden;background:#dedbd4;flex:0 0 auto;}
.bta-home-main .home-gallery-section .gallery-grid a img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .55s,filter .4s;}
.bta-home-main .home-gallery-section .gallery-grid a:hover img{transform:scale(1.045);filter:saturate(1.08)}
.bta-home-main .home-gallery-section .gallery-grid a:after{pointer-events:none}
.bta-home-main .home-gallery-section .gallery-grid a span{left:16px;bottom:14px;font-size:10px;letter-spacing:1.1px}
@media(max-width:640px){.bta-home-main .home-gallery-section{padding:62px 0}.bta-home-main .home-gallery-section .gallery-grid,.bta-home-main .home-gallery-section .gallery-row{gap:8px}.bta-home-main .home-gallery-section .gallery-grid a span{left:10px;bottom:10px;font-size:8px}}
.bta-home-main .gallery-action{margin-top:24px;display:flex;justify-content:center}
.bta-home-main .gallery-action .button{border-radius:0}
.bta-home-main .booking-cta{margin:0}
.bt-footer{background:#171717!important;color:#f7f5f0!important;border-top:0!important}
.bt-footer .bt-footer-main{padding-top:46px!important;padding-bottom:40px!important}
.bt-footer .container{width:min(1180px,calc(100% - 48px))!important;max-width:1180px!important}
.bt-footer h2,.bt-footer h3,.bt-footer p,.bt-footer a,.bt-footer .bt-footer-bottom,.bt-footer .bt-footer-mini-tag{color:#f7f5f0!important}
.bt-footer h3{font-family:var(--display)!important}
.bt-footer a:hover{color:var(--orange)!important}
.bt-footer .bt-footer-bottom{border-color:#343434!important;color:#c0bdb7!important}
@media(max-width:900px){
 
}
@media(max-width:640px){
 .topline{height:28px!important;padding:0 18px!important;font-size:7px!important;letter-spacing:.8px!important}
 .topline span:last-child{display:none}

 
 .bta-home-main .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:24px 12px}
 .bta-home-main .home-gallery-section{padding:62px 0}
 .bt-footer .container{width:calc(100% - 36px)!important}
}


/* Homepage refinements: responsive typography, full-width hero content, no decorative bottom slant. */
.bta-home-main .hero::after{content:none!important;display:none!important}
.bta-home-main .hero-content{
  width:100%!important;
  max-width:none!important;
  margin:0!important;
  padding:90px clamp(18px,calc((100vw - 1180px)/2),120px) 0!important;
  box-sizing:border-box;
}
.bta-home-main .hero h1{
  max-width:none!important;
  font-size:clamp(40px,5.5vw,70px)!important;
  line-height:.96!important;
}
.bta-home-main section h2{
  font-size:clamp(34px,4.8vw,60px)!important;
  line-height:1.02!important;
}
.bta-home-main .hero .hero-text{font-size:14px!important;line-height:1.8;max-width:min(100%,560px)}
@media(max-width:640px){
  .bta-home-main .hero-content{padding:65px 18px 0!important}
  .bta-home-main .hero h1{font-size:clamp(34px,10vw,48px)!important;line-height:.98!important}
  .bta-home-main section h2{font-size:clamp(30px,8vw,42px)!important;line-height:1.04!important}
  .bta-home-main .hero .hero-text{font-size:14px!important;max-width:100%}
}



/* Homepage package cards: prices replace the short descriptions. */
.bta-home-main .package-card-prices{display:flex;flex-direction:column;align-items:flex-start;gap:5px;margin:10px 0 13px;color:#fff}
.bta-home-main .package-price-current{display:flex;align-items:baseline;gap:5px;flex-wrap:wrap}
.bta-home-main .package-price-before{font-size:13px;font-weight:700;color:rgba(255,255,255,.68);text-decoration:line-through;text-decoration-thickness:1.5px}
.bta-home-main .package-price-after{font-size:clamp(19px,2vw,20px);font-weight:900;line-height:1;color:var(--orange);letter-spacing:.1px}
.bta-home-main .package-price-unit{font-size:10px;color:rgba(255,255,255,.76);font-weight:600}
.bta-home-main .package-grid .card-content>p{display:none!important}
.bta-home-main .package-grid .card-link{color:var(--orange)!important}
.bta-home-main .package-grid .card-link span{color:var(--orange)!important}

/* Adventure section: editor-driven title, description below title, category and best-seller badges. */
.bta-home-main .package-section-heading{display:block;margin-bottom:28px}
.bta-home-main .package-section-heading>div:first-child{width:100%;max-width:none;min-width:0}
.bta-home-main .package-section-heading .eyebrow{color:#111!important}
.bta-home-main .package-section-heading .section-description{max-width:680px;margin:16px 0 0;font-size:14px;line-height:1.8}
.bta-home-main .adventure-card{position:relative}
.bta-home-main .package-card-badges{position:absolute;z-index:3;top:16px;left:16px;right:16px;display:flex;flex-direction:row;align-items:flex-start;justify-content:space-between;gap:8px;max-width:calc(100% - 32px);pointer-events:none}
.bta-home-main .package-category-badge{display:inline-flex;align-items:center;max-width:70%;padding:8px 12px;border-radius:50px;background:rgba(0,0,0,.68);border:1px solid rgba(255,255,255,.2);color:#fff;font:800 9px/1 var(--body);letter-spacing:1px;text-transform:uppercase;backdrop-filter:blur(5px);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bta-home-main .package-best-seller{display:inline-flex;align-items:center;gap:7px;flex:0 0 auto;padding:8px 12px;border-radius:50px;background:var(--orange);color:#fff;font:900 9px/1 var(--body);letter-spacing:.9px;text-transform:uppercase}
.bta-home-main .package-best-seller-dot{width:7px;height:7px;flex:0 0 7px;border-radius:50%;background:#fff;box-shadow:0 0 0 0 rgba(255,255,255,.65);animation:best-seller-pulse 1.35s ease-out infinite}
@keyframes best-seller-pulse{0%{box-shadow:0 0 0 0 rgba(255,255,255,.65);opacity:1}70%{box-shadow:0 0 0 5px rgba(255,255,255,0);opacity:.55}100%{box-shadow:0 0 0 0 rgba(255,255,255,0);opacity:1}}
.bta-home-main .package-slider-progress{height:3px;background:rgba(229,34,50,.18);margin-top:14px;overflow:hidden}
.bta-home-main .package-slider-progress-bar{height:100%;width:0;background:#e52232;transition:width .12s linear}
.bta-home-main .package-slider-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 14px}
.bta-home-main .package-slider-count{font-size:9px;font-weight:800;letter-spacing:1.5px;color:#77736b}
.bta-home-main .package-slider-buttons{display:flex;gap:8px}
.bta-home-main .package-slider-btn{width:42px;height:42px;display:grid;place-items:center;border:1px solid #c9c5bc;background:transparent;color:#191919;font-size:20px;line-height:1;cursor:pointer;transition:background .2s,color .2s,border-color .2s}
.bta-home-main .package-slider-btn:hover,.bta-home-main .package-slider-btn:focus-visible{background:var(--orange);border-color:var(--orange);color:#111}
.bta-home-main .package-slider{display:flex;gap:14px;overflow-x:auto;overflow-y:hidden;scroll-snap-type:x mandatory;scroll-behavior:smooth;overscroll-behavior-x:contain;scrollbar-width:none;padding:0 0 8px;margin:0;grid-template-columns:none}
.bta-home-main .package-slider::-webkit-scrollbar{display:none}
.bta-home-main .package-slider>.adventure-card{flex:0 0 calc((100% - 28px)/3);min-width:0;min-height:430px;scroll-snap-align:start}
.bta-home-main .package-slider>.adventure-card.card-featured{min-height:430px}
.bta-home-main .package-slider .card-content{left:0;right:0}
@media(max-width:900px){.bta-home-main .package-section-heading{align-items:flex-start;flex-direction:column;gap:16px}.bta-home-main .package-section-heading .section-description{flex:1 1 auto;max-width:620px;margin:0}.bta-home-main .package-slider>.adventure-card{flex-basis:calc((100% - 14px)/2);min-height:410px}}
@media(max-width:640px){
 .bta-home-main .package-section-heading{margin-bottom:24px}
 .bta-home-main .package-section-heading .section-description{font-size:13px;margin-top:13px}
 .bta-home-main .package-slider-toolbar{margin-bottom:12px}
 .bta-home-main .package-slider-btn{width:38px;height:38px}
 .bta-home-main .package-slider>.adventure-card,.bta-home-main .package-slider>.adventure-card.card-featured{flex-basis:86%;min-height:390px}
}


/* Intro strip converted to a continuous, responsive marquee */
.intro-strip{display:block;overflow:hidden;background:var(--orange);color:#111;padding:0;}
.intro-marquee{width:100%;overflow:hidden;padding:clamp(18px,2.2vw,27px) 0;}
.intro-marquee-track{display:flex;width:max-content;will-change:transform;animation:intro-marquee-scroll 32s linear infinite;}
.intro-marquee-group{display:flex;flex:0 0 auto;align-items:center;gap:clamp(22px,3.5vw,52px);padding-right:clamp(22px,3.5vw,52px);white-space:nowrap;}
.intro-marquee-kicker{font:900 10px/1.2 var(--body);letter-spacing:clamp(1.2px,.18vw,2.2px);text-transform:uppercase;}
.intro-marquee-title{font:800 clamp(24px,3.3vw,42px)/.95 var(--display);letter-spacing:.5px;text-transform:uppercase;white-space:nowrap;}
.intro-marquee-separator{font-size:clamp(18px,2vw,27px);line-height:1;color:#fff;flex:0 0 auto;}
@keyframes intro-marquee-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.intro-strip:hover .intro-marquee-track{animation-play-state:paused;}
@media(max-width:640px){.intro-marquee{padding:18px 0}.intro-marquee-track{animation-duration:25s}.intro-marquee-kicker{font-size:9px}.intro-marquee-title{font-size:clamp(22px,6vw,29px)}}
@media(prefers-reduced-motion:reduce){.intro-marquee-track{animation:none;transform:none}.intro-marquee-group[aria-hidden="true"]{display:none}.intro-marquee{overflow-x:auto}.intro-marquee-group{white-space:normal}}

/* Hero slider: soft black-to-transparent gradient overlay for readable text */
.bta-home-main .hero .hero-shade{
  z-index:1;
  background:linear-gradient(90deg,rgba(0,0,0,.76) 0%,rgba(0,0,0,.48) 42%,rgba(0,0,0,.12) 100%),
             linear-gradient(0deg,rgba(0,0,0,.42) 0%,rgba(0,0,0,0) 58%)!important;
  pointer-events:none;
}
.bta-home-main .hero .hero-content{z-index:2}
.bta-home-main .hero .bta-hero-dots{z-index:3}
@media(max-width:640px){
  .bta-home-main .hero .hero-shade{
    background:linear-gradient(90deg,rgba(0,0,0,.70) 0%,rgba(0,0,0,.38) 70%,rgba(0,0,0,.16) 100%),
               linear-gradient(0deg,rgba(0,0,0,.48) 0%,rgba(0,0,0,0) 62%)!important;
  }
}

/* Manifesto story button: white label, black label on hover */
.manifesto-content.wrap .button{color:#fff!important;}
.manifesto-content.wrap .button:hover{color:#111!important;background:#fff!important;border-color:#fff!important;}
/* Mosaic gallery: varied tile sizes with a clean responsive grid. */
.bta-home-main .home-gallery-section .section-heading .eyebrow{color:#191919!important}
.bta-home-main .home-gallery-section .gallery-grid{
  display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;
  grid-auto-rows:clamp(125px,13vw,175px);gap:12px;width:100%;
}
.bta-home-main .home-gallery-section .gallery-grid>a{
  height:100%!important;min-height:0;min-width:0;display:block;position:relative;overflow:hidden;
}
.bta-home-main .home-gallery-section .gallery-grid>a:nth-child(1){grid-column:span 2;grid-row:span 2}
.bta-home-main .home-gallery-section .gallery-grid>a:nth-child(4),
.bta-home-main .home-gallery-section .gallery-grid>a:nth-child(7){grid-column:span 2}
.bta-home-main .home-gallery-section .gallery-grid>a img{display:block;width:100%;height:100%;object-fit:cover}
@media(max-width:850px){
 .bta-home-main .home-gallery-section .gallery-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;grid-auto-rows:clamp(145px,27vw,230px);gap:10px}
 .bta-home-main .home-gallery-section .gallery-grid>a:nth-child(1){grid-column:span 2;grid-row:span 1}
 .bta-home-main .home-gallery-section .gallery-grid>a:nth-child(4),
 .bta-home-main .home-gallery-section .gallery-grid>a:nth-child(7){grid-column:span 2}
}
@media(max-width:640px){
 .bta-home-main .home-gallery-section .gallery-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;grid-auto-rows:clamp(125px,39vw,185px);gap:9px}
 .bta-home-main .home-gallery-section .gallery-grid>a:nth-child(1){grid-column:span 2;grid-row:span 1}
 .bta-home-main .home-gallery-section .gallery-grid>a:nth-child(4),
 .bta-home-main .home-gallery-section .gallery-grid>a:nth-child(7){grid-column:span 2}
}
</style>
<script>document.body.classList.add('bta-redesign-home');</script>
<div class="bta-home-main">
  <section class="hero" id="home" aria-label="Bali Tarantula Adventure homepage hero">
    <div class="hero-image" id="btaHeroImage" role="img" aria-label="Bali outdoor adventure" style="background-image:url('<?=e($heroSlides[0]['url'] ?? $heroUrl)?>')"></div>
    <?php if ($heroMediaMode === 'video' && $heroVideo): ?>
      <video class="bta-new-hero-video" autoplay muted loop playsinline preload="metadata" poster="<?=e($heroPosterUrl)?>"><source src="<?=e($heroVideo)?>"></video>
    <?php endif; ?>
    <div class="hero-shade"></div>
    <?php if (!empty($home['hero_enabled'])): ?>
    <div class="hero-content">
      <p class="eyebrow"><span class="eyebrow-line"></span><?=e($home['hero_kicker'] ?? 'YOUR NEXT ADVENTURE STARTS HERE')?></p>
      <h1><?=e($home['hero_title'] ?? 'CHASE THE')?><br><span><?=e($home['hero_accent'] ?? 'UNTAMED.')?></span></h1>
      <p class="hero-text"><?=e($home['hero_text'] ?? 'Trade the ordinary for muddy trails, wild landscapes, and stories worth telling. Bali is calling. Are you ready?')?></p>
      <div class="hero-actions">
        <?php
          $heroPrimaryText = trim((string)($home['hero_button_text'] ?? ''));
          if ($heroPrimaryText === '') {
              $heroPrimaryText = 'EXPLORE ADVENTURES';
          }
          $heroPrimaryUrl = trim((string)($home['hero_button_url'] ?? ''));
          if ($heroPrimaryUrl === '') {
              $heroPrimaryUrl = '#adventures';
          } elseif (!str_starts_with($heroPrimaryUrl, '#') && !preg_match('~^https?://~i', $heroPrimaryUrl)) {
              $heroPrimaryUrl = BASE_URL . '/' . ltrim($heroPrimaryUrl, '/');
          }

          $heroSecondaryText = trim((string)($home['hero_secondary_text'] ?? ''));
          if ($heroSecondaryText === '') {
              $heroSecondaryText = 'EXPLORE EXPERIENCES';
          }
          $heroSecondaryUrl = trim((string)($home['hero_secondary_url'] ?? ''));
          if ($heroSecondaryUrl === '') {
              $heroSecondaryUrl = '#about';
          } elseif (!str_starts_with($heroSecondaryUrl, '#') && !preg_match('~^https?://~i', $heroSecondaryUrl)) {
              $heroSecondaryUrl = BASE_URL . '/' . ltrim($heroSecondaryUrl, '/');
          }
        ?>
        <a class="button button-orange" href="<?=e($heroPrimaryUrl)?>"><?=e($heroPrimaryText)?> <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span></a>
        <a class="button button-outline" href="<?=e($heroSecondaryUrl)?>"><span class="play-icon"><i class="fa-solid fa-play" aria-hidden="true"></i></span><?=e($heroSecondaryText)?></a>
      </div>
    </div>
    
    <?php endif; ?>
    <?php if (count($heroSlides) > 1 && $heroMediaMode !== 'video'): ?><div class="bta-hero-dots" aria-label="Hero image slides"><?php foreach($heroSlides as $i => $slide): ?><button type="button" data-bta-hero-dot="<?=$i?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-label="Show hero image <?=$i+1?>"></button><?php endforeach; ?></div><?php endif; ?>
  </section>

  <?php $introKicker = trim((string)($home['intro_kicker'] ?? 'NOT JUST A RIDE.')) ?: 'NOT JUST A RIDE.'; $introTitle = trim((string)($home['intro_title'] ?? "IT'S YOUR CALL TO ADVENTURE.")) ?: "IT'S YOUR CALL TO ADVENTURE."; ?>
  <section class="intro-strip" aria-label="Adventure highlights">
    <div class="intro-marquee">
      <div class="intro-marquee-track">
        <div class="intro-marquee-group">
          <span class="intro-marquee-kicker"><?=e($introKicker)?></span><span class="intro-marquee-separator" aria-hidden="true">✳</span>
          <span class="intro-marquee-title"><?=e($introTitle)?></span><span class="intro-marquee-separator" aria-hidden="true">✳</span>
          <span class="intro-marquee-kicker"><?=e($introKicker)?></span><span class="intro-marquee-separator" aria-hidden="true">✳</span>
          <span class="intro-marquee-title"><?=e($introTitle)?></span><span class="intro-marquee-separator" aria-hidden="true">✳</span>
        </div>
        <div class="intro-marquee-group" aria-hidden="true">
          <span class="intro-marquee-kicker"><?=e($introKicker)?></span><span class="intro-marquee-separator">✳</span>
          <span class="intro-marquee-title"><?=e($introTitle)?></span><span class="intro-marquee-separator">✳</span>
          <span class="intro-marquee-kicker"><?=e($introKicker)?></span><span class="intro-marquee-separator">✳</span>
          <span class="intro-marquee-title"><?=e($introTitle)?></span><span class="intro-marquee-separator">✳</span>
        </div>
      </div>
    </div>
  </section>

  <?php if (!empty($home['packages_enabled'])): ?>
  <section class="section adventures wrap" id="adventures"><span id="experiences" style="position:relative;top:-90px;visibility:hidden;display:block;"></span>
    <div class="section-heading package-section-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span><?=e($home['adventures_eyebrow'] ?? 'UNLEASH ADVENTURE WITH')?></p><h2><?=e($home['adventures_title'] ?? 'BALI TARANTULA')?> <span class="orange"><?=e($home['adventures_accent'] ?? 'ATV')?></span></h2><p class="section-description"><?=e($home['intro_text'] ?? 'From jungle trails to rushing rivers, choose the experience that gets your heart racing.')?></p></div></div>
    <div class="package-slider-toolbar" aria-label="Package slider controls"><span class="package-slider-count">SCROLL TO EXPLORE</span><div class="package-slider-buttons"><button type="button" class="package-slider-btn" data-package-direction="prev" aria-label="Previous packages"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button><button type="button" class="package-slider-btn" data-package-direction="next" aria-label="Next packages"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div></div>
    <div class="adventure-grid package-grid package-slider" id="packageSlider" tabindex="0" aria-label="Adventure packages">
      <?php if ($packages): foreach ($packages as $packageIndex => $p): ?>
        <article class="adventure-card<?= $packageIndex === 0 ? ' card-featured' : '' ?>">
          <?php if (!empty($p['cover_image'])): ?><img src="<?=e(asset_url($p['cover_image']))?>" alt="<?=e($p['name'] ?? 'Bali adventure package')?>" loading="<?= $packageIndex < 3 ? 'eager' : 'lazy' ?>"><?php else: ?><img src="https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1200&q=85" alt="Bali adventure experience" loading="lazy"><?php endif; ?>
          <div class="card-shade"></div>
          <div class="package-card-badges"><span class="package-category-badge"><?=e(trim((string)($p['category_name'] ?? '')) ?: 'BALI ADVENTURE')?></span><?php if ($packageIndex === 0): ?><span class="package-best-seller"><span class="package-best-seller-dot" aria-hidden="true"></span>BEST SELLER</span><?php endif; ?></div>
          <div class="card-content"><h3><?=e($p['name'] ?? 'Adventure Package')?></h3><div class="package-card-prices"><?php $originalPrice = $packageOriginalPrices[(int)($p['id'] ?? 0)] ?? null; $currentPrice = (float)($p['price_adult'] ?? 0); if ($originalPrice !== null && $originalPrice > $currentPrice): ?><span class="package-price-before">Rp <?=number_format($originalPrice, 0, ',', '.')?></span><?php endif; ?><div class="package-price-current"><strong class="package-price-after">Rp <?=number_format($currentPrice, 0, ',', '.')?></strong><span class="package-price-unit">/ person</span></div></div><a class="card-link" href="<?=e(BASE_URL)?>/package/<?=e($p['slug'] ?? '')?>">EXPLORE PACKAGE <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span></a></div>
        </article>
      <?php endforeach; else: ?><p>No active adventure packages are available at the moment.</p><?php endif; ?>
    </div>
    <div class="package-slider-progress" aria-hidden="true"><div class="package-slider-progress-bar" id="packageSliderProgressBar"></div></div>
    <div class="section-foot"><span><?=count($packages)?> EXPERIENCES</span><span class="foot-line"></span><span>YOUR NEXT STORY IS OUT THERE.</span></div>
  </section>
  <?php endif; ?>

  <?php
    $storyImage = !empty($home['story_image']) ? $home['story_image'] : '';
    if (!$storyImage && !empty($gallery[1]['image_path'])) $storyImage = $gallery[1]['image_path'];
    if (!$storyImage && !empty($packages[0]['cover_image'])) $storyImage = $packages[0]['cover_image'];
  ?>
  <?php if (!empty($home['story_enabled'])): ?>
  <section class="manifesto" id="about">
    <div class="manifesto-image"<?= $storyImage ? ' style="background-image:url(\''.e(asset_url($storyImage)).'\')"' : '' ?>></div><div class="manifesto-overlay"></div>
    <div class="manifesto-content wrap"><p class="eyebrow"><span class="eyebrow-line"></span><?=e($home['story_kicker'] ?? 'WHY BALI TARANTULA')?></p><h2><?=e($home['story_title'] ?? 'ADVENTURE IS OUT THERE.')?></h2><p><?=e(trim((string)($home['story_text_1'] ?? '')) ?: 'Trade the ordinary for unforgettable outdoor experiences, wild landscapes, and authentic Bali hospitality.')?></p><?php if (!empty($home['story_text_2'])): ?><p><?=e($home['story_text_2'])?></p><?php endif; ?><a class="button button-outline" href="<?=e(BASE_URL)?>/about"><?=e($home['story_button_text'] ?? 'DISCOVER OUR STORY')?> <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span></a></div><span class="manifesto-side">NATURE · ADVENTURE · BALI</span>
  </section>
  <?php endif; ?>

  <?php if (!empty($home['values_enabled']) && $tracks): ?>
  <section class="section tracks-section" id="tracks"><div class="wrap"><div class="section-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span><?=e($home['values_kicker'] ?? 'PICK YOUR TERRAIN')?></p><h2><?=e($home['values_title'] ?? 'EXPLORE OUR')?> <span class="orange">TRACKS.</span></h2></div><p class="section-description"><?=e($home['values_text'] ?? 'Choose your route and get ready for mud, jungle, river crossings, and unforgettable Bali landscapes.')?></p></div>
    <div class="tracks-grid" id="tracksSlider">
      <?php foreach ($tracks as $trackIndex => $track): $difficultySlug = strtolower(trim((string)($track['difficulty'] ?? ''))); $difficultyClass = in_array($difficultySlug, ['easy'], true) ? 'track-difficulty-easy' : (in_array($difficultySlug, ['moderate','medium','mid'], true) ? 'track-difficulty-moderate' : (in_array($difficultySlug, ['challenging','challenge'], true) ? 'track-difficulty-challenging' : 'track-difficulty-extreme')); $difficultyLabel = in_array($difficultySlug, ['medium','mid'], true) ? 'MODERATE' : strtoupper($difficultySlug ?: 'MODERATE'); $trackDistance = trim((string)($track['distance'] ?? $track['distance_km'] ?? '')); ?>
      <article class="track-card"><img src="<?=e(!empty($track['image_path']) ? asset_url($track['image_path']) : 'https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&w=1000&q=85')?>" alt="<?=e($track['name'] ?? 'Adventure track')?>" loading="lazy"><div class="track-overlay"></div><span class="track-index">TRACK / <?=sprintf('%02d',$trackIndex+1)?></span><span class="track-badge <?=$difficultyClass?>"><span><?=e($difficultyLabel)?></span></span><div class="track-info"><h3><?=e($track['name'] ?? 'Adventure Track')?></h3><span class="track-desc"><?=e($track['description'] ?? 'Explore Bali on an unforgettable adventure route.')?></span><div class="track-meta"><span class="track-meta-item"><i class="fa-regular fa-clock" aria-hidden="true"></i><?=e(trim((string)($track['duration'] ?? '')) ?: 'Duration on request')?></span><?php if ($trackDistance !== ''): ?><span class="track-meta-item track-meta-distance" aria-label="Distance <?=e($trackDistance)?>"><i class="fa-solid fa-route" aria-hidden="true"></i><span><?=e($trackDistance)?></span></span><?php else: ?><span class="track-meta-item track-meta-distance" aria-label="Distance"><i class="fa-solid fa-route" aria-hidden="true"></i></span><?php endif; ?></div></div></article>
      <?php endforeach; ?>
    </div><div class="tracks-slider-progress" aria-label="Track slider progress"><div class="tracks-slider-progress-bar" id="tracksSliderProgressBar"></div></div><div class="tracks-note"><span class="tracks-note-mark">!</span><p>Every track offers a different way to experience Bali. Contact our team if you need help choosing the right adventure.</p></div>
  </div></section>
  <?php endif; ?>

  <?php if (!empty($home['why_enabled'])): ?>
  <section class="section why-us"><div class="wrap"><div class="section-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span><?=e($home['why_kicker'] ?? 'WHY CHOOSE US')?></p><h2><?=e($home['why_title'] ?? 'BUILT FOR THE')?> <span class="orange">WILD.</span></h2></div><p class="section-description"><?=e($home['why_text'] ?? 'A local team, thoughtful service, and experiences designed to make every Bali adventure memorable.')?></p></div>
    <div class="reasons">
      <?php for ($wi=1; $wi<=4; $wi++): $whyTitle = trim((string)($home['why_'.$wi.'_title'] ?? '')); $whyText = trim((string)($home['why_'.$wi.'_text'] ?? '')); $whyIcon = trim((string)($home['why_'.$wi.'_icon'] ?? '')); $whyIconFallback = ['fa-solid fa-water','fa-solid fa-shield-heart','fa-solid fa-mountain-sun','fa-solid fa-people-group'][$wi-1]; ?>
      <article><span class="reason-no">0<?=$wi?></span><div class="reason-icon"><i class="<?=e($whyIcon ?: $whyIconFallback)?>" aria-hidden="true"></i></div><h3><?=e($whyTitle ?: ['Adventure Ready','Safety First','Local Experience','Memorable Moments'][$wi-1])?></h3><p><?=e($whyText ?: ['Discover the best of Bali through exciting outdoor activities.','Our team puts guest comfort and clear activity guidance first.','Experience Bali with a friendly local team.','Make memories worth taking home.'][$wi-1])?></p></article>
      <?php endfor; ?>
    </div></div></section>
  <?php endif; ?>

  <?php if (!empty($home['stats_enabled'])): ?><section class="stats-strip"><div class="wrap stats-grid"><?php for ($si=1; $si<=4; $si++): ?><div class="stat-item"><strong><?=e($home['stat_'.$si.'_number'] ?? ['5+','50K+','10+','100%'][$si-1])?></strong><span><?=e($home['stat_'.$si.'_label'] ?? ['Years of Experience','Happy Visitors','Adventure Experiences','Guest Satisfaction'][$si-1])?></span></div><?php endfor; ?></div></section><?php endif; ?>

  <?php if (!empty($home['reviews_enabled']) && $reviews): ?>
  <section class="reviews-section" style="background-image:url('<?=e(asset_url($reviewsBg))?>')"><div class="wrap"><div class="section-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span><?=e($home['reviews_kicker'] ?? 'GUEST STORIES')?></p><h2><?=e($home['reviews_title'] ?? 'REAL STORIES.')?> <span class="orange">REAL ADVENTURE.</span></h2></div><p class="section-description"><?=e($home['reviews_text'] ?? 'See what fellow adventurers have to say about their Bali experience.')?></p></div>
    <div class="review-grid" id="reviewsSlider" tabindex="0" aria-label="Guest reviews slider"><?php foreach (array_slice($reviews, 0, 6) as $review): $rating = max(1,min(5,(int)($review['rating'] ?? 5))); $reviewPhoto = trim((string)($review['image'] ?? $review['customer_photo'] ?? $review['photo'] ?? $review['avatar'] ?? $review['image_path'] ?? '')); ?><article class="review-card"><div class="review-card-top"><span class="review-quote" aria-hidden="true">“</span><div class="review-stars" aria-label="<?=$rating?> out of 5 stars"><?php for($s=1;$s<=5;$s++): ?><i class="<?=$s<=$rating?'fa-solid fa-star':'fa-regular fa-star'?>" aria-hidden="true"></i><?php endfor; ?></div></div><blockquote><?=e($review['review_text'] ?? '')?></blockquote><footer><?php if ($reviewPhoto !== ''): ?><img class="review-avatar" src="<?=e(asset_url($reviewPhoto))?>" alt="<?=e($review['customer_name'] ?? 'Guest')?>" loading="lazy"><?php else: ?><span class="review-avatar review-avatar-fallback" aria-hidden="true"><i class="fa-solid fa-user"></i></span><?php endif; ?><div class="review-author"><?=e($review['customer_name'] ?? 'Guest')?><span>Our Customers</span></div></footer></article><?php endforeach; ?></div><div class="review-slider-progress" aria-label="Review slider progress"><div class="review-slider-progress-bar" id="reviewsSliderProgressBar"></div></div><p class="review-note">Reviews shown are from the website's existing guest review records.</p>
  </div></section>
  <?php endif; ?>

  <?php if ((int)($home['gallery_enabled'] ?? 1) === 1 && $gallery): $galleryItems = array_values(array_filter($gallery, static fn($item) => !empty($item['image_path']))); $galleryTitle = trim((string)($home['gallery_title'] ?? 'ADVENTURE MOMENTS')) ?: 'ADVENTURE MOMENTS'; $galleryButton = trim((string)($home['gallery_button_text'] ?? 'VIEW FULL GALLERY')) ?: 'VIEW FULL GALLERY'; $galleryButtonUrl = trim((string)($home['gallery_button_url'] ?? '')); if ($galleryButtonUrl === '') $galleryButtonUrl = BASE_URL.'/gallery'; elseif (!preg_match('~^(https?://|/)~i', $galleryButtonUrl)) $galleryButtonUrl = BASE_URL.'/'.ltrim($galleryButtonUrl,'/'); ?>
  <section class="home-gallery-section" id="gallery"><div class="wrap"><div class="section-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span><?=e($home['gallery_kicker'] ?? 'CAPTURE THE MOMENT')?></p><h2><?=e($galleryTitle)?></h2></div><p class="section-description">A glimpse of the trails, landscapes, and memories waiting for you in Bali.</p></div><div class="gallery-grid"><?php foreach (array_slice($galleryItems,0,6) as $gi => $photo): ?><a href="<?=e($galleryButtonUrl)?>"><img src="<?=e(asset_url($photo['image_path']))?>" alt="<?=e($photo['alt_text'] ?? 'Bali adventure moment')?>" loading="lazy"><span><?=e($photo['title'] ?? ('ADVENTURE MOMENT '.sprintf('%02d',$gi+1)))?></span></a><?php endforeach; ?></div><div class="gallery-action"><a class="button button-dark" href="<?=e($galleryButtonUrl)?>"><?=e($galleryButton)?> <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span></a></div></div></section>
  <?php endif; ?>



  <?php if (!empty($home['cta_enabled'])): $ctaBackgroundPath = trim((string)($home['cta_bg_image'] ?? $home['cta_background_image'] ?? $home['cta_image'] ?? '')); $ctaBackgroundUrl = $ctaBackgroundPath !== '' ? asset_url($ctaBackgroundPath) : ''; ?><section class="booking-cta"<?= $ctaBackgroundUrl !== '' ? ' style="background-image:url(\''.e($ctaBackgroundUrl).'\');background-size:cover;background-position:center;background-repeat:no-repeat;"' : '' ?>><div class="cta-inner"><p class="eyebrow"><span class="eyebrow-line"></span><?=e($home['cta_kicker'] ?? 'YOUR NEXT STORY STARTS HERE')?><span class="eyebrow-line"></span></p><h2><?=e($home['cta_title'] ?? 'READY TO GET')?> <span><?=e($home['cta_accent'] ?? 'WILD?')?></span></h2><p><?=e($home['cta_text'] ?? 'Your Bali adventure is waiting. Choose your experience and make it unforgettable.')?></p><a class="button button-dark" href="<?=e(BASE_URL)?>/packages"><?=e($home['cta_button_text'] ?? 'BOOK YOUR ADVENTURE')?> <span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span></a><small>ATV · RAFTING · NATURE · ADVENTURE</small></div></section><?php endif; ?>
</div>
<script>
/* Supplied main.js behavior adapted to the legacy shared header markup. */
(function(){
  var header=document.querySelector('.site-header');
  if(header){var update=function(){header.classList.toggle('is-scrolled',window.scrollY>40);};update();window.addEventListener('scroll',update,{passive:true});}
  var nav=document.getElementById('mainNav'),toggle=document.getElementById('navBtn');
  if(nav&&toggle){nav.addEventListener('click',function(ev){if(ev.target.closest('a'))document.body.classList.remove('menu-open');});toggle.addEventListener('click',function(){document.body.classList.toggle('menu-open');});}

  var packageSlider=document.getElementById('packageSlider');
  if(packageSlider){
    var progressBar=document.getElementById('packageSliderProgressBar');
    var updatePackageProgress=function(){
      if(!progressBar)return;
      var maxScroll=packageSlider.scrollWidth-packageSlider.clientWidth;
      var progress=maxScroll>0?(packageSlider.scrollLeft/maxScroll)*100:100;
      progressBar.style.width=Math.max(0,Math.min(100,progress))+'%';
    };
    packageSlider.addEventListener('scroll',updatePackageProgress,{passive:true});
    window.addEventListener('resize',updatePackageProgress,{passive:true});
    updatePackageProgress();
    document.querySelectorAll('[data-package-direction]').forEach(function(button){
      button.addEventListener('click',function(){
        var firstCard=packageSlider.querySelector('.adventure-card');
        if(!firstCard)return;
        var gap=parseFloat(getComputedStyle(packageSlider).columnGap)||14;
        var distance=Math.max(firstCard.getBoundingClientRect().width+gap,packageSlider.clientWidth*.8);
        packageSlider.scrollBy({left:(button.getAttribute('data-package-direction')==='next'?distance:-distance),behavior:'smooth'});
      });
    });
  }
  var reviewsSlider=document.getElementById('reviewsSlider');
  if(reviewsSlider){
    var reviewsProgressBar=document.getElementById('reviewsSliderProgressBar');
    var updateReviewsProgress=function(){
      if(!reviewsProgressBar)return;
      var maxScroll=Math.max(0,reviewsSlider.scrollWidth-reviewsSlider.clientWidth);
      var visibleRatio=reviewsSlider.scrollWidth>0?reviewsSlider.clientWidth/reviewsSlider.scrollWidth:1;
      var thumbWidth=Math.max(12,Math.min(100,visibleRatio*100));
      var travel=100-thumbWidth;
      var progress=maxScroll>0?(reviewsSlider.scrollLeft/maxScroll)*travel:0;
      reviewsProgressBar.style.width=thumbWidth+'%';
      reviewsProgressBar.style.left=progress+'%';
    };
    var reviewDrag=false,reviewStartX=0,reviewStartScroll=0;
    reviewsSlider.addEventListener('pointerdown',function(ev){if(ev.pointerType==='mouse'&&ev.button===0){reviewDrag=true;reviewStartX=ev.clientX;reviewStartScroll=reviewsSlider.scrollLeft;reviewsSlider.setPointerCapture(ev.pointerId);}}, {passive:true});
    reviewsSlider.addEventListener('pointermove',function(ev){if(reviewDrag)reviewsSlider.scrollLeft=reviewStartScroll-(ev.clientX-reviewStartX);});
    reviewsSlider.addEventListener('pointerup',function(){reviewDrag=false;});
    reviewsSlider.addEventListener('pointercancel',function(){reviewDrag=false;});
    reviewsSlider.addEventListener('scroll',updateReviewsProgress,{passive:true});
    window.addEventListener('resize',updateReviewsProgress,{passive:true});
    updateReviewsProgress();
  }

  var tracksSlider=document.getElementById('tracksSlider');
  if(tracksSlider){
    var tracksProgressBar=document.getElementById('tracksSliderProgressBar');
    var updateTracksProgress=function(){
      if(!tracksProgressBar)return;
      var maxScroll=tracksSlider.scrollWidth-tracksSlider.clientWidth;
      var progress=maxScroll>0?(tracksSlider.scrollLeft/maxScroll)*100:100;
      tracksProgressBar.style.width=Math.max(0,Math.min(100,progress))+'%';
    };
    tracksSlider.addEventListener('scroll',updateTracksProgress,{passive:true});
    window.addEventListener('resize',updateTracksProgress,{passive:true});
    updateTracksProgress();
  }
  var hero=document.getElementById('btaHeroImage');
  var slides=<?=json_encode(array_values(array_map(static fn($slide) => $slide['url'], $heroSlides)), JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP)?>;
  var dots=Array.from(document.querySelectorAll('[data-bta-hero-dot]'));var current=0;var timer=null;
  function showSlide(index){if(!hero||!slides.length)return;current=(index+slides.length)%slides.length;hero.style.backgroundImage='url("'+String(slides[current]).replace(/["\\]/g,'\\$&')+'")';dots.forEach(function(dot,i){dot.classList.toggle('active',i===current);});}
  if(hero&&slides.length>1){dots.forEach(function(dot){dot.addEventListener('click',function(){showSlide(Number(dot.getAttribute('data-bta-hero-dot')));clearInterval(timer);timer=setInterval(function(){showSlide(current+1);},5500);});});timer=setInterval(function(){showSlide(current+1);},5500);}
})();
</script>

</main>
<?php require __DIR__.'/includes/footer.php'; ?>
