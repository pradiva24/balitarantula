<?php
require __DIR__.'/includes/auth.php';
if(($_SESSION['admin_role']??'admin')!=='admin'){http_response_code(403);exit('Forbidden');}
$checks=[];
$checks[]=['HTTPS',!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','Use HTTPS on production.'];
$checks[]=['PHP version',version_compare(PHP_VERSION,'8.0.0','>='),PHP_VERSION];
$checks[]=['PDO MySQL',extension_loaded('pdo_mysql'),'Required for database access.'];
$checks[]=['OpenSSL',extension_loaded('openssl'),'Required for TLS and secure integrations.'];
$checks[]=['cURL',extension_loaded('curl'),'Required for payment/API integrations.'];
$checks[]=['GD or Imagick',extension_loaded('gd')||class_exists('Imagick'),'Required for safe image processing.'];
$checks[]=['Uploads protected',is_file(__DIR__.'/../uploads/.htaccess'),'Uploads should block script execution.'];
$checks[]=['Payment proofs private',is_file(__DIR__.'/../uploads/payment-proofs/.htaccess'),'Payment proof images should not be directly public.'];
$checks[]=['Config protected',is_file(__DIR__.'/../config/.htaccess') || (is_file(__DIR__.'/../.htaccess') && (bool)preg_match('/(?:^|\n)\s*<Directory \"config\">/i',file_get_contents(__DIR__.'/../.htaccess'))),'Application config must not be directly downloadable.'];
$setup=glob(__DIR__.'/../setup*.php')?:[];
$checks[]=['Setup files removed',count($setup)===0,count($setup).' setup file(s) remain. Delete them after migrations.'];
$checks[]=['Diagnostic blocked',!(is_file(__DIR__.'/../check-image.php') && is_file(__DIR__.'/../.htaccess') && preg_match('/check-image\.php/i',file_get_contents(__DIR__.'/../.htaccess'))===0),'Remove or block check-image.php.'];
$checks[]=['Display errors',!(bool)ini_get('display_errors'),'Disable display_errors on production.'];
$checks[]=['Session cookies',ini_get('session.cookie_httponly')==='1' && ini_get('session.use_strict_mode')==='1','HttpOnly and strict session cookies should be enabled.'];
$page_title='Security';require __DIR__.'/includes/header.php';?><div class="card"><h3>Production Security Checklist</h3><p class="muted-line">Run this after deployment. A failed check is a recommendation, not proof of a vulnerability.</p><div class="table-wrap"><table><thead><tr><th>Check</th><th>Status</th><th>Notes</th></tr></thead><tbody><?php foreach($checks as $c):?><tr><td><?=admin_e($c[0])?></td><td><?= $c[1]?fa_icon('success','fa-solid fa-check').' PASS':fa_icon('help','fa-solid fa-triangle-exclamation').' REVIEW' ?></td><td><?=admin_e($c[2])?></td></tr><?php endforeach;?></tbody></table></div></div><?php require __DIR__.'/includes/footer.php';?>
