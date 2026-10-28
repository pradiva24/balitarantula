<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$page_title = 'Contact Social Links';
$db = null;
try { $db = admin_db(); } catch (Throwable $e) {}
if ($db instanceof PDO) {
    $db->exec("CREATE TABLE IF NOT EXISTS contact_page (id INT NOT NULL PRIMARY KEY, data LONGTEXT NULL, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$defaults = [
    'follow_title' => 'Follow Manuaba Crystalfall',
    'follow_text' => 'Stay connected and see our latest updates, promo, and exciting moments at Manuaba Crystalfall.',
    'social_links' => []
];
$data = $defaults;
if ($db instanceof PDO) {
    $stmt = $db->query("SELECT data FROM contact_page WHERE id=1 LIMIT 1");
    $row = $stmt ? $stmt->fetch() : false;
    if ($row && !empty($row['data'])) {
        $saved = json_decode((string)$row['data'], true);
        if (is_array($saved)) $data = array_merge($data, $saved);
    }
}
$iconPresets = [
    'Instagram' => 'fa-brands fa-instagram',
    'Facebook' => 'fa-brands fa-facebook-f',
    'YouTube' => 'fa-brands fa-youtube',
    'TikTok' => 'fa-brands fa-tiktok',
    'WhatsApp' => 'fa-brands fa-whatsapp',
    'Telegram' => 'fa-brands fa-telegram',
    'LinkedIn' => 'fa-brands fa-linkedin-in',
    'Pinterest' => 'fa-brands fa-pinterest-p',
    'X / Twitter' => 'fa-brands fa-x-twitter',
    'Google Maps' => 'fa-solid fa-location-dot',
    'Email' => 'fa-solid fa-envelope',
    'Phone' => 'fa-solid fa-phone',
];
function social_clean($v): string { return trim((string)$v); }
function social_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function social_class($v): string {
    $v = trim((string)$v);
    return preg_replace('/[^a-zA-Z0-9_\- ]+/', '', $v) ?: 'fa-solid fa-link';
}
function social_slug($name): string {
    $name = strtolower(trim((string)$name));
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    return trim($name, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_social_links'])) {
    admin_verify_csrf();
    $data['follow_title'] = social_clean($_POST['follow_title'] ?? 'Follow Manuaba Crystalfall');
    $data['follow_text'] = social_clean($_POST['follow_text'] ?? '');
    $rows = [];
    $names = $_POST['social_name'] ?? [];
    $urls = $_POST['social_url'] ?? [];
    $icons = $_POST['social_icon'] ?? [];
    $logoUrls = $_POST['social_logo'] ?? [];
    $enabled = $_POST['social_enabled'] ?? [];
    $count = max(count($names), count($urls), count($icons), count($logoUrls));
    for ($i=0; $i<$count; $i++) {
        $name = social_clean($names[$i] ?? '');
        $url = social_clean($urls[$i] ?? '');
        $icon = social_class($icons[$i] ?? '');
        $logo = social_clean($logoUrls[$i] ?? '');
        $isEnabled = isset($enabled[$i]) && (string)$enabled[$i] === '1';
        if ($name === '' && $url === '' && $logo === '') continue;
        if ($url !== '' && !preg_match('~^(https?://|mailto:|tel:)~i', $url)) $url = 'https://' . $url;
        $rows[] = ['name'=>$name ?: 'Social Link', 'url'=>$url, 'icon'=>$icon, 'logo'=>$logo, 'enabled'=>$isEnabled];
    }
    $data['social_links'] = $rows;
    // Keep the legacy fields synchronized for compatibility with older templates.
    foreach (['instagram','facebook','youtube','tiktok','tripadvisor'] as $legacy) $data[$legacy.'_url'] = '';
    foreach ($rows as $r) {
        $slug = social_slug($r['name']);
        if (isset($data[$slug.'_url'])) $data[$slug.'_url'] = $r['url'];
        if ($slug === 'x-twitter' && isset($data['twitter_url'])) $data['twitter_url'] = $r['url'];
    }
    try {
        if ($db instanceof PDO) {
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $db->prepare("INSERT INTO contact_page (id,data) VALUES (1,?) ON DUPLICATE KEY UPDATE data=VALUES(data)")->execute([$payload]);
        }
        admin_flash('success', 'Contact social links saved successfully.');
    } catch (Throwable $e) { admin_flash('error', $e->getMessage()); }
    header('Location: social-links.php'); exit;
}

$links = [];
if (!empty($data['social_links']) && is_array($data['social_links'])) {
    foreach ($data['social_links'] as $r) if (is_array($r)) $links[] = array_merge(['name'=>'','url'=>'','icon'=>'fa-solid fa-link','logo'=>'','enabled'=>true], $r);
}
if (!$links) {
    $legacy = [
        ['name'=>'Instagram','url'=>$data['instagram_url'] ?? '','icon'=>$iconPresets['Instagram']],
        ['name'=>'Facebook','url'=>$data['facebook_url'] ?? '','icon'=>$iconPresets['Facebook']],
        ['name'=>'YouTube','url'=>$data['youtube_url'] ?? '','icon'=>$iconPresets['YouTube']],
        ['name'=>'TikTok','url'=>$data['tiktok_url'] ?? '','icon'=>$iconPresets['TikTok']],
        ['name'=>'Tripadvisor','url'=>$data['tripadvisor_url'] ?? '','icon'=>'fa-solid fa-star'],
    ];
    foreach ($legacy as $r) { if ($r['url'] !== '') $links[] = array_merge($r, ['logo'=>'','enabled'=>true]); }
}
require __DIR__ . '/includes/header.php';
?>
<style>
.social-admin{max-width:1120px;margin:0 auto;padding:28px 24px 60px;color:#17251f}.social-admin *{box-sizing:border-box}
.social-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:22px}.social-title{margin:0;font-size:28px;font-weight:800}.social-sub{margin:5px 0 0;color:#6c7973;font-size:13px}.social-card{background:#fff;border:1px solid #dfe7e2;border-radius:12px;padding:21px;margin-bottom:18px;box-shadow:0 4px 18px rgba(0,0,0,.035)}
.social-card h2{margin:0 0 16px;font-size:18px}.social-field label{display:block;font-size:12px;font-weight:700;margin-bottom:6px}.social-field input,.social-field select,.social-field textarea{width:100%;border:1px solid #ccd8d1;border-radius:7px;padding:10px 11px;background:#fff;color:#17392b;font:inherit;font-size:13px}.social-field textarea{min-height:80px;resize:vertical}.social-help{font-size:10px;color:#77847e;margin-top:5px}
.social-row{border:1px solid #dfe8e2;border-radius:10px;padding:15px;margin-bottom:12px;background:#fbfdfb}.social-row-grid{display:grid;grid-template-columns:1.05fr 1.4fr 1fr 1.25fr auto;gap:10px;align-items:end}.social-preview{width:42px;height:42px;border-radius:50%;background:#dcefd9;color:#205e3d;display:grid;place-items:center;font-size:17px;margin-bottom:4px}.social-actions{display:flex;align-items:center;gap:8px;height:42px}.social-remove{border:1px solid #e0bcbc;background:#fff5f5;color:#a33b3b;border-radius:7px;padding:9px 11px;cursor:pointer;font-size:11px}.social-add,.social-save{border:0;border-radius:8px;background:#176344;color:#fff;padding:11px 15px;font-weight:800;cursor:pointer}.social-add{background:#fff;color:#176344;border:1px solid #bcd1c4}.social-save-wrap{position:sticky;bottom:12px;display:flex;justify-content:flex-end;z-index:10}.social-save{padding:13px 24px;box-shadow:0 6px 20px rgba(23,99,68,.2)}.social-check{display:flex;align-items:center;gap:7px;font-size:11px;white-space:nowrap}.social-topnote{background:#eef7ef;border:1px solid #d5e7d8;border-radius:8px;padding:11px 13px;font-size:11px;color:#41604e;margin-bottom:15px}
@media(max-width:1000px){.social-row-grid{grid-template-columns:1fr 1fr}.social-actions{grid-column:1/-1;justify-content:flex-end}}@media(max-width:650px){.social-admin{padding:20px 14px 45px}.social-row-grid{grid-template-columns:1fr}.social-head{flex-direction:column}.social-actions{grid-column:auto}}
</style>
<main class="social-admin">
  <div class="social-head"><div><h1 class="social-title">Contact Social Links</h1><p class="social-sub">Manage the social media logos and links shown in the Follow section on Contact Us.</p></div></div>
  <div class="social-topnote"><strong>How it works:</strong> Add as many social links as you need. Each row can have its own logo/icon and URL. Disable or remove a row and it will not appear on the Contact Us page.</div>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=social_e(admin_csrf())?>">
    <section class="social-card">
      <h2>Follow Section</h2>
      <div class="social-field" style="margin-bottom:12px"><label>Section Title</label><input name="follow_title" value="<?=social_e($data['follow_title'] ?? 'Follow Manuaba Crystalfall')?>"></div>
      <div class="social-field"><label>Description</label><textarea name="follow_text"><?=social_e($data['follow_text'] ?? '')?></textarea></div>
    </section>
    <section class="social-card">
      <h2>Social Links</h2>
      <div id="socialRows">
      <?php foreach($links as $i=>$r): ?>
        <div class="social-row">
          <div class="social-row-grid">
            <div class="social-field"><label>Platform / Name</label><input name="social_name[]" value="<?=social_e($r['name'])?>" placeholder="Instagram"></div>
            <div class="social-field"><label>Link URL</label><input name="social_url[]" value="<?=social_e($r['url'])?>" placeholder="https://instagram.com/..."></div>
            <div class="social-field"><label>Icon</label><select name="social_icon[]" onchange="previewSocial(this)"><?php foreach($iconPresets as $label=>$cls): ?><option value="<?=social_e($cls)?>" <?=($r['icon']===$cls?'selected':'')?>><?=social_e($label)?></option><?php endforeach; ?><option value="custom" <?=(!in_array($r['icon'],array_values($iconPresets),true)?'selected':'')?>>Custom</option></select><div class="social-help">Font Awesome 6 brand/solid icon.</div></div>
            <div class="social-field"><label>Custom Logo URL <small>(optional)</small></label><input name="social_logo[]" value="<?=social_e($r['logo'] ?? '')?>" placeholder="https://.../logo.png"><div class="social-help">If filled, this image is used instead of the icon.</div></div>
            <div class="social-actions"><label class="social-check"><input type="checkbox" name="social_enabled[<?=$i?>]" value="1" <?=(!empty($r['enabled'])?'checked':'')?>> Show</label><button type="button" class="social-remove" onclick="this.closest('.social-row').remove()">Remove</button></div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
      <button type="button" class="social-add" onclick="addSocialRow()">＋ Add Social Link</button>
    </section>
    <div class="social-save-wrap"><button class="social-save" type="submit" name="save_social_links" value="1">Save Social Links</button></div>
  </form>
</main>
<script>
const presets = <?=json_encode($iconPresets, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
function optionsHtml(){let h='';for(const [n,c] of Object.entries(presets))h+=`<option value="${c.replaceAll('"','&quot;')}">${n}</option>`;h+='<option value="custom">Custom</option>';return h;}
function addSocialRow(){const wrap=document.getElementById('socialRows');const div=document.createElement('div');const idx=wrap.children.length;div.className='social-row';div.innerHTML=`<div class="social-row-grid"><div class="social-field"><label>Platform / Name</label><input name="social_name[]" placeholder="Instagram"></div><div class="social-field"><label>Link URL</label><input name="social_url[]" placeholder="https://..."></div><div class="social-field"><label>Icon</label><select name="social_icon[]">${optionsHtml()}</select></div><div class="social-field"><label>Custom Logo URL <small>(optional)</small></label><input name="social_logo[]" placeholder="https://.../logo.png"></div><div class="social-actions"><label class="social-check"><input type="checkbox" name="social_enabled[${idx}]" value="1" checked> Show</label><button type="button" class="social-remove" onclick="this.closest('.social-row').remove()">Remove</button></div></div>`;wrap.appendChild(div);}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
