<?php
require __DIR__.'/includes/auth.php';require_once __DIR__.'/../includes/smtp.php';require_once __DIR__.'/../includes/booking-email.php';require_once __DIR__.'/../includes/eticket-pdf.php';$p=db();$id=(int)($_GET['id']??0);
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_template'])){
 admin_verify_csrf();
 $stx=$p->prepare("SELECT b.*,p.name package_name,p.short_description,p.cover_image,p.price_adult FROM bookings b LEFT JOIN packages p ON p.id=b.package_id WHERE b.id=?");
 $stx->execute([$id]);
 $bx=$stx->fetch();
 $type=(string)($_POST['send_template']??'');
 $site=trim((string)site_brand_name())?:site_brand_name();
 $url=rtrim(setting('site_url',BASE_URL),'/').'/booking-confirmation?code='.rawurlencode((string)$bx['booking_code']).'&token='.rawurlencode((string)($bx['access_token']??''));
 $templates=[
  'received'=>['Booking Received','Thank you for your booking. Your reservation has been received and your e-ticket is attached to this email.'],
  'confirmed'=>['Booking Confirmed','Your booking has been confirmed. Please keep the attached e-ticket for your visit.'],
  'paid'=>['Payment Confirmed','We have confirmed the payment for your booking. Your e-ticket is attached for your convenience.'],
  'reminder'=>['Your Bali Adventure Reminder','This is a friendly reminder about your upcoming activity. Please bring your e-ticket with you.'],
  'completed'=>['Thank You for Visiting','Thank you for joining us. We hope you had a wonderful experience in Bali. Your booking e-ticket is attached for your records.']
 ];
 if(isset($templates[$type])&&filter_var($bx['customer_email']??'',FILTER_VALIDATE_EMAIL)){
   [$title,$intro]=$templates[$type];
   $emailBooking=$bx;
   $emailBooking['booking_status']=$bx['booking_status']??'received';
   $emailBooking['payment_status']=$bx['payment_status']??'pending';
   $emailHtml=bta_booking_email_html($emailBooking,$url,$title,$intro);
   $ticketPath=bta_make_eticket_pdf($emailBooking);
   $attachments=$ticketPath?[['path'=>$ticketPath,'filename'=>$bx['booking_code'].'-E-Ticket.pdf','mime'=>'application/pdf']]:[];
   $ok=@smtp_send($bx['customer_email'],$title.' - '.$bx['booking_code'],$emailHtml,'',null,$attachments);
   if($ticketPath)@unlink($ticketPath);
   admin_flash($ok?'':'',$ok?'Email sent to '.$bx['customer_email']: 'Unable to send email. Check SMTP settings and customer email.');
 }else admin_flash('','Unable to send email. Check SMTP and customer email.');
 header("Location: booking-detail.php?id=".$id);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){admin_verify_csrf();$newPay=$_POST['payment_status'];$paidAt=$newPay==='paid'?'NOW()':'NULL';$p->prepare("UPDATE bookings SET booking_status=?,payment_status=?,paid_at=$paidAt,admin_note=?,updated_at=NOW() WHERE id=?")->execute([$_POST['booking_status'],$newPay,trim($_POST['admin_note']??''),$id]);admin_flash('','Booking updated');header("Location: booking-detail.php?id=".$id);exit;}
