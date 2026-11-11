<?php
require __DIR__.'/config/database.php';
require __DIR__.'/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

/*
 * Use the public canonical domain directly so a stale settings.site_url value
 * cannot publish sitemap URLs for another website.
 */
$base = 'https://balitarantula.com';

$urls = [];
$add = function(string $path, string $change='weekly', ?string $lastmod=null) use (&$urls, $base) {
    $loc = $path === '/' ? $base.'/' : $base.'/'.ltrim($path, '/');
    $urls[] = ['loc'=>$loc, 'change'=>$change, 'lastmod'=>$lastmod];
};

$add('/', 'daily');
$add('/about', 'monthly');
$add('/packages', 'weekly');
$add('/gallery', 'weekly');
$add('/contact-us', 'monthly');
$add('/review', 'weekly');
$add('/blog', 'weekly');

try {
    foreach (db()->query("SELECT slug,updated_at FROM pages WHERE status=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $slug = trim((string)$r['slug']);
        if ($slug === '') continue;
        $key = 'page:'.$slug;
        $st = db()->prepare("SELECT robots FROM seo_meta WHERE page_key=? LIMIT 1");
        $st->execute([$key]);
        $robots = (string)($st->fetchColumn() ?: 'index,follow');
        if (stripos($robots, 'noindex') !== false) continue;
        $lastmod = !empty($r['updated_at']) ? date('c', strtotime((string)$r['updated_at'])) : null;
        $add('/page/'.rawurlencode($slug), 'monthly', $lastmod);
    }
} catch (Throwable $e) {
    error_log('Sitemap pages query failed: '.$e->getMessage());
}

try {
    foreach (db()->query("SELECT slug,updated_at FROM packages WHERE status=1 ORDER BY homepage_order ASC,id ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $slug = trim((string)$r['slug']);
        if ($slug === '') continue;
        $key = 'package:'.$slug;
        $st = db()->prepare("SELECT robots FROM seo_meta WHERE page_key=? LIMIT 1");
        $st->execute([$key]);
        $robots = (string)($st->fetchColumn() ?: 'index,follow');
        if (stripos($robots, 'noindex') !== false) continue;
        $lastmod = !empty($r['updated_at']) ? date('c', strtotime((string)$r['updated_at'])) : null;
        $add('/package/'.rawurlencode($slug), 'weekly', $lastmod);
    }
} catch (Throwable $e) {
    error_log('Sitemap packages query failed: '.$e->getMessage());
}

try {
    foreach (db()->query("SELECT slug,updated_at FROM blog_posts WHERE status='published' ORDER BY published_at DESC,id DESC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $slug = trim((string)$r['slug']);
        if ($slug === '') continue;
        $key = 'blog:'.$slug;
        $st = db()->prepare("SELECT robots FROM seo_meta WHERE page_key=? LIMIT 1");
        $st->execute([$key]);
        $robots = (string)($st->fetchColumn() ?: 'index,follow');
        if (stripos($robots, 'noindex') !== false) continue;
        $lastmod = !empty($r['updated_at']) ? date('c', strtotime((string)$r['updated_at'])) : null;
        $add('/blog/'.rawurlencode($slug), 'weekly', $lastmod);
    }
} catch (Throwable $e) {
    error_log('Sitemap blog query failed: '.$e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
foreach ($urls as $url) {
    echo '<url><loc>'.htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8').'</loc>';
    if ($url['lastmod']) echo '<lastmod>'.htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8').'</lastmod>';
    echo '<changefreq>'.htmlspecialchars($url['change'], ENT_XML1, 'UTF-8').'</changefreq></url>'."\n";
}
echo '</urlset>';
