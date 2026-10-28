<?php
require __DIR__.'/config/database.php';
header('Content-Type: text/plain; charset=utf-8');
$base=rtrim((string)setting('site_url',BASE_URL),'/');
echo "User-agent: *\nAllow: /\n\n";
echo "Disallow: /admin/\nDisallow: /config/\nDisallow: /database/\nDisallow: /includes/\nDisallow: /admin/includes/\nDisallow: /uploads/payment-proofs/\n";
echo "Disallow: /setup_v11.php\nDisallow: /setup_v17.php\n\n";
echo "Sitemap: ".$base."/sitemap\n";
