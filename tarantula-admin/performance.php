<?php
require __DIR__.'/includes/auth.php';
$page_title='SEO & Performance';
require __DIR__.'/includes/header.php';
?>
<div class="grid2 section">
<div class="card"><h2>SEO Checklist</h2>
<ul class="checklist">
<li>Unique title and meta description on important pages</li><li>Canonical URLs enabled</li><li>Open Graph metadata enabled</li><li>JSON-LD TravelAgency schema enabled</li><li>XML sitemap and robots.txt should be reachable</li><li>Compress hero images and serve WebP/AVIF where possible</li>
</ul></div>
<div class="card"><h2>Performance Checklist</h2>
<ul class="checklist"><li>Enable browser caching for static assets</li><li>Keep CSS/JS minimal on the public frontend</li><li>Lazy-load below-the-fold images</li><li>Set width/height on images to reduce layout shift</li><li>Use HTTPS in production</li><li>Keep WordPress-style admin/install files out of production (this project is native PHP)</li></ul></div>
</div>
<div class="card section"><h2>Production Security</h2><p>Use strong admin passwords, HTTPS, protected uploads, PDO prepared statements, CSRF tokens and least-privilege database credentials. Never place gateway server keys in public JavaScript.</p></div>
<?php require __DIR__.'/includes/footer.php'; ?>