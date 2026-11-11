<?php
require __DIR__.'/includes/auth.php';
$page_title='About Us';

$db = db();
try { $st=$db->prepare("UPDATE about_page SET story_quote_author=? WHERE id=1 AND (story_quote_author LIKE '%Tarantula%' OR story_quote_author LIKE '%Aloha Ubud%')"); $st->execute(['— '.site_brand_name()]); } catch(Throwable $e) {}
$db->exec("CREATE TABLE IF NOT EXISTS about_page (
 id INT NOT NULL PRIMARY KEY,
 hero_kicker VARCHAR(120) NOT NULL DEFAULT 'ABOUT US', hero_title VARCHAR(255) NOT NULL DEFAULT 'More Than An Adventure',
 hero_title_accent VARCHAR(255) NOT NULL DEFAULT 'More Than An Adventure', hero_text TEXT NULL,
 hero_button_text VARCHAR(120) NOT NULL DEFAULT 'Our Story', hero_button_url VARCHAR(255) NOT NULL DEFAULT '#our-story',
 hero_image VARCHAR(500) NULL, hero_alt VARCHAR(255) NULL,
 story_kicker VARCHAR(120) NOT NULL DEFAULT 'OUR STORY', story_title VARCHAR(255) NOT NULL DEFAULT 'Born from a Love for Bali’s Beauty',
 story_text_1 TEXT NULL, story_text_2 TEXT NULL, story_button_text VARCHAR(120) NOT NULL DEFAULT 'Discover Our Experience',
 story_button_url VARCHAR(255) NOT NULL DEFAULT 'packages.php', story_image VARCHAR(500) NULL, story_alt VARCHAR(255) NULL,
 story_quote TEXT NULL, story_quote_author VARCHAR(120) NOT NULL DEFAULT '— Manuaba Crystalfall',
 why_kicker VARCHAR(120) NOT NULL DEFAULT 'WHY CHOOSE US', why_title VARCHAR(255) NOT NULL DEFAULT 'A Unique Experience in the Heart of Bali',
 why_text TEXT NULL,
 why_1_icon VARCHAR(50) NOT NULL DEFAULT '⌁', why_1_title VARCHAR(120) NOT NULL DEFAULT 'Stunning Natural Views', why_1_text TEXT NULL,
 why_2_icon VARCHAR(50) NOT NULL DEFAULT '✓', why_2_title VARCHAR(120) NOT NULL DEFAULT 'Safety First', why_2_text TEXT NULL,
 why_3_icon VARCHAR(50) NOT NULL DEFAULT '⌾', why_3_title VARCHAR(120) NOT NULL DEFAULT 'Perfect Adventure Spots', why_3_text TEXT NULL,
 why_4_icon VARCHAR(50) NOT NULL DEFAULT '♧', why_4_title VARCHAR(120) NOT NULL DEFAULT 'Warm Local Hospitality', why_4_text TEXT NULL,
 values_kicker VARCHAR(120) NOT NULL DEFAULT 'OUR VALUES', values_title VARCHAR(255) NOT NULL DEFAULT 'Nature, People, and Meaningful Travel', values_text TEXT NULL,
 values_image VARCHAR(500) NULL, values_alt VARCHAR(255) NULL,
 value_1_icon VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-leaf', value_1_title VARCHAR(120) NOT NULL DEFAULT 'Sustainability', value_1_text TEXT NULL,
 value_2_icon VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-handshake', value_2_title VARCHAR(120) NOT NULL DEFAULT 'Community Support', value_2_text TEXT NULL,
 value_3_icon VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-heart', value_3_title VARCHAR(120) NOT NULL DEFAULT 'Authentic Experiences', value_3_text TEXT NULL,
 value_4_icon VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-star', value_4_title VARCHAR(120) NOT NULL DEFAULT 'Excellence', value_4_text TEXT NULL,
 stats_kicker VARCHAR(120) NOT NULL DEFAULT 'OUR IMPACT IN NUMBERS',
 stat_1_number VARCHAR(50) NOT NULL DEFAULT '5+', stat_1_label VARCHAR(120) NOT NULL DEFAULT 'Years of Experience',
 stat_2_number VARCHAR(50) NOT NULL DEFAULT '50K+', stat_2_label VARCHAR(120) NOT NULL DEFAULT 'Happy Visitors',
 stat_3_number VARCHAR(50) NOT NULL DEFAULT '10+', stat_3_label VARCHAR(120) NOT NULL DEFAULT 'Adventure Experiences',
 stat_4_number VARCHAR(50) NOT NULL DEFAULT '100%', stat_4_label VARCHAR(120) NOT NULL DEFAULT 'Guest Satisfaction',
 cta_kicker VARCHAR(120) NOT NULL DEFAULT 'BE PART OF OUR STORY', cta_title VARCHAR(255) NOT NULL DEFAULT 'Create Your Unforgettable Moments in Bali',
 cta_text TEXT NULL, cta_button_text VARCHAR(120) NOT NULL DEFAULT 'Book Your Experience', cta_button_url VARCHAR(255) NOT NULL DEFAULT 'packages.php',
 cta_image VARCHAR(500) NULL, cta_alt VARCHAR(255) NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

foreach ([
 ['value_1_icon', "ALTER TABLE about_page ADD value_1_icon VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-leaf' AFTER values_alt"],
 ['value_2_icon', "ALTER TABLE about_page ADD value_2_icon VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-handshake' AFTER value_1_text"],
 ['value_3_icon', "ALTER TABLE about_page ADD value_3_icon VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-heart' AFTER value_2_text"],
 ['value_4_icon', "ALTER TABLE about_page ADD value_4_icon VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-star' AFTER value_3_text"]
] as $m){ try{$db->query("SELECT ".$m[0]." FROM about_page LIMIT 1");}catch(Throwable $e){try{$db->exec($m[1]);}catch(Throwable $ignore){}} }

if (!(int)$db->query("SELECT COUNT(*) FROM about_page WHERE id=1")->fetchColumn()) $db->exec("INSERT INTO about_page (id) VALUES (1)");

$about=$db->query("SELECT * FROM about_page WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

$fields = [
 'hero_kicker','hero_title','hero_title_accent','hero_text','hero_button_text','hero_button_url','hero_alt',
 'story_kicker','story_title','story_text_1','story_text_2','story_button_text','story_button_url','story_alt','story_quote','story_quote_author',
 'why_kicker','why_title','why_text',
 'why_1_icon','why_1_title','why_1_text','why_2_icon','why_2_title','why_2_text','why_3_icon','why_3_title','why_3_text','why_4_icon','why_4_title','why_4_text',
 'values_kicker','values_title','values_text','values_alt','value_1_icon','value_1_title','value_1_text','value_2_icon','value_2_title','value_2_text','value_3_icon','value_3_title','value_3_text','value_4_icon','value_4_title','value_4_text',
 'stats_kicker','stat_1_number','stat_1_label','stat_2_number','stat_2_label','stat_3_number','stat_3_label','stat_4_number','stat_4_label',
 'cta_kicker','cta_title','cta_text','cta_button_text','cta_button_url','cta_alt'
];

function about_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function about_upload_image(string $field, string $old=''): string { $selected=admin_media_selected($field); if($selected!==null) return $selected; return admin_upload($field, 'about', $old); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    admin_verify_csrf();
    $set=[]; $vals=[];
    foreach($fields as $f){ $set[]=$f.'=?'; $vals[]=trim((string)($_POST[$f]??'')); }
    foreach(['hero_image','story_image','values_image','cta_image'] as $img){
        $set[]=$img.'=?';
        $vals[]=about_upload_image($img, (string)($about[$img]??''));
    }
    $vals[]=1;
    $db->prepare('UPDATE about_page SET '.implode(',',$set).' WHERE id=?')->execute($vals);
    admin_flash('success','About Us updated successfully.');
    header('Location: about.php');
    exit;
}

$about=$db->query("SELECT * FROM about_page WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
require __DIR__.'/includes/header.php';
?>
<style>
.about-cms{max-width:1180px;margin:0 auto;padding:0 0 48px}
.about-cms .cms-intro{margin:0 0 22px;color:#718078;font-size:13px}
.about-cms .cms-card{background:#fff;border:1px solid #e0e7e2;border-radius:12px;padding:22px;margin:0 0 18px;box-shadow:0 4px 16px rgba(20,45,33,.04)}
.about-cms .cms-card h2{margin:0 0 18px;font-size:18px;color:#14583e}
.about-cms .cms-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.about-cms .cms-full{grid-column:1/-1}
.about-cms label{display:block;font-size:12px;font-weight:700;margin:0 0 6px;color:#33443c}
.about-cms input,.about-cms textarea{width:100%;box-sizing:border-box;border:1px solid #d5dfd8;border-radius:7px;padding:10px 11px;font:13px 'Inter',Arial,sans-serif;color:#20352c;background:#fff}
.about-cms input{height:42px}
.about-cms textarea{min-height:105px;resize:vertical;line-height:1.55}
.about-cms .file-input{padding:8px;background:#f7faf7}
.about-cms .preview{display:block;width:220px;height:125px;object-fit:cover;border-radius:8px;margin-top:9px;border:1px solid #dce5df}
.about-cms .stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.about-cms .stat-card{border:1px solid #e0e7e2;border-radius:10px;padding:16px;background:#fafcfb}
.about-cms .stat-card h3{margin:0 0 13px;font-size:14px;color:#14583e}
.about-cms .cms-actions{display:flex;justify-content:flex-end;margin-top:22px}
.about-cms .save-btn{border:0;border-radius:7px;background:#126044;color:#fff;padding:13px 25px;font:700 13px 'Inter',Arial,sans-serif;cursor:pointer}
.about-cms .save-btn:hover{filter:brightness(.95)}
.about-cms .help{font-size:11px;color:#87928c;margin-top:5px}
@media(max-width:900px){.about-cms .cms-grid,.about-cms .stat-grid{grid-template-columns:1fr}.about-cms .cms-full{grid-column:auto}}
@media(max-width:600px){.about-cms{padding-bottom:30px}.about-cms .cms-card{padding:16px}.about-cms .cms-actions{justify-content:stretch}.about-cms .save-btn{width:100%}.about-cms .preview{width:100%;max-width:300px}}
</style>

<div class="about-cms">
  <h2 style="margin:0 0 6px">Edit About Us</h2>
  <p class="cms-intro">Edit all About Us content, images, buttons and SEO alt text from this page.</p>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=about_h(admin_csrf())?>">

    <section class="cms-card"><h2>Hero Section</h2><div class="cms-grid">
      <div><label>Kicker</label><input name="hero_kicker" value="<?=about_h($about['hero_kicker']??'')?>"></div>
      <div><label>Title</label><input name="hero_title" value="<?=about_h($about['hero_title']??'')?>"></div>
      <div><label>Title Accent</label><input name="hero_title_accent" value="<?=about_h($about['hero_title_accent']??'')?>"></div>
      <div><label>Button Text</label><input name="hero_button_text" value="<?=about_h($about['hero_button_text']??'')?>"></div>
      <div class="cms-full"><label>Description</label><textarea name="hero_text"><?=about_h($about['hero_text']??'')?></textarea></div>
      <div><label>Button URL</label><input name="hero_button_url" value="<?=about_h($about['hero_button_url']??'')?>"></div>
      <div><label>Hero Image Alt Text (SEO)</label><input name="hero_alt" value="<?=about_h($about['hero_alt']??'')?>"></div>
      <div class="cms-full"><label>Hero Image</label><input class="file-input" type="file" name="hero_image" accept="image/jpeg,image/png,image/webp"><div class="help">JPG, PNG or WebP. Maximum 100 MB.</div><?php if(!empty($about['hero_image'])):?><img class="preview" src="<?=about_h(asset_url($about['hero_image']))?>" alt=""><?php endif;?></div>
    </div></section>

    <section class="cms-card"><h2>Our Story</h2><div class="cms-grid">
      <div><label>Section Kicker</label><input name="story_kicker" value="<?=about_h($about['story_kicker']??'')?>"></div>
      <div><label>Title</label><input name="story_title" value="<?=about_h($about['story_title']??'')?>"></div>
      <div class="cms-full"><label>Paragraph 1</label><textarea name="story_text_1"><?=about_h($about['story_text_1']??'')?></textarea></div>
      <div class="cms-full"><label>Paragraph 2</label><textarea name="story_text_2"><?=about_h($about['story_text_2']??'')?></textarea></div>
      <div><label>Button Text</label><input name="story_button_text" value="<?=about_h($about['story_button_text']??'')?>"></div>
      <div><label>Button URL</label><input name="story_button_url" value="<?=about_h($about['story_button_url']??'')?>"></div>
      <div><label>Quote</label><textarea name="story_quote"><?=about_h($about['story_quote']??'')?></textarea></div>
      <div><label>Quote Author</label><input name="story_quote_author" value="<?=about_h($about['story_quote_author']??'')?>"></div>
      <div class="cms-full"><label>Story Image</label><input class="file-input" type="file" name="story_image" accept="image/jpeg,image/png,image/webp"><div class="help">JPG, PNG or WebP. Maximum 100 MB.</div><?php if(!empty($about['story_image'])):?><img class="preview" src="<?=about_h(asset_url($about['story_image']))?>" alt=""><?php endif;?></div>
      <div class="cms-full"><label>Story Image Alt Text (SEO)</label><input name="story_alt" value="<?=about_h($about['story_alt']??'')?>"></div>
    </div></section>

    <section class="cms-card"><h2>Why Choose Us</h2><p class="help">Use Font Awesome Free classes such as <code>fa-solid fa-water</code>. Global icons can also be managed from Admin → Icon Manager.</p><div class="cms-grid">
      <div><label>Kicker</label><input name="why_kicker" value="<?=about_h($about['why_kicker']??'')?>"></div>
      <div><label>Title</label><input name="why_title" value="<?=about_h($about['why_title']??'')?>"></div>
      <div class="cms-full"><label>Intro Text</label><textarea name="why_text"><?=about_h($about['why_text']??'')?></textarea></div>
      <?php for($i=1;$i<=4;$i++): ?>
      <div><label>Icon <?=$i?></label><input name="why_<?=$i?>_icon" value="<?=about_h($about["why_{$i}_icon"]??'')?>"></div>
      <div><label>Title <?=$i?></label><input name="why_<?=$i?>_title" value="<?=about_h($about["why_{$i}_title"]??'')?>"></div>
      <div class="cms-full"><label>Description <?=$i?></label><textarea name="why_<?=$i?>_text"><?=about_h($about["why_{$i}_text"]??'')?></textarea></div>
      <?php endfor; ?>
    </div></section>

    <section class="cms-card"><h2>Our Values</h2><div class="cms-grid">
      <div><label>Kicker</label><input name="values_kicker" value="<?=about_h($about['values_kicker']??'')?>"></div>
      <div><label>Title</label><input name="values_title" value="<?=about_h($about['values_title']??'')?>"></div>
      <div class="cms-full"><label>Description</label><textarea name="values_text"><?=about_h($about['values_text']??'')?></textarea></div>
      <div class="cms-full"><label>Values Image</label><input class="file-input" type="file" name="values_image" accept="image/jpeg,image/png,image/webp"><div class="help">JPG, PNG or WebP. Maximum 100 MB.</div><?php if(!empty($about['values_image'])):?><img class="preview" src="<?=about_h(asset_url($about['values_image']))?>" alt=""><?php endif;?></div>
      <div class="cms-full"><label>Values Image Alt Text (SEO)</label><input name="values_alt" value="<?=about_h($about['values_alt']??'')?>"></div>
      <?php for($i=1;$i<=4;$i++): ?>
      <div><label>Value <?=$i?> Icon</label><input name="value_<?=$i?>_icon" value="<?=about_h($about["value_{$i}_icon"]??['fa-solid fa-leaf','fa-solid fa-handshake','fa-solid fa-heart','fa-solid fa-star'][$i-1])?>" placeholder="fa-solid fa-leaf"><div class="help">Font Awesome Free class</div></div>
      <div><label>Value <?=$i?> Title</label><input name="value_<?=$i?>_title" value="<?=about_h($about["value_{$i}_title"]??'')?>"></div>
      <div><label>Value <?=$i?> Description</label><textarea name="value_<?=$i?>_text"><?=about_h($about["value_{$i}_text"]??'')?></textarea></div>
      <?php endfor; ?>
    </div></section>

    <section class="cms-card"><h2>Impact in Numbers</h2><div class="cms-grid">
      <div class="cms-full"><label>Section Kicker</label><input name="stats_kicker" value="<?=about_h($about['stats_kicker']??'')?>"></div>
    </div>
    <div class="stat-grid">
      <?php for($i=1;$i<=4;$i++): ?>
      <div class="stat-card"><h3>Statistic <?=$i?></h3>
        <label>Number <?=$i?></label><input name="stat_<?=$i?>_number" value="<?=about_h($about["stat_{$i}_number"]??'')?>">
        <label style="margin-top:12px">Label <?=$i?></label><input name="stat_<?=$i?>_label" value="<?=about_h($about["stat_{$i}_label"]??'')?>">
      </div>
      <?php endfor; ?>
    </div></section>

    <section class="cms-card"><h2>Call To Action</h2><div class="cms-grid">
      <div><label>Kicker</label><input name="cta_kicker" value="<?=about_h($about['cta_kicker']??'')?>"></div>
      <div><label>Title</label><input name="cta_title" value="<?=about_h($about['cta_title']??'')?>"></div>
      <div class="cms-full"><label>Description</label><textarea name="cta_text"><?=about_h($about['cta_text']??'')?></textarea></div>
      <div><label>Button Text</label><input name="cta_button_text" value="<?=about_h($about['cta_button_text']??'')?>"></div>
      <div><label>Button URL</label><input name="cta_button_url" value="<?=about_h($about['cta_button_url']??'')?>"></div>
      <div class="cms-full"><label>CTA Background Image</label><input class="file-input" type="file" name="cta_image" accept="image/jpeg,image/png,image/webp"><div class="help">JPG, PNG or WebP. Maximum 100 MB.</div><?php if(!empty($about['cta_image'])):?><img class="preview" src="<?=about_h(asset_url($about['cta_image']))?>" alt=""><?php endif;?></div>
      <div class="cms-full"><label>CTA Image Alt Text (SEO)</label><input name="cta_alt" value="<?=about_h($about['cta_alt']??'')?>"></div>
    </div></section>

    <div class="cms-actions"><button class="save-btn" type="submit">Save About Us</button></div>
  </form>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
