<?php
require __DIR__.'/includes/auth.php';
$p=db();
$keys=['site_name','site_url','site_email','site_phone','site_whatsapp','site_address','site_tagline','site_description','currency','timezone','booking_notification_email','booking_ticket_only_enabled','booking_hotel_pickup_enabled','smtp_host','smtp_port','smtp_username','smtp_password','smtp_secure','smtp_from_email','smtp_from_name'];

if(isset($_GET['smtp_test'])){
    http_response_code(405); header('Allow: POST'); exit('Method Not Allowed');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_verify_csrf();
    $isSmtpTest=isset($_POST['smtp_test']);

    // Save submitted settings first, so "Send SMTP Test" tests the values just entered.
    foreach($keys as $k){
        if($k==='smtp_password'){
            $v=(string)($_POST[$k]??'');
            // Blank password means keep the existing password.
            if($v==='') continue;
        }else{
            $v=trim((string)($_POST[$k]??''));
        }
        $st=$p->prepare("SELECT id FROM settings WHERE setting_key=?");
        $st->execute([$k]);
        $id=$st->fetchColumn();
        if($id){
            $p->prepare("UPDATE settings SET setting_value=?,updated_at=NOW() WHERE id=?")->execute([$v,$id]);
        }else{
            $p->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)")->execute([$k,$v]);
        }
    }

    if($isSmtpTest){
        $st=$p->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('booking_notification_email','site_email','smtp_host','smtp_port','smtp_username','smtp_password','smtp_secure','smtp_from_email','smtp_from_name')");
        $cfg=[];
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row) $cfg[$row['setting_key']]=$row['setting_value'];
        // SMTP test recipient fallback chain:
        // Admin Booking Email -> Site Email -> SMTP Username -> From Email.
        // This allows testing SMTP even when a separate notification address has not been configured yet.
        $candidates = [
            trim((string)($cfg['booking_notification_email'] ?? '')),
            trim((string)($cfg['site_email'] ?? '')),
            trim((string)($cfg['smtp_username'] ?? '')),
            trim((string)($cfg['smtp_from_email'] ?? '')),
        ];
        $to = '';
        foreach($candidates as $candidate){
            if(filter_var($candidate, FILTER_VALIDATE_EMAIL)){
                $to = $candidate;
                break;
            }
        }

        require_once __DIR__.'/../includes/smtp.php';
        if($to===''){
            admin_flash('error','SMTP test failed: enter a valid email address in Admin Booking Email, Site Email, SMTP Username, or From Email.');
        }else{
            $subject=site_brand_name().' SMTP Test';
            $html='<div style="font-family:Arial,sans-serif;line-height:1.6"><h2>SMTP Test Successful</h2><p>This email confirms that the website SMTP settings are working correctly.</p><p>Sent: '.e(date('Y-m-d H:i:s')).'</p></div>';
            $ok=smtp_send($to,$subject,$html,'',$cfg);
            if($ok){
                admin_flash('success','SMTP test sent successfully to '.$to.'. Check the inbox and Spam/Junk folder.');
            }else{
                $detail=smtp_last_error();
                admin_flash('error','SMTP test failed: '.$detail);
            }
        }
        header('Location: settings.php');
        exit;
    }

    if(admin_media_has_selection('admin_logo') || !empty($_FILES['admin_logo']['name'])){
        try{$path=admin_upload('admin_logo','branding',setting('admin_logo',''));$st=$p->prepare("SELECT id FROM settings WHERE setting_key=?");$st->execute(['admin_logo']);$id=$st->fetchColumn();if($id)$p->prepare("UPDATE settings SET setting_value=?,updated_at=NOW() WHERE id=?")->execute([$path,$id]);else$p->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)")->execute(['admin_logo',$path]);}
        catch(Throwable $ex){admin_flash('error','Admin logo upload failed: '.$ex->getMessage());header('Location: settings.php');exit;}
    }
    if(admin_media_has_selection('site_logo') || !empty($_FILES['site_logo']['name'])){
        try{$path=admin_upload('site_logo','branding',setting('site_logo',''));$st=$p->prepare("SELECT id FROM settings WHERE setting_key=?");$st->execute(['site_logo']);$id=$st->fetchColumn();if($id)$p->prepare("UPDATE settings SET setting_value=?,updated_at=NOW() WHERE id=?")->execute([$path,$id]);else$p->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)")->execute(['site_logo',$path]);}
        catch(Throwable $ex){admin_flash('error','Website logo upload failed: '.$ex->getMessage());header('Location: settings.php');exit;}
    }
    if(admin_media_has_selection('favicon') || !empty($_FILES['favicon']['name'])){
        try{$path=admin_upload('favicon','branding',setting('favicon',''));$st=$p->prepare("SELECT id FROM settings WHERE setting_key=?");$st->execute(['favicon']);$id=$st->fetchColumn();if($id)$p->prepare("UPDATE settings SET setting_value=?,updated_at=NOW() WHERE id=?")->execute([$path,$id]);else$p->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)")->execute(['favicon',$path]);}
        catch(Throwable $ex){admin_flash('error','Favicon upload failed: '.$ex->getMessage());header('Location: settings.php');exit;}
    }
    admin_flash('success','Settings saved');
    header('Location: settings.php');
    exit;
}

