<?php
require __DIR__.'/includes/auth.php';
$p=db();

/* Additive migration: keep redemption history without changing existing bookings. */
$columns=$p->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('redeemed_at',$columns,true)) $p->exec("ALTER TABLE bookings ADD COLUMN redeemed_at DATETIME NULL");
if(!in_array('redeemed_by',$columns,true)) $p->exec("ALTER TABLE bookings ADD COLUMN redeemed_by INT UNSIGNED NULL");
if(!in_array('paid_at',$columns,true)) $p->exec("ALTER TABLE bookings ADD COLUMN paid_at DATETIME NULL");

$code=trim((string)($_GET['code']??''));
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['redeem_ticket'])){
 admin_verify_csrf(); $code=trim((string)($_POST['booking_code']??''));
 if($code===''){admin_flash('error','Enter a booking or e-ticket code.');}
 else{
  try{
   $p->beginTransaction();
   $st=$p->prepare("SELECT id,booking_code,payment_status,booking_status,payment_method,total,redeemed_at FROM bookings WHERE booking_code=? LIMIT 1 FOR UPDATE");
   $st->execute([$code]); $row=$st->fetch(PDO::FETCH_ASSOC);
   if(!$row){$p->rollBack();admin_flash('error','Ticket not found.');}
   elseif(!empty($row['redeemed_at'])){$p->rollBack();admin_flash('error','This ticket has already been redeemed.');}
   elseif(($row['booking_status']??'')==='cancelled'||($row['payment_status']??'')==='refunded'){$p->rollBack();admin_flash('error','Cancelled or refunded bookings cannot be redeemed.');}
   elseif((stripos((string)($row['payment_method']??''),'venue')!==false || stripos((string)($row['payment_method']??''),'cash on arrival')!==false) && ($_POST['cash_received_confirmed']??'')!=='1'){
    $p->rollBack();admin_flash('error','Cash receipt confirmation is required before redeeming this Pay at Venue booking.');
   }else{
    $up=$p->prepare("UPDATE bookings SET payment_status='paid',paid_at=COALESCE(paid_at,NOW()),redeemed_at=NOW(),redeemed_by=?,updated_at=NOW() WHERE id=? AND redeemed_at IS NULL");
    $up->execute([(int)($_SESSION['admin_id']??0),(int)$row['id']]);
    if($up->rowCount()!==1){$p->rollBack();admin_flash('error','Ticket was updated by another admin. Refresh and check again.');}
    else{$p->commit();admin_flash('success','Ticket redeemed. Payment status is now PAID.');}
   }
  }catch(Throwable $e){if($p->inTransaction())$p->rollBack();error_log('BTA redeem error: '.$e->getMessage());admin_flash('error','Unable to redeem ticket. Please try again.');}
 }
 header('Location: redeem.php'.($code!==''?'?code='.rawurlencode($code):''));exit;
}
$ticket=null;
if($code!==''){$st=$p->prepare("SELECT b.*,pk.name AS package_name FROM bookings b LEFT JOIN packages pk ON pk.id=b.package_id WHERE b.booking_code=? LIMIT 1");$st->execute([$code]);$ticket=$st->fetch(PDO::FETCH_ASSOC)?:null;}
$recent=$p->query("SELECT b.booking_code,b.customer_name,b.booking_date,b.redeemed_at,pk.name AS package_name FROM bookings b LEFT JOIN packages pk ON pk.id=b.package_id WHERE b.redeemed_at IS NOT NULL ORDER BY b.redeemed_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
$page_title='Redeem Ticket';require __DIR__.'/includes/header.php';
?>
<style>
.redeem-wrap{max-width:1100px;margin:24px auto;padding:0 18px}.redeem-card{background:#fff;border:1px solid #e4e7ec;border-radius:16px;padding:22px;margin-bottom:18px;box-shadow:0 4px 16px rgba(16,24,40,.04)}.redeem-intro{color:#667085;margin-top:-5px}.redeem-form{display:flex;gap:10px;max-width:720px}.redeem-form input{flex:1;min-width:0;border:1px solid #d0d5dd;border-radius:10px;padding:13px 14px;font:inherit}.redeem-btn{border:0;border-radius:10px;padding:12px 20px;background:#ed3833;color:#fff;font-weight:800;cursor:pointer}.ticket-result{margin-top:18px;border:1px solid #e4e7ec;border-radius:12px;padding:18px}.ticket-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.ticket-grid span{display:block;color:#667085;font-size:12px}.ticket-grid strong{display:block;margin-top:3px}.state{display:inline-block;border-radius:30px;padding:5px 10px;font-size:12px;font-weight:800;background:#ecfdf3;color:#027a48}.state.used{background:#f2f4f7;color:#475467}.state.pending{background:#fffaeb;color:#b54708}.redeem-table{width:100%;border-collapse:collapse}.redeem-table th,.redeem-table td{text-align:left;padding:12px;border-bottom:1px solid #eaecf0;font-size:13px}.redeem-table th{color:#667085}.empty-note{color:#667085;padding:16px 0}@media(max-width:640px){.redeem-form{flex-direction:column}.redeem-btn{width:100%}.ticket-grid{grid-template-columns:1fr}.redeem-card{padding:16px}.redeem-table{min-width:680px}.table-scroll{overflow-x:auto}}
</style>
<main class="redeem-wrap">
 <h2>Redeem E-Ticket</h2><p class="redeem-intro">Scan the guest's ticket or enter the booking code. Successful redemption automatically changes payment status to PAID.</p>
 <section class="redeem-card">
  <form class="redeem-form" method="get" action="redeem.php"><input id="ticketCodeInput" type="search" name="code" value="<?=admin_e($code)?>" placeholder="Enter booking / e-ticket code" required autofocus><button class="redeem-btn" type="submit">Check Ticket</button></form>
  <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap"><button class="redeem-btn" type="button" id="openCameraBtn">📷 Scan Ticket with Camera</button><span id="cameraStatus" style="font-size:13px;color:#667085">Allow camera access to scan the ticket QR code.</span></div>
  <div id="cameraBox" hidden style="margin-top:14px;max-width:520px"><video id="ticketCamera" playsinline autoplay muted style="display:block;width:100%;max-height:360px;background:#101828;border-radius:12px;object-fit:cover"></video><button class="redeem-btn" type="button" id="stopCameraBtn" style="margin-top:8px;background:#475467">Stop Camera</button></div>
  <?php if($ticket): ?>
   <div class="ticket-result">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px"><strong style="font-size:18px"><?=admin_e($ticket['booking_code'])?></strong>
    <?php if(!empty($ticket['redeemed_at'])): ?><span class="state used">ALREADY REDEEMED</span><?php elseif(($ticket['booking_status']??'')==='cancelled'||($ticket['payment_status']??'')==='refunded'): ?><span class="state pending">NOT ELIGIBLE</span><?php else: ?><span class="state pending">READY TO REDEEM</span><?php endif; ?></div>
    <div class="ticket-grid"><div><span>Guest</span><strong><?=admin_e($ticket['customer_name'])?></strong></div><div><span>Activity</span><strong><?=admin_e($ticket['package_name']??'—')?></strong></div><div><span>Activity Date</span><strong><?=admin_e($ticket['booking_date'])?></strong></div><div><span>Payment Method</span><strong><?=admin_e($ticket['payment_method']??'—')?></strong></div><div><span>Booking Total</span><strong><?=admin_e(function_exists('rupiah')?rupiah($ticket['total']):('Rp '.number_format((float)$ticket['total'],0,',','.')))?></strong></div><div><span>Payment Status</span><strong><?=admin_e(strtoupper($ticket['payment_status']))?></strong></div><?php if(!empty($ticket['redeemed_at'])): ?><div><span>Redeemed At</span><strong><?=admin_e($ticket['redeemed_at'])?></strong></div><?php endif; ?></div>
    <?php if(empty($ticket['redeemed_at'])&&($ticket['booking_status']??'')!=='cancelled'&&($ticket['payment_status']??'')!=='refunded'): ?>
    <form id="redeemConfirmForm" method="post" style="margin-top:18px"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="booking_code" value="<?=admin_e($ticket['booking_code'])?>"><input type="hidden" name="cash_received_confirmed" id="cashReceivedConfirmed" value="0"><input type="hidden" name="redeem_ticket" value="1"><button class="redeem-btn" type="submit">Confirm Redeem Ticket</button></form>
    <?php endif; ?>
   </div>
  <?php elseif($code!==''): ?><p class="empty-note">Ticket not found. Please check the code.</p><?php endif; ?>
 </section>
 <section class="redeem-card"><h3>Recently Redeemed Tickets</h3>
 <?php if(!$recent): ?><p class="empty-note">No tickets have been redeemed yet.</p><?php else: ?><div class="table-scroll"><table class="redeem-table"><thead><tr><th>Booking Code</th><th>Guest</th><th>Activity</th><th>Activity Date</th><th>Payment</th><th>Redeemed At</th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td><strong><?=admin_e($r['booking_code'])?></strong></td><td><?=admin_e($r['customer_name'])?></td><td><?=admin_e($r['package_name']??'—')?></td><td><?=admin_e($r['booking_date'])?></td><td><span class="state">PAID</span></td><td><?=admin_e($r['redeemed_at'])?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
 </section>
</main>
<div id="cashConfirmModal" hidden style="position:fixed;inset:0;z-index:99999;background:rgba(16,24,40,.68);padding:20px;align-items:center;justify-content:center">
 <div style="background:#fff;border-radius:16px;padding:24px;width:min(100%,440px);box-shadow:0 20px 60px rgba(0,0,0,.25)">
  <h3 style="margin:0 0 8px">Confirm Cash Payment</h3>
  <p style="margin:0 0 8px;color:#475467">This booking uses <strong>Pay at Venue</strong>.</p>
  <p style="margin:0 0 18px">Have you received the cash payment of <strong><?=admin_e(function_exists('rupiah')?rupiah($ticket['total']??0):('Rp '.number_format((float)($ticket['total']??0),0,',','.')))?></strong> from the guest?</p>
  <div style="display:flex;gap:10px;flex-wrap:wrap"><button type="button" id="cashConfirmYes" class="redeem-btn" style="flex:1">Yes, Cash Received</button><button type="button" id="cashConfirmNo" class="redeem-btn" style="background:#667085;flex:1">No, Cancel</button></div>
 </div>
</div>
<script>
(function(){
 const scanBtn=document.getElementById('openCameraBtn'), stopBtn=document.getElementById('stopCameraBtn'), box=document.getElementById('cameraBox'), video=document.getElementById('ticketCamera'), input=document.getElementById('ticketCodeInput'), status=document.getElementById('cameraStatus');
 let stream=null, scanning=false, detector=null, frame=null;
 function stopCamera(){scanning=false;if(frame)cancelAnimationFrame(frame);if(stream)stream.getTracks().forEach(t=>t.stop());stream=null;video.srcObject=null;box.hidden=true;scanBtn.disabled=false;status.textContent='Camera stopped.';}
 function extractCode(value){try{const u=new URL(value);return u.searchParams.get('code')||u.searchParams.get('booking_code')||value.trim();}catch(e){return value.trim();}}
 async function scanFrame(){if(!scanning)return;try{if(video.readyState>=2){const found=await detector.detect(video);if(found.length){const code=extractCode(found[0].rawValue||'');if(code){input.value=code;stopCamera();status.textContent='QR detected. Checking ticket…';input.form.submit();return;}}}}catch(e){}frame=requestAnimationFrame(scanFrame);}
 scanBtn?.addEventListener('click',async()=>{if(!navigator.mediaDevices?.getUserMedia){status.textContent='Camera is unavailable. Use HTTPS or enter the code manually.';return;}if(!('BarcodeDetector'in window)){status.textContent='QR scanning is not supported by this browser. Please enter the code manually.';return;}try{detector=new BarcodeDetector({formats:['qr_code']});stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});video.srcObject=stream;await video.play();box.hidden=false;scanning=true;scanBtn.disabled=true;status.textContent='Point the camera at the ticket QR code.';scanFrame();}catch(e){stopCamera();status.textContent='Cannot access camera. Check browser permission and HTTPS.';}});
 stopBtn?.addEventListener('click',stopCamera);
 const form=document.getElementById('redeemConfirmForm'), modal=document.getElementById('cashConfirmModal'), yes=document.getElementById('cashConfirmYes'), no=document.getElementById('cashConfirmNo'), confirmed=document.getElementById('cashReceivedConfirmed');
 const isVenue=<?=json_encode(stripos((string)($ticket['payment_method']??''),'venue')!==false || stripos((string)($ticket['payment_method']??''),'cash on arrival')!==false)?>;
 form?.addEventListener('submit',e=>{if(isVenue&&confirmed.value!=='1'){e.preventDefault();modal.hidden=false;modal.style.display='flex';}else if(!isVenue&&!confirm('Redeem this ticket now? Payment status will automatically change to PAID.'))e.preventDefault();});
 yes?.addEventListener('click',()=>{confirmed.value='1';modal.hidden=true;modal.style.display='none';form.submit();});
 no?.addEventListener('click',()=>{modal.hidden=true;modal.style.display='none';});
})();
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
