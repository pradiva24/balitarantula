<?php
require __DIR__.'/includes/auth.php';
if(($_SESSION['admin_role']??'admin')!=='admin'){http_response_code(403);exit('Forbidden');}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Method Not Allowed');}
admin_verify_csrf();
$p=db();$q=trim($_POST['q']??'');$bs=$_POST['booking_status']??'';$ps=$_POST['payment_status']??'';$from=$_POST['from']??'';$to=$_POST['to']??'';$where=[];$args=[];
if($q!==''){$where[]='(b.booking_code LIKE ? OR b.customer_name LIKE ? OR b.customer_email LIKE ? OR b.customer_whatsapp LIKE ?)';$x='%'.$q.'%';array_push($args,$x,$x,$x,$x);}
if(in_array($bs,['new','confirmed','paid','completed','cancelled'],true)){$where[]='b.booking_status=?';$args[]=$bs;}if(in_array($ps,['pending','paid','failed','refunded'],true)){$where[]='b.payment_status=?';$args[]=$ps;}if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]='b.booking_date>=?';$args[]=$from;}if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]='b.booking_date<=?';$args[]=$to;}
$w=$where?' WHERE '.implode(' AND ',$where):'';$st=$p->prepare("SELECT b.booking_code,b.customer_name,b.customer_email,b.customer_country,b.customer_whatsapp,p.name package_name,b.booking_date,b.adults,b.children,b.pickup_required,a.name pickup_area,b.hotel_name,b.room_number,b.pickup_time,b.total,b.payment_status,b.booking_status,b.created_at FROM bookings b JOIN packages p ON p.id=b.package_id LEFT JOIN pickup_areas a ON a.id=b.pickup_area_id $w ORDER BY b.id DESC");$st->execute($args);
header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="bali-tarantula-bookings-'.date('Y-m-d').'.csv"');$o=fopen('php://output','w');fputcsv($o,['Booking Code','Customer','Email','Country','WhatsApp','Package','Date','Adults','Children','Pickup','Area','Hotel','Room','Pickup Time','Total','Payment','Booking Status','Created At']);while($r=$st->fetch(PDO::FETCH_ASSOC))fputcsv($o,$r);fclose($o);exit;
