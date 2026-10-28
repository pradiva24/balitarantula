<?php
require __DIR__.'/includes/auth.php';$p=db();
$month=trim($_GET['month']??date('Y-m'));if(!preg_match('/^\d{4}-\d{2}$/',$month))$month=date('Y-m');
$first=$month.'-01';$days=(int)date('t',strtotime($first));$start=(int)date('N',strtotime($first));
$st=$p->prepare("SELECT booking_date,COUNT(*) count_total,SUM(total) revenue,SUM(adults+children) guests FROM bookings WHERE booking_date BETWEEN ? AND ? AND booking_status!='cancelled' GROUP BY booking_date ORDER BY booking_date");
$st->execute([$first,$month.'-'.$days]);$map=[];foreach($st->fetchAll() as $r)$map[$r['booking_date']]=$r;
$page_title='Booking Calendar';require __DIR__.'/includes/header.php';?>
<div class="head"><div><h2>Booking Calendar</h2><div class="small">Operational view of upcoming activity dates</div></div><a class="btn" href="bookings.php">All Bookings</a></div>
<div class="calendar-toolbar"><a class="btn gray" href="?month=<?=date('Y-m',strtotime($first.' -1 month'))?>">← Previous</a><strong><?=date('F Y',strtotime($first))?></strong><a class="btn gray" href="?month=<?=date('Y-m',strtotime($first.' +1 month'))?>">Next →</a></div>
<div class="calendar"><div class="cal-head"><?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d):?><div><?= $d ?></div><?php endforeach;?></div><div class="cal-grid"><?php for($i=1;$i<$start;$i++):?><div class="cal-cell empty-day"></div><?php endfor;for($d=1;$d<=$days;$d++):$date=$month.'-'.str_pad($d,2,'0',STR_PAD_LEFT);$x=$map[$date]??null;?><div class="cal-cell <?=$x?'has-booking':''?>"><div class="cal-date"><?=$d?></div><?php if($x):?><a href="bookings.php?from=<?=$date?>&to=<?=$date?>" class="cal-info"><b><?=$x['count_total']?> booking<?=$x['count_total']!=1?'s':''?></b><span><?=$x['guests']?> guests</span><span><?=rupiah($x['revenue'])?></span></a><?php endif;?></div><?php endfor;?></div></div>
<?php require __DIR__.'/includes/footer.php';?>