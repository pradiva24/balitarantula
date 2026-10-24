<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$admin = require_role(['owner','admin','content']);
$pdo = Database::connection();

$groups = [
 'Hero & slider' => [
  ['hero_eyebrow','Hero eyebrow','text','YOUR NEXT ADVENTURE STARTS HERE'],
  ['hero_title_main','Hero title — first line','text','CHASE THE'],
  ['hero_title_accent','Hero title — accent line','text','UNTAMED.'],
  ['hero_description','Hero description','textarea','Trade the ordinary for muddy trails, wild landscapes, and stories worth telling. Bali is calling. Are you ready?'],
  ['hero_cta_primary','Primary button label','text','EXPLORE ADVENTURES'],
  ['hero_cta_secondary','Secondary button label','text','DISCOVER OUR WORLD'],
  ['hero_image_1','Hero slider image 1','image',''],
  ['hero_image_2','Hero slider image 2','image',''],
  ['hero_image_3','Hero slider image 3','image',''],
 ],
 'Intro strip' => [
  ['intro_label','Small label','text','NOT JUST A RIDE.'],
  ['intro_title','Main text','text',"IT'S YOUR CALL TO ADVENTURE."],
 ],
 'Adventure cards' => [
  ['adventures_eyebrow','Section eyebrow','text','PICK YOUR THRILL'],
  ['adventures_title_main','Section title — first line','text','FIND YOUR'],
  ['adventures_title_accent','Section title — accent','text','WILD.'],
  ['adventures_description','Section description','textarea','From jungle trails to rushing rivers, choose the experience that gets your heart racing.'],
  ['adventure_1_tag','Card 1 tag','text','DIRT. POWER. FREEDOM.'],
  ['adventure_1_title','Card 1 title','text','ATV ADVENTURE'],
  ['adventure_1_description','Card 1 description','textarea','Push through rugged tracks, rice fields, and tropical terrain.'],
  ['adventure_1_image','Card 1 image','image',''],
  ['adventure_2_tag','Card 2 tag','text','RIDE THE CURRENT.'],
  ['adventure_2_title','Card 2 title','text','WHITE WATER RAFTING'],
  ['adventure_2_description','Card 2 description','textarea',"Take on the river and feel the rush of Bali's wild side."],
  ['adventure_2_image','Card 2 image','image',''],
  ['adventure_3_tag','Card 3 tag','text','OFF THE BEATEN PATH.'],
  ['adventure_3_title','Card 3 title','text','JUNGLE EXPLORER'],
  ['adventure_3_description','Card 3 description','textarea','Discover hidden corners and unforgettable natural scenery.'],
  ['adventure_3_image','Card 3 image','image',''],
 ],
 'Tracks section' => [
  ['tracks_eyebrow','Section eyebrow','text','PICK YOUR TERRAIN'],
  ['tracks_title_main','Section title — first line','text','FOUR TRACKS.'],
  ['tracks_title_accent','Section title — accent','text','ONE WILD RIDE.'],
  ['tracks_description','Section description','textarea','Choose your route and get ready for mud, jungle, river crossings, and unforgettable Bali landscapes.'],
  ['track_1_title','Track 1 title','text','TARANTULA JUNGLE TRACK'],
  ['track_1_description','Track 1 description','textarea','Ride through wild greenery and rugged off-road terrain.'],
  ['track_1_image','Track 1 image','image',''],
  ['track_2_title','Track 2 title','text','MUD TRACK'],
  ['track_2_description','Track 2 description','textarea','Take on muddy paths for a raw, muddy ATV experience.'],
  ['track_2_image','Track 2 image','image',''],
  ['track_3_title','Track 3 title','text','JUNGLE TRAIL'],
  ['track_3_description','Track 3 description','textarea','Explore shaded forest trails surrounded by tropical nature.'],
  ['track_3_image','Track 3 image','image',''],
  ['track_4_title','Track 4 title','text','RIVER ADVENTURE'],
  ['track_4_description','Track 4 description','textarea','Add a splash of excitement as the route meets the water.'],
  ['track_4_image','Track 4 image','image',''],
 ],
 'About / manifesto' => [
  ['manifesto_eyebrow','Section eyebrow','text','MORE THAN AN ACTIVITY'],
  ['manifesto_title_main','Heading — main lines','text','LEAVE THE COMFORT ZONE.'],
  ['manifesto_title_accent','Heading — accent line','text','FIND YOUR EDGE.'],
  ['manifesto_description','Description','textarea','We believe the best memories happen when you get out there. Get muddy, get moving, and experience Bali beyond the usual path—with a team that lives for adventure.'],
  ['manifesto_image','Background image','image',''],
  ['manifesto_cta','Button label','text','MEET YOUR NEXT ADVENTURE'],
 ],
 'Why choose us' => [
  ['why_eyebrow','Section eyebrow','text','THE TARANTULA DIFFERENCE'],
  ['why_title_main','Section title — first line','text','BUILT FOR'],
  ['why_title_accent','Section title — accent','text','THE THRILL.'],
  ['why_description','Section description','textarea','Good adventures are remembered. Great ones become part of your story.'],
  ['why_1_title','Reason 1 title','text','REAL ADVENTURE'],
  ['why_1_description','Reason 1 description','textarea','Immersive outdoor experiences that take you beyond the ordinary.'],
  ['why_2_title','Reason 2 title','text','LOCAL KNOW-HOW'],
  ['why_2_description','Reason 2 description','textarea','Explore Bali with a team that knows the terrain and loves the island.'],
  ['why_3_title','Reason 3 title','text','YOUR KIND OF WILD'],
  ['why_3_description','Reason 3 description','textarea','Choose an experience that matches your crew, your pace, and your spirit.'],
 ],
 'Guest reviews' => [
  ['reviews_eyebrow','Section eyebrow','text','GUEST REVIEWS'],
  ['reviews_title_main','Heading — first line','text','THE ADVENTURE'],
  ['reviews_title_accent','Heading — accent','text','IN THEIR WORDS.'],
  ['reviews_description','Section description','textarea','Real feedback helps future adventurers choose the right experience.'],
  ['review_1_quote','Review 1 quote','textarea','Add a verified guest review here.'],
  ['review_1_author','Review 1 author','text','Guest review'],
  ['review_2_quote','Review 2 quote','textarea','Add another verified guest review here.'],
  ['review_2_author','Review 2 author','text','Guest review'],
  ['review_3_quote','Review 3 quote','textarea','Add another verified guest review here.'],
  ['review_3_author','Review 3 author','text','Guest review'],
 ],
 'Final CTA' => [
  ['cta_eyebrow','Section eyebrow','text','YOUR STORY STARTS HERE'],
  ['cta_title_main','Heading — first line','text','READY TO GET'],
  ['cta_title_accent','Heading — accent','text','DIRTY?'],
  ['cta_description','Description','textarea',"Bring your crew. Bring your courage. We'll bring the adventure."],
  ['cta_button','Button label','text','PLAN YOUR ADVENTURE'],
  ['cta_whatsapp_url','WhatsApp / booking URL','text','https://wa.me/'],
 ],
];

