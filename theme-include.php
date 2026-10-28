<?php
$btThemeCss = '/assets/css/theme.php';
if (defined('BASE_URL')) {
  $btThemeCss = rtrim(BASE_URL,'/').'/assets/css/theme.php';
}
?>
<link rel="stylesheet" href="<?=htmlspecialchars($btThemeCss,ENT_QUOTES,'UTF-8')?>">
