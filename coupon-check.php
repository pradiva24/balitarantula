<?php
require_once __DIR__.'/config/database.php';require_once __DIR__.'/includes/functions.php';require_once __DIR__.'/includes/coupon.php';
header('Content-Type: application/json; charset=utf-8');
try{$r=coupon_validate($_GET['code']??'',(float)($_GET['subtotal']??0),(int)($_GET['package_id']??0),$_GET['date']??date('Y-m-d'));echo json_encode($r);}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'message'=>'Unable to validate promo.','discount'=>0]);}
