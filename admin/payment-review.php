<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$admin=require_role(['owner','admin','finance']);
$pdo=Database::connection();$notice=null;$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf_or_fail();
 $id=filter_input(INPUT_POST,'booking_id',FILTER_VALIDATE_INT)?:0;
 try{
  $pdo->beginTransaction();
  $q=$pdo->prepare('SELECT id,booking_code,participants,total_amount,currency,booking_status,payment_status FROM bookings WHERE id=:id FOR UPDATE');
  $q->execute(['id'=>$id]);$b=$q->fetch();
  if(!$b)throw new DomainException('Booking not found.');
  if($b['payment_status']==='paid')throw new DomainException('Payment is already marked paid.');
  if(in_array($b['booking_status'],['cancelled','expired'],true))throw new DomainException('Cancelled or expired bookings cannot be confirmed.');
  $q=$pdo->prepare("UPDATE payments SET status='paid',verified_by=:admin,verified_at=NOW(),paid_at=NOW() WHERE booking_id=:id AND status IN ('pending','submitted','rejected')");
  $q->execute(['admin'=>(int)$admin['id'],'id'=>$id]);
  if($q->rowCount()===0)throw new DomainException('No pending payment was found.');
  $pdo->prepare("UPDATE bookings SET payment_status='paid',booking_status='confirmed' WHERE id=:id")->execute(['id'=>$id]);
  $q=$pdo->prepare('SELECT id FROM tickets WHERE booking_id=:id LIMIT 1');$q->execute(['id'=>$id]);
  if(!$q->fetch()){
   $code='BTA-TK-'.strtoupper(bin2hex(random_bytes(5)));
   $pdo->prepare("INSERT INTO tickets (booking_id,ticket_code,quantity,status) VALUES (:id,:code,:qty,'valid')")->execute(['id'=>$id,'code'=>$code,'qty'=>(int)$b['participants']]);
   $pdo->prepare("UPDATE bookings SET ticket_status='issued' WHERE id=:id")->execute(['id'=>$id]);
  }
  $pdo->commit();$notice='Payment confirmed. Ticket status has been updated.';
 }catch(DomainException $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
 catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Payment review failed: '.$e->getMessage());$error='Could not update this booking. Check the application log.';}
}
$rows=$pdo->query("SELECT b.id,b.booking_code,b.created_at,b.total_amount,b.currency,b.payment_status,b.booking_status,c.full_name,c.email,p.name package_name FROM bookings b JOIN customers c ON c.id=b.customer_id JOIN packages p ON p.id=b.package_id WHERE b.payment_status IN ('unpaid','pending_review','failed') ORDER BY b.created_at LIMIT 250")->fetchAll();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment review | BTA Admin</title><style>:root{--orange:#ff5a1f}*{box-sizing:border-box}body{margin:0;background:#f4f2ed;color:#191919;font:14px/1.5 Arial,sans-serif}.top{background:#151515;color:#fff;padding:18px 24px;display:flex;justify-content:space-between}.top a{color:#fff}.wrap{max-width:1200px;margin:auto;padding:28px 18px}.panel{background:#fff;padding:20px;border-top:3px solid var(--orange);overflow:auto}.notice{padding:12px;background:#e5f4e7;margin:14px 0}.error{background:#fde8e5}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #e6e2da;white-space:nowrap}th{font-size:10px}.muted{color:#777;font-size:11px}button{padding:10px 12px;background:var(--orange);border:0;font-weight:900;cursor:pointer}@media(max-width:700px){.panel{padding:10px}}</style></head><body><header class="top"><strong>BALI TARANTULA <span style="color:#ff5a1f">ADMIN</span></strong><a href="index.php">← Dashboard</a></header><main class="wrap"><h1>Payment review</h1><p>Only mark a transfer as paid after the business has actually received and verified the funds.</p><?php if($notice):?><div class="notice"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?><section class="panel"><table><thead><tr><th>BOOKING</th><th>GUEST</th><th>PACKAGE</th><th>TOTAL</th><th>STATUS</th><th>ACTION</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['booking_code'])?><div class="muted"><?=e($r['created_at'])?></div></td><td><?=e($r['full_name'])?><div class="muted"><?=e($r['email'])?></div></td><td><?=e($r['package_name'])?></td><td><?=e($r['currency'])?> <?=number_format((float)$r['total_amount'],2)?></td><td><?=e($r['payment_status'])?> / <?=e($r['booking_status'])?></td><td><?php if(!in_array($r['booking_status'],['cancelled','expired'],true)):?><form method="post" onsubmit="return confirm('Confirm the transfer has been received?');"><?=csrf_field()?><input type="hidden" name="booking_id" value="<?=(int)$r['id']?>"><button type="submit">CONFIRM PAID</button></form><?php else:?>No action<?php endif;?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="6">No payments need review.</td></tr><?php endif;?></tbody></table></section></main></body></html>