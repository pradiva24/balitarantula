<?php
require __DIR__.'/includes/auth.php';
$p=db();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $from=date('Y-m-01');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) $to=date('Y-m-d');
if($from>$to){ $tmp=$from; $from=$to; $to=$tmp; }

$baseWhere = "WHERE booking_date BETWEEN ? AND ?";
$args = [$from,$to];

$stmt=$p->prepare("SELECT COUNT(*) FROM bookings $baseWhere");
$stmt->execute($args); $bookingCount=(int)$stmt->fetchColumn();

$stmt=$p->prepare("SELECT COALESCE(SUM(total),0) FROM bookings $baseWhere AND payment_status='paid'");
$stmt->execute($args); $paidRevenue=(float)$stmt->fetchColumn();

$stmt=$p->prepare("SELECT COUNT(*) FROM bookings $baseWhere AND payment_status='pending'");
$stmt->execute($args); $pendingCount=(int)$stmt->fetchColumn();

$stmt=$p->prepare("SELECT COALESCE(AVG(total),0) FROM bookings $baseWhere");
$stmt->execute($args); $avgBooking=(float)$stmt->fetchColumn();

$stmt=$p->prepare("SELECT booking_status,COUNT(*) total FROM bookings $baseWhere GROUP BY booking_status ORDER BY total DESC");
$stmt->execute($args); $statusRows=$stmt->fetchAll();

$stmt=$p->prepare("SELECT payment_status,COUNT(*) total FROM bookings $baseWhere GROUP BY payment_status ORDER BY total DESC");
$stmt->execute($args); $paymentRows=$stmt->fetchAll();

