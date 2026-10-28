<?php
require __DIR__.'/includes/auth.php';
$p=db();

$q=trim($_GET['q'] ?? '');
$from=$_GET['from'] ?? '';
$to=$_GET['to'] ?? '';
$status=$_GET['status'] ?? '';
$payment=$_GET['payment'] ?? '';

$where=[];
$args=[];
if($q!==''){
  $where[]="(b.booking_code LIKE ? OR b.customer_name LIKE ? OR b.customer_email LIKE ? OR b.customer_whatsapp LIKE ?)";
  $like="%$q%"; array_push($args,$like,$like,$like,$like);
}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){ $where[]="b.booking_date >= ?"; $args[]=$from; } else $from='';
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){ $where[]="b.booking_date <= ?"; $args[]=$to; } else $to='';
$validStatus=['new','confirmed','paid','completed','cancelled'];
$validPayment=['pending','paid','failed','refunded'];
if(in_array($status,$validStatus,true)){ $where[]="b.booking_status=?"; $args[]=$status; } else $status='';
if(in_array($payment,$validPayment,true)){ $where[]="b.payment_status=?"; $args[]=$payment; } else $payment='';

$sql="SELECT b.*,p.name package_name,a.name pickup_name
      FROM bookings b
      LEFT JOIN packages p ON p.id=b.package_id
      LEFT JOIN pickup_areas a ON a.id=b.pickup_area_id";
if($where) $sql.=" WHERE ".implode(" AND ",$where);
$sql.=" ORDER BY b.booking_date DESC,b.id DESC LIMIT 300";
$stmt=$p->prepare($sql); $stmt->execute($args); $rows=$stmt->fetchAll();

$sumTotal=0; $sumPaid=0;
foreach($rows as $r){ $sumTotal+=(float)$r['total']; if(($r['payment_status']??'')==='paid') $sumPaid+=(float)$r['total']; }

$page_title='Bookings';
require __DIR__.'/includes/header.php';
?>
<div class="toolbar">
  <form class="filters" method="get">
    <input type="search" name="q" value="<?=admin_e($q)?>" placeholder="Search code, name, email, WhatsApp">
    <input type="date" name="from" value="<?=admin_e($from)?>">
    <input type="date" name="to" value="<?=admin_e($to)?>">
    <select name="status">
      <option value="">All booking status</option>
      <?php foreach($validStatus as $v): ?><option value="<?=$v?>" <?=$status===$v?'selected':''?>><?=ucfirst($v)?></option><?php endforeach; ?>
    </select>
    <select name="payment">
      <option value="">All payment status</option>
      <?php foreach($validPayment as $v): ?><option value="<?=$v?>" <?=$payment===$v?'selected':''?>><?=ucfirst($v)?></option><?php endforeach; ?>
    </select>
    <button class="btn">Search</button>
    <a class="btn gray" href="bookings.php">Reset</a>
  </form>
  <form method="post" action="export-bookings.php" style="display:inline"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="from" value="<?=admin_e($from)?>"><input type="hidden" name="to" value="<?=admin_e($to)?>"><button class="btn gray" type="submit">Export CSV</button></form>
</div>

<div class="stats">
  <div class="card stat-card"><div class="statlabel">Results</div><div class="stat"><?=number_format(count($rows))?></div><div class="stat-note">Maximum 300 shown</div></div>
  <div class="card stat-card"><div class="statlabel">Order Value</div><div class="stat">Rp <?=number_format($sumTotal,0,',','.')?></div><div class="stat-note">Filtered bookings</div></div>
  <div class="card stat-card"><div class="statlabel">Paid Value</div><div class="stat">Rp <?=number_format($sumPaid,0,',','.')?></div><div class="stat-note">Payment status: paid</div></div>
</div>

<div class="tablebox section">
<table class="table">
<thead><tr>
<th>Booking</th><th>Customer</th><th>Activity</th><th>Date</th><th>Guests</th><th>Total</th><th>Payment</th><th>Status</th><th>Action</th>
</tr></thead>
<tbody>
<?php if(!$rows): ?>
<tr><td colspan="9"><div class="empty"><strong>No bookings found</strong><span>Try changing your search or date filters.</span></div></td></tr>
<?php else: foreach($rows as $r): ?>
<tr>
<td><b><?=admin_e($r['booking_code'])?></b><div class="small"><?=admin_e(substr((string)$r['created_at'],0,16))?></div></td>
<td><b><?=admin_e($r['customer_name'])?></b><div class="small"><?=admin_e($r['customer_country']??'')?></div><div class="small"><?=admin_e($r['customer_whatsapp'])?></div><div class="small"><?=admin_e($r['customer_email'])?></div></td>
<td><?=admin_e($r['package_name']??'-')?></td>
<td><?=admin_e($r['booking_date'])?></td>
<td><?=number_format((int)$r['adults'])?> adult<?=((int)$r['adults']===1?'':'s')?><?php if((int)$r['children']>0): ?> · <?=number_format((int)$r['children'])?> child<?=((int)$r['children']===1?'':'ren')?><?php endif; ?></td>
<td><b>Rp <?=number_format((float)$r['total'],0,',','.')?></b></td>
<td><span class="badge"><?=admin_e($r['payment_status'])?></span><?php if(!empty($r['payment_method'])): ?><div class="small"><?=admin_e($r['payment_method'])?></div><?php endif; ?></td>
<td><span class="badge"><?=admin_e($r['booking_status'])?></span></td>
<td><a class="btn smallbtn" href="booking-detail.php?id=<?=intval($r['id'])?>">View</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
