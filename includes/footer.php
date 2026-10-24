<?php
if (!function_exists('bta_content')) require_once __DIR__ . '/cms.php';
$footerText = static fn(string $key, string $fallback): string => htmlspecialchars(bta_content($key, $fallback), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<footer class="site-footer"><div class="wrap footer-main"><a class="brand" href="index.php"><span class="brand-mark">BT<span>A</span></span><span class="brand-copy">BALI TARANTULA<small>ADVENTURE</small></span></a><p><?= $footerText('footer_description','GO BEYOND THE ORDINARY.') ?><br><span><?= $footerText('footer_subdescription','EXPERIENCE THE WILD SIDE OF BALI.') ?></span></p><a class="back-top" href="#top">BACK TO TOP ↑</a></div><div class="wrap footer-bottom"><span>© <span id="year">2026</span> <?= $footerText('footer_bottom_left','BALI TARANTULA ADVENTURE') ?></span><span><?= $footerText('footer_bottom_right','MADE FOR THE WILD.') ?></span></div></footer>