$stmt=$p->prepare("SELECT COALESCE(p.name,'Unknown') package_name, COUNT(*) total, COALESCE(SUM(b.total),0) revenue
                   FROM bookings b LEFT JOIN packages p ON p.id=b.package_id
                   WHERE b.booking_date BETWEEN ? AND ?
                   GROUP BY b.package_id,p.name ORDER BY total DESC LIMIT 10");
$stmt->execute($args); $packageRows=$stmt->fetchAll();

$stmt=$p->prepare("SELECT COALESCE(a.name,'No pickup') area_name, COUNT(*) total, COALESCE(SUM(b.pickup_price),0) pickup_revenue
                   FROM bookings b LEFT JOIN pickup_areas a ON a.id=b.pickup_area_id
                   WHERE b.booking_date BETWEEN ? AND ? AND b.pickup_required=1
                   GROUP BY b.pickup_area_id,a.name ORDER BY total DESC LIMIT 10");
$stmt->execute($args); $pickupRows=$stmt->fetchAll();

$monthRows=[];
$stmt=$p->prepare("SELECT DATE_FORMAT(booking_date,'%Y-%m') ym, COUNT(*) total,
                   COALESCE(SUM(CASE WHEN payment_status='paid' THEN total ELSE 0 END),0) revenue
                   FROM bookings
                   WHERE booking_date BETWEEN ? AND ?
                   GROUP BY DATE_FORMAT(booking_date,'%Y-%m') ORDER BY ym");
$stmt->execute($args); $monthRows=$stmt->fetchAll();
$maxRevenue=0;
foreach($monthRows as $m) $maxRevenue=max($maxRevenue,(float)$m['revenue']);

$page_title='Reports & Analytics';
require __DIR__.'/includes/header.php';
?>
<div class="toolbar">
  <form class="filters" method="get">
    <label class="small">From <input type="date" name="from" value="<?=admin_e($from)?>"></label>
    <label class="small">To <input type="date" name="to" value="<?=admin_e($to)?>"></label>
    <button class="btn">Apply</button>
    <a class="btn gray" href="reports.php">This Month</a>
  </form>
  <form method="post" action="export-bookings.php" style="display:inline"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="from" value="<?=admin_e($from)?>"><input type="hidden" name="to" value="<?=admin_e($to)?>"><button class="btn gray" type="submit">Export Period CSV</button></form>
</div>

<div class="muted-line">Report period: <b><?=admin_e($from)?></b> → <b><?=admin_e($to)?></b></div>

<div class="stats">
  <div class="card stat-card"><div class="statlabel">Bookings</div><div class="stat"><?=number_format($bookingCount)?></div><div class="stat-note">All bookings in period</div></div>
  <div class="card stat-card"><div class="statlabel">Paid Revenue</div><div class="stat">Rp <?=number_format($paidRevenue,0,',','.')?></div><div class="stat-note">Payment status: paid</div></div>
  <div class="card stat-card"><div class="statlabel">Pending Payment</div><div class="stat"><?=number_format($pendingCount)?></div><div class="stat-note">Needs follow-up</div></div>
  <div class="card stat-card"><div class="statlabel">Average Booking</div><div class="stat">Rp <?=number_format($avgBooking,0,',','.')?></div><div class="stat-note">Average order value</div></div>
</div>

<div class="grid2 section">
  <div class="card">
    <div class="head"><div><h2>Monthly Revenue</h2><div class="small">Paid revenue by booking month</div></div></div>
    <?php if(!$monthRows): ?>
      <div class="empty"><strong>No data</strong><span>No bookings match this period.</span></div>
    <?php else: foreach($monthRows as $m): $pct=$maxRevenue>0?((float)$m['revenue']/$maxRevenue*100):0; ?>
      <div style="margin:16px 0">
        <div style="display:flex;justify-content:space-between;gap:12px;font-size:13px;margin-bottom:6px">
          <b><?=admin_e($m['ym'])?></b><span>Rp <?=number_format((float)$m['revenue'],0,',','.')?> · <?=number_format((int)$m['total'])?> bookings</span>
        </div>
        <div style="height:10px;background:#eef2f7;border-radius:99px;overflow:hidden"><div style="height:100%;width:<?=$pct?>%;background:currentColor;border-radius:99px"></div></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="card">
    <div class="head"><div><h2>Booking Status</h2><div class="small">Operational pipeline</div></div></div>
    <?php if(!$statusRows): ?><div class="empty"><strong>No data</strong><span>No bookings match this period.</span></div>
    <?php else: foreach($statusRows as $r): ?>
      <div style="display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px solid #eef2f7">
        <span class="badge"><?=admin_e($r['booking_status'])?></span><b><?=number_format((int)$r['total'])?></b>
      </div>
    <?php endforeach; endif; ?>
    <div class="head" style="margin-top:20px"><div><h2>Payment Status</h2></div></div>
    <?php foreach($paymentRows as $r): ?>
      <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #eef2f7">
        <span class="badge"><?=admin_e($r['payment_status'])?></span><b><?=number_format((int)$r['total'])?></b>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="section">
  <div class="head"><div><h2>Top Packages</h2><div class="small">Packages with the most bookings in the selected period</div></div></div>
  <div class="tablebox"><table class="table">
    <tr><th>#</th><th>Package</th><th>Bookings</th><th>Revenue</th></tr>
    <?php if(!$packageRows): ?><tr><td colspan="4"><div class="empty"><strong>No package data</strong></div></td></tr>
    <?php else: $i=1; foreach($packageRows as $r): ?><tr>
      <td><?=$i++?></td><td><b><?=admin_e($r['package_name'])?></b></td><td><?=number_format((int)$r['total'])?></td><td>Rp <?=number_format((float)$r['revenue'],0,',','.')?></td>
    </tr><?php endforeach; endif; ?>
  </table></div>
</div>

<div class="section">
  <div class="head"><div><h2>Pickup Area Performance</h2><div class="small">Most requested pickup areas and pickup revenue</div></div></div>
  <div class="tablebox"><table class="table">
    <tr><th>#</th><th>Pickup Area</th><th>Bookings</th><th>Pickup Revenue</th></tr>
    <?php if(!$pickupRows): ?><tr><td colspan="4"><div class="empty"><strong>No pickup data</strong><span>No pickup bookings match this period.</span></div></td></tr>
    <?php else: $i=1; foreach($pickupRows as $r): ?><tr>
      <td><?=$i++?></td><td><?=admin_e($r['area_name'])?></td><td><?=number_format((int)$r['total'])?></td><td>Rp <?=number_format((float)$r['pickup_revenue'],0,',','.')?></td>
    </tr><?php endforeach; endif; ?>
  </table></div>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
