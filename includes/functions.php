<?php
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rupiah($amount): string {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

function slugify($text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    return trim($text, '-');
}

function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function site_brand_name(string $default='Manuaba Crystalfall'): string {
    $name = trim((string)setting('site_name', $default));
    if ($name === '') $name = $default;
    return $name;
}

function brand_replace_legacy(string $text): string {
    $brand = site_brand_name();
    if ($brand === '') return $text;
    return str_ireplace([
        'Bali Tarantula Adventure',
        'Tarantula Adventure',
        'Aloha Ubud Swing',
        'Aloha Ubud'
    ], $brand, $text);
}

function brand_output_buffer(string $buffer): string {
    return brand_replace_legacy($buffer);
}

function setting(string $key, $default='') {
    static $cache=null;
    if($cache===null){
        try { $cache=[]; foreach(db()->query("SELECT setting_key,setting_value FROM settings")->fetchAll() as $r) $cache[$r['setting_key']]=$r['setting_value']; }
        catch(Throwable $e){ $cache=[]; }
    }
    return array_key_exists($key,$cache)?$cache[$key]:$default;
}
require_once __DIR__.'/icon-system.php';

function asset_url(string $path): string {
    $path=trim($path);
    if($path==='') return '';
    if(preg_match('~^https?://~i',$path)) return $path;
    return rtrim(BASE_URL,'/').'/'.ltrim($path,'/');
}

/** Responsive image helpers. Generated variants are stored under uploads/_variants and are not Media Library items. */
function bt_media_variant_relative(string $path, int $width): string {
    $path = trim((string)(parse_url($path, PHP_URL_PATH) ?: $path));
    $path = ltrim($path, '/');
    if ($path === '' || stripos($path, 'uploads/') !== 0 || stripos($path, 'uploads/_variants/') === 0) return '';
    $rel = substr($path, 8);
    $info = pathinfo($rel);
    if (empty($info['filename'])) return '';
    $dir = trim((string)($info['dirname'] ?? ''), './\\');
    return 'uploads/_variants/'.($dir !== '' ? $dir.'/' : '').$info['filename'].'-'.$width.'w.webp';
}
function bt_media_variant_exists(string $path, int $width): bool {
    $v = bt_media_variant_relative($path, $width);
    return $v !== '' && is_file(__DIR__.'/../'.$v);
}
function bt_responsive_srcset(string $path, array $widths=[480,768,1024,1440,1920]): string {
    $out=[];
    foreach ($widths as $w) {
        $w=(int)$w;
        if ($w>0 && bt_media_variant_exists($path,$w)) $out[]=asset_url(bt_media_variant_relative($path,$w)).' '.$w.'w';
    }
    return implode(', ',$out);
}
function bt_responsive_sizes(string $context='content'): string {
    if ($context==='hero') return '100vw';
    if ($context==='card') return '(max-width: 600px) 92vw, (max-width: 1000px) 45vw, 31vw';
    if ($context==='gallery') return '(max-width: 700px) 50vw, (max-width: 1024px) 33vw, 25vw';
    return '(max-width: 700px) 92vw, (max-width: 1100px) 80vw, 60vw';
}
function bt_responsive_img_attrs(string $path, string $context='content'): string {
    $srcset=bt_responsive_srcset($path);
    return $srcset!=='' ? ' srcset="'.e($srcset).'" sizes="'.e(bt_responsive_sizes($context)).'"' : '';
}

function whatsapp_url(string $number,string $text=''): string {
    $n=preg_replace('/\D+/','',$number);
    return 'https://wa.me/'.$n.($text!==''?'?text='.rawurlencode($text):'');
}


/** Central SEO helpers used by the public header and Admin > SEO. */
function seo_current_page_key(): string {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = rawurldecode((string)(parse_url($uri, PHP_URL_PATH) ?: '/'));
    $siteUrl = (string)setting('site_url', BASE_URL);
    $basePath = trim((string)(parse_url($siteUrl, PHP_URL_PATH) ?: ''), '/');
    if ($basePath !== '') {
        $prefix = '/'.$basePath;
        if ($path === $prefix) $path = '/';
        elseif (strpos($path, $prefix.'/') === 0) $path = substr($path, strlen($prefix));
    }
    $path = '/'.trim(preg_replace('~/+~', '/', $path), '/');
    if ($path === '//') $path = '/';

    $static = [
        '/' => 'home',
        '/about' => 'about', '/about.php' => 'about',
        '/packages' => 'packages', '/packages.php' => 'packages',
        '/gallery' => 'gallery', '/gallery.php' => 'gallery',
        '/contact' => 'contact', '/contact-us' => 'contact', '/contact.php' => 'contact',
        '/review' => 'review', '/review.php' => 'review',
        '/blog' => 'blog', '/blog.php' => 'blog',
        '/booking' => 'booking', '/booking.php' => 'booking',
        '/booking-success' => 'booking-success', '/booking-success.php' => 'booking-success',
        '/booking-confirmation' => 'booking-confirmation', '/booking-confirmation.php' => 'booking-confirmation',
        '/my-booking' => 'my-booking', '/my-booking.php' => 'my-booking',
        '/payment' => 'payment', '/payment.php' => 'payment',
        '/midtrans-checkout' => 'midtrans-checkout', '/midtrans-checkout.php' => 'midtrans-checkout',
        '/404' => '404', '/404.php' => '404',
        '/sitemap' => 'sitemap', '/sitemap.php' => 'sitemap',
    ];
    if (isset($static[$path])) return $static[$path];

    if (preg_match('~^/page/([^/]+)$~i', $path, $m) && trim($m[1])!=='') {
        return 'page:'.trim($m[1]);
    }
    if ($path === '/page.php') {
        $slug=trim((string)($_GET['slug']??''));
        return $slug!==''?'page:'.$slug:'page';
    }
    if (http_response_code() === 404) return '404';
    return '';
}

function seo_get_record(string $pageKey): array {
    static $cache=[];
    if ($pageKey==='') return [];
    if (array_key_exists($pageKey,$cache)) return $cache[$pageKey];
    try {
        $st=db()->prepare("SELECT * FROM seo_meta WHERE page_key=? LIMIT 1");
        $st->execute([$pageKey]);
        return $cache[$pageKey]=$st->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch(Throwable $e) { return $cache[$pageKey]=[]; }
}

function seo_value(array $row,string $key,$fallback='') {
    $v=trim((string)($row[$key]??''));
    return $v!==''?$v:$fallback;
}

function seo_absolute_asset(string $value): string {
    $value=trim($value);
    if($value==='') return '';
    if(preg_match('~^https?://~i',$value)) return $value;
    if(strpos($value,'//')===0){
        $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https:':'http:';
        return $scheme.$value;
    }
    return asset_url($value);
}

function seo_normalize_canonical(string $url): string {
    $url=trim($url);
    if($url==='') return '';
    if(!preg_match('~^https?://~i',$url)) $url=rtrim((string)setting('site_url',BASE_URL),'/').'/'.ltrim($url,'/');
    $p=parse_url($url);
    if(!$p||empty($p['scheme'])||empty($p['host'])) return $url;
    $scheme=strtolower($p['scheme']); $host=strtolower($p['host']); $port=isset($p['port'])?':'.$p['port']:'';
    $path='/'.trim(preg_replace('~/+~','/',$p['path']??'/'),'/');
    if($path==='//') $path='/';
    if($path!=='/') $path=rtrim($path,'/');
    return $scheme.'://'.$host.$port.$path;
}

function seo_json_is_valid(string $json): bool {
    if(trim($json)==='') return true;
    json_decode($json,true);
    return json_last_error()===JSON_ERROR_NONE;
}