$page_title='Settings';require __DIR__.'/includes/header.php';?>
<form class="form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=admin_csrf()?>">
<h3>Branding</h3><div class="form-grid"><div class="field"><label>Website Logo</label><input type="file" name="site_logo" accept="image/png,image/jpeg,image/webp"><?php if(setting('site_logo','')):?><div class="logo-preview"><img src="<?=admin_e(asset_url(setting('site_logo','')))?>" alt="Website Logo"></div><?php endif;?><small class="muted-line">Main logo shown on the public website. PNG, JPG or WebP · max 100 MB</small></div><div class="field"><label>Admin Logo</label><input type="file" name="admin_logo" accept="image/png,image/jpeg,image/webp"><?php if(setting('admin_logo','')):?><div class="logo-preview"><img src="<?=admin_e(asset_url(setting('admin_logo','')))?>" alt="Admin Logo"></div><?php endif;?><small class="muted-line">PNG, JPG or WebP · max 100 MB</small></div><div class="field"><label>Favicon</label><input type="file" name="favicon" accept="image/png,image/jpeg,image/webp"><?php if(setting('favicon','')):?><div class="favicon-preview"><img src="<?=admin_e(asset_url(setting('favicon','')))?>" alt="Favicon"></div><?php endif;?><small class="muted-line">PNG/JPG/WebP · recommended 512×512</small></div></div>
<h3>Website</h3><div class="form-grid"><div class="field"><label>Site Name</label><input name="site_name" value="<?=admin_e(site_brand_name())?>"></div><div class="field"><label>Site Tagline</label><input name="site_tagline" value="<?=admin_e(setting("site_tagline","Bali's Hidden Waterfall & Nature Adventure"))?>"></div><div class="field full"><label>Site Description</label><textarea name="site_description" rows="3"><?=admin_e(setting("site_description","Discover Manuaba Crystalfall, Bali's hidden waterfall and nature adventure experience."))?></textarea></div><div class="field"><label>Site URL</label><input name="site_url" value="<?=admin_e(setting('site_url',BASE_URL))?>"></div><div class="field"><label>Site Email</label><input name="site_email" value="<?=admin_e(setting('site_email',''))?>"></div><div class="field"><label>Phone</label><input name="site_phone" value="<?=admin_e(setting('site_phone',''))?>"></div><div class="field"><label>WhatsApp</label><input name="site_whatsapp" value="<?=admin_e(setting('site_whatsapp',''))?>"></div><div class="field"><label>Currency</label><input name="currency" value="<?=admin_e(setting('currency','IDR'))?>"></div><div class="field full"><label>Address</label><input name="site_address" value="<?=admin_e(setting('site_address','Bali, Indonesia'))?>"></div></div>
<h3>Booking Notification</h3><div class="form-grid"><div class="field"><label>Admin Booking Email</label><input type="email" name="booking_notification_email" value="<?=admin_e(setting('booking_notification_email',''))?>"></div><div class="field"><label>Timezone</label><input name="timezone" value="<?=admin_e(setting('timezone','Asia/Makassar'))?>"></div></div>
<h3>Booking Availability</h3><p class="muted-line">Choose which booking options customers can use. Changes apply to the public booking form.</p><div class="form-grid"><div class="field"><label><input type="checkbox" name="booking_ticket_only_enabled" value="1" <?=setting('booking_ticket_only_enabled','1')==='1'?'checked':''?>> Enable Ticket Only</label><small class="muted-line">Customers can book tickets without hotel transport.</small></div><div class="field"><label><input type="checkbox" name="booking_hotel_pickup_enabled" value="1" <?=setting('booking_hotel_pickup_enabled','1')==='1'?'checked':''?>> Enable Hotel Pickup</label><small class="muted-line">Customers can choose hotel pickup and see pickup details.</small></div></div>
<h3>Payment</h3>
<div class="field full"><small class="muted-line">Payment settings have been moved to <strong>Admin → Payment Gateway</strong>. Bank details, payment methods, Midtrans, DOKU and Xendit are managed there.</small></div>
<h3>SMTP</h3><p class="muted-line">Use your domain mailbox credentials. For security, the saved SMTP password is never shown here. Leave the password blank to keep the existing password.</p><div class="form-grid"><div class="field"><label>SMTP Host</label><input name="smtp_host" placeholder="mail.yourdomain.com" value="<?=admin_e(setting('smtp_host',''))?>"></div><div class="field"><label>SMTP Port</label><input name="smtp_port" value="<?=admin_e(setting('smtp_port','587'))?>"></div><div class="field"><label>Username</label><input name="smtp_username" autocomplete="username" value="<?=admin_e(setting('smtp_username',''))?>"></div><div class="field"><label>Password</label><input type="password" name="smtp_password" autocomplete="new-password" value="" placeholder="Leave blank to keep current password"></div><div class="field"><label>Security</label><select name="smtp_secure"><option value="tls" <?=setting('smtp_secure','tls')==='tls'?'selected':''?>>TLS</option><option value="ssl" <?=setting('smtp_secure','tls')==='ssl'?'selected':''?>>SSL</option><option value="none" <?=setting('smtp_secure','tls')==='none'?'selected':''?>>None</option></select></div><div class="field"><label>From Email</label><input type="email" name="smtp_from_email" value="<?=admin_e(setting('smtp_from_email',''))?>"></div><div class="field full"><label>From Name</label><input name="smtp_from_name" value="<?=admin_e(setting('smtp_from_name',site_brand_name()))?>"></div></div>
<div class="actions"><button class="btn" type="submit">Save Settings</button> <button class="btn gray" type="submit" name="smtp_test" value="1">Send SMTP Test</button></div></form>
<?php require __DIR__.'/includes/footer.php';?>
