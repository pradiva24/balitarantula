<?php
require __DIR__.'/includes/auth.php';
$db=admin_db();
$db->exec("CREATE TABLE IF NOT EXISTS merchandise_items (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(180) NOT NULL,
 description TEXT NULL,
 image_path VARCHAR(500) NULL,
 whatsapp_message VARCHAR(500) NULL,
 sort_order INT NOT NULL DEFAULT 0,
 status TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), KEY idx_merchandise_status_order(status,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$message='';$type='success';
if($_SERVER['REQUEST_METHOD']==='POST'){
 admin_verify_csrf();
 $action=(string)($_POST['action']??'');
 try{
  if($action==='save'){
   $id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$description=trim((string)($_POST['description']??''));
   $wa=trim((string)($_POST['whatsapp_message']??''));$sort=(int)($_POST['sort_order']??0);$status=!empty($_POST['status'])?1:0;
   if($name==='')throw new RuntimeException('Merchandise name is required.');
   $old='';if($id){$q=$db->prepare("SELECT image_path FROM merchandise_items WHERE id=?");$q->execute([$id]);$old=(string)($q->fetchColumn()?:'');}
   $image=admin_upload('image','merchandise',$old);
   if($id){$q=$db->prepare("UPDATE merchandise_items SET name=?,description=?,image_path=?,whatsapp_message=?,sort_order=?,status=? WHERE id=?");$q->execute([$name,$description,$image,$wa,$sort,$status,$id]);}
   else{$q=$db->prepare("INSERT INTO merchandise_items(name,description,image_path,whatsapp_message,sort_order,status) VALUES(?,?,?,?,?,?)");$q->execute([$name,$description,$image,$wa,$sort,$status]);}
   admin_flash('success','Merchandise item saved.');header('Location: merchandise.php');exit;
  }elseif($action==='delete'){
   $id=(int)($_POST['id']??0);if($id<1)throw new RuntimeException('Invalid item.');
   $db->prepare("DELETE FROM merchandise_items WHERE id=?")->execute([$id]);admin_flash('success','Merchandise item deleted.');header('Location: merchandise.php');exit;
  }
 }catch(Throwable $e){$message=$e->getMessage();$type='error';error_log('BTA Merchandise admin: '.$e->getMessage());}
}
$edit=null;$editId=(int)($_GET['edit']??0);if($editId){$q=$db->prepare("SELECT * FROM merchandise_items WHERE id=?");$q->execute([$editId]);$edit=$q->fetch(PDO::FETCH_ASSOC)?:null;}
$rows=$db->query("SELECT * FROM merchandise_items ORDER BY sort_order ASC,id DESC")->fetchAll(PDO::FETCH_ASSOC);
$page_title='Merchandise';
require __DIR__.'/includes/header.php';
?>
<div class="card">
 <div class="head"><div><h2>Merchandise Manager</h2><p class="muted-line">Manage products displayed on the public merchandise archive page.</p></div><a class="btn gray" href="<?=BASE_URL?>/merchandise.php" target="_blank" rel="noopener">View Page ↗</a></div>
 <?php if($message):?><div class="alert <?=admin_e($type)?>"><?=admin_e($message)?></div><?php endif;?>
 <form class="form" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>">
  <h3><?=$edit?'Edit Merchandise':'Add Merchandise'?></h3>
  <div class="form-grid">
   <div class="field full"><label>Product Name</label><input name="name" required maxlength="180" value="<?=admin_e($edit['name']??'')?>"></div>
   <div class="field full"><label>Short Description</label><textarea name="description" rows="3" maxlength="1500"><?=admin_e($edit['description']??'')?></textarea></div>
   <div class="field full"><label>Product Image</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"><small>Upload JPG, PNG, WebP or GIF. If editing, leave empty to keep the current image.</small>
   <?php if(!empty($edit['image_path'])):?><img class="thumb" style="width:180px;height:130px;object-fit:cover" src="<?=admin_e(asset_url($edit['image_path']))?>" alt=""><?php endif;?></div>
   <div class="field"><label>WhatsApp Message (optional)</label><input name="whatsapp_message" maxlength="500" value="<?=admin_e($edit['whatsapp_message']??'')?>" placeholder="Hi, I am interested in this merchandise."></div>
   <div class="field"><label>Sort Order</label><input type="number" name="sort_order" value="<?=admin_e($edit['sort_order']??'0')?>"></div>
   <div class="field"><label><input type="checkbox" name="status" value="1" <?=!isset($edit['status'])||(int)$edit['status']===1?'checked':''?>> Published</label></div>
  </div>
  <div class="actions"><button class="btn"><?=$edit?'Update Item':'Add Item'?></button><?php if($edit):?><a class="btn gray" href="merchandise.php">Cancel</a><?php endif;?></div>
 </form>
</div>
<div class="section"><div class="head"><h2>Merchandise Items (<?=count($rows)?>)</h2></div><div class="tablebox"><table class="table"><thead><tr><th>Image</th><th>Product</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td><?php if($row['image_path']):?><img class="thumb" src="<?=admin_e(asset_url($row['image_path']))?>" alt=""><?php else:?>—<?php endif;?></td><td><b><?=admin_e($row['name'])?></b><br><small><?=admin_e($row['description'])?></small></td><td><?=(int)$row['sort_order']?></td><td><span class="badge <?=((int)$row['status']===1?'green':'yellow')?>"><?=((int)$row['status']===1?'Published':'Hidden')?></span></td><td><a class="btn gray" href="merchandise.php?edit=<?=(int)$row['id']?>">Edit</a> <form method="post" style="display:inline" onsubmit="return confirm('Delete this merchandise item?')"><input type="hidden" name="csrf" value="<?=admin_csrf()?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button class="btn red" type="submit">Delete</button></form></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="5" class="empty">No merchandise yet. Add your first product above.</td></tr><?php endif;?>
</tbody></table></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>