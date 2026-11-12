<?php
require __DIR__.'/includes/auth.php';
$db=admin_db();
$db->exec("CREATE TABLE IF NOT EXISTS redirect_rules (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 source_path VARCHAR(500) NOT NULL,
 target_path VARCHAR(1000) NOT NULL,
 redirect_type SMALLINT NOT NULL DEFAULT 301,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_redirect_source (source_path(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
function redirect_path_clean(string $v): string {
 $v=trim($v); if($v==='') return '';
 if(preg_match('~^https?://~i',$v)) return $v;
 return '/'.trim(preg_replace('~/+~','/',$v),'/');
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 admin_verify_csrf(); $action=(string)($_POST['action']??'save');
 try{
  $id=(int)($_POST['id']??0);
  if($action==='delete'){ $db->prepare("DELETE FROM redirect_rules WHERE id=?")->execute([$id]); admin_flash('success','Redirect dihapus.'); }
  elseif($action==='toggle'){ $db->prepare("UPDATE redirect_rules SET is_active=1-is_active WHERE id=?")->execute([$id]); admin_flash('success','Status redirect diperbarui.'); }
  else {
   $src=redirect_path_clean((string)($_POST['source_path']??'')); $dst=redirect_path_clean((string)($_POST['target_path']??'')); $type=(int)($_POST['redirect_type']??301);
   if($src===''||$dst===''||$src==='/'||$src[0]!=='/'||!in_array($type,[301,302],true)||$src===$dst||strpos($src,'?')!==false||strpos($src,'#')!==false) throw new RuntimeException('Periksa URL lama, URL tujuan, dan tipe redirect.');
   if($id){$db->prepare("UPDATE redirect_rules SET source_path=?,target_path=?,redirect_type=? WHERE id=?")->execute([$src,$dst,$type,$id]);}
   else{$db->prepare("INSERT INTO redirect_rules(source_path,target_path,redirect_type) VALUES(?,?,?)")->execute([$src,$dst,$type]);}
   admin_flash('success','Redirect berhasil disimpan.');
  }
 }catch(Throwable $e){admin_flash('error','Gagal: '.$e->getMessage());}
 header('Location: redirects.php');exit;
}
$edit=null;
if(isset($_GET['edit'])){$s=$db->prepare("SELECT * FROM redirect_rules WHERE id=?");$s->execute([(int)$_GET['edit']]);$edit=$s->fetch(PDO::FETCH_ASSOC)?:null;}
$rows=$db->query("SELECT * FROM redirect_rules ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$page_title='URL Redirects'; require __DIR__.'/includes/header.php';
?>
<div class="card">
 <h2><?= $edit?'Edit Redirect':'Add URL Redirect' ?></h2>
 <p class="muted-line">Masukkan path URL lama dan tujuan baru. Contoh: /old-atv-page menuju /packages.</p>
 <form class="form" method="post">
  <input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>">
  <div class="form-grid">
   <div class="field"><label>URL Lama</label><input name="source_path" required placeholder="/old-page" value="<?=admin_e($edit['source_path']??'')?>"></div>
   <div class="field"><label>URL Tujuan</label><input name="target_path" required placeholder="/packages atau https://..." value="<?=admin_e($edit['target_path']??'')?>"></div>
   <div class="field"><label>Tipe Redirect</label><select name="redirect_type"><option value="301" <?=((int)($edit['redirect_type']??301)===301?'selected':'')?>>301 Permanent</option><option value="302" <?=((int)($edit['redirect_type']??301)===302?'selected':'')?>>302 Temporary</option></select></div>
  </div><div class="actions"><button class="btn">Save Redirect</button><?php if($edit):?><a class="btn gray" href="redirects.php">Cancel</a><?php endif;?></div>
 </form>
</div>
<div class="card" style="margin-top:20px"><h2>Daftar Redirect (<?=count($rows)?>)</h2><p class="muted-line">URL yang tidak terdaftar atau nonaktif akan diarahkan ke homepage.</p>
<div style="overflow-x:auto"><table class="table"><thead><tr><th>URL Lama</th><th>URL Tujuan</th><th>Type</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="5">Belum ada aturan redirect.</td></tr><?php endif;?>
<?php foreach($rows as $r):?><tr><td><code><?=admin_e($r['source_path'])?></code></td><td><code><?=admin_e($r['target_path'])?></code></td><td><?=(int)$r['redirect_type']?></td><td><?=((int)$r['is_active']?'Active':'Inactive')?></td><td style="white-space:nowrap"><a class="btn gray" href="redirects.php?edit=<?=(int)$r['id']?>">Edit</a>
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="toggle"><button class="btn gray"><?=((int)$r['is_active']?'Disable':'Enable')?></button></form>
<form method="post" style="display:inline" onsubmit="return confirm('Hapus redirect ini?')"><input type="hidden" name="csrf" value="<?=admin_e(admin_csrf())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="delete"><button class="btn gray">Delete</button></form></td></tr><?php endforeach;?>
</tbody></table></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>