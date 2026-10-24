<?php
declare(strict_types=1);
$sidebarAdmin = $admin ?? current_admin() ?? [];
$sidebarActive = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$sidebarGroups = [
    'WORKSPACE' => [
        ['index.php','Dashboard','layout-dashboard'],
        ['bookings.php','Bookings','calendar-check'],
        ['catalog.php','Activities & Packages','package'],
        ['slots.php','Activity Slots','clock'],
        ['payment-review.php','Payments','wallet'],
        ['redeem.php','Redeem Tickets','ticket-check'],
    ],
    'WEBSITE CONTENT' => [
        ['homepage-editor.php','Homepage Editor','panels-top-left'],
        ['homepage-editor.php#media','Media Library','images'],
    ],
    'SYSTEM' => [
        ['index.php#settings','Settings','settings'],
        ['logout.php','Sign out','log-out'],
    ],
];
?>
<link rel="stylesheet" href="../assets/css/admin-sidebar.css?v=20261024">
<button class="admin-menu-toggle" type="button" aria-label="Open admin menu" aria-expanded="false" aria-controls="admin-sidebar"><span></span><span></span><span></span></button>
<div class="admin-sidebar-backdrop" data-sidebar-close></div>
<aside class="admin-sidebar" id="admin-sidebar">
  <a class="admin-sidebar-brand" href="index.php"><span class="admin-brand-mark">BT<span>A</span></span><span class="admin-brand-copy">BALI TARANTULA<small>ADMIN PANEL</small></span></a>
  <div class="admin-profile"><span class="admin-avatar"><?= e(strtoupper(substr((string)($sidebarAdmin['name'] ?? 'A'),0,1))) ?></span><span><strong><?= e((string)($sidebarAdmin['name'] ?? 'Administrator')) ?></strong><small><?= e(ucfirst((string)($sidebarAdmin['role'] ?? 'admin'))) ?></small></span></div>
  <nav class="admin-sidebar-nav" aria-label="Admin navigation">
  <?php foreach ($sidebarGroups as $groupName => $items): ?>
    <p class="admin-nav-label"><?= e($groupName) ?></p>
    <?php foreach ($items as [$href,$label,$icon]): $target = explode('#',$href)[0]; $active = $sidebarActive === $target && !str_contains($href,'#'); ?>
      <a class="admin-nav-link<?= $active ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
        <span class="admin-nav-icon" aria-hidden="true"><?= match($icon) {'layout-dashboard'=>'▦','calendar-check'=>'▣','package'=>'◇','clock'=>'◷','wallet'=>'＄','ticket-check'=>'▤','panels-top-left'=>'▧','images'=>'▧','settings'=>'⚙','log-out'=>'↪',default=>'•'} ?></span><span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  </nav>
  <div class="admin-sidebar-footer"><span class="admin-status-dot"></span> Workspace ready</div>
</aside>
<script src="../assets/js/admin-sidebar.js?v=20261024" defer></script>