$message = null;
$error = null;
$tableReady = true;
try {
    $pdo->query('SELECT content_key FROM cms_content LIMIT 1');
} catch (Throwable $e) {
    $tableReady = false;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableReady) {
    verify_csrf_or_fail();
    try {
        $pdo->beginTransaction();
        $save = $pdo->prepare('INSERT INTO cms_content (content_key, content_value, updated_by) VALUES (:key, :value, :admin) ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_by = VALUES(updated_by)');
        foreach ($groups as $fields) {
            foreach ($fields as [$key, $label, $type, $fallback]) {
                if ($type !== 'image' && array_key_exists($key, $_POST)) {
                    $value = trim((string)$_POST[$key]);
                    if (mb_strlen($value) > 12000) {
                        throw new DomainException($label . ' is too long.');
                    }
                    $save->execute(['key'=>$key,'value'=>$value,'admin'=>(int)$admin['id']]);
                }
                if ($type === 'image' && isset($_FILES[$key]) && (int)($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES[$key];
                    if ((int)$file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 8 * 1024 * 1024) {
                        throw new DomainException($label . ': upload failed or exceeds 8 MB.');
                    }
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file((string)$file['tmp_name']);
                    $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
                    if (!isset($extensions[$mime])) {
                        throw new DomainException($label . ': use JPG, PNG, WEBP, or GIF.');
                    }
                    $uploadDir = dirname(__DIR__) . '/assets/uploads/homepage';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        throw new RuntimeException('Upload directory could not be created.');
                    }
                    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                    if (!move_uploaded_file((string)$file['tmp_name'], $uploadDir . '/' . $filename)) {
                        throw new RuntimeException('Could not save uploaded image.');
                    }
                    $save->execute(['key'=>$key,'value'=>'/assets/uploads/homepage/'.$filename,'admin'=>(int)$admin['id']]);
                }
            }
        }
        $pdo->commit();
        $message = 'Homepage content saved. Open the public homepage in a new tab to review the changes.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Homepage CMS save failed: ' . $e->getMessage());
        $error = $e instanceof DomainException ? $e->getMessage() : 'Could not save changes. Check upload permissions and database configuration.';
    }
}
$stored = [];
if ($tableReady) {
    $stored = $pdo->query('SELECT content_key, content_value FROM cms_content')->fetchAll(PDO::FETCH_KEY_PAIR);
}
function cms_editor_value(array $stored, string $key, string $fallback): string {
    return (string)($stored[$key] ?? $fallback);
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Homepage Editor | Bali Tarantula Admin</title>
<style>
:root{--orange:#ff5a1f;--ink:#20231f;--muted:#70766f;--line:#e4e8e2;--bg:#f5f6f4}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.55 Inter,Arial,sans-serif}.top{background:#fff;color:#20231f;padding:18px 26px;display:flex;align-items:center;justify-content:space-between;gap:15px;border-bottom:1px solid var(--line)}.top a{color:#303c32;text-decoration:none;font-weight:700}.wrap{max-width:1250px;margin:auto;padding:30px 22px 60px}h1{font-size:clamp(30px,4vw,42px);letter-spacing:-1px;margin:4px 0}.sub{color:var(--muted);max-width:760px}.eyebrow{font-size:10px;font-weight:900;letter-spacing:1.8px;color:var(--orange)}.notice{padding:13px 16px;border-radius:5px;margin:18px 0;background:#e4f5e8;color:#245531}.notice.error{background:#ffebe5;color:#852c17}.notice.info{background:#fff5e7;color:#72460e}.editor-group{background:#fff;border:1px solid var(--line);border-radius:8px;margin:20px 0;overflow:hidden}.editor-group h2{font-size:17px;margin:0;padding:17px 20px;border-bottom:1px solid var(--line)}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;padding:20px}.field{min-width:0}.field.full{grid-column:1/-1}.field label{display:block;font-size:11px;font-weight:800;margin-bottom:7px}.field input[type=text],.field input[type=url],.field textarea{width:100%;padding:12px;border:1px solid #d8ddd6;border-radius:4px;background:#fff;color:#20231f;font:14px/1.5 Inter,Arial,sans-serif}.field textarea{min-height:94px;resize:vertical}.field input:focus,.field textarea:focus{outline:2px solid #ffb08f;border-color:var(--orange)}.field input[type=file]{width:100%;font-size:12px}.help{display:block;margin-top:6px;color:#81877f;font-size:10px}.preview{display:block;width:min(100%,320px);max-height:150px;object-fit:cover;margin:10px 0;border:1px solid var(--line);border-radius:4px}.actions{position:sticky;bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 16px;background:#17221b;color:#fff;border-radius:7px;box-shadow:0 8px 28px #0002}.actions p{font-size:11px;color:#c4cec6;margin:0}.btn{border:0;border-radius:4px;background:var(--orange);color:#171b17;padding:13px 18px;font-weight:900;cursor:pointer}.btn:disabled{opacity:.4;cursor:not-allowed}.quicklinks{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.quicklinks a{background:#fff;border:1px solid var(--line);padding:10px 13px;color:#2b392e;text-decoration:none;font-size:12px;font-weight:700;border-radius:4px}@media(max-width:700px){.field-grid{grid-template-columns:1fr;padding:15px}.field.full{grid-column:auto}.wrap{padding:22px 12px 40px}.top{padding-left:60px}.actions{align-items:flex-start;flex-direction:column}.actions .btn{width:100%}}
</style><link rel="stylesheet" href="../assets/css/admin-sidebar.css?v=20261024"></head><body>
<?php require __DIR__ . '/_sidebar.php'; ?>
<header class="top"><strong>WEBSITE CONTENT / HOMEPAGE EDITOR</strong><a href="../index.php" target="_blank" rel="noopener">Preview website ↗</a></header>
<main class="wrap"><p class="eyebrow">CONTENT MANAGEMENT</p><h1>Homepage Editor</h1><p class="sub">Edit the homepage text and imagery from one place. Uploaded images are stored in the project and the public page uses safe fallback content if the CMS migration has not been installed.</p>
<?php if($message):?><div class="notice"><?=e($message)?></div><?php endif;?>
<?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?>
<?php if(!$tableReady):?><div class="notice info"><strong>Database setup required.</strong> Import <code>database/migrations/20261024_homepage_cms.sql</code> in phpMyAdmin first, then reload this page. The public homepage remains available with its current default content.</div><?php endif;?>
<form method="post" enctype="multipart/form-data"><?=csrf_field()?>
<?php foreach($groups as $groupTitle=>$fields):?><section class="editor-group" id="<?=e(strtolower(str_replace([' ','/'],'-',$groupTitle)))?>"><h2><?=e($groupTitle)?></h2><div class="field-grid">
<?php foreach($fields as [$key,$label,$type,$fallback]):$value=cms_editor_value($stored,$key,$fallback);?><div class="field<?=$type==='textarea'?' full':''?>"><label for="<?=e($key)?>"><?=e($label)?></label>
<?php if($type==='textarea'):?><textarea id="<?=e($key)?>" name="<?=e($key)?>" maxlength="12000" <?=$tableReady?'':'disabled'?>><?=e($value)?></textarea>
<?php elseif($type==='image'):?><?php if($value!==''):?><img class="preview" src="<?=e($value)?>" alt="<?=e($label)?> current image" loading="lazy"><?php endif;?><input id="<?=e($key)?>" type="file" name="<?=e($key)?>" accept="image/jpeg,image/png,image/webp,image/gif" <?=$tableReady?'':'disabled'?>><span class="help">JPG, PNG, WEBP, GIF · max 8 MB. Leave empty to keep the current image.</span>
<?php else:?><input id="<?=e($key)?>" type="text" name="<?=e($key)?>" value="<?=e($value)?>" maxlength="12000" <?=$tableReady?'':'disabled'?>><?php endif;?></div><?php endforeach;?>
</div></section><?php endforeach;?>
<div class="actions"><p>Changes are saved to the database and shown on the public homepage after saving.</p><button class="btn" type="submit" <?=$tableReady?'':'disabled'?>>SAVE HOMEPAGE CHANGES</button></div></form>
<div class="quicklinks"><a href="../index.php" target="_blank" rel="noopener">Open public homepage ↗</a><a href="catalog.php">Manage package prices and availability ↗</a></div>
</main></body></html>
