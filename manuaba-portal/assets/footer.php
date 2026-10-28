<?php
require __DIR__.'/includes/auth.php';
$p=db();

$keys=['footer_name','footer_description','footer_tagline','footer_bottom_text','footer_quick_title','footer_quick_links','footer_popular_title','footer_popular_links','footer_contact_title','footer_slider_title','footer_hours','footer_offer_title','footer_offer_text','footer_newsletter_placeholder','footer_newsletter_button','footer_social_instagram','footer_social_facebook','footer_social_youtube','footer_social_tripadvisor','footer_social_tiktok','footer_payment_title','footer_payment_methods','footer_trust_1_title','footer_trust_1_text','footer_trust_2_title','footer_trust_2_text','footer_trust_3_title','footer_trust_3_text'];

function footer_setting_save(PDO $db,string $key,string $value): void {
    $st=$db->prepare("SELECT id FROM settings WHERE setting_key=?");
    $st->execute([$key]);
    $id=$st->fetchColumn();
    if($id) $db->prepare("UPDATE settings SET setting_value=?,updated_at=NOW() WHERE id=?")->execute([$value,$id]);
    else $db->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)")->execute([$key,$value]);
}

function footer_asset_upload(string $field, string $folder, ?string $old=null): string {
    if(empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return (string)($old??'');
    $f=$_FILES[$field];
    if(($f['error'] ?? UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (PHP error '.$f['error'].').');
    if(($f['size'] ?? 0)>20*1024*1024) throw new RuntimeException('Maximum file size is 20 MB.');
    $name=(string)($f['name']??'');
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    $allowed=['svg','png','jpg','jpeg','webp'];
    if(!in_array($ext,$allowed,true)) throw new RuntimeException('Allowed formats: SVG, PNG, JPG, JPEG or WebP.');
    $tmp=$f['tmp_name'];
    if(!is_uploaded_file($tmp)) throw new RuntimeException('Invalid uploaded file.');

    // Keep SVG as vector. Raster footer assets are normalized to WebP through admin_upload().
    if($ext==='svg'){
        $raw=@file_get_contents($tmp);
        if($raw===false || stripos($raw,'<svg')===false) throw new RuntimeException('Invalid SVG file.');
        if(preg_match('/<\s*(script|foreignObject)\b|\bon[a-z]+\s*=|javascript\s*:/i',$raw)) throw new RuntimeException('Unsafe SVG file.');
        $base=__DIR__.'/../uploads';
        $dir=$base.'/'.$folder;
        if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Cannot create upload folder: uploads/'.$folder);
        $file=bin2hex(random_bytes(12)).'.svg';
        $dest=$dir.'/'.$file;
        if(!move_uploaded_file($tmp,$dest)) throw new RuntimeException('Cannot save uploaded SVG. Check folder permissions.');
        if($old){
            $oldFile=__DIR__.'/../../'.ltrim($old,'/');
            if(is_file($oldFile)) @unlink($oldFile);
        }
        return 'uploads/'.$folder.'/'.$file;
    }

    return admin_upload($field,$folder,$old);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_verify_csrf();
    try {
        foreach($keys as $k) footer_setting_save($p,$k,trim($_POST[$k]??''));

        // Footer logo
        if(!empty($_FILES['footer_logo']['name'])){
            $path=footer_asset_upload('footer_logo','footer',setting('footer_logo',''));
            footer_setting_save($p,'footer_logo',$path);
        }

        // Footer background
        if(!empty($_FILES['footer_background']['name'])){
            $path=footer_asset_upload('footer_background','footer',setting('footer_background',''));
            footer_setting_save($p,'footer_background',$path);
        }

        // Footer image slider (up to 5 slides)
        $existingSlides=[];
        $rawSlides=(string)setting('footer_image_slides','');
        $decodedSlides=json_decode($rawSlides,true);
        if(is_array($decodedSlides)){
            foreach($decodedSlides as $sl){
                if(is_array($sl) && !empty($sl['image'])) $existingSlides[]=$sl;
            }
        }
        $slides=[];
        for($i=1;$i<=5;$i++){
            $old=$existingSlides[$i-1]??[];
            $field='footer_slide_'.$i.'_image';
            $img=(string)($old['image']??'');
            if(!empty($_FILES[$field]['name'])){
                $img=footer_asset_upload($field,'footer/slides',$img);
            }
            $url=trim($_POST['footer_slide_'.$i.'_url']??($old['url']??''));
            $alt=trim($_POST['footer_slide_'.$i.'_alt']??($old['alt']??''));
            $newtab=!empty($_POST['footer_slide_'.$i.'_newtab']);
            if($img!==''){
                $slides[]=['image'=>$img,'url'=>$url,'alt'=>$alt,'newtab'=>$newtab];
            }
        }
        footer_setting_save($p,'footer_image_slides',json_encode($slides,JSON_UNESCAPED_SLASHES));

        // Wonderful Indonesia logo
        if(!empty($_FILES['footer_wonderful_logo']['name'])){
            $path=footer_asset_upload('footer_wonderful_logo','footer',setting('footer_wonderful_logo',''));
            footer_setting_save($p,'footer_wonderful_logo',$path);
        }

        // Payment names + icons (7 slots)
        $paymentNames=[];
        for($i=1;$i<=7;$i++){
            $name=trim($_POST['footer_payment_'.$i.'_name']??'');
            if($name!=='') $paymentNames[]=$name;
            $field='footer_payment_'.$i.'_icon';
            if(!empty($_FILES[$field]['name'])){
                $path=footer_asset_upload($field,'footer/payments',setting($field,''));
                footer_setting_save($p,$field,$path);
            }
        }
        footer_setting_save($p,'footer_payment_methods',implode(',',$paymentNames));

        admin_flash('','Footer settings saved');
    } catch(Throwable $ex) {
        admin_flash('error','Footer image upload failed: '.$ex->getMessage());
    }
    header('Location: footer.php'); exit;
}

$page_title='Footer Builder';
require __DIR__.'/includes/header.php';
$paymentNames=array_values(array_filter(array_map('trim',explode(',',setting('footer_payment_methods','VISA,MASTERCARD,BCA,MANDIRI,BNI,BRI,QRIS')))));
for($i=1;$i<=7;$i++) $paymentNames[$i-1]=$paymentNames[$i-1]??'';
?>
<div class="card">
<div class="card-head"><div><h2>Footer Builder</h2><p class="muted-line">Manage footer content, Wonderful Indonesia logo and payment icons from the dashboard.</p></div><a class="btn" target="_blank" href="<?=BASE_URL?>/">Preview Website ↗</a></div>
<form class="form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=admin_csrf()?>">
<h3>Brand & Background</h3><div class="form-grid">
<div class="field"><label>Footer Logo</label><input type="file" name="footer_logo" accept="image/svg+xml,image/png,image/jpeg,image/webp"><?php if(setting('footer_logo','')):?><div class="logo-preview"><img src="<?=admin_e(asset_url(setting('footer_logo','')))?>" alt="Footer logo"></div><?php endif;?><small class="muted-line">SVG, PNG, JPG or WebP. Max 20 MB.</small></div>
<div class="field"><label>Footer Background</label><input type="file" name="footer_background" accept="image/png,image/jpeg,image/webp"><?php if(setting('footer_background','')):?><div class="logo-preview"><img src="<?=admin_e(asset_url(setting('footer_background','')))?>" alt="Footer background"></div><?php endif;?><small class="muted-line">PNG, JPG or WebP. Max 20 MB.</small></div>
<div class="field"><label>Wonderful Indonesia Logo</label><input type="file" name="footer_wonderful_logo" accept="image/svg+xml,image/png,image/jpeg,image/webp"><?php if(setting('footer_wonderful_logo','')):?><div class="logo-preview"><img src="<?=admin_e(asset_url(setting('footer_wonderful_logo','')))?>" alt="Wonderful Indonesia"></div><?php endif;?><small class="muted-line">Upload SVG or PNG for best quality. Max 20 MB.</small></div>
<div class="field"><label>Footer Name</label><input name="footer_name" value="<?=admin_e(setting('footer_name',site_brand_name()))?>"></div>
<div class="field"><label>Footer Tagline</label><input name="footer_tagline" value="<?=admin_e(setting('footer_tagline','NATURE · ADVENTURE · HAPPINESS'))?>"></div>
<div class="field full"><label>Description</label><textarea name="footer_description"><?=admin_e(setting('footer_description','Create unforgettable memories with exciting Bali adventures, nature experiences, and local hospitality.'))?></textarea></div>
</div>
<h3>Quick Links</h3><div class="form-grid"><div class="field"><label>Column Title</label><input name="footer_quick_title" value="<?=admin_e(setting('footer_quick_title','Quick Links'))?>"></div><div class="field"><label>Links</label><textarea name="footer_quick_links" placeholder="Label|/url"><?=admin_e(setting('footer_quick_links',"Home|/\nAbout Us|/about.php\nActivities|/packages.php\nPackages|/packages.php\nGallery|/#gallery\nBlog|/blog.php\nContact|/contact.php"))?></textarea><small class="muted-line">One link per line: Label|URL</small></div></div>
<h3>Popular Activities</h3><div class="form-grid"><div class="field"><label>Column Title</label><input name="footer_popular_title" value="<?=admin_e(setting('footer_popular_title','Popular Activities'))?>"></div><div class="field"><label>Links</label><textarea name="footer_popular_links" placeholder="Label|/url"><?=admin_e(setting('footer_popular_links',"Bali ATV|/packages.php\nAyung Rafting|/packages.php\nBali Swing|/packages.php\nUbud Activities|/packages.php\nKintamani Tour|/packages.php\nBali Car Rental|/contact.php"))?></textarea><small class="muted-line">One link per line: Label|URL</small></div></div>
<h3>Contact & Offers</h3><div class="form-grid"><div class="field"><label>Contact Title</label><input name="footer_contact_title" value="<?=admin_e(setting('footer_contact_title','Contact Us'))?>"></div><div class="field"><label>Opening Hours</label><input name="footer_hours" value="<?=admin_e(setting('footer_hours','Mon – Sun : 08:00 – 18:00'))?>"></div><div class="field"><label>Offer Title</label><input name="footer_offer_title" value="<?=admin_e(setting('footer_offer_title','Get Special Offers'))?>"></div><div class="field"><label>Offer Text</label><input name="footer_offer_text" value="<?=admin_e(setting('footer_offer_text','Subscribe to get the latest updates and exclusive deals.'))?>"></div><div class="field"><label>Newsletter Placeholder</label><input name="footer_newsletter_placeholder" value="<?=admin_e(setting('footer_newsletter_placeholder','Your email address'))?>"></div><div class="field"><label>Newsletter Button</label><input name="footer_newsletter_button" value="<?=admin_e(setting('footer_newsletter_button','Subscribe'))?>"></div></div>
<p class="muted-line">Address, phone, email and WhatsApp are taken automatically from <b>Settings</b>.</p>
<h3>Footer Image Slider</h3>
<div class="footer-slider-admin-grid">
  <div class="field full"><label>Slider Title</label><input name="footer_slider_title" value="<?=admin_e(setting('footer_slider_title','Featured Offers'))?>"><small class="muted-line">Images appear horizontally in the footer beside the Special Offers column.</small></div>
  <?php
  $footerAdminSlides=[]; $rawAdminSlides=(string)setting('footer_image_slides',''); $tmpAdmin=json_decode($rawAdminSlides,true);
  if(is_array($tmpAdmin)) foreach($tmpAdmin as $sl) if(is_array($sl)) $footerAdminSlides[]=$sl;
  for($i=1;$i<=5;$i++): $sl=$footerAdminSlides[$i-1]??[]; $img=(string)($sl['image']??''); $url=(string)($sl['url']??''); $alt=(string)($sl['alt']??''); $nt=!empty($sl['newtab']); ?>
  <div class="footer-slider-admin-card">
    <div class="footer-slider-admin-number">Slide <?= $i ?></div>
    <?php if($img && $footerAssetPreview=asset_url($img)): ?><div class="footer-slider-preview"><img src="<?=admin_e($footerAssetPreview)?>" alt="Slide <?= $i ?>"></div><?php endif; ?>
    <label>Image</label><input type="file" name="footer_slide_<?= $i ?>_image" accept="image/svg+xml,image/png,image/jpeg,image/webp">
    <label>Click URL</label><input name="footer_slide_<?= $i ?>_url" value="<?=admin_e($url)?>" placeholder="https://example.com or /packages">
    <label>Alt Text</label><input name="footer_slide_<?= $i ?>_alt" value="<?=admin_e($alt)?>" placeholder="Promotional banner">
    <label class="check-row"><input type="checkbox" name="footer_slide_<?= $i ?>_newtab" value="1"<?= $nt?' checked':'' ?>> Open link in new tab</label>
  </div>
  <?php endfor; ?>
</div>
<h3>Social Media</h3><div class="form-grid"><div class="field"><label>Instagram URL</label><input name="footer_social_instagram" value="<?=admin_e(setting('footer_social_instagram',''))?>"></div><div class="field"><label>Facebook URL</label><input name="footer_social_facebook" value="<?=admin_e(setting('footer_social_facebook',''))?>"></div><div class="field"><label>YouTube URL</label><input name="footer_social_youtube" value="<?=admin_e(setting('footer_social_youtube',''))?>"></div><div class="field"><label>Tripadvisor URL</label><input name="footer_social_tripadvisor" value="<?=admin_e(setting('footer_social_tripadvisor',''))?>"></div><div class="field"><label>TikTok URL</label><input name="footer_social_tiktok" value="<?=admin_e(setting('footer_social_tiktok',''))?>"></div></div>
<h3>Payments & Trust</h3>
<div class="form-grid">
<div class="field full"><label>Payment Title</label><input name="footer_payment_title" value="<?=admin_e(setting('footer_payment_title','We Accept'))?>"></div>
<?php for($i=1;$i<=7;$i++): $icon=setting('footer_payment_'.$i.'_icon',''); ?>
<div class="field payment-admin-field"><label>Payment <?= $i ?></label><input name="footer_payment_<?= $i ?>_name" value="<?=admin_e($paymentNames[$i-1])?>" placeholder="e.g. BCA"><input type="file" name="footer_payment_<?= $i ?>_icon" accept="image/svg+xml,image/png,image/jpeg,image/webp"><?php if($icon): ?><div class="logo-preview"><img src="<?=admin_e(asset_url($icon))?>" alt="Payment <?= $i ?>"></div><?php endif; ?><small class="muted-line">SVG / PNG / JPG / WebP</small></div>
<?php endfor; ?>
<div class="field"><label>Trust 1</label><input name="footer_trust_1_title" value="<?=admin_e(setting('footer_trust_1_title','Authentic'))?>"><input name="footer_trust_1_text" value="<?=admin_e(setting('footer_trust_1_text','Bali Experience'))?>"></div><div class="field"><label>Trust 2</label><input name="footer_trust_2_title" value="<?=admin_e(setting('footer_trust_2_title','Safe & Secure'))?>"><input name="footer_trust_2_text" value="<?=admin_e(setting('footer_trust_2_text','Booking'))?>"></div><div class="field"><label>Trust 3</label><input name="footer_trust_3_title" value="<?=admin_e(setting('footer_trust_3_title','Friendly'))?>"><input name="footer_trust_3_text" value="<?=admin_e(setting('footer_trust_3_text','Local Team'))?>"></div><div class="field full"><label>Copyright Ending</label><input name="footer_bottom_text" value="<?=admin_e(setting('footer_bottom_text','All rights reserved.'))?>"></div></div>
<div class="actions"><button class="btn btn-primary">Save Footer</button></div></form></div>
<?php require __DIR__.'/includes/footer.php'; ?>
