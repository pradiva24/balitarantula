<?php
require __DIR__.'/includes/auth.php';
$p=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
 admin_verify_csrf(); $act=$_POST['act']??'';
 try{
  if($act==='save_capacity'){
   $id=(int)$_POST['id']; $cap=max(0,(int)$_POST['max_guests']);
   $p->prepare('UPDATE packages SET max_guests=? WHERE id=?')->execute([$cap,$id]); admin_flash('','Capacity updated'); header('Location: capacity.php'); exit;
  }
  if($act==='blackout'){
   $pid=(int)$_POST['package_id']; $date=$_POST['blackout_date']; $reason=trim($_POST['reason']??'');
   if(!$pid||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new Exception();
   $p->prepare('INSERT INTO package_blackouts(package_id,blackout_date,reason) VALUES(?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason)')->execute([$pid,$date,$reason?:null]); admin_flash('','Blackout date saved'); header('Location: capacity.php'); exit;
  }
  if($act==='delete_blackout'){$p->prepare('DELETE FROM package_blackouts WHERE id=?')->execute([(int)$_POST['id']]);admin_flash('','Blackout removed');header('Location: capacity.php');exit;}
 }catch(Throwable $e){admin_flash('error','Could not save capacity/date rule.');header('Location: capacity.php');exit;}
}
$packages=$p->query('SELECT id,name,max_guests FROM packages ORDER BY name')->fetchAll();
$blackouts=$p->query('SELECT b.*,p.name package_name FROM package_blackouts b JOIN packages p ON p.id=b.package_id WHERE b.blackout_date>=CURDATE() ORDER BY b.blackout_date,p.name LIMIT 200')->fetchAll();
$page_title='Capacity & Dates'; require __DIR__.'/includes/header.php';
?>
<div class="grid2">
<div class="card"><h2>Package Capacity</h2><p class="small">Set the maximum guests per activity date. <b>0 = unlimited</b>. Existing bookings are counted unless cancelled.</p>
<div class="tablebox"><table class="table"><tr><th>Package</th><th>Max Guests</th><th></th></tr>
<?php foreach($packages as $x):?><tr><form method="post"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="save_capacity"><input type="hidden" name="id" value="<?=$x['id']?>"><td><b><?=admin_e($x['name'])?></b></td><td><input type="number" min="0" name="max_guests" value="<?=admin_e($x['max_guests']??0)?>" style="max-width:120px"></td><td><button class="btn">Save</button></td></form></tr><?php endforeach;?></table></div></div>
<div class="card"><h2>Block a Date</h2><p class="small">Use this for private events, maintenance, holidays, or unavailable dates.</p><form method="post" class="form"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="blackout"><label>Package</label><select name="package_id" required><option value="">Select package</option><?php foreach($packages as $x):?><option value="<?=$x['id']?>"><?=admin_e($x['name'])?></option><?php endforeach;?></select><label>Date</label><input type="date" name="blackout_date" min="<?=date('Y-m-d')?>" required><label>Reason</label><input name="reason" maxlength="191" placeholder="Private event"><button class="btn">Block Date</button></form></div></div>
<div class="card section"><h2>Upcoming Blocked Dates</h2><div class="tablebox"><table class="table"><tr><th>Date</th><th>Package</th><th>Reason</th><th></th></tr><?php foreach($blackouts as $b):?><tr><td><b><?=date('d M Y',strtotime($b['blackout_date']))?></b></td><td><?=admin_e($b['package_name'])?></td><td><?=admin_e($b['reason']?:'Unavailable')?></td><td><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="delete_blackout"><input type="hidden" name="id" value="<?=$b['id']?>"><button class="btn red" data-confirm="Remove this blocked date?">Remove</button></form></td></tr><?php endforeach; if(!$blackouts):?><tr><td colspan="4" class="small">No upcoming blocked dates.</td></tr><?php endif;?></table></div></div>
<?php require __DIR__.'/includes/footer.php';
