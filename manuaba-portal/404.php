<?php
require __DIR__.'/includes/auth.php';
$db=admin_db();

$defaults=[
 '404_eyebrow'=>'404 · OOPS!',
 '404_title'=>'Page Not Found',
 '404_description'=>"It looks like this page took a little adventure and got lost in the jungle. But don't worry, there are still many amazing experiences waiting for you at <?=admin_e(site_brand_name())?>!",
 '404_button_text'=>'Back to Homepage',
 '404_button_url'=>BASE_URL.'/',
];
if($_SERVER['REQUEST_METHOD']==='POST'){
  admin_verify_csrf();
  foreach($defaults as $key=>$default){
    $value=trim((string)($_POST[$key]??''));
    $st=$db->prepare("SELECT id FROM settings WHERE setting_key=?");
    $st->execute([$key]);
    $id=$st->fetchColumn();
    if($id) $db->prepare("UPDATE settings SET setting_value=?,updated_at=NOW() WHERE id=?")->execute([$value,$id]);
    else $db->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)")->execute([$key,$value]);
  }
  if(admin_media_has_selection('404_background') || !empty($_FILES['404_background']['name'])){
    try{
      $old=setting('404_background','');
      $path=admin_upload('404_background','404',$old);
      $st=$db->prepare("SELECT id FROM settings WHERE setting_key=?");
      $st->execute(['404_background']);
      $id=$st->fetchColumn();
      if($id) $db->prepare("UPDATE settings SET setting_value=?,updated_at=NOW() WHERE id=?")->execute([$path,$id]);
      else $db->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)")->execute(['404_background',$path]);
    }catch(Throwable $ex){
      admin_flash('error','404 background upload failed: '.$ex->getMessage());
      header('Location: 404.php'); exit;
    }
  }
  admin_flash('','404 Page settings saved');
  header('Location: 404.php'); exit;
}
$page_title='404 Page';
require __DIR__.'/includes/header.php';
$bg=setting('404_background','uploads/homepage/dd5c08b6cf28dcfb4147.jpg');
?>
<div class="card">
  <h2>404 Page Editor</h2>
  <p class="muted-line">Customize the public 404 error page. The page appears automatically when a frontend URL cannot be found.</p>
  <form class="form" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=admin_csrf()?>">
    <h3>404 Content</h3>
    <div class="form-grid">
      <div class="field full">
        <label>Small Label</label>
        <input name="404_eyebrow" value="<?=admin_e(setting('404_eyebrow',$defaults['404_eyebrow']))?>">
      </div>
      <div class="field full">
        <label>Title</label>
        <input name="404_title" value="<?=admin_e(setting('404_title',$defaults['404_title']))?>">
      </div>
      <div class="field full">
        <label>Description</label>
        <textarea name="404_description" rows="5"><?=admin_e(setting('404_description',$defaults['404_description']))?></textarea>
      </div>
      <div class="field">
        <label>Button Text</label>
        <input name="404_button_text" value="<?=admin_e(setting('404_button_text',$defaults['404_button_text']))?>">
      </div>
      <div class="field">
        <label>Button URL</label>
        <input name="404_button_url" value="<?=admin_e(setting('404_button_url',$defaults['404_button_url']))?>">
      </div>
    </div>

    <h3>404 Background Image</h3>
    <div class="form-grid">
      <div class="field full">
        <label>Replace Background</label>
        <input type="file" name="404_background" accept="image/jpeg,image/png,image/webp,image/gif">
        <small class="muted-line">Recommended landscape image. JPG, PNG, WebP or GIF · max 20 MB</small>
        <?php if($bg): ?>
          <div style="margin-top:14px;max-width:560px;border-radius:14px;overflow:hidden;border:1px solid #dce7e1;background:#f4f8f5">
            <img src="<?=admin_e(asset_url($bg))?>" alt="404 Background" style="display:block;width:100%;height:260px;object-fit:cover">
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="actions">
      <button class="btn">Save 404 Page</button>
      <a class="btn gray" target="_blank" rel="noopener" href="<?=BASE_URL?>/this-page-does-not-exist">Preview 404 <?=fa_icon('permalinks','fa-solid fa-arrow-up-right-from-square')?></a>
    </div>
  </form>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
