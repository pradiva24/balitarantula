<?php
require __DIR__.'/config/database.php';
require __DIR__.'/includes/functions.php';

$code=trim($_GET['code']??$_POST['booking_code']??'');
$email=trim($_GET['email']??$_POST['email']??'');
$booking=null; $sent=false; $error='';

if($code!=='' && $email!==''){
  $st=db()->prepare("SELECT b.*,p.name package_name FROM bookings b LEFT JOIN packages p ON p.id=b.package_id
                     WHERE b.booking_code=? AND LOWER(b.customer_email)=LOWER(?) LIMIT 1");
  $st->execute([$code,$email]); $booking=$st->fetch();
}
if($_SERVER['REQUEST_METHOD']==='POST' && $booking){
  if(!verify_csrf($_POST['csrf']??'')){ $error='Your session expired.'; }
  elseif(($booking['booking_status']??'')!=='completed'){ $error='Reviews can be submitted after the activity is completed.'; }
  else {
    $rating=max(1,min(5,(int)($_POST['rating']??5)));
    $name=trim($_POST['name']??$booking['customer_name']);
    $text=trim($_POST['review_text']??'');
    if($text==='') $error='Please write a short review.';
    else {
      # Detect common review schema used by the project and insert safely.
      $cols=[]; $stmt=db()->query("DESCRIBE reviews");
      foreach($stmt->fetchAll() as $col) $cols[]=$col['Field'];
      $data=[];
      if(in_array('booking_id',$cols,true)) $data['booking_id']=$booking['id'];
      if(in_array('package_id',$cols,true)) $data['package_id']=$booking['package_id'];
      if(in_array('name',$cols,true)) $data['name']=$name;
      elseif(in_array('customer_name',$cols,true)) $data['customer_name']=$name;
      if(in_array('rating',$cols,true)) $data['rating']=$rating;
      if(in_array('review_text',$cols,true)) $data['review_text']=$text;
      elseif(in_array('content',$cols,true)) $data['content']=$text;
      elseif(in_array('message',$cols,true)) $data['message']=$text;
      if(in_array('status',$cols,true)) $data['status']='pending';
      if(in_array('is_published',$cols,true)) $data['is_published']=0;
      if(in_array('created_at',$cols,true)) $data['created_at']=date('Y-m-d H:i:s');
      if($data){
        $keys=array_keys($data); $sql="INSERT INTO reviews (`".implode('`,`',$keys)."`) VALUES (".implode(',',array_fill(0,count($keys),'?')).")";
        db()->prepare($sql)->execute(array_values($data)); $sent=true;
      } else $error='Review storage is not configured.';
    }
  }
}
$page_title='Leave a Review';
require __DIR__.'/includes/header.php';
?>
<main style="background:#f5f4ef;padding:60px 0 90px;min-height:70vh;font-family:'Inter',sans-serif">
<section class="container" style="max-width:760px;margin:auto;padding:0 20px">
<div class="booking-box" style="background:#fff;border:1px solid #dedbd4;border-top:3px solid #ff5a1f;border-radius:14px;padding:clamp(24px, 5vw, 40px);box-shadow:0 12px 36px rgba(30,25,15,.06)">
<?php if($sent): ?>
  <div style="text-align:center;padding:30px">
    <div style="font-size:46px;color:#ff5a1f"><?=fa_icon('review_thankyou','fa-solid fa-star')?></div>
    <h1 style="font-family:'Barlow Condensed',sans-serif;text-transform:uppercase;font-weight:900;letter-spacing:-0.5px;font-size:38px;color:#191919;margin:12px 0 8px">Thank You!</h1>
    <p style="color:#5d5b56;font-size:15px;margin:0 0 24px">Your review has been submitted and will be reviewed by our team.</p>
    <a class="btn primary" style="background:#ff5a1f;color:#111;padding:14px 28px;border-radius:8px;text-decoration:none;font-family:'Barlow Condensed',sans-serif;font-weight:900;font-size:16px;letter-spacing:1.5px;text-transform:uppercase;display:inline-block" href="./">Back to Homepage</a>
  </div>
<?php elseif(!$booking): ?>
  <div style="text-align:center;margin-bottom:25px">
    <span class="eyebrow" style="display:inline-block;color:#ff5a1f;font-family:'Barlow Condensed',sans-serif;font-weight:800;letter-spacing:2.5px;font-size:13px;text-transform:uppercase;margin-bottom:8px">CUSTOMER REVIEW</span>
    <h1 style="font-family:'Barlow Condensed',sans-serif;text-transform:uppercase;font-weight:900;letter-spacing:-0.5px;font-size:38px;color:#191919;margin:0 0 8px">Leave a Review</h1>
    <p style="color:#5d5b56;font-size:15px;margin:0">Enter the booking code and email from your reservation.</p>
  </div>
  <?php if($error): ?><div class="alert error" style="background:#fef2f2;border:1px solid #fee2e2;color:#991b1b;padding:12px 14px;border-radius:8px;margin-bottom:20px"><?=e($error)?></div><?php endif; ?>
  <form method="get" class="booking-form"><div class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
    <label style="display:block;font-size:12px;font-weight:700;color:#191919;text-transform:uppercase">Booking Code<input name="code" required value="<?=e($code)?>" style="width:100%;box-sizing:border-box;margin-top:6px;padding:12px 14px;border:1px solid #d8d4cc;border-radius:8px;color:#191919;font:inherit"></label>
    <label style="display:block;font-size:12px;font-weight:700;color:#191919;text-transform:uppercase">Email<input type="email" name="email" required value="<?=e($email)?>" style="width:100%;box-sizing:border-box;margin-top:6px;padding:12px 14px;border:1px solid #d8d4cc;border-radius:8px;color:#191919;font:inherit"></label>
  </div><button class="btn primary" style="background:#ff5a1f;color:#111;border:0;padding:14px 30px;border-radius:8px;font-family:'Barlow Condensed',sans-serif;font-weight:900;letter-spacing:1.5px;font-size:16px;text-transform:uppercase;cursor:pointer">Continue</button></form>
<?php elseif(($booking['booking_status']??'')!=='completed'): ?>
  <div style="text-align:center">
    <h1 style="font-family:'Barlow Condensed',sans-serif;text-transform:uppercase;font-weight:900;letter-spacing:-0.5px;font-size:32px;color:#191919;margin:0 0 10px">Review Not Available Yet</h1>
    <p style="color:#5d5b56;font-size:15px">Your booking status is <strong><?=e($booking['booking_status'])?></strong>. You can review after the activity is completed.</p>
  </div>
<?php else: ?>
  <div style="margin-bottom:25px">
    <span class="eyebrow" style="display:inline-block;color:#ff5a1f;font-family:'Barlow Condensed',sans-serif;font-weight:800;letter-spacing:2.5px;font-size:13px;text-transform:uppercase;margin-bottom:8px"><?=e($booking['package_name']??site_brand_name())?></span>
    <h1 style="font-family:'Barlow Condensed',sans-serif;text-transform:uppercase;font-weight:900;letter-spacing:-0.5px;font-size:38px;color:#191919;margin:0 0 6px">How was your experience?</h1>
    <p style="color:#5d5b56;font-size:15px;margin:0">Booking <?=e($booking['booking_code'])?></p>
  </div>
  <?php if($error): ?><div class="alert error" style="background:#fef2f2;border:1px solid #fee2e2;color:#991b1b;padding:12px 14px;border-radius:8px;margin-bottom:20px"><?=e($error)?></div><?php endif; ?>
  <form method="post" class="booking-form">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="booking_code" value="<?=e($code)?>">
    <input type="hidden" name="email" value="<?=e($email)?>">
    <label style="display:block;margin-bottom:16px;font-size:12px;font-weight:700;color:#191919;text-transform:uppercase">Your Name<input name="name" value="<?=e($booking['customer_name'])?>" required style="width:100%;box-sizing:border-box;margin-top:6px;padding:12px 14px;border:1px solid #d8d4cc;border-radius:8px;color:#191919;font:inherit"></label>
    <label style="display:block;margin-bottom:16px;font-size:12px;font-weight:700;color:#191919;text-transform:uppercase">Rating<select name="rating" style="width:100%;box-sizing:border-box;margin-top:6px;padding:12px 14px;border:1px solid #d8d4cc;border-radius:8px;color:#191919;font:inherit"><option value="5">★★★★★ — Excellent</option><option value="4">★★★★☆ — Very Good</option><option value="3">★★★☆☆ — Good</option><option value="2">★★☆☆☆ — Fair</option><option value="1">★☆☆☆☆ — Poor</option></select></label>
    <label style="display:block;margin-bottom:20px;font-size:12px;font-weight:700;color:#191919;text-transform:uppercase">Your Review<textarea name="review_text" rows="6" required placeholder="Tell us about your Bali adventure..." style="width:100%;box-sizing:border-box;margin-top:6px;padding:12px 14px;border:1px solid #d8d4cc;border-radius:8px;color:#191919;font:inherit"></textarea></label>
    <button class="btn primary" style="background:#ff5a1f;color:#111;border:0;padding:14px 30px;border-radius:8px;font-family:'Barlow Condensed',sans-serif;font-weight:900;font-size:16px;letter-spacing:1px;text-transform:uppercase;cursor:pointer">Submit Review</button>
  </form>
<?php endif; ?>
</div></section>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>
