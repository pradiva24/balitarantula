<?php
require __DIR__.'/config/database.php';
require __DIR__.'/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');
$base=rtrim((string)setting('site_url',BASE_URL),'/');

$urls=[];
$add=function(string $path,string $change='weekly',?string $lastmod=null) use (&$urls,$base){
    $loc=$path==='/'?$base.'/':$base.'/'.ltrim($path,'/');
    $urls[]=['loc'=>$loc,'change'=>$change,'lastmod'=>$lastmod];
};
$add('/','daily');
$add('/about','monthly');
$add('/packages','weekly');
$add('/gallery','weekly');
$add('/contact-us','monthly');
$add('/review','weekly');
$add('/blog','weekly');

try{
    foreach(db()->query("SELECT slug,updated_at FROM pages WHERE status=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r){
        $k='page:'.trim((string)$r['slug']);
        $s=db()->prepare("SELECT robots FROM seo_meta WHERE page_key=? LIMIT 1");$s->execute([$k]);
        $robots=(string)($s->fetchColumn()??'index,follow');
        if(stripos($robots,'noindex')!==false) continue;
        $add('/page/'.rawurlencode((string)$r['slug']),'monthly',!empty($r['updated_at'])?date('c',strtotime($r['updated_at'])):null);
    }
    foreach(db()->query("SELECT slug,updated_at FROM packages WHERE status=1 ORDER BY homepage_order ASC,id ASC")->fetchAll(PDO::FETCH_ASSOC) as $r){
        $k='package:'.trim((string)$r['slug']);
        $s=db()->prepare("SELECT robots FROM seo_meta WHERE page_key=? LIMIT 1");$s->execute([$k]);
        $robots=(string)($s->fetchColumn()??'index,follow');
        if(stripos($robots,'noindex')!==false) continue;
        $add('/package/'.rawurlencode((string)$r['slug']),'weekly',!empty($r['updated_at'])?date('c',strtotime($r['updated_at'])):null);
    }
    foreach(db()->query("SELECT slug,updated_at FROM blog_posts WHERE status='published' ORDER BY published_at DESC,id DESC")->fetchAll(PDO::FETCH_ASSOC) as $r){
        $k='blog:'.trim((string)$r['slug']);
        $s=db()->prepare("SELECT robots FROM seo_meta WHERE page_key=? LIMIT 1");$s->execute([$k]);
        $robots=(string)($s->fetchColumn()??'index,follow');
        if(stripos($robots,'noindex')!==false) continue;
        $add('/blog/'.rawurlencode((string)$r['slug']),'weekly',!empty($r['updated_at'])?date('c',strtotime($r['updated_at'])):null);
    }
}catch(Throwable $e){}

echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
foreach($urls as $u){
    echo '<url><loc>'.htmlspecialchars($u['loc'],ENT_XML1,'UTF-8').'</loc>';
    if($u['lastmod']) echo '<lastmod>'.htmlspecialchars($u['lastmod'],ENT_XML1,'UTF-8').'</lastmod>';
    echo '<changefreq>'.htmlspecialchars($u['change'],ENT_XML1,'UTF-8').'</changefreq></url>'."\n";
}
echo '</urlset>';
