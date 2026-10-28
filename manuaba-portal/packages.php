<?php
require __DIR__.'/includes/auth.php';
$p=db();
$action=$_GET['action']??'list';
$id=(int)($_GET['id']??0);

// Per-package activity start times, editable in the package form.
try { $p->exec("CREATE TABLE IF NOT EXISTS package_activity_times (id INT UNSIGNED NOT NULL AUTO_INCREMENT, package_id INT UNSIGNED NOT NULL, activity_time TIME NOT NULL, sort_order INT NOT NULL DEFAULT 0, PRIMARY KEY(id), UNIQUE KEY uq_package_activity_time(package_id,activity_time), KEY idx_package_activity_times(package_id), CONSTRAINT fk_package_activity_times_package FOREIGN KEY(package_id) REFERENCES packages(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(Throwable $e) {}


function package_hero_upsert(PDO $db, string $key, string $value): void {
  $st=$db->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
  $st->execute([$key,$value]);
}
function package_hero_upload(string $field, string $old=''): string {
  if(empty($_FILES[$field]) || ($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return $old;
  return admin_upload($field,'packages',$old);
}

// Package highlights are managed from the package editor. Create the table automatically for existing installs.
// Control whether each package appears in the public booking Activity selector.
// Existing packages default to visible so this change is backward-compatible.
try {
  $hasBookingEnabled = $p->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='packages' AND COLUMN_NAME='booking_enabled'")->fetchColumn();
  if (!(int)$hasBookingEnabled) {
    $p->exec("ALTER TABLE packages ADD COLUMN booking_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER status");
  }
} catch (Throwable $e) {}
try { $hasOgImage=$p->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='packages' AND COLUMN_NAME='og_image'")->fetchColumn(); if(!(int)$hasOgImage)$p->exec("ALTER TABLE packages ADD COLUMN og_image VARCHAR(500) NULL AFTER canonical_url"); } catch(Throwable $e) {}

// Best Seller flag: independently controls the orange-highlighted package card
// on the public Packages page and homepage. Existing packages default to off.
$bestSellerReady = false;
try {
  $col = $p->query("SHOW COLUMNS FROM packages LIKE 'best_seller'")->fetch(PDO::FETCH_ASSOC);
  if (!$col) {
    $p->exec("ALTER TABLE packages ADD COLUMN best_seller TINYINT(1) NOT NULL DEFAULT 0");
  }
  $bestSellerReady = (bool)$p->query("SHOW COLUMNS FROM packages LIKE 'best_seller'")->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // Keep the Packages admin usable if this DB user cannot ALTER TABLE.
  $bestSellerReady = false;
}

// Manual homepage package ordering. Lower number = higher position on the homepage.
// Keep this migration compatible with existing MySQL/MariaDB installations.
$homepageOrderReady = false;
try {
  $col = $p->query("SHOW COLUMNS FROM packages LIKE 'homepage_order'")->fetch(PDO::FETCH_ASSOC);
  if (!$col) {
    // Do not depend on another column name/order; this also works on older schemas.
    $p->exec("ALTER TABLE packages ADD COLUMN homepage_order INT NOT NULL DEFAULT 0");
    $p->exec("UPDATE packages SET homepage_order=id WHERE homepage_order=0");
  }
  $col = $p->query("SHOW COLUMNS FROM packages LIKE 'homepage_order'")->fetch(PDO::FETCH_ASSOC);
  $homepageOrderReady = (bool)$col;
} catch (Throwable $e) {
  // Keep the Packages admin page usable if the DB user cannot ALTER TABLE.
  $homepageOrderReady = false;
}

try {
  $p->exec("CREATE TABLE IF NOT EXISTS package_highlights (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NOT NULL,
    highlight_text VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_package_highlights_package (package_id),
    CONSTRAINT fk_package_highlights_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

// Package hero feature items are managed per package from the package editor.
// Each package has four editable feature slots: icon class, title and subtitle.
try {
  $p->exec("CREATE TABLE IF NOT EXISTS package_hero_features (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NOT NULL,
    feature_key VARCHAR(40) NOT NULL,
    icon VARCHAR(120) NOT NULL,
    title VARCHAR(120) NOT NULL,
    subtitle VARCHAR(120) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_package_hero_feature (package_id,feature_key),
    KEY idx_package_hero_features_package (package_id),
    CONSTRAINT fk_package_hero_features_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

// Package benefit strip items shown below the package description.
// Four editable items: icon, title and subtitle.
try {
  $p->exec("CREATE TABLE IF NOT EXISTS package_benefits (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NOT NULL,
    benefit_key VARCHAR(40) NOT NULL,
    icon VARCHAR(120) NOT NULL,
    title VARCHAR(120) NOT NULL,
    subtitle VARCHAR(180) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_package_benefit (package_id,benefit_key),
    KEY idx_package_benefits_package (package_id),
    CONSTRAINT fk_package_benefits_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

// Package Include / Exclude items are managed from the package editor.
try {
  $p->exec("CREATE TABLE IF NOT EXISTS package_includes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NOT NULL,
    item_text VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_package_includes_package (package_id),
    CONSTRAINT fk_package_includes_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $p->exec("CREATE TABLE IF NOT EXISTS package_excludes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NOT NULL,
    item_text VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_package_excludes_package (package_id),
    CONSTRAINT fk_package_excludes_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

// Package Need For Information FAQ items are managed from the package editor.
try {
  $p->exec("CREATE TABLE IF NOT EXISTS package_faqs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NOT NULL,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_package_faqs_package (package_id),
    CONSTRAINT fk_package_faqs_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

// Package booking action buttons are managed per package.
// Keep this schema compatible with older installs that may already have
// package_action_buttons without the newer "label" column.
try {
  $p->exec("CREATE TABLE IF NOT EXISTS package_action_buttons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NOT NULL,
    button_key VARCHAR(40) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    custom_url VARCHAR(1000) NULL,
    label VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_package_action_button (package_id,button_key),
    KEY idx_package_action_button_package (package_id),
    CONSTRAINT fk_package_action_button_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  // Migration for databases created by the previous button version.
  $hasLabel = $p->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='package_action_buttons' AND COLUMN_NAME='label'")->fetchColumn();
  if (!(int)$hasLabel) {
    $p->exec("ALTER TABLE package_action_buttons ADD COLUMN label VARCHAR(100) NULL AFTER custom_url");
  }

  // Ensure the unique key used by the upsert exists. If it is already there,
  // MySQL will simply skip this step through the metadata check.
  $hasUnique = $p->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='package_action_buttons'
    AND INDEX_NAME='uq_package_action_button'")->fetchColumn();
  if (!(int)$hasUnique) {
    try { $p->exec("ALTER TABLE package_action_buttons ADD UNIQUE KEY uq_package_action_button (package_id,button_key)"); } catch (Throwable $e) {}
  }
} catch (Throwable $e) {}

// Package price-card settings and bullet list are managed from the package editor.
try {
  $p->exec("CREATE TABLE IF NOT EXISTS package_price_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NOT NULL,
    original_price DECIMAL(12,2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_package_price_settings_package (package_id),
    CONSTRAINT fk_package_price_settings_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $p->exec("CREATE TABLE IF NOT EXISTS package_price_bullets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NOT NULL,
    item_text VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_package_price_bullets_package (package_id),
    CONSTRAINT fk_package_price_bullets_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

try {
  if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_verify_csrf();
    $act=$_POST['form_action']??'';

    if($act==='hero'){
      $heroOld='';
      try{
        $q=$p->prepare("SELECT setting_value FROM settings WHERE setting_key='packages_hero_image' LIMIT 1");
        $q->execute(); $heroOld=(string)$q->fetchColumn();
      }catch(Throwable $e){}
      $heroImage=package_hero_upload('package_hero_image',$heroOld);
      if(isset($_POST['remove_package_hero_image']) && $_POST['remove_package_hero_image']==='1'){
        if($heroOld) admin_delete_image($heroOld);
        $heroImage='';
      }
      package_hero_upsert($p,'packages_hero_kicker',trim($_POST['package_hero_kicker']??''));
      package_hero_upsert($p,'packages_hero_title',trim($_POST['package_hero_title']??''));
      package_hero_upsert($p,'packages_hero_accent',trim($_POST['package_hero_accent']??''));
      package_hero_upsert($p,'packages_hero_text',trim($_POST['package_hero_text']??''));
      package_hero_upsert($p,'packages_hero_button_text',trim($_POST['package_hero_button_text']??''));
      package_hero_upsert($p,'packages_hero_button_url',trim($_POST['package_hero_button_url']??''));
      package_hero_upsert($p,'packages_hero_image',$heroImage);
      admin_flash('','Packages page hero saved');
      header('Location: packages.php?action=hero');exit;
    }

    if($act==='reorder'){
      $order=$_POST['package_order']??[];
      if(!is_array($order) || !$order){
        throw new RuntimeException('No package order was submitted.');
      }
      $order=array_values(array_unique(array_map('intval',$order)));
      $valid=$p->query('SELECT id FROM packages')->fetchAll(PDO::FETCH_COLUMN);
      $valid=array_map('intval',$valid);
      $validSet=array_fill_keys($valid,true);
      $order=array_values(array_filter($order, function($v) use ($validSet) { return isset($validSet[$v]); }));
      foreach($valid as $pid){ if(!in_array($pid,$order,true)) $order[]=$pid; }
      $p->beginTransaction();
      $st=$p->prepare('UPDATE packages SET homepage_order=? WHERE id=?');
      foreach($order as $position=>$pid){ $st->execute([$position+1,$pid]); }
      $p->commit();
      admin_flash('','Homepage package order saved');
      header('Location: packages.php');exit;
    }

    if($act==='save'){
      $id=(int)$_POST['id'];
      $name=trim($_POST['name']);
      $slug=admin_slug($_POST['slug']?:$name);
      $cat=(int)($_POST['category_id']??0)?:null;
      $data=[
        $cat,$name,$slug,$_POST['short_description']??'',$_POST['description']??'',
        $_POST['duration']??'',(float)($_POST['price_adult']??0),(float)($_POST['price_child']??0),
        isset($_POST['featured'])?1:0,isset($_POST['best_seller'])?1:0,(int)($_POST['status']??1),((int)($_POST['booking_enabled']??1)===1?1:0),$_POST['seo_title']??'',
        $_POST['meta_description']??'',$_POST['focus_keyword']??'',$_POST['canonical_url']??'',$_POST['og_image']??'',
        $_POST['og_title']??'',$_POST['og_description']??'',$_POST['schema_json']??''
      ];
      $old=null;
      if($id){$q=$p->prepare('SELECT cover_image FROM packages WHERE id=?');$q->execute([$id]);$old=$q->fetchColumn();}
      $oldOg=''; if($id){$q=$p->prepare('SELECT og_image FROM packages WHERE id=?');$q->execute([$id]);$oldOg=(string)($q->fetchColumn()?:'');}
      $ogImage=admin_upload('og_image_upload','packages',$oldOg); if(trim((string)($_POST['og_image']??''))!=='')$ogImage=trim((string)$_POST['og_image']); $data[16]=$ogImage;
      $cover=admin_upload('cover_image','packages',$old);
      $data[]=$cover;
      if($id){
        $st=$p->prepare('UPDATE packages SET category_id=?,name=?,slug=?,short_description=?,description=?,duration=?,price_adult=?,price_child=?,featured=?,best_seller=?,status=?,booking_enabled=?,seo_title=?,meta_description=?,focus_keyword=?,canonical_url=?,og_image=?,og_title=?,og_description=?,schema_json=?,cover_image=?,updated_at=NOW() WHERE id=?');
        $data[]=$id;$st->execute($data);
      }else{
        $st=$p->prepare('INSERT INTO packages(category_id,name,slug,short_description,description,duration,price_adult,price_child,featured,best_seller,status,booking_enabled,seo_title,meta_description,focus_keyword,canonical_url,og_image,og_title,og_description,schema_json,cover_image) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute($data);$id=(int)$p->lastInsertId();
        try {
          $nextOrder=(int)$p->query('SELECT COALESCE(MAX(homepage_order),0)+1 FROM packages')->fetchColumn();
          $p->prepare('UPDATE packages SET homepage_order=? WHERE id=?')->execute([$nextOrder,$id]);
        } catch(Throwable $e) {}
      }

      // Save the activity time options for this package (one HH:MM value per line).
      try {
        $p->prepare('DELETE FROM package_activity_times WHERE package_id=?')->execute([$id]);
        $rawTimes=preg_split('/\r\n|\r|\n/', (string)($_POST['activity_times']??''));
        $timeInsert=$p->prepare('INSERT IGNORE INTO package_activity_times(package_id,activity_time,sort_order) VALUES(?,?,?)');
        $seenTimes=[];$timeOrder=0;
        foreach($rawTimes as $rawTime){$rawTime=trim($rawTime);if($rawTime==='')continue;if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$rawTime))continue;if(isset($seenTimes[$rawTime]))continue;$seenTimes[$rawTime]=true;$timeInsert->execute([$id,$rawTime.':00',$timeOrder++]);}
      } catch(Throwable $e) { throw new RuntimeException('Package saved, but activity times could not be saved. Please check the database permissions.'); }

      // Optional gallery images from the Media Library or direct upload.
      if($id && (admin_media_has_selection('gallery_images') || (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])))){
        $q=$p->prepare('SELECT COALESCE(MAX(sort_order),-1) FROM package_images WHERE package_id=?');
        $q->execute([$id]);$order=(int)$q->fetchColumn()+1;
        $ins=$p->prepare('INSERT INTO package_images(package_id,image_path,alt_text,sort_order) VALUES(?,?,?,?)');

        $mediaGallery=admin_media_selected_multiple('gallery_images');
        $existsImage=$p->prepare('SELECT id FROM package_images WHERE package_id=? AND image_path=? LIMIT 1');
        foreach($mediaGallery as $mediaIndex=>$path){
          $existsImage->execute([$id,$path]);
          if($existsImage->fetchColumn()) continue;
          $alt=trim($_POST['gallery_alt'][$mediaIndex]??'') ?: $name;
          $ins->execute([$id,$path,$alt,$order++]);
        }
        $count=count($_FILES['gallery_images']['name']);
        for($i=0;$i<$count;$i++){
          if(($_FILES['gallery_images']['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
          $_FILES['__gallery_single']=[
            'name'=>$_FILES['gallery_images']['name'][$i],
            'type'=>$_FILES['gallery_images']['type'][$i],
            'tmp_name'=>$_FILES['gallery_images']['tmp_name'][$i],
            'error'=>$_FILES['gallery_images']['error'][$i],
            'size'=>$_FILES['gallery_images']['size'][$i]
          ];
          $path=admin_upload('__gallery_single','packages');
          unset($_FILES['__gallery_single']);
          $existsImage->execute([$id,$path]);
          if($existsImage->fetchColumn()) continue;
          $alt=trim($_POST['gallery_alt'][$i]??'') ?: $name;
          $ins->execute([$id,$path,$alt,$order++]);
        }
        unset($_FILES['__gallery_single']);
      }

      // Save package hero features: four editable icon + text slots.
      // The frontend already reads package_hero_features and falls back to defaults
      // when no custom values have been saved.
      try {
        $p->prepare('DELETE FROM package_hero_features WHERE package_id=?')->execute([$id]);
        $hf=$p->prepare('INSERT INTO package_hero_features(package_id,feature_key,icon,title,subtitle,sort_order) VALUES(?,?,?,?,?,?)');
        $heroKeys=['adventure','guide','photo','safe'];
        $heroIcons=$_POST['hero_feature_icon']??[];
        $heroTitles=$_POST['hero_feature_title']??[];
        $heroSubtitles=$_POST['hero_feature_subtitle']??[];
        if(!is_array($heroIcons)) $heroIcons=[];
        if(!is_array($heroTitles)) $heroTitles=[];
        if(!is_array($heroSubtitles)) $heroSubtitles=[];
        foreach($heroKeys as $i=>$key){
          $icon=trim((string)($heroIcons[$i]??''));
          $title=trim((string)($heroTitles[$i]??''));
          $subtitle=trim((string)($heroSubtitles[$i]??''));
          if($icon==='' && $title==='' && $subtitle==='') continue;
          if($icon==='') $icon=['fa-solid fa-mountain-sun','fa-solid fa-user-tie','fa-solid fa-camera','fa-solid fa-shield-heart'][$i];
          if($title==='') $title=['Iconic Bali','Professional','Photo','Safe &'][$i];
          if($subtitle==='') $subtitle=['Adventure','Guide','Opportunities','Comfortable'][$i];
          $hf->execute([$id,$key,$icon,$title,$subtitle,$i]);
        }
      } catch (Throwable $e) {}

      // Save package benefit strip: four editable icon + text slots.
      try {
        $p->prepare('DELETE FROM package_benefits WHERE package_id=?')->execute([$id]);
        $bf=$p->prepare('INSERT INTO package_benefits(package_id,benefit_key,icon,title,subtitle,sort_order) VALUES(?,?,?,?,?,?)');
        $benefitKeys=['suitable','location','experience','safety'];
        $benefitIcons=$_POST['benefit_icon']??[];
        $benefitTitles=$_POST['benefit_title']??[];
        $benefitSubtitles=$_POST['benefit_subtitle']??[];
        if(!is_array($benefitIcons)) $benefitIcons=[];
        if(!is_array($benefitTitles)) $benefitTitles=[];
        if(!is_array($benefitSubtitles)) $benefitSubtitles=[];
        $defaults=[
          ['fa-solid fa-people-group','Suitable For All','Couples · Families · Solo Travelers'],
          ['fa-solid fa-location-dot','Bali – Bali','Bali, Indonesia'],
          ['fa-solid fa-sparkles','New Experience','Exciting Bali Adventure'],
          ['fa-solid fa-circle-check','Safety','Safety equipment provided'],
        ];
        foreach($benefitKeys as $i=>$key){
          $icon=trim((string)($benefitIcons[$i]??''));
          $title=trim((string)($benefitTitles[$i]??''));
          $subtitle=trim((string)($benefitSubtitles[$i]??''));
          if($icon==='' && $title==='' && $subtitle==='') continue;
          if($icon==='') $icon=$defaults[$i][0];
          if($title==='') $title=$defaults[$i][1];
          if($subtitle==='') $subtitle=$defaults[$i][2];
          $bf->execute([$id,$key,$icon,$title,$subtitle,$i]);
        }
      } catch (Throwable $e) {}

      // Save package highlights, one item per line.
      try {
        $p->prepare('DELETE FROM package_highlights WHERE package_id=?')->execute([$id]);
        $hi=$p->prepare('INSERT INTO package_highlights(package_id,highlight_text,sort_order) VALUES(?,?,?)');
        $highlights=array_values(array_filter(array_map('trim',preg_split('/\R/',$_POST['highlights']??''))));
        foreach(array_slice($highlights,0,10) as $i=>$text){
          if($text!=='') $hi->execute([$id,$text,$i]);
        }
      } catch (Throwable $e) {}

      // Save package Include / Exclude lists, one item per line.
      try {
        $p->prepare('DELETE FROM package_includes WHERE package_id=?')->execute([$id]);
        $ii=$p->prepare('INSERT INTO package_includes(package_id,item_text,sort_order) VALUES(?,?,?)');
        $includes=array_values(array_filter(array_map('trim',preg_split('/\R/',$_POST['includes']??''))));
        foreach(array_slice($includes,0,30) as $i=>$text){
          if($text!=='') $ii->execute([$id,$text,$i]);
        }
        $p->prepare('DELETE FROM package_excludes WHERE package_id=?')->execute([$id]);
        $ee=$p->prepare('INSERT INTO package_excludes(package_id,item_text,sort_order) VALUES(?,?,?)');
        $excludes=array_values(array_filter(array_map('trim',preg_split('/\R/',$_POST['excludes']??''))));
        foreach(array_slice($excludes,0,30) as $i=>$text){
          if($text!=='') $ee->execute([$id,$text,$i]);
        }
      } catch (Throwable $e) {}

      // Save Need For Information FAQ items. Each question is paired with its answer.
      try {
        $p->prepare('DELETE FROM package_faqs WHERE package_id=?')->execute([$id]);
        $fq=$p->prepare('INSERT INTO package_faqs(package_id,question,answer,sort_order) VALUES(?,?,?,?)');
        $questions=$_POST['faq_question']??[];
        $answers=$_POST['faq_answer']??[];
        if(!is_array($questions)) $questions=[];
        if(!is_array($answers)) $answers=[];
        foreach(array_slice($questions,0,20) as $i=>$question){
          $question=trim((string)$question);
          $answer=trim((string)($answers[$i]??''));
          if($question!=='' && $answer!=='') $fq->execute([$id,$question,$answer,$i]);
        }
      } catch (Throwable $e) {}

      // Save price-card original price and editable benefit list.
      try {
        $originalPrice=trim((string)($_POST['original_price']??''));
        $originalPrice=($originalPrice==='') ? null : (float)$originalPrice;
        $p->prepare('INSERT INTO package_price_settings(package_id,original_price) VALUES(?,?) ON DUPLICATE KEY UPDATE original_price=VALUES(original_price)')->execute([$id,$originalPrice]);
        $p->prepare('DELETE FROM package_price_bullets WHERE package_id=?')->execute([$id]);
        $pb=$p->prepare('INSERT INTO package_price_bullets(package_id,item_text,sort_order) VALUES(?,?,?)');
        $bullets=array_values(array_filter(array_map('trim',preg_split('/\R/',$_POST['price_bullets']??''))));
        foreach(array_slice($bullets,0,10) as $i=>$text){
          if($text!=='') $pb->execute([$id,$text,$i]);
        }
      } catch (Throwable $e) {}

      // Save booking-card action buttons.
      // Use SELECT -> UPDATE/INSERT instead of relying only on an upsert,
      // so older databases without the unique index still save correctly.
      try {
        $buttons=[
          'book_now'=>[isset($_POST['button_book_now'])?1:0,'',null],
          'book_now_2'=>[isset($_POST['button_book_now_2'])?1:0,trim((string)($_POST['button_book_now_2_url']??'')),trim((string)($_POST['button_book_now_2_label']??''))],
          'whatsapp_1'=>[isset($_POST['button_whatsapp_1'])?1:0,trim((string)($_POST['button_whatsapp_1_url']??'')),trim((string)($_POST['button_whatsapp_1_label']??''))],
          'whatsapp_2'=>[isset($_POST['button_whatsapp_2'])?1:0,'',null],
        ];

        // Make sure the new label column exists before saving.
        $hasLabel = $p->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='package_action_buttons' AND COLUMN_NAME='label'")->fetchColumn();
        if (!(int)$hasLabel) {
          $p->exec("ALTER TABLE package_action_buttons ADD COLUMN label VARCHAR(100) NULL AFTER custom_url");
        }

        $findButton=$p->prepare('SELECT id FROM package_action_buttons WHERE package_id=? AND button_key=? LIMIT 1');
        $insertButton=$p->prepare('INSERT INTO package_action_buttons(package_id,button_key,enabled,custom_url,label) VALUES(?,?,?,?,?)');
        $updateButton=$p->prepare('UPDATE package_action_buttons SET enabled=?,custom_url=?,label=? WHERE id=?');

        foreach($buttons as $key=>$vals){
          $findButton->execute([$id,$key]);
          $existingId=$findButton->fetchColumn();
          $customUrl=$vals[1]!==''?$vals[1]:null;
          $label=$vals[2]!==''?$vals[2]:null;

          if($existingId){
            $updateButton->execute([$vals[0],$customUrl,$label,(int)$existingId]);
          } else {
            $insertButton->execute([$id,$key,$vals[0],$customUrl,$label]);
          }
        }
      } catch (Throwable $e) {
        // Do not break the rest of the package save if an old DB schema is encountered.
      }

      admin_flash('','Package saved');
      header('Location: packages.php?action=edit&id='.$id);exit;
    }

    if($act==='delete'){
      $q=$p->prepare('SELECT cover_image FROM packages WHERE id=?');$q->execute([(int)$_POST['id']]);
      admin_delete_image($q->fetchColumn());
      $p->prepare('DELETE FROM packages WHERE id=?')->execute([(int)$_POST['id']]);
      admin_flash('','Package deleted');header('Location: packages.php');exit;
    }

    if($act==='image'){
      $pid=(int)$_POST['package_id'];
      $path=admin_upload('image','packages');
      $dup=$p->prepare('SELECT id FROM package_images WHERE package_id=? AND image_path=? LIMIT 1');
      $dup->execute([$pid,$path]);
      if($dup->fetchColumn()){
        admin_flash('','This image is already in this package gallery.');
      }else{
        $st=$p->prepare('INSERT INTO package_images(package_id,image_path,alt_text,sort_order) VALUES(?,?,?,?)');
        $st->execute([$pid,$path,$_POST['alt_text']??'',(int)($_POST['sort_order']??0)]);
        admin_flash('','Gallery image added');
      }
      header('Location: packages.php?action=images&id='.$pid);exit;
    }

    if($act==='delimage'){
      $q=$p->prepare('SELECT image_path,package_id FROM package_images WHERE id=?');$q->execute([(int)$_POST['image_id']]);$r=$q->fetch();
      if($r){
        $p->prepare('DELETE FROM package_images WHERE id=?')->execute([(int)$_POST['image_id']]);
        if(!admin_image_is_referenced($p,(string)$r['image_path'])) admin_delete_image($r['image_path']);
        header('Location: packages.php?action=images&id='.$r['package_id']);exit;
      }
    }
  }
}catch(Throwable $e){
  admin_flash('error',$e->getMessage());
  header('Location: packages.php'.($id?'?action=edit&id='.$id:''));exit;
}

$cats=$p->query('SELECT id,name FROM categories WHERE status=1 ORDER BY name')->fetchAll();
$page_title='Packages';
require __DIR__.'/includes/header.php';

if($action==='edit'){
  $st=$p->prepare('SELECT * FROM packages WHERE id=?');$st->execute([$id]);$item=$st->fetch();
  if(!$item)exit('Package not found');
}

if($action==='images'){
  $st=$p->prepare('SELECT * FROM packages WHERE id=?');$st->execute([$id]);$item=$st->fetch();
  if(!$item)exit('Package not found');
  $imgs=$p->prepare('SELECT * FROM package_images WHERE package_id=? ORDER BY sort_order,id');$imgs->execute([$id]);$imgs=$imgs->fetchAll();
  ?>
  <div class="head"><h2>Gallery: <?=admin_e($item['name'])?></h2><a class="btn gray" href="packages.php?action=edit&id=<?=$id?>">Back to Package</a></div>
  <div class="form">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="form_action" value="image"><input type="hidden" name="package_id" value="<?=$id?>">
      <div class="formgrid">
        <div class="field"><label>Image</label><input required type="file" name="image" accept="image/jpeg,image/png,image/webp"><small class="muted-line">JPG, PNG or WebP. Max 100 MB.</small></div>
        <div class="field"><label>Alt Text</label><input name="alt_text" placeholder="Describe this photo"></div>
        <div class="field"><label>Sort Order</label><input type="number" name="sort_order" value="0"></div>
      </div>
      <div class="actions"><button class="btn">Upload Image</button></div>
    </form>
  </div>
  <div class="section"><div class="tablebox"><table class="table"><tr><th>Preview</th><th>Alt</th><th>Order</th><th></th></tr>
  <?php foreach($imgs as $im):?><tr><td><img class="thumb" src="../<?=admin_e($im['image_path'])?>"></td><td><?=admin_e($im['alt_text'])?></td><td><?=$im['sort_order']?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="form_action" value="delimage"><input type="hidden" name="image_id" value="<?=$im['id']?>"><button class="btn red" data-confirm="Delete image?">Delete</button></form></td></tr><?php endforeach;?>
  </table></div></div>
  <?php require __DIR__.'/includes/footer.php';exit;
}

if($action==='hero'):
  $heroAdmin=[
    'kicker'=>strtoupper(site_brand_name()),
    'title'=>'CHOOSE YOUR',
    'accent'=>'ADVENTURE',
    'text'=>'Discover exciting Bali experiences, from ATV and rafting to swing, Volkswagen tours and unforgettable combination packages.',
    'button_text'=>'Explore Packages',
    'button_url'=>'#adventure-packages',
    'image'=>''
  ];
  try{
    $hq=$p->query("SELECT setting_key,setting_value FROM settings WHERE setting_key LIKE 'packages_hero_%'")->fetchAll();
    foreach($hq as $hr){$heroAdmin[str_replace('packages_hero_','',$hr['setting_key'])]=$hr['setting_value'];}
  }catch(Throwable $e){}
  if(preg_match('/Tarantula|Aloha Ubud/i',(string)($heroAdmin['kicker']??''))) $heroAdmin['kicker']=strtoupper(site_brand_name());
  ?>
  <div class="head">
    <div><h2>Packages Page Hero</h2><p class="admin-page-subtitle">Edit the hero section displayed at the top of packages.php.</p></div>
    <a class="btn gray" href="packages.php">← Back to Packages</a>
  </div>
  <div class="form">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?=admin_csrf()?>">
      <input type="hidden" name="form_action" value="hero">
      <div class="hero-admin-box">
        <div class="hero-admin-grid">
          <div class="field"><label>Kicker</label><input name="package_hero_kicker" value="<?=admin_e($heroAdmin['kicker'])?>"></div>
          <div class="field"><label>Main Title</label><input name="package_hero_title" value="<?=admin_e($heroAdmin['title'])?>"></div>
          <div class="field"><label>Accent Title</label><input name="package_hero_accent" value="<?=admin_e($heroAdmin['accent'])?>"></div>
          <div class="field"><label>Button Text</label><input name="package_hero_button_text" value="<?=admin_e($heroAdmin['button_text'])?>"></div>
          <div class="field full"><label>Button URL</label><input name="package_hero_button_url" value="<?=admin_e($heroAdmin['button_url'])?>"></div>
          <div class="field full"><label>Hero Description</label><textarea name="package_hero_text" rows="4"><?=admin_e($heroAdmin['text'])?></textarea></div>
          <div class="field full">
            <label>Hero Image</label>
            <?php if(!empty($heroAdmin['image'])): ?>
              <img class="hero-admin-preview" src="../<?=admin_e($heroAdmin['image'])?>" alt="">
            <?php else: ?>
              <div class="hero-admin-empty">No custom hero image. The first active package cover image will be used automatically.</div>
            <?php endif; ?>
            <input type="file" name="package_hero_image" accept="image/jpeg,image/png,image/webp">
            <label class="hero-check"><input type="checkbox" name="remove_package_hero_image" value="1"> Remove custom image and use the first active package cover image</label>
            <small class="muted-line">Recommended 1600 × 700 px or larger. JPG, PNG or WebP.</small>
          </div>
        </div>
      </div>
      <div class="actions"><button class="btn">Save Hero</button><a class="btn gray" href="packages.php">Cancel</a></div>
    </form>
  </div>
  <style>
  .admin-page-subtitle{margin:4px 0 0;color:#748079;font-size:12px}
  .hero-admin-box{border:1px solid #dce7df;border-radius:12px;padding:20px;background:linear-gradient(135deg,#f4faf5,#fff)}
  .hero-admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
  .hero-admin-grid .full{grid-column:1/-1}
  .hero-admin-preview{display:block;width:100%;max-width:760px;height:260px;object-fit:cover;border-radius:10px;border:1px solid #dce5df;margin:8px 0 12px}
  .hero-admin-empty{max-width:760px;min-height:130px;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px;box-sizing:border-box;border-radius:10px;background:#eef5ef;color:#68776e;font-size:12px;margin:8px 0 12px}
  .hero-check{font-size:12px!important;font-weight:500!important;margin:8px 0!important}
  @media(max-width:700px){.hero-admin-grid{grid-template-columns:1fr}.hero-admin-grid .full{grid-column:auto}}
  </style>
  <?php require __DIR__.'/includes/footer.php';exit; endif;

if($action==='add'||$action==='edit'):
  $x=$item??[];
  $packageHeroAdmin=[
    'kicker'=>strtoupper(site_brand_name()),
    'title'=>'CHOOSE YOUR',
    'accent'=>'ADVENTURE',
    'text'=>'Discover exciting Bali experiences, from ATV and rafting to swing, Volkswagen tours and unforgettable combination packages.',
    'button_text'=>'Explore Packages',
    'button_url'=>'#adventure-packages',
    'image'=>''
  ];
  try{
    $hq=$p->query("SELECT setting_key,setting_value FROM settings WHERE setting_key LIKE 'packages_hero_%'")->fetchAll();
    foreach($hq as $hr){$packageHeroAdmin[str_replace('packages_hero_','',$hr['setting_key'])]=$hr['setting_value'];}
  }catch(Throwable $e){}
  if(preg_match('/Tarantula|Aloha Ubud/i',(string)($packageHeroAdmin['kicker']??''))) $packageHeroAdmin['kicker']=strtoupper(site_brand_name());

  $editOriginalPrice=null; $editPriceBullets=[];
  $editHeroFeatures=[];
  $heroFeatureDefaultsAdmin=[
    ['key'=>'adventure','icon'=>'fa-solid fa-mountain-sun','title'=>'Iconic Bali','subtitle'=>'Adventure'],
    ['key'=>'guide','icon'=>'fa-solid fa-user-tie','title'=>'Professional','subtitle'=>'Guide'],
    ['key'=>'photo','icon'=>'fa-solid fa-camera','title'=>'Photo','subtitle'=>'Opportunities'],
    ['key'=>'safe','icon'=>'fa-solid fa-shield-heart','title'=>'Safe &','subtitle'=>'Comfortable'],
  ];
  if(!empty($x['id'])){
    try{
      $hfq=$p->prepare('SELECT feature_key,icon,title,subtitle FROM package_hero_features WHERE package_id=? ORDER BY sort_order,id LIMIT 4');
      $hfq->execute([(int)$x['id']]);
      foreach($hfq->fetchAll() as $hfr){ $editHeroFeatures[(string)$hfr['feature_key']]=$hfr; }
    }catch(Throwable $e){}
  }
  foreach($heroFeatureDefaultsAdmin as $hfd){
    if(!isset($editHeroFeatures[$hfd['key']])) $editHeroFeatures[$hfd['key']]=$hfd;
  }
  $editBenefits=[];
  $benefitDefaultsAdmin=[
    ['key'=>'suitable','icon'=>'fa-solid fa-people-group','title'=>'Suitable For All','subtitle'=>'Couples · Families · Solo Travelers'],
    ['key'=>'location','icon'=>'fa-solid fa-location-dot','title'=>'Bali – Bali','subtitle'=>'Bali, Indonesia'],
    ['key'=>'experience','icon'=>'fa-solid fa-sparkles','title'=>'New Experience','subtitle'=>'Exciting Bali Adventure'],
    ['key'=>'safety','icon'=>'fa-solid fa-circle-check','title'=>'Safety','subtitle'=>'Safety equipment provided'],
  ];
  if(!empty($x['id'])){
    try{
      $bfq=$p->prepare('SELECT benefit_key,icon,title,subtitle FROM package_benefits WHERE package_id=? ORDER BY sort_order,id LIMIT 4');
      $bfq->execute([(int)$x['id']]);
      foreach($bfq->fetchAll() as $bfr){ $editBenefits[(string)$bfr['benefit_key']]=$bfr; }
    }catch(Throwable $e){}
  }
  foreach($benefitDefaultsAdmin as $bdf){
    if(!isset($editBenefits[$bdf['key']])) $editBenefits[$bdf['key']]=$bdf;
  }
  $editActionButtons=[
    'book_now'=>['enabled'=>1,'url'=>'','label'=>'Book Now'],
    'book_now_2'=>['enabled'=>0,'url'=>'','label'=>'Book Now 2'],
    'whatsapp_1'=>['enabled'=>0,'url'=>'','label'=>'WhatsApp 1'],
    'whatsapp_2'=>['enabled'=>1,'url'=>'','label'=>'WhatsApp'],
  ];
  if(!empty($x['id'])){
    try{
      $ps=$p->prepare('SELECT original_price FROM package_price_settings WHERE package_id=? LIMIT 1');$ps->execute([(int)$x['id']]);$editOriginalPrice=$ps->fetchColumn();
      $pbq=$p->prepare('SELECT item_text FROM package_price_bullets WHERE package_id=? ORDER BY sort_order,id LIMIT 10');$pbq->execute([(int)$x['id']]);$editPriceBullets=$pbq->fetchAll(PDO::FETCH_COLUMN);
      $abq=$p->prepare('SELECT button_key,enabled,custom_url,label FROM package_action_buttons WHERE package_id=?');$abq->execute([(int)$x['id']]);
      foreach($abq->fetchAll() as $abr){ if(isset($editActionButtons[$abr['button_key']])) $editActionButtons[$abr['button_key']]=['enabled'=>(int)$abr['enabled'],'url'=>(string)$abr['custom_url'],'label'=>(string)($abr['label']??'')]; }
    }catch(Throwable $e){}
  }
  $galleryStmt=$p->prepare('SELECT * FROM package_images WHERE package_id=? ORDER BY sort_order,id');$galleryStmt->execute([(int)($x['id']??0)]);$editGallery=$galleryStmt->fetchAll();
?>
<div class="head"><h2><?=($action==='edit'?'Edit':'Add')?> Package</h2><?php if(!empty($x['id'])):?><a class="btn gray" href="packages.php?action=images&id=<?=$x['id']?>">Manage Gallery</a><?php endif;?></div>
<div class="form">
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="form_action" value="save"><input type="hidden" name="id" value="<?=admin_e($x['id']??0)?>">
<div class="package-page-hero-admin">
  <div class="package-page-hero-head">
    <div><h3>Packages Page Hero</h3><p>Edit the hero shown at the top of <b>packages.php</b>. Changes apply immediately after saving.</p></div>
    <span class="package-page-hero-badge">FRONTEND HERO</span>
  </div>
  <div class="formgrid">
    <div class="field"><label>Kicker</label><input name="package_hero_kicker" value="<?=admin_e($packageHeroAdmin['kicker'])?>"></div>
    <div class="field"><label>Main Title</label><input name="package_hero_title" value="<?=admin_e($packageHeroAdmin['title'])?>"></div>
    <div class="field"><label>Accent Title</label><input name="package_hero_accent" value="<?=admin_e($packageHeroAdmin['accent'])?>"></div>
    <div class="field"><label>Button Text</label><input name="package_hero_button_text" value="<?=admin_e($packageHeroAdmin['button_text'])?>"></div>
    <div class="field"><label>Button URL</label><input name="package_hero_button_url" value="<?=admin_e($packageHeroAdmin['button_url'])?>"></div>
    <div class="field full"><label>Hero Description</label><textarea name="package_hero_text" rows="4"><?=admin_e($packageHeroAdmin['text'])?></textarea></div>
    <div class="field full"><label>Hero Image</label>
      <?php if(!empty($packageHeroAdmin['image'])): ?><img class="package-page-hero-preview" src="../<?=admin_e($packageHeroAdmin['image'])?>" alt="">
      <?php else: ?><div class="package-page-hero-preview empty">Default: first active package cover image</div><?php endif; ?>
      <input type="file" name="package_hero_image" accept="image/jpeg,image/png,image/webp">
      <div class="hero-remove-line"><input type="checkbox" name="remove_package_hero_image" value="1"> Remove custom hero image and use the first active package cover image</div>
      <small class="muted-line">Recommended: landscape image, at least 1600 × 700 px. JPG, PNG or WebP.</small>
    </div>
  </div>
</div>
<div class="formgrid">
<div class="field"><label>Name</label><input required name="name" value="<?=admin_e($x['name']??'')?>"></div>
<div class="field"><label>Slug</label><input name="slug" value="<?=admin_e($x['slug']??'')?>"></div>
<div class="field"><label>Category</label><select name="category_id"><option value="">No category</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=($x['category_id']??'')==$c['id']?'selected':''?>><?=admin_e($c['name'])?></option><?php endforeach;?></select></div>
<div class="field"><label>Duration</label><input name="duration" value="<?=admin_e($x['duration']??'')?>"></div>
<div class="field full"><label>Activity Start Times</label><textarea name="activity_times" rows="5" placeholder="08:00&#10;09:00&#10;10:00"><?php if(!empty($x['id'])){try{$atq=$p->prepare('SELECT TIME_FORMAT(activity_time, "%H:%i") FROM package_activity_times WHERE package_id=? ORDER BY sort_order,activity_time');$atq->execute([(int)$x['id']]);echo admin_e(implode("\n",$atq->fetchAll(PDO::FETCH_COLUMN)));}catch(Throwable $e){}}?></textarea><small class="muted-line">One start time per line in 24-hour format (for example 08:00, 09:00, 10:00). Customers will select one when booking. Leave empty if this activity has no fixed start time.</small></div>
<div class="field"><label>Adult Price</label><input type="number" step="0.01" name="price_adult" value="<?=admin_e($x['price_adult']??0)?>"></div>
<div class="field"><label>Child Price</label><input type="number" step="0.01" name="price_child" value="<?=admin_e($x['price_child']??0)?>"></div>
<div class="field"><label>Original Price / Before Discount</label><input type="number" step="0.01" min="0" name="original_price" value="<?=admin_e($editOriginalPrice??'')?>" placeholder="e.g. 250000"><small class="muted-line">Shown crossed out above the current adult price. Leave empty to hide it.</small></div>
<div class="field full package-price-card-admin-field">
<label>Price Card List</label>
<textarea name="price_bullets" rows="6" placeholder="One item per line"><?php
if($editPriceBullets){echo admin_e(implode("\n",$editPriceBullets));}
else{echo admin_e(implode("\n",['Without Transport',($x['category_name']??'Adventure').' Package',($x['duration']??'2 Hours'),'Easy online booking']));}
?></textarea>
<small class="muted-line">One item per line. Maximum 10 items. These appear under the price in the booking card.</small>
</div>
<div class="field full package-book-actions-admin">
  <label>Booking Buttons</label>
  <div class="package-book-actions-grid">
    <div class="package-action-admin-item">
      <label><input type="checkbox" name="button_book_now" value="1" <?=!empty($editActionButtons['book_now']['enabled'])?'checked':''?>> Book Now <span class="action-default">Label locked</span></label>
      <small>Uses the existing package booking link. Do not change the URL.</small>
    </div>
    <div class="package-action-admin-item">
      <label><input type="checkbox" name="button_book_now_2" value="1" <?=!empty($editActionButtons['book_now_2']['enabled'])?'checked':''?>> Book Now 2</label>
      <input type="text" name="button_book_now_2_label" value="<?=admin_e($editActionButtons['book_now_2']['label']?:'Book Now 2')?>" placeholder="Button label">
      <input type="url" name="button_book_now_2_url" value="<?=admin_e($editActionButtons['book_now_2']['url'])?>" placeholder="https://example.com/your-link">
      <small>Custom label + custom link.</small>
    </div>
    <div class="package-action-admin-item">
      <label><input type="checkbox" name="button_whatsapp_1" value="1" <?=!empty($editActionButtons['whatsapp_1']['enabled'])?'checked':''?>> WhatsApp 1</label>
      <input type="text" name="button_whatsapp_1_label" value="<?=admin_e($editActionButtons['whatsapp_1']['label']?:'WhatsApp 1')?>" placeholder="Button label">
      <input type="url" name="button_whatsapp_1_url" value="<?=admin_e($editActionButtons['whatsapp_1']['url'])?>" placeholder="https://wa.me/628xxxxxxxxxx">
      <small>Custom label + custom WhatsApp link.</small>
    </div>
    <div class="package-action-admin-item">
      <label><input type="checkbox" name="button_whatsapp_2" value="1" <?=!empty($editActionButtons['whatsapp_2']['enabled'])?'checked':''?>> WhatsApp <span class="action-default">Label locked</span></label>
      <small>Uses the existing site WhatsApp link and package message.</small>
    </div>
  </div>
  <small class="muted-line">Display order on the package booking card: Book Now → Book Now 2 → WhatsApp 1 → WhatsApp 2.</small>
</div>
<div class="field"><label>Status</label><select name="status"><option value="1" <?=($x['status']??1)?'selected':''?>>Active</option><option value="0" <?=isset($x['status'])&&!$x['status']?'selected':''?>>Hidden</option></select></div>
<div class="field"><label>Show in Booking Activity</label><div style="display:flex;gap:16px;align-items:center;min-height:42px"><label style="margin:0"><input type="radio" name="booking_enabled" value="1" <?=(!isset($x['booking_enabled']) || (int)$x['booking_enabled']===1)?'checked':''?>> Yes</label><label style="margin:0"><input type="radio" name="booking_enabled" value="0" <?=isset($x['booking_enabled']) && (int)$x['booking_enabled']===0?'checked':''?>> No</label></div><small class="muted-line">Yes = show this package in the Booking → Activity list. No = hide it from the list.</small></div>
<div class="field"><label><input type="checkbox" name="featured" <?=($x['featured']??0)?'checked':''?>> Featured</label></div>
<div class="field"><label style="display:flex;align-items:center;gap:8px">
  <input type="checkbox" name="best_seller" <?=($x['best_seller']??0)?'checked':''?>>
  <span><strong>Best Seller</strong><small class="muted-line" style="display:block">Show the Best Seller badge and orange border on public package cards.</small></span>
</label></div>
<div class="field full"><label>Short Description</label><textarea name="short_description"><?=admin_e($x['short_description']??'')?></textarea></div>
<div class="field full"><label>Description</label><textarea name="description"><?=admin_e($x['description']??'')?></textarea></div>

<div class="field full package-hero-features-admin-field">
  <div class="package-hero-features-admin-head">
    <div><label>Package Hero Features</label><small class="muted-line">Edit the 4 icon + text items shown directly on the package hero. Icon uses a Font Awesome class, for example <code>fa-solid fa-camera</code>.</small></div>
  </div>
  <div class="package-hero-features-admin-grid">
    <?php foreach($heroFeatureDefaultsAdmin as $hfIndex=>$hfd): $hfv=$editHeroFeatures[$hfd['key']]; ?>
      <div class="package-hero-feature-admin-item">
        <div class="package-hero-feature-admin-title"><strong><?=($hfIndex+1)?>. <?=admin_e(ucfirst($hfd['key']))?></strong><span class="hero-icon-preview"><i class="<?=admin_e($hfv['icon'])?>"></i></span></div>
        <label>Icon Class</label>
        <input type="text" name="hero_feature_icon[]" value="<?=admin_e($hfv['icon'])?>" placeholder="fa-solid fa-camera">
        <label>Title</label>
        <input type="text" name="hero_feature_title[]" value="<?=admin_e($hfv['title'])?>" placeholder="Photo">
        <label>Subtitle</label>
        <input type="text" name="hero_feature_subtitle[]" value="<?=admin_e($hfv['subtitle'])?>" placeholder="Opportunities">
      </div>
    <?php endforeach; ?>
  </div>
  <small class="muted-line">Perubahan akan tampil pada hero halaman package setelah klik Save Package. Tidak mengubah hero Packages page.</small>
</div>

<div class="field full package-benefits-admin-field">
  <div class="package-benefits-admin-head">
    <div><label>Package Benefit Strip</label><small class="muted-line">Edit the 4 items shown below the package description: icon, title and subtitle. Icon uses a Font Awesome class, for example <code>fa-solid fa-camera</code>.</small></div>
  </div>
  <div class="package-benefits-admin-grid">
    <?php foreach($benefitDefaultsAdmin as $bfIndex=>$bdf): $bfv=$editBenefits[$bdf['key']]; ?>
      <div class="package-benefit-admin-item">
        <div class="package-benefit-admin-title"><strong><?=($bfIndex+1)?>. <?=admin_e(ucfirst($bdf['key']))?></strong><span class="benefit-icon-preview"><i class="<?=admin_e($bfv['icon'])?>"></i></span></div>
        <label>Icon Class</label>
        <input type="text" name="benefit_icon[]" value="<?=admin_e($bfv['icon'])?>" placeholder="fa-solid fa-location-dot">
        <label>Title</label>
        <input type="text" name="benefit_title[]" value="<?=admin_e($bfv['title'])?>" placeholder="Suitable For All">
        <label>Subtitle</label>
        <input type="text" name="benefit_subtitle[]" value="<?=admin_e($bfv['subtitle'])?>" placeholder="Couples · Families · Solo Travelers">
      </div>
    <?php endforeach; ?>
  </div>
  <small class="muted-line">Perubahan akan tampil pada strip benefit di halaman detail package setelah klik Save Package.</small>
</div>

<div class="field"><label>Cover Image</label><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"><?php if(!empty($x['cover_image'])):?><img class="thumb" src="../<?=admin_e($x['cover_image'])?>"><?php endif;?></div>

<div class="field full package-gallery-admin-field">
  <label>Package Gallery</label>
  <div class="gallery-admin-help">Add multiple photos here. The first photo shown in the package page is the Cover Image, followed by these gallery photos. They automatically become the large image + clickable thumbnails.</div>
  <input type="file" name="gallery_images[]" accept="image/jpeg,image/png,image/webp" multiple id="packageGalleryFiles">
  <div id="packageGalleryPreview" class="package-gallery-admin-preview"></div>
  <?php if($editGallery): ?>
    <div class="package-gallery-admin-existing">
      <?php foreach($editGallery as $gi): ?>
        <div class="package-gallery-admin-item">
          <img src="../<?=admin_e($gi['image_path'])?>" alt="<?=admin_e($gi['alt_text'])?>">
          <div><strong>#<?=((int)$gi['sort_order']+1)?></strong><span><?=admin_e($gi['alt_text']?:'Gallery photo')?></span></div>
          <a class="btn red" href="packages.php?action=images&id=<?=$x['id']?>">Manage / Delete</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if(!empty($x['id'])):?><small class="muted-line">Existing photos: <?=count($editGallery)?>. Use “Manage / Delete” to remove or change their order.</small><?php endif;?>
</div>

<div class="field full package-highlights-admin-field">
<label>Highlights</label>
<textarea name="highlights" rows="6" placeholder="One highlight per line"><?php if(!empty($x['id'])){try{$hq=$p->prepare('SELECT highlight_text FROM package_highlights WHERE package_id=? ORDER BY sort_order,id');$hq->execute([(int)$x['id']]);echo admin_e(implode("\n",$hq->fetchAll(PDO::FETCH_COLUMN)));}catch(Throwable $e){}} ?></textarea>
<small class="muted-line">Add one highlight per line. Maximum 10 items. These appear in the Highlight section on package.php.</small>
</div>

<div class="field full package-content-admin-field">
<label>Include</label>
<textarea name="includes" rows="7" placeholder="One included item per line"><?php if(!empty($x['id'])){try{$iq=$p->prepare('SELECT item_text FROM package_includes WHERE package_id=? ORDER BY sort_order,id');$iq->execute([(int)$x['id']]);echo admin_e(implode("\n",$iq->fetchAll(PDO::FETCH_COLUMN)));}catch(Throwable $e){}} ?></textarea>
<small class="muted-line">Add one included item per line. Maximum 30 items.</small>
</div>

<div class="field full package-content-admin-field exclude-admin">
<label>Exclude</label>
<textarea name="excludes" rows="7" placeholder="One excluded item per line"><?php if(!empty($x['id'])){try{$eq=$p->prepare('SELECT item_text FROM package_excludes WHERE package_id=? ORDER BY sort_order,id');$eq->execute([(int)$x['id']]);echo admin_e(implode("\n",$eq->fetchAll(PDO::FETCH_COLUMN)));}catch(Throwable $e){}} ?></textarea>
<small class="muted-line">Add one excluded item per line. Maximum 30 items.</small>
</div>

<?php
$editFaqs=[];
if(!empty($x['id'])){
  try{
    $fq=$p->prepare('SELECT question,answer FROM package_faqs WHERE package_id=? ORDER BY sort_order,id');
    $fq->execute([(int)$x['id']]);
    $editFaqs=$fq->fetchAll();
  }catch(Throwable $e){}
}
?>
<div class="field full package-faq-admin-field">
  <div class="package-faq-admin-head"><div><label>Need For Information (FAQ)</label><small class="muted-line">Create expandable FAQ/dropdown items for this package. Maximum 20 items.</small></div><button type="button" class="btn gray" id="addFaqBtn">+ Add FAQ</button></div>
  <div id="faqAdminList">
    <?php if($editFaqs): foreach($editFaqs as $fi=>$faq): ?>
      <div class="faq-admin-item">
        <div class="faq-admin-top"><strong>FAQ #<?=($fi+1)?></strong><button type="button" class="btn red faq-remove">Remove</button></div>
        <label>Question</label><input name="faq_question[]" value="<?=admin_e($faq['question'])?>" placeholder="e.g. Is safety equipment provided?">
        <label>Answer</label><textarea name="faq_answer[]" rows="4" placeholder="Write the answer shown when visitors open this FAQ..."><?=admin_e($faq['answer'])?></textarea>
      </div>
    <?php endforeach; else: ?>
      <div class="faq-admin-item">
        <div class="faq-admin-top"><strong>FAQ #1</strong><button type="button" class="btn red faq-remove">Remove</button></div>
        <label>Question</label><input name="faq_question[]" placeholder="e.g. Is safety equipment provided?">
        <label>Answer</label><textarea name="faq_answer[]" rows="4" placeholder="Write the answer shown when visitors open this FAQ..."></textarea>
      </div>
    <?php endif; ?>
  </div>
  <small class="muted-line">Only FAQ items with both a question and an answer are saved. The first item stays closed until a visitor clicks it.</small>
</div>

<div class="field"><label>SEO Title</label><input id="pkgSeoTitle" name="seo_title" maxlength="255" value="<?=admin_e($x['seo_title']??'')?>"><span class="seo-meter">Recommended ≤ 60 chars</span></div>
<div class="field full"><label>Meta Description</label><textarea id="pkgMetaDescription" name="meta_description" maxlength="320"><?=admin_e($x['meta_description']??'')?></textarea><span class="seo-meter">Recommended 140–160 chars</span></div>
<div class="field"><label>Focus Keyword</label><input id="pkgFocusKeyword" name="focus_keyword" value="<?=admin_e($x['focus_keyword']??'')?>"></div>
<div class="field"><label>Canonical URL</label><input id="pkgCanonical" name="canonical_url" value="<?=admin_e($x['canonical_url']??'')?>" placeholder="https://manuabacrystalfall.com/package/slug"></div>
<div class="field"><label>OG Title</label><input id="pkgOgTitle" name="og_title" value="<?=admin_e($x['og_title']??'')?>"></div>
<div class="field"><label>OG Description</label><textarea id="pkgOgDescription" name="og_description"><?=admin_e($x['og_description']??'')?></textarea></div>
<div class="field full"><label>Open Graph Image URL / Path</label><input id="pkgOgImage" name="og_image" value="<?=admin_e($x['og_image']??'')?>" placeholder="uploads/packages/social-image.webp or https://..."><small class="muted-line">Leave blank to use the cover image.</small></div>
<div class="field"><label>Upload Open Graph Image</label><input id="pkgOgUpload" type="file" name="og_image_upload" accept="image/jpeg,image/png,image/webp,image/gif"><small class="muted-line">Recommended landscape 1200 × 630 px.</small><?php if(!empty($x['og_image'])):?><img class="thumb" src="../<?=admin_e($x['og_image'])?>" alt="Current OG image"><?php endif;?></div>
<div class="field full"><label>Schema JSON-LD</label><textarea id="pkgSchema" name="schema_json"><?=admin_e($x['schema_json']??'')?></textarea></div>
<?php
$pkgSeoTitle0=trim((string)($x['seo_title']??'')); $pkgSeoDesc0=trim((string)($x['meta_description']??'')); $pkgSeoKeyword0=trim((string)($x['focus_keyword']??'')); $pkgSeoCanonical0=trim((string)($x['canonical_url']??'')); $pkgSeoOgTitle0=trim((string)($x['og_title']??'')); $pkgSeoOgDesc0=trim((string)($x['og_description']??'')); $pkgSeoOgImage0=trim((string)($x['og_image']??'')); $pkgSeoSchema0=trim((string)($x['schema_json']??''));
$pkgChecks0=[]; $ptl=strlen($pkgSeoTitle0); $pdl=strlen($pkgSeoDesc0); $pkw=mb_strtolower($pkgSeoKeyword0,'UTF-8');
$pkgChecks0[]=[($ptl>=30&&$ptl<=60?'good':(($ptl>=20&&$ptl<=70)?'warn':'bad')),'SEO title',$ptl.' karakter. Target 30–60 karakter; topik utama sebaiknya di awal.'];
$pkgChecks0[]=[$pdl>=120&&$pdl<=160?'good':(($pdl>=70&&$pdl<=200)?'warn':'bad'),'Meta description',$pdl.' karakter. Target sekitar 120–160 karakter.'];
$pkgChecks0[]=[$pkw!==''?(mb_stripos($pkgSeoTitle0,$pkgSeoKeyword0,0,'UTF-8')!==false?'good':'bad'):'warn','Focus keyword di title',$pkw===''?'Isi focus keyword untuk analisis.':(mb_stripos($pkgSeoTitle0,$pkgSeoKeyword0,0,'UTF-8')!==false?'Keyword ditemukan di SEO title.':'Keyword belum ditemukan di SEO title.')];
$pkgChecks0[]=[$pkw!==''?(mb_stripos($pkgSeoDesc0,$pkgSeoKeyword0,0,'UTF-8')!==false?'good':'warn'):'warn','Focus keyword di description',$pkw===''?'Focus keyword belum diisi.':(mb_stripos($pkgSeoDesc0,$pkgSeoKeyword0,0,'UTF-8')!==false?'Keyword ditemukan di meta description.':'Sebaiknya masukkan keyword secara alami ke description.')];
$pkgChecks0[]=[$pkgSeoCanonical0!==''&&preg_match('~^https?://~i',$pkgSeoCanonical0)?'good':'bad','Canonical URL',$pkgSeoCanonical0!==''?'Canonical URL terisi. Pastikan mengarah ke URL paket yang benar.':'Canonical URL kosong atau tidak valid.'];
$pkgChecks0[]=[($pkgSeoOgTitle0!==''||$pkgSeoTitle0!=='')?'good':'bad','Open Graph title',($pkgSeoOgTitle0!==''||$pkgSeoTitle0!=='')?'Judul social sharing tersedia.':'Tambahkan OG title atau SEO title.'];
$pkgChecks0[]=[($pkgSeoOgDesc0!==''||$pkgSeoDesc0!=='')?'good':'warn','Open Graph description',($pkgSeoOgDesc0!==''||$pkgSeoDesc0!=='')?'Deskripsi social sharing tersedia.':'Tambahkan OG description atau meta description.'];
$pkgChecks0[]=[($pkgSeoOgImage0!==''||trim((string)($x['cover_image']??''))!=='')?'good':'warn','Open Graph image',($pkgSeoOgImage0!==''?'Gambar OG khusus tersedia.':(trim((string)($x['cover_image']??''))!==''?'Gambar cover paket akan digunakan sebagai fallback.':'Belum ada gambar OG atau cover paket. Tambahkan gambar landscape 1200×630 px.'))];
$pkgChecks0[]=[($pkgSeoSchema0!=='')?(function_exists('seo_json_is_valid')?(seo_json_is_valid($pkgSeoSchema0)?'good':'bad'):(json_decode($pkgSeoSchema0)!==null?'good':'bad')):'warn','Schema JSON-LD',$pkgSeoSchema0!==''?'JSON schema lolos pemeriksaan sintaks.':'Schema custom kosong; pastikan schema default website tersedia.'];
$pkgGood0=count(array_filter($pkgChecks0,fn($v)=>$v[0]==='good')); $pkgBad0=count(array_filter($pkgChecks0,fn($v)=>$v[0]==='bad')); $pkgWarn0=count($pkgChecks0)-$pkgGood0-$pkgBad0; $pkgScoreClass0=$pkgBad0?'bad':($pkgWarn0?'warn':'good');
?>
<div class="field full" style="border:1px solid #dce8df;border-radius:12px;padding:16px;background:#f8fcf9"><strong>Package SEO Analysis</strong><span id="pkgSeoScore" class="pkg-seo-score <?=admin_e($pkgScoreClass0)?>" style="float:right;font-weight:800;padding:6px 10px;border-radius:20px"><?=$pkgGood0?> baik · <?=$pkgWarn0?> perlu ditingkatkan · <?=$pkgBad0?> masalah</span><div id="pkgSeoChecks" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;clear:both;padding-top:12px"><?php foreach($pkgChecks0 as $pc): ?><div class="pkg-seo-check <?=admin_e($pc[0])?>"><span class="pkg-seo-dot"></span><div><strong><?=admin_e($pc[1])?></strong><div class="pkg-seo-detail"><?=admin_e($pc[2])?></div></div></div><?php endforeach; ?></div><div style="margin-top:14px;background:#fff;border:1px solid #dfe7e1;border-radius:10px;padding:12px"><strong>Search & Social Preview</strong><div id="pkgSeoPreviewTitle" style="color:#1a5bda;font-size:18px;margin-top:8px"><?=admin_e($pkgSeoTitle0?:($x['name']??'Package SEO title'))?></div><div id="pkgSeoPreviewUrl" style="font-size:11px;color:#36764d;margin-top:3px"><?=admin_e($pkgSeoCanonical0?:'https://manuabacrystalfall.com/')?></div><div id="pkgSeoPreviewDesc" style="font-size:12px;color:#5b665f;margin-top:5px"><?=admin_e($pkgSeoDesc0?:'Meta description preview')?></div><img id="pkgSeoPreviewImage" alt="OG preview" src="<?=admin_e((function($v){$v=trim((string)$v);if($v===''||preg_match('~^https?://~i',$v))return $v;return '../'.ltrim($v,'/');})($pkgSeoOgImage0?:($x['cover_image']??'')))?>" style="<?=($pkgSeoOgImage0||!empty($x['cover_image']))?'display:block;':'display:none;'?>width:min(100%,480px);max-height:220px;object-fit:cover;border-radius:8px;margin-top:10px"></div><div id="pkgSeoNote" style="font-size:11px;color:#68756c;margin-top:10px">Hasil awal dihitung di server, lalu diperbarui langsung saat Anda mengubah isian.</div></div>
</div>
<div class="actions"><button class="btn">Save Package</button><a class="btn gray" href="packages.php">Cancel</a></div>
</form>
</div>
<style>
.pkg-seo-score.good{background:#dcfce7;color:#166534}.pkg-seo-score.warn{background:#fef3c7;color:#92400e}.pkg-seo-score.bad{background:#fee2e2;color:#991b1b}.pkg-seo-check{display:flex;gap:9px;align-items:flex-start;padding:10px;border:1px solid #e4eae6;background:#fff;border-radius:9px;min-width:0;font-size:12px}.pkg-seo-check.good{border-color:#bbf7d0}.pkg-seo-check.warn{border-color:#fde68a}.pkg-seo-check.bad{border-color:#fecaca}.pkg-seo-dot{width:10px;height:10px;border-radius:50%;flex:0 0 10px;margin-top:4px;background:#9ca3af}.pkg-seo-check.good .pkg-seo-dot{background:#16a34a}.pkg-seo-check.warn .pkg-seo-dot{background:#d97706}.pkg-seo-check.bad .pkg-seo-dot{background:#dc2626}.pkg-seo-detail{font-size:11px;line-height:1.5;color:#68756c;margin-top:3px;overflow-wrap:anywhere}
.package-benefits-admin-field{grid-column:1/-1;border:1px solid #dce7df;border-radius:12px;padding:16px;background:#f7fbf8}.package-benefits-admin-head{margin-bottom:12px}.package-benefits-admin-head label{display:block;font-weight:800;color:#174d36;margin-bottom:3px}.package-benefits-admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.package-benefit-admin-item{border:1px solid #dfe7e1;border-radius:9px;padding:13px;background:#fff}.package-benefit-admin-title{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:9px}.package-benefit-admin-title strong{font-size:12px;color:#236a49}.benefit-icon-preview{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;background:#edf6ef;color:#28724e;border:1px solid #d4e7d9}.package-benefit-admin-item label{display:block;font-size:11px;font-weight:700;color:#58635d;margin:8px 0 4px}.package-benefit-admin-item input{width:100%;box-sizing:border-box}.package-benefit-admin-item input[type=text]{min-height:38px}@media(max-width:700px){.package-benefits-admin-grid{grid-template-columns:1fr}}
.package-page-hero-admin{grid-column:1/-1;border:1px solid #dce7df;border-radius:12px;padding:18px;background:linear-gradient(135deg,#f4faf5,#fff);margin-bottom:2px}
.package-hero-features-admin-field{grid-column:1/-1;border:1px solid #dce7df;border-radius:12px;padding:18px;background:#fff;margin-top:2px}
.package-hero-features-admin-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px}
.package-hero-features-admin-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.package-hero-feature-admin-item{border:1px solid #e1e8e3;border-radius:10px;background:#f8fbf9;padding:13px}
.package-hero-feature-admin-item label{display:block;font-size:11px;font-weight:700;color:#4f5c54;margin:10px 0 5px}
.package-hero-feature-admin-item input{width:100%;box-sizing:border-box;border:1px solid #d7e0da;border-radius:7px;padding:9px 10px;background:#fff;color:#18221d;font-size:12px}
.package-hero-feature-admin-title{display:flex;align-items:center;justify-content:space-between;gap:8px;color:#1d6847;font-size:12px}
.hero-icon-preview{width:30px;height:30px;border:1px solid #a8c93b;color:#72a91f;border-radius:50%;display:grid;place-items:center;background:#fff;flex:0 0 30px}
@media(max-width:1000px){.package-hero-features-admin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.package-hero-features-admin-grid{grid-template-columns:1fr}}
.package-page-hero-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px}
.package-page-hero-head h3{margin:0 0 4px;font-size:17px;color:#174d36}
.package-page-hero-head p{margin:0;color:#718078;font-size:12px;line-height:1.5}
.package-page-hero-badge{font-size:9px;font-weight:800;letter-spacing:1px;color:#fff;background:#246447;padding:7px 9px;border-radius:5px;white-space:nowrap}
.package-page-hero-preview{display:block;width:100%;max-width:520px;height:190px;object-fit:cover;border-radius:9px;margin:8px 0 10px;border:1px solid #dfe6e1}
.package-page-hero-preview.empty{display:flex;align-items:center;justify-content:center;background:#eef5ef;color:#718078;font-size:12px}
.hero-remove-line{font-size:11px;color:#68736d;margin:8px 0}
@media(max-width:600px){.package-page-hero-head{flex-direction:column}.package-page-hero-badge{align-self:flex-start}}
.package-book-actions-admin{grid-column:1/-1;border:1px solid #dce7df;border-radius:12px;padding:16px;background:#fbfdfb}.package-book-actions-admin>label{display:block;font-weight:800;color:#174d36;margin-bottom:10px}.package-book-actions-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.package-action-admin-item{border:1px solid #e2e9e4;border-radius:9px;padding:12px;background:#fff}.package-action-admin-item>label{display:block;font-size:13px;font-weight:800;color:#25352d;margin-bottom:7px}.package-action-admin-item input[type=url]{width:100%;box-sizing:border-box}.package-action-admin-item small{display:block;color:#718078;font-size:11px;line-height:1.45;margin-top:6px}.action-default{font-size:9px;color:#2d7a52;background:#e8f5ec;border-radius:4px;padding:3px 5px;margin-left:4px}@media(max-width:700px){.package-book-actions-grid{grid-template-columns:1fr}}
</style>
<style>
.head-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.package-price-card-admin-field{border:1px solid #dce7df;border-radius:10px;padding:16px;background:#f5faf6}.package-price-card-admin-field textarea{min-height:120px}
.package-highlights-admin-field,.package-content-admin-field{border:1px solid #e4e9e6;border-radius:10px;padding:16px;background:#fffaf7}.package-highlights-admin-field textarea,.package-content-admin-field textarea{min-height:140px}.package-content-admin-field{background:#fbfcfb}.package-content-admin-field.exclude-admin{background:#fff9f8}
.package-gallery-admin-field{border:1px solid #e4e9e6;border-radius:10px;padding:16px;background:#fbfcfb}
.package-gallery-admin-help{font-size:13px;color:#6f7973;line-height:1.55;margin:-3px 0 10px}
.package-gallery-admin-preview{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}
.package-gallery-admin-preview .preview-item{width:120px;border:1px solid #e1e6e2;border-radius:8px;overflow:hidden;background:#fff}
.package-gallery-admin-preview img{display:block;width:100%;height:78px;object-fit:cover}
.package-gallery-admin-preview span{display:block;padding:6px;font-size:11px;color:#5e6761;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.package-gallery-admin-existing{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:15px}
.package-gallery-admin-item{border:1px solid #e2e7e4;border-radius:8px;overflow:hidden;background:#fff}
.package-gallery-admin-item img{width:100%;height:105px;object-fit:cover;display:block}
.package-gallery-admin-item>div{padding:7px 8px;display:flex;flex-direction:column;gap:2px}
.package-gallery-admin-item strong{font-size:11px;color:#1d6847}.package-gallery-admin-item span{font-size:11px;color:#707a74;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.package-gallery-admin-item .btn{display:block;margin:0 8px 8px;text-align:center;font-size:11px;padding:6px}
@media(max-width:900px){.package-gallery-admin-existing{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:600px){.package-gallery-admin-existing{grid-template-columns:repeat(2,minmax(0,1fr))}}
.package-faq-admin-field{border:1px solid #e4e9e6;border-radius:10px;padding:16px;background:#f8fbf9;grid-column:1/-1}
.package-faq-admin-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:12px}.package-faq-admin-head label{display:block;margin-bottom:2px}.package-faq-admin-head .muted-line{display:block}
.faq-admin-item{border:1px solid #dfe6e1;background:#fff;border-radius:9px;padding:14px;margin-bottom:12px}.faq-admin-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.faq-admin-top strong{font-size:13px;color:#236a49}.faq-admin-item label{display:block;font-size:12px;font-weight:700;margin:9px 0 5px}.faq-admin-item input,.faq-admin-item textarea{width:100%;box-sizing:border-box}.faq-admin-item textarea{min-height:90px;resize:vertical}.faq-admin-item .faq-remove{font-size:11px;padding:5px 9px}
@media(max-width:600px){.package-faq-admin-head{flex-direction:column}.package-faq-admin-head .btn{width:100%}}
</style>
<script>
(function(){
 const list=document.getElementById('faqAdminList'),add=document.getElementById('addFaqBtn');
 if(list&&add){
   function renumber(){Array.from(list.querySelectorAll('.faq-admin-item')).forEach((el,i)=>{const n=el.querySelector('.faq-admin-top strong');if(n)n.textContent='FAQ #'+(i+1);});}
   function bindRemove(btn){btn.addEventListener('click',function(){const items=list.querySelectorAll('.faq-admin-item');if(items.length<=1){const q=list.querySelector('input[name="faq_question[]"]');const a=list.querySelector('textarea[name="faq_answer[]"]');if(q)q.value='';if(a)a.value='';return;}btn.closest('.faq-admin-item').remove();renumber();});}
   list.querySelectorAll('.faq-remove').forEach(bindRemove);
   add.addEventListener('click',function(){
     const count=list.querySelectorAll('.faq-admin-item').length;if(count>=20){alert('Maximum 20 FAQ items.');return;}
     const item=document.createElement('div');item.className='faq-admin-item';
     item.innerHTML='<div class="faq-admin-top"><strong>FAQ #'+(count+1)+'</strong><button type="button" class="btn red faq-remove">Remove</button></div><label>Question</label><input name="faq_question[]" placeholder="e.g. Is safety equipment provided?"><label>Answer</label><textarea name="faq_answer[]" rows="4" placeholder="Write the answer shown when visitors open this FAQ..."></textarea>';
     list.appendChild(item);bindRemove(item.querySelector('.faq-remove'));item.querySelector('input').focus();
   });
 }
})();
(function(){
 const input=document.getElementById('packageGalleryFiles'),preview=document.getElementById('packageGalleryPreview');
 if(!input||!preview)return;
 input.addEventListener('change',function(){
   preview.innerHTML='';
   Array.from(input.files||[]).forEach(function(file){
     if(!file.type.startsWith('image/'))return;
     const item=document.createElement('div');item.className='preview-item';
     const img=document.createElement('img');const name=document.createElement('span');name.textContent=file.name;
     item.appendChild(img);item.appendChild(name);preview.appendChild(item);
     const reader=new FileReader();reader.onload=e=>img.src=e.target.result;reader.readAsDataURL(file);
   });
 });
})();
</script>
<?php else:?>
<div class="head"><h2>All Packages</h2><div class="head-actions"><a class="btn gray" href="?action=hero">✦ Edit Page Hero</a><a class="btn" href="?action=add">+ Add Package</a></div></div>
<div class="tablebox">
<?php if($homepageOrderReady): ?>
<form method="post" id="homepagePackageOrderForm">
<input type="hidden" name="csrf" value="<?=admin_csrf()?>">
<input type="hidden" name="form_action" value="reorder">
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid #edf1ee;background:#f8faf9">
  <div><strong>Homepage Package Order</strong><div style="font-size:12px;color:#718078;margin-top:3px">Drag packages to arrange the order shown on the homepage. Position 1 appears first.</div></div>
  <button class="btn" type="submit">Save Order</button>
</div>
</form>
<?php else: ?>
<div class="alert error">Manual homepage ordering is unavailable because the database could not add the <code>homepage_order</code> column. The rest of Packages remains available.</div>
<?php endif; ?>
<table class="table" id="homepagePackageOrderTable"><tr><th style="width:70px">Order</th><th>Package</th><th>Category</th><th>Adult</th><th>Best Seller</th><th>Status</th><th>Actions</th></tr>
<?php
  $packageOrderSql = $homepageOrderReady
    ? 'SELECT p.*,c.name category FROM packages p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.homepage_order ASC, p.id ASC'
    : 'SELECT p.*,c.name category FROM packages p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.id DESC';
  $rows=$p->query($packageOrderSql)->fetchAll();
  foreach($rows as $i=>$r):?>
<tr class="homepage-order-row" draggable="true" data-package-id="<?=$r['id']?>" style="cursor:grab">
<td><span class="homepage-drag-handle" title="Drag to reorder" style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:30px;border:1px solid #dce5df;border-radius:6px;background:#fff;color:#60736a;font-size:17px">☷</span><button type="button" class="homepage-move-btn homepage-move-up" title="Move up" style="margin-left:4px;border:0;background:transparent;cursor:pointer;font-size:16px">↑</button><button type="button" class="homepage-move-btn homepage-move-down" title="Move down" style="border:0;background:transparent;cursor:pointer;font-size:16px">↓</button><span class="homepage-order-number" style="margin-left:8px;font-weight:700"><?=($i+1)?></span><input type="hidden" form="homepagePackageOrderForm" name="package_order[]" value="<?=$r['id']?>"></td>
<td><strong><?=admin_e($r['name'])?></strong></td><td><?=admin_e($r['category'])?></td><td><?=rupiah($r['price_adult'])?></td><td><?=$r['best_seller']??0?'<span class="badge" style="background:#fff7e8;color:#e58b00;border:1px solid #f59e0b">★ Best Seller</span>':'<span style="color:#8a9891">—</span>'?></td><td><?=$r['status']?'<span class="badge green">Active</span>':'<span class="badge redtxt">Hidden</span>'?></td><td><a class="btn gray" href="?action=edit&id=<?=$r['id']?>">Edit</a> <a class="btn gray" href="?action=images&id=<?=$r['id']?>">Gallery</a> <form class="inline" method="post"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn red" data-confirm="Delete this package and its images?">Delete</button></form></td></tr>
<?php endforeach;?></table>
</div>
<style>
#homepagePackageOrderTable .homepage-order-row.dragging{opacity:.45}
#homepagePackageOrderTable .homepage-order-row.drop-target{outline:2px dashed #8aa596;outline-offset:-2px}
#homepagePackageOrderTable .homepage-drag-handle{user-select:none}
</style>
<script>
(function(){
  const table=document.getElementById('homepagePackageOrderTable'); if(!table) return;
  let dragged=null;
  const rows=()=>Array.from(table.querySelectorAll('tr.homepage-order-row'));
  function refreshNumbers(){ rows().forEach((row,i)=>{ const n=row.querySelector('.homepage-order-number'); if(n)n.textContent=i+1; }); }
  rows().forEach(row=>{
    row.addEventListener('dragstart',function(e){ dragged=row; row.classList.add('dragging'); if(e.dataTransfer)e.dataTransfer.effectAllowed='move'; });
    row.addEventListener('dragend',function(){ row.classList.remove('dragging'); rows().forEach(r=>r.classList.remove('drop-target')); dragged=null; refreshNumbers(); });
    row.addEventListener('dragover',function(e){
      e.preventDefault(); if(!dragged || dragged===row)return;
      rows().forEach(r=>r.classList.remove('drop-target')); row.classList.add('drop-target');
      const rect=row.getBoundingClientRect();
      if(e.clientY < rect.top + rect.height/2) row.parentNode.insertBefore(dragged,row);
      else row.parentNode.insertBefore(dragged,row.nextSibling);
      refreshNumbers();
    });
    row.addEventListener('drop',function(e){e.preventDefault(); row.classList.remove('drop-target'); refreshNumbers();});
  });
  table.querySelectorAll('.homepage-move-up').forEach(btn=>btn.addEventListener('click',function(){
    const row=btn.closest('tr.homepage-order-row'), prev=row&&row.previousElementSibling;
    if(prev && prev.classList.contains('homepage-order-row')) { row.parentNode.insertBefore(row,prev); refreshNumbers(); }
  }));
  table.querySelectorAll('.homepage-move-down').forEach(btn=>btn.addEventListener('click',function(){
    const row=btn.closest('tr.homepage-order-row'), next=row&&row.nextElementSibling;
    if(next && next.classList.contains('homepage-order-row')) { row.parentNode.insertBefore(next,row); refreshNumbers(); }
  }));
})();
</script>
<script>(function(){
const $=id=>document.getElementById(id),t=$('pkgSeoTitle'),d=$('pkgMetaDescription'),k=$('pkgFocusKeyword'),c=$('pkgCanonical'),ot=$('pkgOgTitle'),od=$('pkgOgDescription'),oi=$('pkgOgImage'),sc=$('pkgSchema'),up=$('pkgOgUpload'),checks=$('pkgSeoChecks'),score=$('pkgSeoScore'),pt=$('pkgSeoPreviewTitle'),pu=$('pkgSeoPreviewUrl'),pd=$('pkgSeoPreviewDesc'),pi=$('pkgSeoPreviewImage');if(!checks)return;
const norm=s=>(s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
function run(){const a=[],T=t.value.trim(),D=d.value.trim(),K=norm(k.value),C=c.value.trim(),I=oi.value.trim(),OT=ot.value.trim(),OD=od.value.trim(),S=sc.value.trim();function add(status,name,detail){a.push({status,name,detail})}
add(T.length>=30&&T.length<=60?'good':(T.length>=20&&T.length<=70?'warn':'bad'),'SEO title',T.length+' karakter. Target 30–60 karakter; topik utama sebaiknya di awal.');
add(D.length>=120&&D.length<=160?'good':(D.length>=70&&D.length<=200?'warn':'bad'),'Meta description',D.length+' karakter. Target sekitar 120–160 karakter.');
add(K?(norm(T).includes(K)?'good':'bad'):'warn','Focus keyword di title',K?(norm(T).includes(K)?'Keyword ditemukan di SEO title.':'Keyword belum ditemukan di SEO title.'):'Isi focus keyword untuk analisis.');
add(K?(norm(D).includes(K)?'good':'warn'):'warn','Focus keyword di description',K?(norm(D).includes(K)?'Keyword ditemukan di meta description.':'Sebaiknya masukkan keyword secara alami ke description.'):'Focus keyword belum diisi.');
add(C&&/^https?:\/\//i.test(C)?'good':'bad','Canonical URL',C?'Canonical URL terisi. Pastikan mengarah ke URL paket yang benar.':'Canonical URL kosong atau bukan URL absolut.');
add((OT||T)?'good':'bad','Open Graph title',(OT||T)?'Judul social sharing tersedia.':'Tambahkan OG title atau SEO title.');
add((OD||D)?'good':'warn','Open Graph description',(OD||D)?'Deskripsi social sharing tersedia.':'Tambahkan OG description atau meta description.');
const effectiveImage=I||coverImage;add(effectiveImage?'good':'warn','Open Graph image',I?'Gambar OG khusus tersedia.':(coverImage?'Gambar cover paket digunakan sebagai fallback.':'Belum ada gambar OG atau cover paket. Tambahkan gambar landscape 1200×630 px.'));
add(S?(function(){try{JSON.parse(S);return 'good'}catch(e){return 'bad'}})():'warn','Schema JSON-LD',S?'Schema berisi JSON valid. Validitas tipe schema perlu diperiksa lebih lanjut.':'Schema custom kosong; schema default website mungkin tetap digunakan.');
const good=a.filter(x=>x.status==='good').length,bad=a.filter(x=>x.status==='bad').length,warn=a.length-good-bad;score.textContent=good+' baik · '+warn+' perlu ditingkatkan · '+bad+' masalah';score.className='pkg-seo-score '+(bad?'bad':(warn?'warn':'good'));checks.innerHTML='';a.forEach(x=>{const el=document.createElement('div');el.className='pkg-seo-check '+x.status;const dot=document.createElement('span');dot.className='pkg-seo-dot';const body=document.createElement('div'),name=document.createElement('strong'),detail=document.createElement('div');detail.className='pkg-seo-detail';name.textContent=x.name;detail.textContent=x.detail;body.append(name,detail);el.append(dot,body);checks.appendChild(el)});
pt.textContent=T||'Package SEO title';pu.textContent=C||'Canonical URL belum diisi';pd.textContent=D||'Meta description preview';if(effectiveImage){pi.src=/^https?:\/\//i.test(effectiveImage)?effectiveImage:'../'+effectiveImage.replace(/^\//,'');pi.style.display='block'}else if(up.files&&up.files[0]){pi.src=URL.createObjectURL(up.files[0]);pi.style.display='block'}else{pi.style.display='none';pi.removeAttribute('src')}
}
['pkgSeoTitle','pkgMetaDescription','pkgFocusKeyword','pkgCanonical','pkgOgTitle','pkgOgDescription','pkgOgImage','pkgSchema'].forEach(id=>$(id)?.addEventListener('input',run));up?.addEventListener('change',()=>{if(up.files?.[0]){pi.src=URL.createObjectURL(up.files[0]);pi.style.display='block'}});run();
})();</script>
<?php endif;require __DIR__.'/includes/footer.php';
