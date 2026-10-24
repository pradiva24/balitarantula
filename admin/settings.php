<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$admin = require_role(['owner','admin']);
require_once dirname(__DIR__) . '/includes/site-settings.php';
require_once dirname(__DIR__) . '/includes/smtp.php';
$pdo = Database::connection();

$fields = [
 'site_title'=>['Site title','text','Bali Tarantula Adventure'],
 'site_description'=>['Site description','textarea','ATV adventures, jungle tracks, and unforgettable outdoor experiences in Bali.'],
 'site_url'=>['Website URL','url','https://tarantula.baliatvgo.com'],
 'contact_email'=>['Contact email','email',''],
 'contact_phone'=>['Contact phone','text',''],
 'contact_whatsapp'=>['WhatsApp number (international digits)','text',''],
 'contact_address'=>['Business address','textarea','Bali, Indonesia'],
 'contact_hours'=>['Business hours','text','Daily, by reservation'],
 'social_instagram'=>['Instagram URL','url',''],
 'social_facebook'=>['Facebook URL','url',''],
 'social_tiktok'=>['TikTok URL','url',''],
 'social_youtube'=>['YouTube URL','url',''],
 'social_tripadvisor'=>['Tripadvisor URL','url',''],
 'social_google_maps'=>['Google Maps URL','url',''],
 'smtp_host'=>['SMTP host','text',''],
 'smtp_port'=>['SMTP port','number','587'],
 'smtp_encryption'=>['Encryption','select','tls'],
 'smtp_username'=>['SMTP username','text',''],
 'smtp_from_email'=>['Sender email address','email',''],
 'smtp_from_name'=>['Sender display name','text','Bali Tarantula Adventure'],
];
$defaults = [];
foreach ($fields as $key=>$field) $defaults[$key] = $field[2];
$values = $defaults;
$logoPath = '';
$faviconPath = '';
$ready = true;
$error = null;
$success = null;
try {
    $rows = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($values as $key=>$value) if (isset($rows[$key]) && (string)$rows[$key] !== '') $values[$key] = (string)$rows[$key];
    $logoPath = (string)($rows['site_logo'] ?? '');
    $faviconPath = (string)($rows['site_favicon'] ?? '');
    $ready = true;
} catch (Throwable $e) {
    $ready = false;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $action = (string)($_POST['action'] ?? 'save');
    if (!$ready) {
        $error = 'Database table site_settings is missing. Import database/migrations/20261024_site_settings.sql first.';
    } elseif ($action === 'test_email') {
        $recipient = filter_var(trim((string)($_POST['test_email_to'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$recipient) {
            $error = 'Enter a valid recipient email address for the SMTP test.';
        } else {
            try {
                bta_smtp_send($recipient, 'Bali Tarantula Adventure — SMTP test', "This is a test email from your Bali Tarantula Adventure admin settings.\n\nIf you received this email, the SMTP connection and sender settings are working.");
                $success = 'Test email accepted by the SMTP server. Check the recipient inbox and spam folder.';
            } catch (Throwable $e) {
                error_log('BTA SMTP test failed: ' . $e->getMessage());
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'save') {
        try {
            $incoming = [];
            foreach ($fields as $key=>$field) {
                $value = trim((string)($_POST[$key] ?? ''));
                if (mb_strlen($value) > 4000) throw new DomainException($field[0] . ' is too long.');
                if (in_array($field[1], ['url','email'], true) && $value !== '') {
                    if ($field[1] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) throw new DomainException($field[0] . ' must be a valid email address.');
                    if ($field[1] === 'url' && (!filter_var($value, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($value, PHP_URL_SCHEME)), ['https','http'], true))) throw new DomainException($field[0] . ' must be a valid HTTP or HTTPS URL.');
                }
                $incoming[$key] = $value;
            }
            if (!in_array($incoming['smtp_encryption'], ['tls','ssl','none'], true)) throw new DomainException('Select a valid SMTP encryption option.');
            $port = filter_var($incoming['smtp_port'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>65535]]);
            if ($port === false) throw new DomainException('SMTP port must be between 1 and 65535.');
            $incoming['smtp_port'] = (string)$port;
            $currentSecret = (string)($rows['smtp_password_encrypted'] ?? '');
            $newPassword = (string)($_POST['smtp_password'] ?? '');
            if ($newPassword !== '') {
                if (mb_strlen($newPassword) > 2000) throw new DomainException('SMTP password is too long.');
                $incoming['smtp_password_encrypted'] = bta_encrypt_setting_secret($newPassword);
            } elseif ($currentSecret !== '') {
                $incoming['smtp_password_encrypted'] = $currentSecret;
            }
            $newLogo = null;
            $newFavicon = null;
            if (isset($_FILES['site_logo']) && (int)($_FILES['site_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['site_logo'];
                if ((int)$file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 5*1024*1024) throw new DomainException('Logo upload failed or exceeds 5 MB.');
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
                $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if (!isset($extensions[$mime])) throw new DomainException('Logo must be JPG, PNG, or WEBP.');
                $uploadDir = dirname(__DIR__) . '/assets/uploads/site';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException('Logo upload directory could not be created.');
                $name = 'logo-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
                if (!move_uploaded_file((string)$file['tmp_name'], $uploadDir . '/' . $name)) throw new RuntimeException('Logo could not be saved. Check folder permissions.');
                $newLogo = '/assets/uploads/site/' . $name;
            }
            if (isset($_FILES['site_favicon']) && (int)($_FILES['site_favicon']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['site_favicon'];
                if ((int)$file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 2*1024*1024) throw new DomainException('Favicon upload failed or exceeds 2 MB.');
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
                $extensions = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/vnd.microsoft.icon'=>'ico','image/x-icon'=>'ico'];
                if (!isset($extensions[$mime])) throw new DomainException('Favicon must be PNG, JPG, WEBP, or ICO.');
                $uploadDir = dirname(__DIR__) . '/assets/uploads/site';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException('Favicon upload directory could not be created.');
                $name = 'favicon-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
                if (!move_uploaded_file((string)$file['tmp_name'], $uploadDir . '/' . $name)) throw new RuntimeException('Favicon could not be saved. Check folder permissions.');
                $newFavicon = '/assets/uploads/site/' . $name;
            }
            $pdo->beginTransaction();
            $save = $pdo->prepare('INSERT INTO site_settings (setting_key,setting_value,updated_by) VALUES (:key,:value,:admin) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');
            foreach ($incoming as $key=>$value) $save->execute(['key'=>$key,'value'=>$value,'admin'=>(int)$admin['id']]);
            if ($newLogo !== null) $save->execute(['key'=>'site_logo','value'=>$newLogo,'admin'=>(int)$admin['id']]);
            if ($newFavicon !== null) $save->execute(['key'=>'site_favicon','value'=>$newFavicon,'admin'=>(int)$admin['id']]);
            $pdo->commit();
            $success = 'Settings saved successfully.';
            $rows = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($values as $key=>$value) if (isset($rows[$key]) && (string)$rows[$key] !== '') $values[$key] = (string)$rows[$key];
            $logoPath = (string)($rows['site_logo'] ?? $logoPath);
            $faviconPath = (string)($rows['site_favicon'] ?? $faviconPath);
            if ($newLogo !== null) $logoPath = $newLogo;
            if ($newFavicon !== null) $faviconPath = $newFavicon;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Site settings save failed: ' . $e->getMessage());
            $error = $e instanceof DomainException ? $e->getMessage() : ($e instanceof RuntimeException ? $e->getMessage() : 'Could not save settings. Check the database and upload permissions.');
        }
    }
}
function settings_field(string $key, array $field, array $values, bool $ready): void {
    [$label,$type] = $field;
    $value = (string)($values[$key] ?? '');
    echo '<div class="settings-field"><label for="'.e($key).'">'.e($label).'</label>';
    if ($type === 'textarea') {
        echo '<textarea id="'.e($key).'" name="'.e($key).'" rows="3" maxlength="4000" '.($ready?'':'disabled').'>'.e($value).'</textarea>';
    } elseif ($type === 'select') {
        echo '<select id="'.e($key).'" name="'.e($key).'" '.($ready?'':'disabled').'><option value="tls"'.($value==='tls'?' selected':'').'>STARTTLS / TLS (recommended)</option><option value="ssl"'.($value==='ssl'?' selected':'').'>SSL / implicit TLS</option><option value="none"'.($value==='none'?' selected':'').'>None (not recommended)</option></select>';
    } else {
        echo '<input id="'.e($key).'" name="'.e($key).'" type="'.e($type).'" value="'.e($value).'" maxlength="4000" '.($type==='number'?'min="1" max="65535" step="1" ':'').($ready?'':'disabled').'>';
    }
    echo '</div>';
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Settings | Bali Tarantula Admin</title><link rel="stylesheet" href="../assets/css/admin-sidebar.css?v=20261024">
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f6f4;color:#20241f;font:14px/1.55 Inter,Arial,sans-serif}.top{background:#fff;padding:20px 28px;border-bottom:1px solid #e4e8e2;display:flex;justify-content:space-between;align-items:center;gap:12px}.top strong{font-size:12px;letter-spacing:1px}.top a{color:#39453b;text-decoration:none;font-weight:700}.settings-wrap{max-width:1120px;margin:auto;padding:28px 22px 60px}.eyebrow{font-size:10px;letter-spacing:1.8px;color:#ff5a1f;font-weight:900}.settings-wrap h1{font-size:clamp(30px,4vw,42px);letter-spacing:-1px;margin:5px 0}.intro{color:#737b73;max-width:760px}.notice{padding:13px 16px;margin:16px 0;border-radius:5px;background:#e4f5e8;color:#245531}.notice.error{background:#ffebe5;color:#852c17}.notice.info{background:#fff3df;color:#72460e}.settings-card{background:#fff;border:1px solid #e4e8e2;border-radius:8px;margin:20px 0;overflow:hidden}.card-heading{padding:18px 21px;border-bottom:1px solid #e4e8e2;display:flex;align-items:center;gap:12px}.card-heading .num{display:grid;place-items:center;width:35px;height:35px;background:#fff0e9;color:#ff5a1f;font-weight:900;border-radius:5px}.card-heading h2{font-size:17px;margin:0}.card-heading p{font-size:11px;color:#7b827a;margin:3px 0 0}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px;padding:21px}.settings-field{min-width:0}.settings-field.full{grid-column:1/-1}.settings-field label{display:block;font-size:11px;font-weight:800;margin-bottom:7px}.settings-field input,.settings-field textarea,.settings-field select{width:100%;border:1px solid #d9ded8;border-radius:4px;background:#fff;padding:12px;color:#20241f;font:13px/1.5 Inter,Arial,sans-serif}.settings-field textarea{resize:vertical}.settings-field input:focus,.settings-field textarea:focus,.settings-field select:focus{outline:2px solid #ffc1a7;border-color:#ff5a1f}.hint{font-size:10px;color:#838a82;margin-top:6px}.logo-preview{max-width:240px;max-height:110px;object-fit:contain;object-position:left center;background:#f4f5f3;border:1px solid #e4e8e2;padding:10px;display:block;margin:0 0 12px}.password-note{font-size:11px;background:#f5f6f4;border-radius:4px;padding:11px;color:#61695f;margin-bottom:13px}.savebar{position:sticky;bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;background:#17221b;color:#fff;border-radius:7px;box-shadow:0 8px 28px #0002}.savebar p{margin:0;color:#c5cec6;font-size:11px}.btn{display:inline-block;border:0;border-radius:4px;background:#ff5a1f;color:#171b17;padding:12px 18px;font-weight:900;cursor:pointer}.btn.secondary{background:#fff;border:1px solid #dce1da}.smtp-test{display:flex;gap:10px;align-items:end;padding:0 21px 21px}.smtp-test .settings-field{flex:1}.smtp-test .btn{white-space:nowrap}.social-preview{display:flex;gap:10px;flex-wrap:wrap;padding:0 21px 20px}.social-preview a{font-size:11px;color:#39453b;text-decoration:none;border:1px solid #e4e8e2;border-radius:4px;padding:7px 10px}@media(max-width:700px){.fields{grid-template-columns:1fr;padding:16px}.settings-field.full{grid-column:auto}.settings-wrap{padding:20px 12px 40px}.top{padding:18px 12px 18px 62px}.savebar{align-items:flex-start;flex-direction:column}.savebar .btn{width:100%}.smtp-test{align-items:stretch;flex-direction:column;padding:0 16px 16px}.smtp-test .btn{width:100%}}
</style></head><body>
<?php require __DIR__ . '/_sidebar.php'; ?>
<header class="top"><strong>ADMIN / SETTINGS</strong><a href="../index.php" target="_blank" rel="noopener">Preview website ↗</a></header>
<main class="settings-wrap"><p class="eyebrow">GLOBAL CONFIGURATION</p><h1>Website Settings</h1><p class="intro">Manage the public identity, logo, contact channels, social profiles, and SMTP email configuration from one place.</p>
<?php if($success):?><div class="notice"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?>
<?php if(!$ready):?><div class="notice info"><strong>Database setup required.</strong> Import <code>database/migrations/20261024_site_settings.sql</code> in phpMyAdmin, then reload this page.</div><?php endif;?>
<form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="save">
<section class="settings-card"><div class="card-heading"><span class="num">01</span><div><h2>Brand & SEO</h2><p>Website title, description, URL, and logo.</p></div></div><div class="fields">
<?php foreach(['site_title','site_description','site_url'] as $key):?><div class="settings-field<?=$key==='site_description'?' full':''?>"><?php settings_field($key,$fields[$key],$values,$ready);?></div><?php endforeach;?>
<div class="settings-field full"><label for="site_logo">Website logo</label><?php if($logoPath!==''):?><img class="logo-preview" src="<?=e($logoPath)?>" alt="Current website logo"><?php endif;?><input id="site_logo" type="file" name="site_logo" accept="image/jpeg,image/png,image/webp" <?=$ready?'':'disabled'?>><p class="hint">JPG, PNG or WEBP · max 5 MB. Leave empty to keep the current logo.</p></div>
<div class="settings-field full"><label for="site_favicon">Browser favicon</label><?php if($faviconPath!==''):?><div style="display:flex;align-items:center;gap:12px;margin:0 0 12px"><img src="<?=e($faviconPath)?>" alt="Current favicon" style="width:48px;height:48px;object-fit:contain;background:#f4f5f3;border:1px solid #e4e8e2;padding:6px"><span class="hint">Current favicon preview</span></div><?php endif;?><input id="site_favicon" type="file" name="site_favicon" accept=".png,.jpg,.jpeg,.webp,.ico,image/png,image/jpeg,image/webp,image/x-icon" <?=$ready?'':'disabled'?>><p class="hint">PNG, JPG, WEBP or ICO · max 2 MB. This image appears in the browser tab and bookmarks. Leave empty to keep the current favicon.</p></div>
</div></section>
<section class="settings-card"><div class="card-heading"><span class="num">02</span><div><h2>Contact information</h2><p>Contact details displayed across the website.</p></div></div><div class="fields">
<?php foreach(['contact_email','contact_phone','contact_whatsapp','contact_address','contact_hours'] as $key):?><div class="settings-field<?=$key==='contact_address'?' full':''?>"><?php settings_field($key,$fields[$key],$values,$ready);?><?php if($key==='contact_whatsapp'):?><p class="hint">Use international digits only, e.g. 6281234567890. Do not include +, spaces, or dashes.</p><?php endif;?></div><?php endforeach;?>
</div></section>
<section class="settings-card"><div class="card-heading"><span class="num">03</span><div><h2>Social media & maps</h2><p>Paste full public profile URLs. Leave blank to hide the link.</p></div></div><div class="fields">
<?php foreach(['social_instagram','social_facebook','social_tiktok','social_youtube','social_tripadvisor','social_google_maps'] as $key):?><div class="settings-field"><?php settings_field($key,$fields[$key],$values,$ready);?></div><?php endforeach;?>
</div><div class="social-preview"><?php foreach(['Instagram'=>'social_instagram','Facebook'=>'social_facebook','TikTok'=>'social_tiktok','YouTube'=>'social_youtube','Tripadvisor'=>'social_tripadvisor','Google Maps'=>'social_google_maps'] as $label=>$key):$url=bta_setting_public_url($key);if($url==='')$url=trim((string)($_POST[$key]??''));if($url!==''&&filter_var($url,FILTER_VALIDATE_URL)):?><a href="<?=e($url)?>" target="_blank" rel="noopener noreferrer"><?=e($label)?> ↗</a><?php endif;endforeach;?></div></section>
<section class="settings-card"><div class="card-heading"><span class="num">04</span><div><h2>SMTP email settings</h2><p>Configure your mail server and send a test message.</p></div></div><div class="fields">
<?php foreach(['smtp_host','smtp_port','smtp_encryption','smtp_username','smtp_from_email','smtp_from_name'] as $key):?><div class="settings-field"><?php settings_field($key,$fields[$key],$values,$ready);?></div><?php endforeach;?>
<div class="settings-field full"><label for="smtp_password">SMTP password / app password</label><input id="smtp_password" type="password" name="smtp_password" value="" autocomplete="new-password" placeholder="<?=isset($rows['smtp_password_encrypted'])&&$rows['smtp_password_encrypted']!==''?'Saved securely — enter a new value to replace it':'Enter SMTP password'?>" <?=$ready?'':'disabled'?>><p class="hint">The password is encrypted before it is stored. It is never displayed after saving. Leave blank to keep the current password.</p></div>
</div><div class="password-note">Save the SMTP fields first, then send a test email. Use your email provider's documented host, port, and encryption settings. The sender email must be authorized by your provider.</div>
<div class="smtp-test"><div class="settings-field"><label for="test_email_to">Send test email to</label><input id="test_email_to" type="email" name="test_email_to" placeholder="you@example.com" <?=$ready?'':'disabled'?>></div><button class="btn secondary" type="submit" formnovalidate onclick="this.form.querySelector('[name=action]').value='test_email' " <?=$ready?'':'disabled'?>>SEND TEST EMAIL</button></div>
</section>
<div class="savebar"><p>Save changes to update your global site settings.</p><button class="btn" type="submit" <?=$ready?'':'disabled'?>>SAVE SETTINGS</button></div>
</form></main>
<script>
(()=>{const form=document.querySelector('form');if(!form)return;const action=form.querySelector('[name="action"]');const test=form.querySelector('button[formnovalidate]');test?.addEventListener('click',()=>{action.value='test_email'});form.addEventListener('submit',e=>{if(e.submitter!==test)action.value='save'})})();
</script>
</body></html>
