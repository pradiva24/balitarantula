<?php
require __DIR__.'/includes/auth.php';
$page_title='Footer Builder';
$db=db();

$fields=[
 'footer_name'=>'Footer Brand Name',
 'footer_description'=>'Footer Description',
 'footer_tagline'=>'Footer Tagline',
 'footer_bottom_text'=>'Copyright Text',
 'footer_quick_title'=>'Quick Links Title',
 'footer_quick_links'=>'Quick Links (one per line: Label|URL)',
 'footer_popular_title'=>'Popular Activities Title',
 'footer_popular_links'=>'Popular Links (one per line: Label|URL)',
 'footer_contact_title'=>'Contact Section Title',
 'footer_hours'=>'Business Hours',
 'footer_offer_title'=>'Newsletter Heading',
 'footer_offer_text'=>'Newsletter Description',
 'footer_newsletter_placeholder'=>'Newsletter Placeholder',
 'footer_newsletter_button'=>'Newsletter Button',
 'footer_slider_title'=>'Promotion Slider Title',
 'footer_payment_title'=>'Payment Section Title',
 'footer_payment_methods'=>'Payment Methods (comma separated)',
 'footer_trust_1_title'=>'Trust Item 1 Title',
 'footer_trust_1_text'=>'Trust Item 1 Description',
 'footer_trust_2_title'=>'Trust Item 2 Title',
 'footer_trust_2_text'=>'Trust Item 2 Description',
 'footer_trust_3_title'=>'Trust Item 3 Title',
 'footer_trust_3_text'=>'Trust Item 3 Description',
 'footer_social_instagram'=>'Instagram URL',
 'footer_social_facebook'=>'Facebook URL',
 'footer_social_youtube'=>'YouTube URL',
 'footer_social_tripadvisor'=>'Tripadvisor URL',
 'footer_social_tiktok'=>'TikTok URL'
];
$defaults=[
 'footer_name'=>site_brand_name(),
 'footer_description'=>'Create unforgettable memories with exciting Bali adventures, nature experiences, and local hospitality.',
 'footer_tagline'=>'NATURE · ADVENTURE · HAPPINESS',
 'footer_bottom_text'=>'All rights reserved.',
 'footer_quick_title'=>'Quick Links',
 'footer_quick_links'=>"Home|/\nAbout Us|/about.php\nActivities|/packages.php\nPackages|/packages.php\nGallery|/#gallery\nBlog|/blog.php\nContact|/contact.php",
 'footer_popular_title'=>'Popular Activities',
 'footer_popular_links'=>"Bali ATV|/packages.php\nAyung Rafting|/packages.php\nBali Swing|/packages.php\nUbud Activities|/packages.php\nKintamani Tour|/packages.php\nBali Car Rental|/contact.php",
 'footer_contact_title'=>'Contact Us','footer_hours'=>'Mon – Sun : 08:00 – 18:00',
 'footer_offer_title'=>'Get Special Offers','footer_offer_text'=>'Subscribe to get the latest updates and exclusive deals.',
 'footer_newsletter_placeholder'=>'Your email address','footer_newsletter_button'=>'Subscribe',
 'footer_slider_title'=>'Featured Offers','footer_payment_title'=>'We Accept',
 'footer_payment_methods'=>'VISA,MASTERCARD,BCA,MANDIRI,BNI,BRI,QRIS',
 'footer_trust_1_title'=>'Authentic','footer_trust_1_text'=>'Bali Experience',
 'footer_trust_2_title'=>'Safe & Secure','footer_trust_2_text'=>'Booking',
 'footer_trust_3_title'=>'Friendly','footer_trust_3_text'=>'Local Team'
];
$values=[];
foreach($fields as $key=>$label){$values[$key]=setting($key,$defaults[$key]??'');}
$imageKeys=['footer_logo','footer_background','footer_wonderful_logo'];
foreach($imageKeys as $key)$values[$key]=setting($key,'');
for($i=1;$i<=7;$i++)$values['footer_payment_'.$i.'_icon']=setting('footer_payment_'.$i.'_icon','');
$values['footer_image_slides']=setting('footer_image_slides','[]');
$slides=json_decode((string)$values['footer_image_slides'],true);
if(!is_array($slides))$slides=[];
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 admin_verify_csrf();
 try{
  // Match the established Settings page save strategy: update every legacy
  // duplicate row, and insert only when the key does not exist.
  $saveSetting=static function(PDO $db,string $key,string $value): void {
   $check=$db->prepare("SELECT id FROM settings WHERE setting_key=?");
   $check->execute([$key]);
   $ids=$check->fetchAll(PDO::FETCH_COLUMN);
   if($ids){
    $update=$db->prepare("UPDATE settings SET setting_value=?,updated_at=NOW() WHERE setting_key=?");
    $update->execute([$value,$key]);
   }else{
    $insert=$db->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)");
    $insert->execute([$key,$value]);
   }
  };
  foreach($fields as $key=>$label){
   $value=trim((string)($_POST[$key]??''));
   $saveSetting($db,$key,$value);
   $values[$key]=$value;
  }
  foreach($imageKeys as $key){
   $path=admin_upload($key,'footer',$values[$key]);
   if($path!==null){$saveSetting($db,$key,$path);$values[$key]=$path;}
  }
  for($i=1;$i<=7;$i++){
   $key='footer_payment_'.$i.'_icon';$path=admin_upload($key,'footer',$values[$key]);
   if($path!==null){$saveSetting($db,$key,$path);$values[$key]=$path;}
  }
  $raw=trim((string)($_POST['footer_image_slides']??'[]'));
  $decoded=json_decode($raw,true);
  if(!is_array($decoded))throw new RuntimeException('Promotion slides harus berupa JSON array yang valid.');
  $clean=[];
  foreach($decoded as $slide){
   if(!is_array($slide))continue;
   $image=trim((string)($slide['image']??''));
   if($image==='')continue;
   $clean[]=['image'=>$image,'url'=>trim((string)($slide['url']??'')),'alt'=>trim((string)($slide['alt']??'')),'newtab'=>!empty($slide['newtab'])];
  }
  $values['footer_image_slides']=json_encode($clean,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  $saveSetting($db,'footer_image_slides',$values['footer_image_slides']);
  admin_flash('success','Footer settings berhasil disimpan.');
  header('Location: footer.php');exit;
 }catch(Throwable $e){$errors[]=$e->getMessage();}
}
require __DIR__.'/includes/header.php';
?>
<style>
.fb-wrap{max-width:1180px;margin:24px auto;padding:0 18px}.fb-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;margin:0 0 18px}.fb-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.fb-field label{display:block;font-size:13px;font-weight:700;margin:0 0 7px}.fb-field input,.fb-field textarea{width:100%;box-sizing:border-box;padding:11px;border:1px solid #d7dce1;border-radius:8px;font:inherit}.fb-field textarea{min-height:110px;resize:vertical}.fb-field.full{grid-column:1/-1}.fb-hint{font-size:12px;color:#667085;margin-top:5px}.fb-preview{display:flex;gap:12px;align-items:center;margin-top:8px;flex-wrap:wrap}.fb-preview img{max-width:180px;max-height:75px;object-fit:contain;background:#f3f4f6;border-radius:6px;padding:5px}.fb-save{position:sticky;bottom:12px;display:flex;justify-content:flex-end;padding:12px;background:rgba(255,255,255,.94);border:1px solid #e5e7eb;border-radius:12px;z-index:5}.fb-save button{background:#f45b20;color:#fff;border:0;border-radius:9px;padding:13px 24px;font-weight:800;cursor:pointer}.fb-error{background:#fff1f0;color:#9f1d16;border:1px solid #ffc9c4;padding:12px;border-radius:8px;margin-bottom:14px}@media(max-width:700px){.fb-grid{grid-template-columns:1fr}.fb-field.full{grid-column:auto}.fb-card{padding:16px}}
</style>
<main class="fb-wrap">
 <h1>Footer Builder</h1><p>Kelola konten, gambar background, tautan, slider promosi, informasi pembayaran, dan elemen footer website.</p>
 <?php foreach($errors as $error):?><div class="fb-error"><?=admin_e($error)?></div><?php endforeach;?>
 <form method="post" enctype="multipart/form-data" id="footerForm"><input type="hidden" name="csrf" value="<?=admin_csrf()?>">
 <section class="fb-card"><h2>Brand & Background</h2><div class="fb-grid">
 <?php foreach(['footer_name','footer_tagline','footer_bottom_text'] as $key):?><div class="fb-field"><label><?=admin_e($fields[$key])?></label><input name="<?=admin_e($key)?>" value="<?=admin_e($values[$key])?>"></div><?php endforeach;?>
 <div class="fb-field full"><label>Footer Description</label><textarea name="footer_description"><?=admin_e($values['footer_description'])?></textarea></div>
 <?php foreach(['footer_logo'=>'Footer Logo','footer_background'=>'Footer Background Image','footer_wonderful_logo'=>'Wonderful Indonesia Logo'] as $key=>$label):?>
 <div class="fb-field"><label><?=admin_e($label)?></label><input type="file" name="<?=admin_e($key)?>" accept="image/jpeg,image/png,image/webp,image/gif">
 <?php if($values[$key]):?><div class="fb-preview"><img src="<?=admin_e(asset_url($values[$key]))?>" alt=""><small><?=admin_e($values[$key])?></small></div><?php endif;?></div>
 <?php endforeach;?>
 </div></section>
 <section class="fb-card"><h2>Navigation Links</h2><div class="fb-grid">
 <?php foreach(['footer_quick_title','footer_popular_title'] as $key):?><div class="fb-field"><label><?=admin_e($fields[$key])?></label><input name="<?=admin_e($key)?>" value="<?=admin_e($values[$key])?>"></div><?php endforeach;?>
 <?php foreach(['footer_quick_links','footer_popular_links'] as $key):?><div class="fb-field"><label><?=admin_e($fields[$key])?></label><textarea name="<?=admin_e($key)?>"><?=admin_e($values[$key])?></textarea><div class="fb-hint">Satu tautan per baris dengan format Label|URL</div></div><?php endforeach;?>
 </div></section>
 <section class="fb-card"><h2>Contact & Newsletter</h2><div class="fb-grid">
 <?php foreach(['footer_contact_title','footer_hours','footer_offer_title','footer_newsletter_placeholder','footer_newsletter_button'] as $key):?><div class="fb-field"><label><?=admin_e($fields[$key])?></label><input name="<?=admin_e($key)?>" value="<?=admin_e($values[$key])?>"></div><?php endforeach;?>
 <?php foreach(['footer_offer_text'] as $key):?><div class="fb-field full"><label><?=admin_e($fields[$key])?></label><textarea name="<?=admin_e($key)?>"><?=admin_e($values[$key])?></textarea></div><?php endforeach;?>
 </div></section>
 <section class="fb-card"><h2>Social Media</h2><div class="fb-grid"><?php foreach(['footer_social_instagram','footer_social_facebook','footer_social_youtube','footer_social_tripadvisor','footer_social_tiktok'] as $key):?><div class="fb-field"><label><?=admin_e($fields[$key])?></label><input type="url" name="<?=admin_e($key)?>" value="<?=admin_e($values[$key])?>" placeholder="https://"></div><?php endforeach;?></div></section>
 <section class="fb-card"><h2>Featured Offers Slider</h2><div class="fb-field"><label>Slider Title</label><input name="footer_slider_title" value="<?=admin_e($values['footer_slider_title'])?>"></div>
 <div class="fb-field" style="margin-top:14px"><label>Promotion Slides (JSON)</label><textarea name="footer_image_slides" style="min-height:180px"><?=admin_e(json_encode($slides,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></textarea><div class="fb-hint">Setiap item: {"image":"uploads/banner.webp","url":"/packages.php","alt":"ATV offer","newtab":false}. Pilih gambar dari Media Library pada input file bila diperlukan dengan menambahkan path gambar di sini.</div></div></section>
 <section class="fb-card"><h2>Trust & Payment</h2><div class="fb-grid">
 <?php foreach(['footer_trust_1_title','footer_trust_1_text','footer_trust_2_title','footer_trust_2_text','footer_trust_3_title','footer_trust_3_text','footer_payment_title','footer_payment_methods'] as $key):?><div class="fb-field"><label><?=admin_e($fields[$key])?></label><input name="<?=admin_e($key)?>" value="<?=admin_e($values[$key])?>"></div><?php endforeach;?>
 <?php for($i=1;$i<=7;$i++):$key='footer_payment_'.$i.'_icon';?><div class="fb-field"><label>Payment Icon <?=$i?></label><input type="file" name="<?=admin_e($key)?>" accept="image/jpeg,image/png,image/webp,image/gif"><?php if($values[$key]):?><div class="fb-preview"><img src="<?=admin_e(asset_url($values[$key]))?>" alt=""><small><?=admin_e($values[$key])?></small></div><?php endif;?></div><?php endfor;?>
 </div></section>
 <div class="fb-save"><button type="submit">Save Footer Settings</button></div>
 </form>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>
