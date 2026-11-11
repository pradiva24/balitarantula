<?php
require __DIR__.'/includes/auth.php'; $p=db();
$from=$_GET['from']??date('Y-m-d'); $days=max(7,min(60,(int)($_GET['days']??30)));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from))$from=date('Y-m-d');
$dates=[]; for($i=0;$i<$days;$i++)$dates[]=date('Y-m-d',strtotime("$from +$i day"));
$packages=$p->query("SELECT id,name,max_guests FROM packages WHERE status=1 ORDER BY name")->fetchAll();
$counts=[];
if($dates){
  $st=$p->prepare("SELECT package_id,booking_date,SUM(adults+children) guests,COUNT(*) bookings FROM bookings WHERE booking_date BETWEEN ? AND ? AND booking_status<>'cancelled' GROUP BY package_id,booking_date");
  $st->execute([$dates[0],$dates[count($dates)-1]]);
  foreach($st->fetchAll() as $r)$counts[$r['package_id']][$r['booking_date']]=$r;
}
$page_title='Availability Calendar'; require __DIR__.'/includes/header.php';
?>
<div class="toolbar"><form class="filters"><label class="small">Start <input type="date" name="from" value="<?=admin_e($from)?>"></label><label class="small">Days <select name="days"><?php foreach([7,14,30,45,60] as $n):?><option <?=$days===$n?'selected':''?>><?=$n?></option><?php endforeach;?></select></label><button class="btn">View</button></form></div>
<div class="card section"><h2>Booking Availability</h2><p class="small">Operational calendar. Capacity limits and blocked dates can be configured in Capacity & Dates. A date is full when confirmed/new/paid bookings reach the package guest limit.</p>
<div class="tablebox"><table class="table availability"><tr><th>Package / Capacity</th><?php foreach($dates as $dt):?><th><?=date('d M',strtotime($dt))?></th><?php endforeach;?></tr>
<?php foreach($packages as $pkg):?><tr><td><b><?=admin_e($pkg['name'])?></b><div class="small"><?=((int)$pkg['max_guests']>0)?intval($pkg['max_guests']).' pax max':'Unlimited'?></div></td><?php foreach($dates as $dt):$x=$counts[$pkg['id']][$dt]??null;?><td><?php if($x):?><span class="badge"><?=intval($x['bookings'])?> bk</span><div class="small"><?=intval($x['guests'])?> pax</div><?php else:?><span class="small">—</span><?php endif;?></td><?php endforeach;?></tr><?php endforeach;?>
</table></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>