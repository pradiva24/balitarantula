<?php
require __DIR__.'/includes/auth.php';
$page_title='Homepage Editor';

function homepage_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS homepage_content (
        id INT NOT NULL PRIMARY KEY,
        hero_kicker VARCHAR(160) NOT NULL DEFAULT 'Bali Adventure Experiences', hero_title VARCHAR(255) NOT NULL DEFAULT 'More than an adventure.', hero_accent VARCHAR(255) NOT NULL DEFAULT 'It\'s your Bali story.', hero_text TEXT NULL,
        hero_button_text VARCHAR(120) NOT NULL DEFAULT 'Book Your Adventure', hero_button_url VARCHAR(255) NOT NULL DEFAULT '/booking.php', hero_secondary_text VARCHAR(120) NOT NULL DEFAULT 'Explore Experiences', hero_secondary_url VARCHAR(255) NOT NULL DEFAULT '#experiences', hero_image VARCHAR(500) NULL, hero_alt VARCHAR(255) NULL,
        intro_kicker VARCHAR(160) NOT NULL DEFAULT 'Our Experiences', intro_title VARCHAR(255) NOT NULL DEFAULT 'Choose your Bali adventure', intro_text TEXT NULL,
        story_kicker VARCHAR(160) NOT NULL DEFAULT 'Why Manuaba', story_title VARCHAR(255) NOT NULL DEFAULT 'Adventure, nature and authentic Bali in one place', story_text_1 TEXT NULL, story_text_2 TEXT NULL, story_button_text VARCHAR(120) NOT NULL DEFAULT 'Discover Our Story', story_button_url VARCHAR(255) NOT NULL DEFAULT '/about.php', story_image VARCHAR(500) NULL, story_alt VARCHAR(255) NULL, story_quote TEXT NULL, story_quote_author VARCHAR(160) NULL,
        why_kicker VARCHAR(160) NOT NULL DEFAULT 'Why Choose Us', why_title VARCHAR(255) NOT NULL DEFAULT 'A unique experience in the heart of Bali', why_text TEXT NULL,
        why_1_icon VARCHAR(100) DEFAULT '⌁', why_1_title VARCHAR(120) DEFAULT 'Stunning Natural Views', why_1_text TEXT NULL, why_2_icon VARCHAR(100) DEFAULT '✓', why_2_title VARCHAR(120) DEFAULT 'Safety First', why_2_text TEXT NULL, why_3_icon VARCHAR(100) DEFAULT '⌾', why_3_title VARCHAR(120) DEFAULT 'Perfect Adventure Spots', why_3_text TEXT NULL, why_4_icon VARCHAR(100) DEFAULT '♧', why_4_title VARCHAR(120) DEFAULT 'Warm Local Hospitality', why_4_text TEXT NULL,
        values_kicker VARCHAR(160) NOT NULL DEFAULT 'Our Values', values_title VARCHAR(255) NOT NULL DEFAULT 'Nature, people and meaningful travel', values_text TEXT NULL, values_image VARCHAR(500) NULL, values_alt VARCHAR(255) NULL,
        value_1_icon VARCHAR(100) DEFAULT '⌁', value_1_title VARCHAR(120) DEFAULT 'Sustainability', value_1_text TEXT NULL, value_2_icon VARCHAR(100) DEFAULT '♧', value_2_title VARCHAR(120) DEFAULT 'Community Support', value_2_text TEXT NULL, value_3_icon VARCHAR(100) DEFAULT '♡', value_3_title VARCHAR(120) DEFAULT 'Authentic Experiences', value_3_text TEXT NULL, value_4_icon VARCHAR(100) DEFAULT '☆', value_4_title VARCHAR(120) DEFAULT 'Excellence', value_4_text TEXT NULL,
        stats_kicker VARCHAR(160) NOT NULL DEFAULT 'Our Impact In Numbers', stat_1_number VARCHAR(50) DEFAULT '5+', stat_1_label VARCHAR(120) DEFAULT 'Years of Experience', stat_2_number VARCHAR(50) DEFAULT '50K+', stat_2_label VARCHAR(120) DEFAULT 'Happy Visitors', stat_3_number VARCHAR(50) DEFAULT '10+', stat_3_label VARCHAR(120) DEFAULT 'Adventure Experiences', stat_4_number VARCHAR(50) DEFAULT '100%', stat_4_label VARCHAR(120) DEFAULT 'Guest Satisfaction',
        reviews_kicker VARCHAR(160) NOT NULL DEFAULT 'Guest Reviews', reviews_title VARCHAR(255) NOT NULL DEFAULT 'What our guests say', reviews_text TEXT NULL, gallery_kicker VARCHAR(160) NOT NULL DEFAULT 'Adventure Moments', gallery_title VARCHAR(255) NOT NULL DEFAULT 'Explore our gallery', gallery_button_text VARCHAR(120) NOT NULL DEFAULT 'View Full Gallery', gallery_button_url VARCHAR(255) NOT NULL DEFAULT '/gallery.php',
        cta_kicker VARCHAR(160) NOT NULL DEFAULT 'Be Part Of The Adventure', cta_title VARCHAR(255) NOT NULL DEFAULT 'Create your unforgettable moments in Bali', cta_text TEXT NULL, cta_button_text VARCHAR(120) NOT NULL DEFAULT 'Book Your Experience', cta_button_url VARCHAR(255) NOT NULL DEFAULT '/booking.php', cta_image VARCHAR(500) NULL, cta_alt VARCHAR(255) NULL,
        hero_enabled TINYINT(1) NOT NULL DEFAULT 1, packages_enabled TINYINT(1) NOT NULL DEFAULT 1, story_enabled TINYINT(1) NOT NULL DEFAULT 1, why_enabled TINYINT(1) NOT NULL DEFAULT 1, values_enabled TINYINT(1) NOT NULL DEFAULT 1, stats_enabled TINYINT(1) NOT NULL DEFAULT 1, reviews_enabled TINYINT(1) NOT NULL DEFAULT 1, gallery_enabled TINYINT(1) NOT NULL DEFAULT 1, cta_enabled TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Upgrade legacy icon columns so Font Awesome class names are not truncated.
    foreach ([
        'why_1_icon','why_2_icon','why_3_icon','why_4_icon',
        'value_1_icon','value_2_icon','value_3_icon','value_4_icon'
    ] as $iconCol) {
        try { $db->exec("ALTER TABLE homepage_content MODIFY `{$iconCol}` VARCHAR(100) NULL"); } catch (Throwable $e) {}
    }
    $q=$db->query("SELECT COUNT(*) FROM homepage_content");
    if((int)$q->fetchColumn()===0){
        $db->exec("INSERT INTO homepage_content (id,hero_text,intro_text,story_text_1,story_text_2,why_text,why_1_text,why_2_text,why_3_text,why_4_text,values_text,value_1_text,value_2_text,value_3_text,value_4_text,reviews_text,cta_text,story_quote,story_quote_author) VALUES (1,'Ride through tropical landscapes, conquer the river, enjoy scenic swings and discover unforgettable experiences with our local Bali adventure team.','From ATV rides and Ayung River rafting to swings, Volkswagen tours and romantic experiences, choose the adventure that fits your Bali journey.','Bali is more than a destination. It is a place to connect with nature, culture and unforgettable moments. Our experiences are designed to help you discover that side of Bali through exciting outdoor activities.','Our local team focuses on friendly service, clear communication and memorable experiences from the moment you book until your adventure ends.','We are committed to creating safe, enjoyable and memorable experiences for every traveler.','Explore tropical landscapes, rice fields, rivers and beautiful Bali scenery.','Professional equipment, trained guides and clear activity procedures.','Discover scenic locations and capture moments worth remembering.','A friendly local team ready to make your Bali experience comfortable.','We believe a great Bali experience should be exciting while respecting the environment, local communities and the travelers who trust us.','We care about Bali’s natural environment and encourage responsible travel.','We work with local people and businesses to keep experiences authentic.','Real Bali moments designed to create memories that last.','We are committed to friendly service and quality experiences.','Real experiences from travelers who enjoyed Bali with us.','Join travelers discovering Bali through adventure, nature and authentic local experiences.','Create unforgettable Bali moments through nature, adventure and genuine local experiences.','— BALI TARANTULA ADVENTURE')");
    }
    try {
        $brand = site_brand_name();
        $st = $db->prepare("UPDATE homepage_content SET story_kicker=? WHERE id=1 AND (story_kicker LIKE '%Tarantula%' OR story_kicker LIKE '%Aloha Ubud%')");
        $st->execute(['Why '.preg_replace('/\s+Crystalfall$/i','',$brand)]);
        $st = $db->prepare("UPDATE homepage_content SET story_quote_author=? WHERE id=1 AND (story_quote_author LIKE '%Tarantula%' OR story_quote_author LIKE '%Aloha Ubud%')");
        $st->execute(['— '.$brand]);
    } catch (Throwable $e) {}
}
function hp_upload(string $field,string $old=''): string {
    return admin_upload($field,'homepage',$old);
}