$st=$p->prepare("SELECT b.*,p.name package_name,a.name pickup_area FROM bookings b JOIN packages p ON p.id=b.package_id LEFT JOIN pickup_areas a ON a.id=b.pickup_area_id WHERE b.id=?");$st->execute([$id]);$b=$st->fetch();if(!$b)exit('Booking not found');
$st=$p->prepare("SELECT * FROM booking_items WHERE booking_id=? ORDER BY id");$st->execute([$id]);$items=$st->fetchAll();$page_title='Booking '.$b['booking_code'];require __DIR__.'/includes/header.php';?>
<div class="head"><h2>Booking <?=admin_e($b['booking_code'])?></h2><div><a class="btn gray" target="_blank" href="invoice.php?id=<?=$b['id']?>">Invoice / PDF</a> <a class="btn gray" target="_blank" href="<?=admin_e(whatsapp_url($b['customer_whatsapp'],'Hello '. $b['customer_name'].', regarding your booking '.$b['booking_code'].' with '.site_brand_name().'.'))?>">WhatsApp</a> <a class="btn gray" href="bookings.php">Back</a></div></div>
<div class="grid2"><div class="card"><h3>Customer</h3><p><b><?=admin_e($b['customer_name'])?></b><br><?=admin_e($b['customer_email'])?><br><?=admin_e($b['customer_whatsapp'])?></p><h3>Activity</h3><p><?=admin_e($b['package_name'])?><br>Date: <?=admin_e($b['booking_date'])?><br>Activity time: <?=admin_e(substr((string)($b['activity_time']??''),0,5)?:'—')?><br>Guests: <?=$b['adults']?> adult / <?=$b['children']?> child</p><h3>Pickup</h3><p><?= $b['pickup_required']?'Yes':'No' ?><br><?=admin_e($b['pickup_area']?:'—')?><br><?=admin_e($b['hotel_name']?:'—')?>
<br><span style="display:inline-block;margin-top:5px"><b>Room Number:</b> <?=admin_e($b['room_number']?:'—')?> &nbsp; | &nbsp; <b>Pickup Time:</b> <?=admin_e(substr((string)($b['pickup_time']??''),0,5)?:'—')?></span></p></div>
<div class="card"><h3>Payment</h3><p><b>Method:</b> <?=admin_e($b['payment_method']??'—')?><br><b>Status:</b> <?=admin_e($b['payment_status'])?><br><b>Paid at:</b> <?=admin_e($b['paid_at']??'—')?></p><?php if(!empty($b['payment_proof'])):?><a class="btn gray" target="_blank" href="payment-proof.php?id=<?=intval($b['id'])?>">View Payment Proof</a><?php endif;?><h3 style="margin-top:20px">Price Snapshot</h3><table class="table"><tr><th>Item</th><th>Qty</th><th>Total</th></tr><?php foreach($items as $i):?><tr><td><?=admin_e($i['item_name'])?></td><td><?=$i['quantity']?></td><td><?=rupiah($i['total_price'])?></td></tr><?php endforeach;?></table><p><b>Activity:</b> <?=rupiah($b['activity_price'])?><br><b>Pickup:</b> <?=rupiah($b['pickup_price'])?><br><b>Discount:</b> <?=rupiah($b['discount_amount']??0)?><?=!empty($b['discount_code'])?' ('.admin_e($b['discount_code']).')':''?><br><b>Total:</b> <?=rupiah($b['total'])?></p>
<div class="card" style="margin-top:18px"><h3>Customer Communications</h3><div class="quick-grid"><form method="post"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="send_template" value="received"><button class="btn gray" type="submit">Booking Received</button></form><form method="post"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="send_template" value="confirmed"><button class="btn gray" type="submit">Confirmed</button></form><form method="post"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="send_template" value="paid"><button class="btn gray" type="submit">Payment Confirmed</button></form><form method="post"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="send_template" value="reminder"><button class="btn gray" type="submit">Send Reminder</button></form><form method="post"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="send_template" value="completed"><button class="btn gray" type="submit">Thank You</button></form></div></div>
<form method="post"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><label>Payment Status</label><select name="payment_status"><option <?=$b['payment_status']==='pending'?'selected':''?>>pending</option><option <?=$b['payment_status']==='paid'?'selected':''?>>paid</option><option <?=$b['payment_status']==='failed'?'selected':''?>>failed</option><option <?=$b['payment_status']==='refunded'?'selected':''?>>refunded</option></select><label>Booking Status</label><select name="booking_status"><option <?=$b['booking_status']==='new'?'selected':''?>>new</option><option <?=$b['booking_status']==='confirmed'?'selected':''?>>confirmed</option><option <?=$b['booking_status']==='paid'?'selected':''?>>paid</option><option <?=$b['booking_status']==='completed'?'selected':''?>>completed</option><option <?=$b['booking_status']==='cancelled'?'selected':''?>>cancelled</option></select><label>Admin Note</label><textarea name="admin_note"><?=admin_e($b['admin_note'])?></textarea><button class="btn">Save Booking</button></form></div></div>
<?php require __DIR__.'/includes/footer.php';?>
