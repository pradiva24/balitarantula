<?php
require_once __DIR__ . '/cms.php';
require_once __DIR__ . '/site-settings.php';
$headerText = static fn(string $key, string $fallback): string => htmlspecialchars(bta_content($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$siteTitle = bta_setting('site_title', 'Bali Tarantula Adventure');
$siteLogo = bta_setting('site_logo', '');
if (!preg_match('~^/assets/uploads/site/logo-[a-f0-9]+\.(?:jpg|png|webp)$~i', $siteLogo)) $siteLogo = '';
?>
<div class="topline"><span><?= $headerText('topline_left','BUILT FOR THE WILD') ?></span><span><?= $headerText('topline_right','BALI, INDONESIA') ?> <span class="orange">●</span></span></div>
<header class="site-header"><a class="brand" href="index.php" aria-label="<?=e($siteTitle)?> home"><?php if($siteLogo!==''):?><img src="<?=e($siteLogo)?>" alt="<?=e($siteTitle)?>" class="site-brand-logo"><?php else:?><span class="brand-mark">BT<span>A</span></span><?php endif;?><span class="brand-copy"><?=e(bta_setting('site_title','BALI TARANTULA'))?><small>ADVENTURE</small></span></a><button class="menu-toggle" aria-label="Open navigation" aria-expanded="false"><span></span><span></span><span></span></button><nav class="main-nav"><a href="index.php"><?= $headerText('nav_home','Home') ?></a><a href="about.php"><?= $headerText('nav_about','About Us') ?></a><a href="packages.php"><?= $headerText('nav_packages','Packages') ?></a><a href="blog.php"><?= $headerText('nav_blog','Blog') ?></a><a href="gallery.php"><?= $headerText('nav_gallery','Gallery') ?></a><a href="contact.php"><?= $headerText('nav_contact','Contact') ?></a><a class="nav-cta" href="booking.php"><?= $headerText('nav_cta','BOOK NOW') ?> ↗</a></nav></header>
