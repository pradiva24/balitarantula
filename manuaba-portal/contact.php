<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$page_title = 'Contact Us';

$db = null;
try { $db = admin_db(); } catch (Throwable $e) {}

if ($db instanceof PDO) {
    $db->exec("CREATE TABLE IF NOT EXISTS contact_page (
        id INT NOT NULL PRIMARY KEY,
        data LONGTEXT NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$defaults = [
    'hero_kicker'=>'CONTACT US',
    'hero_title'=>"We’re Here",
    'hero_accent'=>'to Help',
    'hero_text'=>"Have a question or need assistance? Our team is ready to help you plan your unforgettable experience at ".site_brand_name().".",
    'hero_image'=>'',
    'address'=>'Jalan Raya Tegallalang, Ubud, Gianyar, Bali – Indonesia',
    'phone'=>'+62 812 3456 7890',
    'whatsapp'=>'+62 812 3456 7890',
    'email'=>setting('site_email',''),
    'opening_hours'=>"Every Day\n8:00 AM – 6:00 PM (WITA)",
    'map_query'=>'Manuaba Waterfall, Kenderan, Tegallalang, Gianyar, Bali 80561, Indonesia',
    'map_rating'=>'Manuaba Crystalfall',
    'follow_title'=>'Follow Us',
    'follow_text'=>"Stay connected and see our latest updates, promo, and exciting moments at ".site_brand_name().".",
    'instagram_url'=>'',
    'facebook_url'=>'',
    'youtube_url'=>'',
    'tiktok_url'=>'',
    'tripadvisor_url'=>'',
    'cta_kicker'=>'READY FOR AN ADVENTURE?',
    'cta_title'=>"Let’s Plan Your Visit to ".site_brand_name(),
    'cta_text'=>'Contact our team today and create unforgettable memories in Bali.',
    'cta_button'=>'Chat on WhatsApp →',
    'cta_image'=>'',
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

function contact_admin_clean($v): string {
    return trim((string)$v);
}
function contact_admin_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_contact'])) {
    admin_verify_csrf();

    foreach ($defaults as $key => $unused) {
        if ($key === 'hero_image' || $key === 'cta_image') continue;
        $data[$key] = contact_admin_clean($_POST[$key] ?? '');
    }

    try {
        if (admin_media_has_selection('hero_image') || !empty($_FILES['hero_image']['name'])) {
            $data['hero_image'] = admin_upload('hero_image', 'contact', $data['hero_image'] ?: null);
        } elseif (isset($_POST['remove_hero_image'])) {
            admin_delete_image($data['hero_image']);
            $data['hero_image'] = '';
        }

        if (admin_media_has_selection('cta_image') || !empty($_FILES['cta_image']['name'])) {
            $data['cta_image'] = admin_upload('cta_image', 'contact', $data['cta_image'] ?: null);
        } elseif (isset($_POST['remove_cta_image'])) {
            admin_delete_image($data['cta_image']);
            $data['cta_image'] = '';
        }

        if ($db instanceof PDO) {
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $db->prepare("INSERT INTO contact_page (id,data) VALUES (1,?) ON DUPLICATE KEY UPDATE data=VALUES(data)")
               ->execute([$payload]);
        }

        admin_flash('success', 'Contact Us content saved successfully.');
    } catch (Throwable $e) {
        admin_flash('error', $e->getMessage());
    }

    header('Location: contact.php');
    exit;
}

$header = __DIR__ . '/includes/header.php';
$footer = __DIR__ . '/includes/footer.php';
if (is_file($header)) require $header;
?>
<style>
.contact-admin{max-width:1180px;margin:0 auto;padding:28px 24px 60px;color:#17251f}
.contact-admin *{box-sizing:border-box}
.contact-admin-head{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:22px}
.contact-admin-title{margin:0;font-size:28px;font-weight:800}
.contact-admin-sub{margin:5px 0 0;color:#6c7973;font-size:13px}
.contact-admin-actions{display:flex;gap:9px}
.contact-admin-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #d5dfd9;border-radius:8px;padding:11px 16px;background:#fff;color:#205d40;font-weight:700;font-size:12px}
.contact-admin-btn.primary{background:#176344;color:#fff;border-color:#176344}
.contact-admin-card{background:#fff;border:1px solid #dfe7e2;border-radius:12px;padding:21px;margin-bottom:18px;box-shadow:0 4px 18px rgba(0,0,0,.035)}
.contact-admin-card h2{margin:0 0 17px;font-size:18px}
.contact-admin-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.contact-admin-field.full{grid-column:1/-1}
.contact-admin-field label{display:block;font-size:12px;font-weight:700;margin-bottom:6px}
.contact-admin-field input,.contact-admin-field textarea{width:100%;border:1px solid #ccd8d1;border-radius:7px;padding:11px 12px;background:#fff;color:#17392b;font:inherit;font-size:13px}
.contact-admin-field input{height:43px}
.contact-admin-field textarea{min-height:92px;resize:vertical}
.contact-admin-help{font-size:10px;color:#77847e;margin-top:5px}
.contact-admin-image{display:grid;grid-template-columns:170px 1fr;gap:15px;align-items:start}
.contact-admin-preview{height:105px;border-radius:8px;overflow:hidden;background:#edf3ef;border:1px solid #dce6e0}
.contact-admin-preview img{width:100%;height:100%;object-fit:cover;display:block}
.contact-admin-preview.empty{display:grid;place-items:center;color:#7b8982;font-size:11px}
.contact-admin-upload input{font-size:12px}
.contact-admin-remove{margin-top:8px;font-size:11px}
.contact-admin-remove label{font-weight:600;display:flex;align-items:center;gap:7px}
.contact-admin-save{position:sticky;bottom:12px;display:flex;justify-content:flex-end;z-index:10}
.contact-admin-save button{border:0;border-radius:8px;background:#176344;color:#fff;padding:13px 24px;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 6px 20px rgba(23,99,68,.2)}
@media(max-width:800px){.contact-admin-grid{grid-template-columns:1fr}.contact-admin-field.full{grid-column:auto}.contact-admin-head{align-items:flex-start;flex-direction:column}.contact-admin-image{grid-template-columns:1fr}.contact-admin{padding:20px 14px 45px}}
</style>

<main class="contact-admin">
  <div class="contact-admin-head">
    <div>
      <h1 class="contact-admin-title">Edit Contact Us</h1>
      <p class="contact-admin-sub">Edit the Contact Us page content without touching the PHP template.</p>
    </div>
    <div class="contact-admin-actions">
      <a class="contact-admin-btn" target="_blank" rel="noopener" href="<?=BASE_URL?>/contact.php">View Page ↗</a>
    </div>
  </div>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=contact_admin_e(admin_csrf())?>">

    <section class="contact-admin-card">
      <h2>Hero Section</h2>
      <div class="contact-admin-grid">
        <div class="contact-admin-field">
          <label>Hero Kicker</label>
          <input name="hero_kicker" value="<?=contact_admin_e($data['hero_kicker'])?>">
        </div>
        <div class="contact-admin-field">
          <label>Hero Main Title</label>
          <input name="hero_title" value="<?=contact_admin_e($data['hero_title'])?>">
        </div>
        <div class="contact-admin-field">
          <label>Hero Accent Title</label>
          <input name="hero_accent" value="<?=contact_admin_e($data['hero_accent'])?>">
        </div>
        <div class="contact-admin-field full">
          <label>Hero Description</label>
          <textarea name="hero_text"><?=contact_admin_e($data['hero_text'])?></textarea>
        </div>
        <div class="contact-admin-field full">
          <label>Hero Image</label>
          <div class="contact-admin-image">
            <div class="contact-admin-preview <?=empty($data['hero_image'])?'empty':''?>">
              <?php if($data['hero_image']): ?><img src="<?=contact_admin_e(asset_url($data['hero_image']))?>" alt=""><?php else: ?>No image<?php endif; ?>
            </div>
            <div class="contact-admin-upload">
              <input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp">
              <div class="contact-admin-help">JPG, PNG or WebP. Max 100 MB.</div>
              <?php if($data['hero_image']): ?><div class="contact-admin-remove"><label><input type="checkbox" name="remove_hero_image" value="1"> Remove current image</label></div><?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="contact-admin-card">
      <h2>Contact Information</h2>
      <div class="contact-admin-grid">
        <div class="contact-admin-field full"><label>Address</label><textarea name="address"><?=contact_admin_e($data['address'])?></textarea></div>
        <div class="contact-admin-field"><label>Phone</label><input name="phone" value="<?=contact_admin_e($data['phone'])?>"></div>
        <div class="contact-admin-field"><label>WhatsApp</label><input name="whatsapp" value="<?=contact_admin_e($data['whatsapp'])?>"></div>
        <div class="contact-admin-field"><label>Email</label><input type="email" name="email" value="<?=contact_admin_e($data['email'])?>"></div>
        <div class="contact-admin-field"><label>Opening Hours</label><textarea name="opening_hours"><?=contact_admin_e($data['opening_hours'])?></textarea></div>
        <div class="contact-admin-field"><label>Google Maps Query</label><input name="map_query" value="<?=contact_admin_e($data['map_query'])?>"></div>
        <div class="contact-admin-field"><label>Map Rating Text</label><input name="map_rating" value="<?=contact_admin_e($data['map_rating'])?>"></div>
      </div>
    </section>

    <section class="contact-admin-card">
      <h2>Follow Us</h2>
      <div class="contact-admin-grid">
        <div class="contact-admin-field"><label>Section Title</label><input name="follow_title" value="<?=contact_admin_e($data['follow_title'])?>"></div>
        <div class="contact-admin-field full"><label>Description</label><textarea name="follow_text"><?=contact_admin_e($data['follow_text'])?></textarea></div>
        <div class="contact-admin-field"><label>Instagram URL</label><input name="instagram_url" value="<?=contact_admin_e($data['instagram_url'])?>" placeholder="https://instagram.com/..."></div>
        <div class="contact-admin-field"><label>Facebook URL</label><input name="facebook_url" value="<?=contact_admin_e($data['facebook_url'])?>" placeholder="https://facebook.com/..."></div>
        <div class="contact-admin-field"><label>YouTube URL</label><input name="youtube_url" value="<?=contact_admin_e($data['youtube_url'])?>" placeholder="https://youtube.com/..."></div>
        <div class="contact-admin-field"><label>TikTok URL</label><input name="tiktok_url" value="<?=contact_admin_e($data['tiktok_url'])?>" placeholder="https://tiktok.com/@..."></div>
        <div class="contact-admin-field"><label>Tripadvisor URL</label><input name="tripadvisor_url" value="<?=contact_admin_e($data['tripadvisor_url'])?>" placeholder="https://tripadvisor.com/..."></div>
      </div>
    </section>

    <section class="contact-admin-card">
      <h2>Bottom CTA</h2>
      <div class="contact-admin-grid">
        <div class="contact-admin-field"><label>CTA Kicker</label><input name="cta_kicker" value="<?=contact_admin_e($data['cta_kicker'])?>"></div>
        <div class="contact-admin-field"><label>CTA Button Text</label><input name="cta_button" value="<?=contact_admin_e($data['cta_button'])?>"></div>
        <div class="contact-admin-field full"><label>CTA Title</label><input name="cta_title" value="<?=contact_admin_e($data['cta_title'])?>"></div>
        <div class="contact-admin-field full"><label>CTA Description</label><textarea name="cta_text"><?=contact_admin_e($data['cta_text'])?></textarea></div>
        <div class="contact-admin-field full">
          <label>CTA Background Image</label>
          <div class="contact-admin-image">
            <div class="contact-admin-preview <?=empty($data['cta_image'])?'empty':''?>">
              <?php if($data['cta_image']): ?><img src="<?=contact_admin_e(asset_url($data['cta_image']))?>" alt=""><?php else: ?>Uses Hero Image<?php endif; ?>
            </div>
            <div class="contact-admin-upload">
              <input type="file" name="cta_image" accept="image/jpeg,image/png,image/webp">
              <div class="contact-admin-help">Leave unchanged to keep current image. If empty, the hero image is used.</div>
              <?php if($data['cta_image']): ?><div class="contact-admin-remove"><label><input type="checkbox" name="remove_cta_image" value="1"> Remove current CTA image</label></div><?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="contact-admin-save">
      <button type="submit" name="save_contact" value="1">Save Contact Us</button>
    </div>
  </form>
</main>

<?php if (is_file($footer)) require $footer; ?>
