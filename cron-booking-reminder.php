<?php
// Optional cron endpoint for a daily H-1 reminder. Protect with CRON_SECRET in this file.
require_once __DIR__.'/config/database.php';require_once __DIR__.'/includes/functions.php';require_once __DIR__.'/includes/smtp.php';
$CRON_SECRET=(string)getenv('BTA_CRON_SECRET');
if ($CRON_SECRET === '' || (PHP_SAPI !== 'cli' && !hash_equals($CRON_SECRET, (string)($_GET['key'] ?? '')))) {
    http_response_code(403);
    exit('Forbidden');
}
$db=db();$tomorrow=date('Y-m-d',strtotime('+1 day'));
$q=$db->prepare("SELECT b.*,p.name package_name FROM bookings b LEFT JOIN packages p ON p.id=b.package_id WHERE b.booking_date=? AND b.booking_status IN ('confirmed','paid') AND b.payment_status IN ('paid','pending')");
$q->execute([$tomorrow]);$sent=0;
foreach($q->fetchAll() as $b){$ck=$db->prepare("SELECT id FROM booking_notifications WHERE booking_id=? AND notification_type='reminder_h1' LIMIT 1");$ck->execute([$b['id']]);if($ck->fetchColumn())continue;if(!filter_var($b['customer_email'],FILTER_VALIDATE_EMAIL))continue;$ok=@smtp_send($b['customer_email'],'Reminder - Your Bali Adventure '.$b['booking_code'],'<div style="font-family:Arial;line-height:1.6"><h2>Your Bali adventure is tomorrow</h2><p>Booking: <b>'.e($b['booking_code']).'</b><br>Package: '.e($b['package_name']).'<br>Date: <b>'.e($b['booking_date']).'</b></p><p>Please be ready according to your pickup information if pickup was selected.</p></div>');if($ok){$db->prepare("INSERT INTO booking_notifications(booking_id,notification_type) VALUES(?,?)")->execute([$b['id'],'reminder_h1']);$sent++;}}
header('Content-Type:text/plain');echo "H-1 reminders sent: ".$sent."\n";