function hp_setting_upsert(PDO $db,string $key,string $value): void {
    $st=$db->prepare("SELECT id FROM settings WHERE setting_key=?");
    $st->execute([$key]);
    $id=$st->fetchColumn();
    if($id){
        $db->prepare("UPDATE settings SET setting_value=?,updated_at=NOW() WHERE id=?")->execute([$value,$id]);
    }else{
        $db->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)")->execute([$key,$value]);
    }
}
function hp_media_upload(string $field,string $folder,string $old='',int $maxBytes=31457280): string {
    if(empty($_FILES[$field]) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return $old;
    $f=$_FILES[$field];
    if($f['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Video upload failed.');
    if($f['size']>$maxBytes) throw new RuntimeException('Video max 30 MB.');
    if(!is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Invalid uploaded file.');
    $finfo=new finfo(FILEINFO_MIME_TYPE);
    $mime=$finfo->file($f['tmp_name']);
    $allowed=[
        'video/mp4'=>'mp4',
        'video/webm'=>'webm',
        'video/ogg'=>'ogv'
    ];
    if(!isset($allowed[$mime])) throw new RuntimeException('Only MP4, WebM or OGG video is allowed.');
    $dir=__DIR__.'/../../uploads/'.$folder;
    if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Cannot create upload folder.');
    $name=bin2hex(random_bytes(12)).'.'.$allowed[$mime];
    $dest=$dir.'/'.$name;
    if(!move_uploaded_file($f['tmp_name'],$dest)) throw new RuntimeException('Cannot save video.');
    if($old){
        $oldfile=__DIR__.'/../../'.ltrim($old,'/');
        if(is_file($oldfile)) @unlink($oldfile);
    }
    return 'uploads/'.$folder.'/'.$name;
}
function hp_remove_media(string $path): void {
    if($path){
        $f=__DIR__.'/../../'.ltrim($path,'/');
        if(is_file($f)) @unlink($f);
    }
}

$db=db();
homepage_schema($db);
$fields=[
'hero_kicker','hero_title','hero_accent','hero_text','hero_button_text','hero_button_url','hero_secondary_text','hero_secondary_url','hero_alt',
'intro_kicker','intro_title','intro_text',
'story_kicker','story_title','story_text_1','story_text_2','story_button_text','story_button_url','story_alt','story_quote','story_quote_author',
'why_kicker','why_title','why_text','why_1_icon','why_1_title','why_1_text','why_2_icon','why_2_title','why_2_text','why_3_icon','why_3_title','why_3_text','why_4_icon','why_4_title','why_4_text',
'values_kicker','values_title','values_text','values_alt','value_1_icon','value_1_title','value_1_text','value_2_icon','value_2_title','value_2_text','value_3_icon','value_3_title','value_3_text','value_4_icon','value_4_title','value_4_text',
'stats_kicker','stat_1_number','stat_1_label','stat_2_number','stat_2_label','stat_3_number','stat_3_label','stat_4_number','stat_4_label',
'reviews_kicker','reviews_title','reviews_text','gallery_kicker','gallery_title','gallery_button_text','gallery_button_url',
'cta_kicker','cta_title','cta_text','cta_button_text','cta_button_url','cta_alt'
];
$bools=['hero_enabled','packages_enabled','story_enabled','why_enabled','values_enabled','stats_enabled','reviews_enabled','gallery_enabled','cta_enabled'];
$saved=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_verify_csrf();
    $set=[];$vals=[];
    foreach($fields as $f){$set[]="$f=?";$vals[]=trim((string)($_POST[$f]??''));}
    foreach($bools as $f){$set[]="$f=?";$vals[]=isset($_POST[$f])?1:0;}
    foreach(['hero_image','story_image','values_image','cta_image'] as $img){
        $old=$db->query("SELECT `$img` FROM homepage_content WHERE id=1")->fetchColumn() ?: '';
        if(isset($_POST['remove_'.$img]) && $_POST['remove_'.$img]==='1'){admin_delete_image($old);$new='';}
        else {$new=hp_upload($img,$old);}
        $set[]="$img=?";$vals[]=$new;
    }

    // Homepage hero media is stored as one JSON setting so no DB migration is required.
    $heroMediaMode = strtolower(trim((string)($_POST['hero_media_mode']??'slider')));
    if(!in_array($heroMediaMode,['slider','video'],true)) $heroMediaMode='slider';
    $currentHeroMedia = json_decode((string)setting('homepage_hero_media',''),true);
    if(!is_array($currentHeroMedia)) $currentHeroMedia=[];
    $heroSlides=[];
    for($i=1;$i<=5;$i++){
        $field='hero_slide_'.$i;
        $old=trim((string)($currentHeroMedia['slides'][$i-1]??''));
        if(isset($_POST['remove_'.$field]) && $_POST['remove_'.$field]==='1'){
            hp_remove_media($old);
            $new='';
        }else{
            $new=hp_upload($field,$old);
        }
        $heroSlides[]=$new;
    }
    $heroVideo=trim((string)($currentHeroMedia['video']??''));
    if(isset($_POST['remove_hero_video']) && $_POST['remove_hero_video']==='1'){
        hp_remove_media($heroVideo);
        $heroVideo='';
    }else{
        $heroVideo=hp_media_upload('hero_video','homepage',$heroVideo,30*1024*1024);
    }
    $heroPoster=trim((string)($currentHeroMedia['poster']??''));
    if(isset($_POST['remove_hero_poster']) && $_POST['remove_hero_poster']==='1'){
        admin_delete_image($heroPoster);
        $heroPoster='';
    }else{
        $heroPoster=hp_upload('hero_poster',$heroPoster);
    }
    hp_setting_upsert($db,'homepage_hero_media',json_encode([
        'mode'=>$heroMediaMode,
        'slides'=>$heroSlides,
        'video'=>$heroVideo,
        'poster'=>$heroPoster
    ],JSON_UNESCAPED_SLASHES));
    // Reviews section background image is stored in settings to avoid a schema migration.
    $oldReviewsBg = trim((string)setting('homepage_reviews_bg','assets/images/reviews-bg.jpg'));
    if (isset($_POST['remove_reviews_bg_image']) && $_POST['remove_reviews_bg_image'] === '1') {
        if (strpos($oldReviewsBg, 'uploads/') === 0) hp_remove_media($oldReviewsBg);
        $reviewsBg = '';
    } else {
        $reviewsBg = hp_upload('reviews_bg_image', $oldReviewsBg);
    }
    hp_setting_upsert($db, 'homepage_reviews_bg', $reviewsBg);
    // Keep the older settings keys synchronized for compatibility with older homepage builds.
    hp_setting_upsert($db,'homepage_hero_media_mode',$heroMediaMode);
    hp_setting_upsert($db,'homepage_hero_video',$heroVideo);

    $vals[]=1;
    $db->prepare('UPDATE homepage_content SET '.implode(',',$set).' WHERE id=?')->execute($vals);

    // Keep homepage icon fields and the central Icon Manager in sync.
    $iconDefaults = [
        'why_1_icon'=>'fa-solid fa-water','why_2_icon'=>'fa-solid fa-shield-heart','why_3_icon'=>'fa-solid fa-mountain-sun','why_4_icon'=>'fa-solid fa-people-group',
        'value_1_icon'=>'fa-solid fa-leaf','value_2_icon'=>'fa-solid fa-handshake','value_3_icon'=>'fa-solid fa-heart','value_4_icon'=>'fa-solid fa-star'
    ];
    foreach ($iconDefaults as $field=>$fallback) {
        $raw = trim((string)($_POST[$field] ?? ''));
        if (!preg_match('/^fa-(?:solid|regular|brands|light|thin|duotone)\s+fa-[a-z0-9-]+$/i', $raw)) $raw=$fallback;
        hp_setting_upsert($db, 'icon_home.'.preg_replace('/_icon$/','',$field), $raw);
    }
    admin_flash('success','Homepage content updated successfully.');
    header('Location: homepage.php');exit;
}
$home=$db->query("SELECT * FROM homepage_content WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hero_media = json_decode((string)setting('homepage_hero_media',''),true);
if(!is_array($hero_media)) $hero_media=[];
$hero_media_mode = in_array(($hero_media['mode']??''),['slider','video'],true) ? $hero_media['mode'] : (strtolower((string)setting('homepage_hero_media_mode','slider'))==='video' ? 'video' : 'slider');
$hero_media_slides = array_pad(is_array($hero_media['slides']??null)?$hero_media['slides']:[],5,'');
$hero_media_video = trim((string)($hero_media['video']??setting('homepage_hero_video','')));
$hero_media_poster = trim((string)($hero_media['poster']??''));
require __DIR__.'/includes/header.php';
function hp_val($k){global $home;return admin_e($home[$k]??'');}
function hp_check($k){global $home;return !empty($home[$k])?'checked':'';}
?>
<style>
.hp-wrap{max-width:1180px}.hp-intro{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:18px}.hp-intro p{margin:4px 0;color:#718078;font-size:12px}.hp-actions{display:flex;gap:8px}.hp-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border-radius:7px;padding:10px 15px;font-size:11px;font-weight:700;border:1px solid #d8e2db;background:#fff;color:#15583e}.hp-btn.primary{background:#126044;border-color:#126044;color:#fff}.hp-card{background:#fff;border:1px solid #e1e9e3;border-radius:11px;padding:21px;margin-bottom:16px;box-shadow:0 4px 15px rgba(19,59,42,.04)}.hp-card h2{font-size:18px;color:#15583e;margin:0}.hp-card .hint{font-size:10px;color:#829088;margin:4px 0 18px}.hp-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.hp-full{grid-column:1/-1}.hp-field label{display:block;font-size:11px;font-weight:700;color:#31483d;margin-bottom:6px}.hp-field input,.hp-field textarea,.hp-field select{width:100%;box-sizing:border-box;border:1px solid #d5dfd8;border-radius:7px;background:#fff;color:#20352c;padding:10px 11px;font:12px 'Inter',Arial,sans-serif;outline:none}.hp-field input:focus,.hp-field textarea:focus{border-color:#78a98e;box-shadow:0 0 0 3px rgba(18,96,68,.07)}.hp-field textarea{min-height:90px;resize:vertical}.hp-section-switch{display:flex;align-items:center;gap:8px;margin-bottom:17px;padding:10px 12px;background:#f5f9f4;border-radius:7px}.hp-section-switch input{accent-color:#126044}.hp-section-switch label{margin:0;font-size:11px;font-weight:700}.hp-image{display:grid;grid-template-columns:160px 1fr;gap:14px;align-items:start}.hp-preview{width:160px;height:95px;object-fit:cover;border-radius:7px;background:#edf3ee;border:1px solid #dce6df}.hp-media-wide{width:100%;height:125px}.hp-mini .hp-media-wide{display:block}.hp-file input{padding:8px}.hp-remove{display:inline-flex;align-items:center;gap:5px;font-size:10px;color:#a34b43;margin-top:6px}.hp-cards{display:grid;grid-template-columns:1fr 1fr;gap:14px}.hp-mini{border:1px solid #e2e9e4;border-radius:9px;padding:14px;background:#fbfdfb}.hp-mini h3{font-size:12px;color:#15583e;margin:0 0 12px}.hp-save{position:sticky;bottom:14px;z-index:20;display:flex;justify-content:flex-end;margin-top:8px}.hp-save button{border:0;border-radius:8px;background:#126044;color:#fff;padding:13px 25px;font:700 12px 'Inter',Arial,sans-serif;cursor:pointer;box-shadow:0 8px 20px rgba(18,96,68,.22)}.hp-note{padding:10px 12px;border-radius:7px;background:#fff8e8;color:#7a5d1c;font-size:10px;margin-bottom:15px}.hp-tabs{display:flex;gap:7px;overflow:auto;margin-bottom:15px}.hp-tab{border:1px solid #dce5df;background:#fff;color:#64746b;padding:9px 13px;border-radius:7px;font-size:10px;font-weight:700;cursor:pointer;white-space:nowrap}.hp-tab.active{background:#126044;color:#fff;border-color:#126044}.hp-panel{display:none}.hp-panel.active{display:block}.hp-counter{font-size:9px;color:#87948d;text-align:right;margin-top:4px}
@media(max-width:800px){.hp-grid,.hp-cards{grid-template-columns:1fr}.hp-full{grid-column:auto}.hp-intro{align-items:flex-start;flex-direction:column}.hp-image{grid-template-columns:1fr}.hp-preview{width:100%;height:160px}.hp-actions{width:100%}.hp-actions .hp-btn{flex:1}}
</style>
<div class="hp-wrap">
<div class="hp-intro"><div><h2 style="margin:0;color:#173c2e">Homepage Editor</h2><p>Edit every main homepage section, text, CTA and image from one place.</p></div><div class="hp-actions"><a class="hp-btn" href="<?=BASE_URL?>/" target="_blank" rel="noopener">Preview Homepage ↗</a><a class="hp-btn" href="seo.php?key=home">Edit Homepage SEO</a></div></div>
<div class="hp-note">Packages, reviews and gallery items themselves are managed from their existing Admin menus. This editor controls the homepage presentation, section copy, CTA, statistics, hero slider/video and selected homepage images.</div>
<div class="hp-tabs">
<?php foreach([['hero','Hero'],['packages','Experiences'],['story','Story'],['why','Why Choose Us'],['values','Tracks'],['stats','Statistics'],['reviews','Reviews'],['gallery','Gallery'],['cta','CTA']] as $i=>$t): ?><button type="button" class="hp-tab <?=$i===0?'active':''?>" data-tab="<?=$t[0]?>"><?=$t[1]?></button><?php endforeach; ?>
</div>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=admin_csrf()?>">

<div class="hp-panel active" data-panel="hero">
<div class="hp-card">
<h2>Hero Section</h2>
<div class="hint">Edit the first screen of the homepage, including the image slider or background video.</div>
<div class="hp-section-switch"><input type="checkbox" name="hero_enabled" id="hero_enabled" <?=hp_check('hero_enabled')?>><label for="hero_enabled">Show Hero Section</label></div>

<div class="hp-grid">
<div class="hp-field"><label>Kicker</label><input name="hero_kicker" value="<?=hp_val('hero_kicker')?>"></div>
<div class="hp-field"><label>Main Title</label><input name="hero_title" value="<?=hp_val('hero_title')?>"></div>
<div class="hp-field"><label>Accent Title</label><input name="hero_accent" value="<?=hp_val('hero_accent')?>"></div>
<div class="hp-field"><label>Primary Button Text</label><input name="hero_button_text" value="<?=hp_val('hero_button_text')?>"></div>
<div class="hp-field"><label>Primary Button URL</label><input name="hero_button_url" value="<?=hp_val('hero_button_url')?>"></div>
<div class="hp-field"><label>Secondary Button Text</label><input name="hero_secondary_text" value="<?=hp_val('hero_secondary_text')?>"></div>
<div class="hp-field"><label>Secondary Button URL</label><input name="hero_secondary_url" value="<?=hp_val('hero_secondary_url')?>"></div>
<div class="hp-field hp-full"><label>Hero Description</label><textarea name="hero_text"><?=hp_val('hero_text')?></textarea></div>
<div class="hp-field hp-full"><label>Default Hero Image & SEO Alt Text</label><div class="hp-image">
<?php if(!empty($home['hero_image'])):?><img class="hp-preview" src="<?=admin_e(asset_url($home['hero_image']))?>" alt=""><?php else:?><div class="hp-preview"></div><?php endif;?>
<div class="hp-file"><input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp"><div class="hp-remove"><input type="checkbox" name="remove_hero_image" value="1"> Remove current image</div><div style="margin-top:8px"><input name="hero_alt" placeholder="Descriptive image alt text" value="<?=hp_val('hero_alt')?>"></div></div>
</div></div>
</div>
</div>

<div class="hp-card">
<h2>Hero Media</h2>
<div class="hint">Choose Image Slider or Background Video. The selected mode is used automatically on the public homepage.</div>
<div class="hp-field hp-full">
<label>Media Type</label>
<select name="hero_media_mode" id="hero_media_mode">
<option value="slider" <?=$hero_media_mode==='slider'?'selected':''?>>Image Slider</option>
<option value="video" <?=$hero_media_mode==='video'?'selected':''?>>Background Video</option>
</select>
</div>

<div id="hero-slider-editor" style="margin-top:16px">
<div class="hp-note" style="margin-top:0">Up to 5 hero slides. If all five are empty, the homepage keeps its existing fallback: the Default Hero Image followed by Gallery images.</div>
<div class="hp-cards">
<?php for($i=1;$i<=5;$i++): $slide=(string)($hero_media_slides[$i-1]??''); ?>
<div class="hp-mini">
<h3>Slide <?=$i?></h3>
<?php if($slide): ?><img class="hp-preview hp-media-wide" src="<?=admin_e(asset_url($slide))?>" alt="Hero Slide <?=$i?>"><?php else: ?><div class="hp-preview hp-media-wide"></div><?php endif; ?>
<div class="hp-field" style="margin-top:10px"><label>Upload Image</label><input type="file" name="hero_slide_<?=$i?>" accept="image/jpeg,image/png,image/webp"></div>
<div class="hp-remove"><input type="checkbox" name="remove_hero_slide_<?=$i?>" value="1"> Remove current slide</div>
</div>
<?php endfor; ?>
</div>
</div>

<div id="hero-video-editor" style="margin-top:16px;display:none">
<div class="hp-grid">
<div class="hp-field">
<label>Background Video</label>
<input type="file" name="hero_video" accept="video/mp4,video/webm,video/ogg">
<?php if($hero_media_video): ?><div style="margin-top:8px;font-size:10px;color:#52645b">Current: <?=admin_e(basename($hero_media_video))?></div><?php endif; ?>
<div class="hp-remove"><input type="checkbox" name="remove_hero_video" value="1"> Remove current video</div>
</div>
<div class="hp-field">
<label>Video Poster Image</label>
<input type="file" name="hero_poster" accept="image/jpeg,image/png,image/webp">
<?php if($hero_media_poster): ?><img class="hp-preview" style="margin-top:8px" src="<?=admin_e(asset_url($hero_media_poster))?>" alt="Video Poster"><?php else: ?><div class="hp-preview" style="margin-top:8px"></div><?php endif; ?>
<div class="hp-remove"><input type="checkbox" name="remove_hero_poster" value="1"> Remove current poster</div>
</div>
</div>
<p style="font-size:10px;color:#718078;margin:12px 0 0">Video limit: 30 MB. Supported formats: MP4, WebM, OGG.</p>
</div>
</div>

<script>
(function(){
  const select=document.getElementById('hero_media_mode');
  const slider=document.getElementById('hero-slider-editor');
  const video=document.getElementById('hero-video-editor');
  function syncHeroMedia(){
    const isVideo=select && select.value==='video';
    if(slider) slider.style.display=isVideo?'none':'block';
    if(video) video.style.display=isVideo?'block':'none';
  }
  select?.addEventListener('change',syncHeroMedia);
  syncHeroMedia();
})();
</script>
</div>

<div class="hp-panel" data-panel="packages"><div class="hp-card"><h2>Adventure Experiences Section</h2><div class="hint">Package cards are automatically loaded from Admin → Packages.</div><div class="hp-section-switch"><input type="checkbox" name="packages_enabled" id="packages_enabled" <?=hp_check('packages_enabled')?>><label for="packages_enabled">Show Adventure Experiences Section</label></div><div class="hp-grid"><div class="hp-field"><label>Section Kicker</label><input name="intro_kicker" value="<?=hp_val('intro_kicker')?>"></div><div class="hp-field"><label>Section Title</label><input name="intro_title" value="<?=hp_val('intro_title')?>"></div><div class="hp-field hp-full"><label>Intro Description</label><textarea name="intro_text"><?=hp_val('intro_text')?></textarea></div></div><p style="font-size:10px;color:#718078;margin:15px 0 0">To change package names, photos, prices or descriptions, use <b>Admin → Packages</b>.</p></div></div>

<div class="hp-panel" data-panel="story"><div class="hp-card"><h2>Why Manuaba / Story</h2><div class="hint">Two-column image and story section.</div><div class="hp-section-switch"><input type="checkbox" name="story_enabled" id="story_enabled" <?=hp_check('story_enabled')?>><label for="story_enabled">Show Story Section</label></div><div class="hp-grid"><div class="hp-field"><label>Kicker</label><input name="story_kicker" value="<?=hp_val('story_kicker')?>"></div><div class="hp-field"><label>Title</label><input name="story_title" value="<?=hp_val('story_title')?>"></div><div class="hp-field hp-full"><label>Paragraph 1</label><textarea name="story_text_1"><?=hp_val('story_text_1')?></textarea></div><div class="hp-field hp-full"><label>Paragraph 2</label><textarea name="story_text_2"><?=hp_val('story_text_2')?></textarea></div><div class="hp-field"><label>Button Text</label><input name="story_button_text" value="<?=hp_val('story_button_text')?>"></div><div class="hp-field"><label>Button URL</label><input name="story_button_url" value="<?=hp_val('story_button_url')?>"></div><div class="hp-field"><label>Quote Author</label><input name="story_quote_author" value="<?=hp_val('story_quote_author')?>"></div><div class="hp-field"><label>Quote</label><textarea name="story_quote"><?=hp_val('story_quote')?></textarea></div><div class="hp-field hp-full"><label>Story Image & SEO Alt Text</label><div class="hp-image"><?php if(!empty($home['story_image'])):?><img class="hp-preview" src="<?=admin_e(asset_url($home['story_image']))?>" alt=""><?php else:?><div class="hp-preview"></div><?php endif;?><div class="hp-file"><input type="file" name="story_image" accept="image/jpeg,image/png,image/webp"><div class="hp-remove"><input type="checkbox" name="remove_story_image" value="1"> Remove current image</div><div style="margin-top:8px"><input name="story_alt" placeholder="Descriptive image alt text" value="<?=hp_val('story_alt')?>"></div></div></div></div></div></div></div>

<div class="hp-panel" data-panel="why"><div class="hp-card"><h2>Why Choose Us</h2><div class="hint">Four icon cards shown below the story section.</div><div class="hp-section-switch"><input type="checkbox" name="why_enabled" id="why_enabled" <?=hp_check('why_enabled')?>><label for="why_enabled">Show Why Choose Us Section</label></div><div class="hp-grid"><div class="hp-field"><label>Kicker</label><input name="why_kicker" value="<?=hp_val('why_kicker')?>"></div><div class="hp-field"><label>Title</label><input name="why_title" value="<?=hp_val('why_title')?>"></div><div class="hp-field hp-full"><label>Intro Text</label><textarea name="why_text"><?=hp_val('why_text')?></textarea></div></div><div class="hp-cards" style="margin-top:14px"><?php for($i=1;$i<=4;$i++):?><div class="hp-mini"><h3>Card <?=$i?></h3><div class="hp-grid"><div class="hp-field"><label>Font Awesome Icon</label><input type="text" name="why_<?=$i?>_icon" value="<?=hp_val("why_{$i}_icon")?>" placeholder="fa-solid fa-water" autocomplete="off"></div><div class="hp-field"><label>Title</label><input name="why_<?=$i?>_title" value="<?=hp_val("why_{$i}_title")?>"></div><div class="hp-field hp-full"><label>Description</label><textarea name="why_<?=$i?>_text"><?=hp_val("why_{$i}_text")?></textarea></div></div></div><?php endfor;?></div></div></div>

<div class="hp-panel" data-panel="values"><div class="hp-card"><h2>Adventure Tracks Section</h2><div class="hint">Atur judul dan teks pengantar untuk bagian Track di homepage. Kartu track (nama, foto, jarak, durasi, kesulitan, deskripsi) dikelola melalui menu Admin → Adventure Tracks.</div><div class="hp-section-switch"><input type="checkbox" name="values_enabled" id="values_enabled" <?=hp_check('values_enabled')?>><label for="values_enabled">Show Tracks Section</label></div><div class="hp-grid"><div class="hp-field"><label>Track Kicker / Small Heading</label><input name="values_kicker" value="<?=hp_val('values_kicker')?>" placeholder="BALI TARANTULA ADVENTURE"></div><div class="hp-field"><label>Track Section Title</label><input name="values_title" value="<?=hp_val('values_title')?>" placeholder="Explore Our Tracks"></div><div class="hp-field hp-full"><label>Track Intro Text</label><textarea name="values_text" placeholder="Choose your adventure and discover the track that matches your style."><?=hp_val('values_text')?></textarea></div></div><p style="font-size:12px;color:#718078;margin:14px 0 0">Untuk mengubah judul setiap kartu track, foto dan detailnya, buka <b>Admin → Adventure Tracks</b>.</p></div></div>

<div class="hp-panel" data-panel="stats"><div class="hp-card"><h2>Impact In Numbers</h2><div class="hint">Edit the four statistics displayed on the homepage.</div><div class="hp-section-switch"><input type="checkbox" name="stats_enabled" id="stats_enabled" <?=hp_check('stats_enabled')?>><label for="stats_enabled">Show Statistics Section</label></div><div class="hp-field"><label>Section Kicker</label><input name="stats_kicker" value="<?=hp_val('stats_kicker')?>"></div><div class="hp-cards" style="margin-top:14px"><?php for($i=1;$i<=4;$i++):?><div class="hp-mini"><h3>Statistic <?=$i?></h3><div class="hp-grid"><div class="hp-field"><label>Number</label><input name="stat_<?=$i?>_number" value="<?=hp_val("stat_{$i}_number")?>"></div><div class="hp-field"><label>Label</label><input name="stat_<?=$i?>_label" value="<?=hp_val("stat_{$i}_label")?>"></div></div></div><?php endfor;?></div></div></div>

<div class="hp-panel" data-panel="reviews"><div class="hp-card"><h2>Guest Reviews Section</h2><div class="hint">Review cards come automatically from Admin → Reviews. You can also change the section background image here.</div><div class="hp-section-switch"><input type="checkbox" name="reviews_enabled" id="reviews_enabled" <?=hp_check('reviews_enabled')?>><label for="reviews_enabled">Show Reviews Section</label></div><div class="hp-grid"><div class="hp-field"><label>Kicker</label><input name="reviews_kicker" value="<?=hp_val('reviews_kicker')?>"></div><div class="hp-field"><label>Title</label><input name="reviews_title" value="<?=hp_val('reviews_title')?>"></div><div class="hp-field hp-full"><label>Intro Text</label><textarea name="reviews_text"><?=hp_val('reviews_text')?></textarea></div><div class="hp-field hp-full"><label>Reviews Section Background Image</label><?php $reviewsBgPreview=trim((string)setting('homepage_reviews_bg','assets/images/reviews-bg.jpg')); ?><div class="hp-image"><?php if($reviewsBgPreview): ?><img class="hp-preview hp-media-wide" src="<?=admin_e(asset_url($reviewsBgPreview))?>" alt="Reviews background preview"><?php else: ?><div class="hp-preview hp-media-wide"></div><?php endif; ?><div class="hp-file"><input type="file" name="reviews_bg_image" accept="image/jpeg,image/png,image/webp,image/gif"><div class="hint" style="margin:5px 0">Recommended: landscape image, JPG, PNG or WebP. A dark overlay is applied automatically on the homepage.</div><div class="hp-remove"><input type="checkbox" name="remove_reviews_bg_image" value="1"> Remove current image and use default/no image</div></div></div></div></div><p style="font-size:10px;color:#718078;margin:15px 0 0">Manage review names, ratings, text and visibility from <b>Admin → Reviews</b>.</p></div></div>

<div class="hp-panel" data-panel="gallery"><div class="hp-card"><h2>Gallery Section</h2><div class="hint">Gallery images are automatically loaded from Admin → Gallery.</div><div class="hp-section-switch"><input type="checkbox" name="gallery_enabled" id="gallery_enabled" <?=hp_check('gallery_enabled')?>><label for="gallery_enabled">Show Gallery Section</label></div><div class="hp-grid"><div class="hp-field"><label>Kicker</label><input name="gallery_kicker" value="<?=hp_val('gallery_kicker')?>"></div><div class="hp-field"><label>Title</label><input name="gallery_title" value="<?=hp_val('gallery_title')?>"></div><div class="hp-field"><label>Button Text</label><input name="gallery_button_text" value="<?=hp_val('gallery_button_text')?>"></div><div class="hp-field"><label>Button URL</label><input name="gallery_button_url" value="<?=hp_val('gallery_button_url')?>"></div></div><p style="font-size:10px;color:#718078;margin:15px 0 0">Manage gallery photos and alt text from <b>Admin → Gallery</b>.</p></div></div>

<div class="hp-panel" data-panel="cta"><div class="hp-card"><h2>Final Call To Action</h2><div class="hint">Bottom booking CTA with background image.</div><div class="hp-section-switch"><input type="checkbox" name="cta_enabled" id="cta_enabled" <?=hp_check('cta_enabled')?>><label for="cta_enabled">Show CTA Section</label></div><div class="hp-grid"><div class="hp-field"><label>Kicker</label><input name="cta_kicker" value="<?=hp_val('cta_kicker')?>"></div><div class="hp-field"><label>Title</label><input name="cta_title" value="<?=hp_val('cta_title')?>"></div><div class="hp-field hp-full"><label>Description</label><textarea name="cta_text"><?=hp_val('cta_text')?></textarea></div><div class="hp-field"><label>Button Text</label><input name="cta_button_text" value="<?=hp_val('cta_button_text')?>"></div><div class="hp-field"><label>Button URL</label><input name="cta_button_url" value="<?=hp_val('cta_button_url')?>"></div><div class="hp-field hp-full"><label>CTA Background Image & SEO Alt Text</label><div class="hp-image"><?php if(!empty($home['cta_image'])):?><img class="hp-preview" src="<?=admin_e(asset_url($home['cta_image']))?>" alt=""><?php else:?><div class="hp-preview"></div><?php endif;?><div class="hp-file"><input type="file" name="cta_image" accept="image/jpeg,image/png,image/webp"><div class="hp-remove"><input type="checkbox" name="remove_cta_image" value="1"> Remove current image</div><div style="margin-top:8px"><input name="cta_alt" placeholder="Descriptive image alt text" value="<?=hp_val('cta_alt')?>"></div></div></div></div></div></div></div>

<div class="hp-save"><button type="submit">Save Homepage Changes</button></div>
</form>
</div>
<script>
const tabs=document.querySelectorAll('.hp-tab'), panels=document.querySelectorAll('.hp-panel');
tabs.forEach(t=>t.addEventListener('click',()=>{tabs.forEach(x=>x.classList.remove('active'));panels.forEach(x=>x.classList.remove('active'));t.classList.add('active');document.querySelector('[data-panel="'+t.dataset.tab+'"]')?.classList.add('active');window.scrollTo({top:0,behavior:'smooth'});}));
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
