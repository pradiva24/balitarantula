<?php
require __DIR__.'/includes/auth.php';
$p=db();
try {$p->exec("CREATE TABLE IF NOT EXISTS adventure_tracks (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(180) NOT NULL, slug VARCHAR(200) NOT NULL,
 image_path VARCHAR(500) DEFAULT NULL, distance VARCHAR(80) DEFAULT NULL, duration VARCHAR(80) DEFAULT NULL,
 difficulty VARCHAR(80) DEFAULT NULL, description TEXT DEFAULT NULL, sort_order INT NOT NULL DEFAULT 0,
 status TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY uq_adventure_tracks_slug(slug), KEY idx_adventure_tracks_home(status,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");} catch(Throwable $e) {}
function track_slug(string $s): string {$s=strtolower(trim($s));$s=preg_replace('/[^a-z0-9]+/','-',$s);return trim($s,'-') ?: 'track';}
$errors=[];$edit=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 admin_verify_csrf();$act=(string)($_POST['act']??'');$id=(int)($_POST['id']??0);
 try {
  if($act==='save'){
   $name=trim((string)($_POST['name']??''));if($name==='')throw new RuntimeException('Track name wajib diisi.');
   $slug=track_slug((string)($_POST['slug']??''));if($slug==='track')$slug=track_slug($name);
   $st=$p->prepare('SELECT id FROM adventure_tracks WHERE slug=? AND id<>?');$st->execute([$slug,$id]);if($st->fetchColumn())$slug.='-'.substr(bin2hex(random_bytes(3)),0,6);
   $old='';if($id){$q=$p->prepare('SELECT image_path FROM adventure_tracks WHERE id=?');$q->execute([$id]);$old=(string)$q->fetchColumn();}
   $image=admin_upload('image','tracks',$old ?: null);
   $data=[$name,$slug,$image,trim((string)($_POST['distance']??'')),trim((string)($_POST['duration']??'')),trim((string)($_POST['difficulty']??'')),trim((string)($_POST['description']??'')),(int)($_POST['sort_order']??0),(int)($_POST['status']??1)];
   if($id){$data[]=$id;$p->prepare('UPDATE adventure_tracks SET name=?,slug=?,image_path=?,distance=?,duration=?,difficulty=?,description=?,sort_order=?,status=? WHERE id=?')->execute($data);}
   else $p->prepare('INSERT INTO adventure_tracks(name,slug,image_path,distance,duration,difficulty,description,sort_order,status) VALUES(?,?,?,?,?,?,?,?,?)')->execute($data);
   admin_flash('success','Track berhasil disimpan.');header('Location: tracks.php');exit;
  }
  if($act==='delete'){$q=$p->prepare('SELECT image_path FROM adventure_tracks WHERE id=?');$q->execute([$id]);$img=$q->fetchColumn();$p->prepare('DELETE FROM adventure_tracks WHERE id=?')->execute([$id]);if($img)admin_delete_image($img);admin_flash('success','Track berhasil dihapus.');header('Location: tracks.php');exit;}
 }catch(Throwable $e){$errors[]=$e->getMessage();}
}
if(isset($_GET['edit'])){$q=$p->prepare('SELECT * FROM adventure_tracks WHERE id=?');$q->execute([(int)$_GET['edit']]);$edit=$q->fetch(PDO::FETCH_ASSOC)?:null;}
$rows=$p->query('SELECT * FROM adventure_tracks ORDER BY sort_order ASC,id DESC')->fetchAll(PDO::FETCH_ASSOC);
$page_title='Adventure Tracks';require __DIR__.'/includes/header.php';
?>
<?php if($errors): ?><div class="alert error"><?=admin_e(implode(' ', $errors))?></div><?php endif; ?>
<div class="form"><h2><?= $edit?'Edit Track':'Add New Track' ?></h2>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
<div class="formgrid">
<div class="field"><label>Track Name *</label><input required name="name" value="<?=admin_e($edit['name']??'')?>" placeholder="The Hidden Waterfall Track"></div>
<div class="field"><label>Slug (optional)</label><input name="slug" value="<?=admin_e($edit['slug']??'')?>" placeholder="hidden-waterfall-track"></div>
<div class="field"><label>Track Photo</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"><?php if(!empty($edit['image_path'])): ?><div class="small">Current: <?=admin_e($edit['image_path'])?></div><?php endif; ?></div>
<div class="field"><label>Distance</label><input name="distance" value="<?=admin_e($edit['distance']??'')?>" placeholder="2.5 km"></div>
<div class="field"><label>Duration</label><input name="duration" value="<?=admin_e($edit['duration']??'')?>" placeholder="60–90 minutes"></div>
<div class="field"><label>Difficulty</label><select name="difficulty"><option value="">Choose difficulty</option><?php foreach(['Easy','Moderate','Challenging','Extreme'] as $d): ?><option value="<?=admin_e($d)?>" <?=($edit['difficulty']??'')===$d?'selected':''?>><?=admin_e($d)?></option><?php endforeach; ?></select></div>
<div class="field"><label>Sort Order</label><input type="number" name="sort_order" value="<?=admin_e($edit['sort_order']??'0')?>"></div>
<div class="field"><label>Status</label><select name="status"><option value="1" <?=((int)($edit['status']??1)===1)?'selected':''?>>Active - show on homepage</option><option value="0" <?=((int)($edit['status']??1)===0)?'selected':''?>>Inactive - hide</option></select></div>
<div class="field" style="grid-column:1/-1"><label>Description</label><textarea name="description" rows="4"><?=admin_e($edit['description']??'')?></textarea></div>
</div><div class="actions"><button class="btn" type="submit">Save Track</button><?php if($edit): ?><a class="btn" href="tracks.php">Cancel</a><?php endif; ?></div></form></div>
<div class="section tablebox"><h2>Track List</h2><table class="table"><tr><th>Photo</th><th>Track</th><th>Distance / Duration</th><th>Difficulty</th><th>Status</th><th>Order</th><th>Actions</th></tr>
<?php foreach($rows as $r): ?><tr><td><?php if($r['image_path']): ?><img class="thumb" src="../<?=admin_e($r['image_path'])?>" alt=""><?php endif; ?></td><td><strong><?=admin_e($r['name'])?></strong><br><span class="small"><?=admin_e($r['slug'])?></span></td><td><?=admin_e($r['distance']?:'—')?> / <?=admin_e($r['duration']?:'—')?></td><td><?=admin_e($r['difficulty']?:'—')?></td><td><?=((int)$r['status']===1)?'Active':'Hidden'?></td><td><?= (int)$r['sort_order'] ?></td><td><div class="actions"><a class="btn" href="tracks.php?edit=<?=(int)$r['id']?>">Edit</a><form method="post" onsubmit="return confirm('Delete this track?')"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn red" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7">Belum ada track. Tambahkan track menggunakan form di atas.</td></tr><?php endif; ?></table></div>
<?php require __DIR__.'/includes/footer.php'; ?>
