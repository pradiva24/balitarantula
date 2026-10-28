<?php
require __DIR__.'/includes/auth.php';
$id=(int)($_GET['id']??0);
if($id<1){http_response_code(404);exit('Not Found');}
$st=db()->prepare('SELECT payment_proof FROM bookings WHERE id=? LIMIT 1');
$st->execute([$id]);
$path=(string)($st->fetchColumn()??'');
if($path==='' || !preg_match('~^uploads/payment-proofs/[A-Za-z0-9_-]+\.(?:webp|jpg|jpeg|png|gif)$~i',$path)){http_response_code(404);exit('Not Found');}
$file=realpath(__DIR__.'/../'.ltrim($path,'/'));
$base=realpath(__DIR__.'/../uploads/payment-proofs');
if(!$file || !$base || strncmp($file,$base.DIRECTORY_SEPARATOR,strlen($base.DIRECTORY_SEPARATOR))!==0 || !is_file($file)){http_response_code(404);exit('Not Found');}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
$allowed=['image/webp','image/jpeg','image/png','image/gif'];
if(!in_array($mime,$allowed,true)){http_response_code(415);exit('Unsupported Media Type');}
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($file));
header('Content-Disposition: inline; filename="payment-proof-'.$id.'.'.strtolower(pathinfo($file,PATHINFO_EXTENSION)).'"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($file);
exit;
