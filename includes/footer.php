<?php
require_once __DIR__ . '/site-settings.php';
$footerText = static fn(string $key, string $fallback): string => htmlspecialchars(bta_content($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$footerSocial = [
 'Instagram'=>'social_instagram','Facebook'=>'social_facebook','TikTok'=>'social_tiktok',
 'YouTube'=>'social_youtube','Tripadvisor'=>'social_tripadvisor','Google Maps'=>'social_google_maps'
];
$footerContacts = [
 'Email'=>['mailto:',bta_setting('contact_email')],
 'Phone'=>['tel:',bta_setting('contact_phone')],
];
$footerWhatsApp = preg_replace('/\D+/', '', bta_setting('contact_whatsapp'));
?>
<footer class="site-footer"><div class="wrap footer-main"><a class="brand" href="index.php"><?php $footerLogo=bta_setting('site_logo');if(preg_match('~^/assets/uploads/site/logo-[a-f0-9]+\.(?:jpg|png|webp|svg)$~i',$footerLogo)):?><img src="<?=e($footerLogo)?>" alt="<?=e(bta_setting('site_title','Bali Tarantula Adventure'))?>" class="site-brand-logo"><?php else:?><span class="brand-mark">BT<span>A</span></span><?php endif;?><span class="brand-copy"><?=e(bta_setting('site_title','BALI TARANTULA'))?><small>BALI, INDONESIA</small></span></a><p><?= $footerText('footer_description','GO BEYOND THE ORDINARY.') ?><br><span><?= $footerText('footer_subdescription','EXPERIENCE THE WILD SIDE OF BALI.') ?></span></p><div class="footer-settings-links"><?php foreach($footerContacts as $label=>$item):if(trim($item[1])!==''):?><a href="<?=e($item[0].$item[1])?>"><?=e($label)?>: <?=e($item[1])?></a><?php endif;endforeach;?><?php if($footerWhatsApp!==''):?><a href="https://wa.me/<?=e($footerWhatsApp)?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif;?><?php $address=bta_setting('contact_address');if($address!==''):?><span><?=e($address)?></span><?php endif;?><?php $hours=bta_setting('contact_hours');if($hours!==''):?><span><?=e($hours)?></span><?php endif;?></div><div class="footer-socials"><?php foreach($footerSocial as $label=>$key):$url=bta_setting_public_url($key);if($url!==''):?><a href="<?=e($url)?>" target="_blank" rel="noopener noreferrer"><?=e($label)?> ↗</a><?php endif;endforeach;?></div><a class="back-top" href="#top">BACK TO TOP ↑</a></div><div class="wrap footer-bottom"><span>© <span id="year">2026</span> <?= $footerText('footer_bottom_left','BALI TARANTULA ADVENTURE') ?></span><span><?= $footerText('footer_bottom_right','MADE FOR THE WILD.') ?></span></div></footer>
